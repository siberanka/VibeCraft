<?php

namespace App\Domain\Commerce;

use App\Core\Audit\AuditLogger;
use App\Jobs\NotifyDiscord;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\WalletEntry;
use App\Notifications\PlayerNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Order state machine. Every transition locks the order row and is idempotent:
 * applying "paid" twice delivers once.
 */
class OrderLifecycle
{
    public function __construct(
        private Ledger $ledger,
        private Fulfillment $fulfillment,
        private AuditLogger $audit,
        private OrderContracts $contracts,
    ) {}

    /**
     * @return bool true when this call performed the transition
     */
    public function markPaid(Order $order, string $provider, ?string $providerPaymentId, int $amountMinor, string $currency, ?Payment $payment = null): bool
    {
        return DB::transaction(function () use ($order, $provider, $providerPaymentId, $amountMinor, $currency, $payment) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [Order::PENDING, Order::CANCELLED], true)) {
                return false;
            }

            if ($payment !== null && ($payment->order_id !== $locked->id || $payment->provider !== $provider
                || $payment->amount_minor !== $locked->total_minor || $payment->currency !== $locked->currency)) {
                throw new CommerceException('payment_source_mismatch');
            }

            if ($amountMinor !== $locked->total_minor || strtoupper($currency) !== strtoupper($locked->currency)) {
                $payment?->forceFill(['status' => 'failed', 'failure_reason' => 'amount_or_currency_mismatch'])->save();
                $locked->forceFill(['fulfillment_status' => 'manual_review', 'admin_note' => 'Payment amount/currency mismatch'])->save();
                $this->audit->log('order.payment_mismatch', $locked, ['expected' => $locked->total_minor, 'received' => $amountMinor, 'currency' => $currency]);
                Log::warning('order.payment_mismatch', ['order' => $locked->public_id]);

                return false;
            }

            $wasCancelled = $locked->status === Order::CANCELLED;

            $locked->forceFill([
                'status' => Order::PAID,
                'paid_at' => now(),
                'cancelled_at' => null,
                'payment_method' => $provider,
            ])->save();

            $payment?->forceFill(['status' => 'succeeded', 'provider_payment_id' => $providerPaymentId, 'succeeded_at' => now()])->save();

            if ($this->contracts->requiredFor($locked) && ! $this->contracts->validFor($locked)) {
                // Provider truth is recorded, but an absent original contract cannot authorize delivery or credit.
                $locked->forceFill(['fulfillment_status' => 'manual_review', 'admin_note' => 'Payment received; original order terms require review before delivery or wallet credit'])->save();
            } elseif ($locked->kind === 'topup') {
                $this->ledger->post($locked->user_id, $locked->total_minor, 'topup', "order:{$locked->id}:topup", $locked->id);
                if ($locked->bonus_minor > 0) {
                    $this->ledger->post($locked->user_id, $locked->bonus_minor, 'bonus', "order:{$locked->id}:bonus", $locked->id);
                }
                $locked->forceFill(['fulfillment_status' => 'not_required'])->save();
            } elseif ($wasCancelled) {
                // Money arrived after the reservation expired: stock/coupon were released.
                $locked->forceFill(['fulfillment_status' => 'manual_review', 'admin_note' => 'Paid after expiry — review stock before delivering'])->save();
            } else {
                $this->fulfillment->schedule($locked);
            }

            $this->audit->log('order.paid', $locked, ['provider' => $provider, 'amount' => $amountMinor]);
            NotifyDiscord::dispatch('order', $locked->id)->afterCommit();
            if ($locked->kind === 'purchase') {
                $locked->user?->notify(new PlayerNotice('order_paid', ['order' => (string) $locked->public_id], '/orders/'.$locked->public_id));
                $locked->giftRecipient?->notify(new PlayerNotice('gift_received', ['player' => (string) $locked->user?->name],
                    $locked->items()->where('held_in_chest', true)->exists() ? '/chest' : '/account'));
            }

            return true;
        });
    }

    public function cancel(Order $order, string $reason): bool
    {
        return DB::transaction(function () use ($order, $reason) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== Order::PENDING) {
                return false;
            }

            $locked->forceFill(['status' => Order::CANCELLED, 'cancelled_at' => now(), 'admin_note' => mb_substr($reason, 0, 500)])->save();
            $locked->payments()->whereIn('status', ['created', 'pending'])->update(['status' => 'cancelled']);

            foreach ($locked->items as $item) {
                if ($item->product_id !== null) {
                    Product::query()->whereKey($item->product_id)->whereNotNull('stock')->increment('stock', $item->quantity);
                }
            }
            if ($locked->coupon_id !== null) {
                $locked->coupon()->where('uses', '>', 0)->decrement('uses');
                CouponRedemption::query()->where('order_id', $locked->id)->delete();
            }

            $this->audit->log('order.cancelled', $locked, ['reason' => $reason]);

            return true;
        });
    }

    /**
     * Records a refund or chargeback. Wallet purchases are credited back; game
     * entitlements follow the product's refund commands.
     */
    public function markRefunded(Order $order, string $source, bool $dispute = false, ?Payment $payment = null): bool
    {
        return DB::transaction(function () use ($order, $source, $dispute, $payment) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($payment !== null && ($payment->order_id !== $locked->id || $payment->provider !== $locked->payment_method
                || ! in_array($payment->status, ['succeeded', 'refunded', 'disputed'], true))) {
                return false;
            }
            if ($locked->status !== Order::PAID && ! ($dispute && $locked->status === Order::REFUNDED)) {
                return false;
            }

            $locked->forceFill(['status' => $dispute ? Order::DISPUTED : Order::REFUNDED, 'refunded_at' => now()])->save();
            $payment?->forceFill(['status' => $dispute ? 'disputed' : 'refunded'])->save();

            if ($locked->payment_method === 'wallet' && $locked->kind === 'purchase') {
                $this->ledger->post($locked->user_id, $locked->total_minor, 'refund', "order:{$locked->id}:refund", $locked->id);
            }
            if ($locked->kind === 'topup') {
                // Claw back credits that were bought with the refunded payment; may go negative.
                $credited = WalletEntry::where('idempotency_key', "order:{$locked->id}:topup")->where('order_id', $locked->id)
                    ->where('user_id', $locked->user_id)->where('type', 'topup')->where('amount_minor', $locked->total_minor)->exists();
                if ($credited) {
                    $this->ledger->post($locked->user_id, -$locked->total_minor, 'reversal', "order:{$locked->id}:topup-reversal", $locked->id, allowNegative: true);
                }
                $bonus = WalletEntry::where('idempotency_key', "order:{$locked->id}:bonus")->where('order_id', $locked->id)
                    ->where('user_id', $locked->user_id)->where('type', 'bonus')->value('amount_minor');
                if ($bonus > 0) {
                    $this->ledger->post($locked->user_id, -$bonus, 'reversal', "order:{$locked->id}:bonus-reversal", $locked->id, allowNegative: true);
                }
            } else {
                $this->fulfillment->revoke($locked);
            }

            $this->audit->log($dispute ? 'order.disputed' : 'order.refunded', $locked, ['source' => $source]);

            return true;
        });
    }
}
