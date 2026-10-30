<?php

namespace App\Domain\Commerce;

use App\Models\Delivery;
use App\Models\Entitlement;
use App\Models\GameIdentity;
use App\Models\Order;
use App\Models\OrderContract;
use App\Models\OrderItem;
use App\Models\ProductCommand;
use App\Models\WalletEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns paid order items into delivery jobs for the in-game bridge. Each job has a
 * deterministic dedup key, so scheduling twice never creates a second command.
 */
class Fulfillment
{
    public function __construct(private OrderContracts $contracts, private Ledger $ledger) {}

    public function schedule(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $this->canGrant($order)) {
                return;
            }
            $created = 0;
            $held = 0;
            foreach ($order->items()->with('product.commands')->get() as $item) {
                if ($item->held_in_chest && $item->chest_claimed_at === null) {
                    // Waits in the player's web chest; nothing is sent until it is claimed.
                    $held++;

                    continue;
                }
                $created += $this->enqueueCommands($item, 'purchase');
                if ($order->fresh()->fulfillment_status === 'manual_review') {
                    return;
                }
                $this->grantEntitlement($order, $item);
            }

            $order->forceFill(['fulfillment_status' => $created > 0 ? 'queued' : ($held > 0 ? 'in_chest' : 'not_required')])->save();
        });
    }

    /** Starts a timed entitlement for an item once; the period begins when it is granted. */
    public function grantEntitlement(Order $order, OrderItem $item): void
    {
        if (! $item->duration_days) {
            return;
        }
        Entitlement::query()->firstOrCreate(['order_item_id' => $item->id], [
            'user_id' => $order->gift_recipient_id ?? $order->user_id,
            'product_id' => $item->product_id,
            'starts_at' => now(),
            'expires_at' => now()->addDays($item->duration_days * max(1, $item->quantity)),
            'status' => 'active',
        ]);
    }

    private function canGrant(Order $order): bool
    {
        if ($order->status !== Order::PAID) {
            return false;
        }
        if ($this->contracts->requiredFor($order) && ! $this->contracts->validFor($order)) {
            $order->forceFill(['fulfillment_status' => 'manual_review', 'admin_note' => 'Original order terms require review before delivery'])->save();

            return false;
        }

        return true;
    }

    public function revoke(Order $order): void
    {
        $purchaseJobs = Delivery::query()->where('order_id', $order->id)->where('phase', 'purchase')->orderBy('id')->lockForUpdate()->get();
        $ambiguous = $purchaseJobs->contains(fn (Delivery $job) => in_array($job->status, [Delivery::LEASED, Delivery::MANUAL_REVIEW, Delivery::DEAD], true));
        $expiryInFlight = Delivery::query()->where('order_id', $order->id)->where('phase', 'expire')
            ->whereIn('status', [Delivery::LEASED, Delivery::MANUAL_REVIEW])->lockForUpdate()->get();
        if ($expiryInFlight->isNotEmpty()) {
            $ambiguous = true;
            foreach ($expiryInFlight as $job) {
                $job->forceFill(['status' => Delivery::MANUAL_REVIEW, 'lease_expires_at' => null, 'last_error' => 'Refund while expiry command execution is unconfirmed'])->save();
            }
        }
        foreach ($purchaseJobs->groupBy('order_item_id') as $jobs) {
            if ($jobs->contains('status', Delivery::DELIVERED) && ! $jobs->every(fn (Delivery $job) => $job->status === Delivery::DELIVERED)) {
                $ambiguous = true;
            }
        }
        foreach ($purchaseJobs as $job) {
            if ($job->status === Delivery::QUEUED) {
                $job->forceFill(['status' => Delivery::CANCELLED, 'lease_expires_at' => null])->save();
            } elseif (in_array($job->status, [Delivery::LEASED, Delivery::DEAD], true)) {
                // A command already sent to a game server cannot be retracted.
                $job->forceFill(['status' => Delivery::MANUAL_REVIEW, 'lease_expires_at' => null, 'last_error' => 'Refund while command execution is unconfirmed'])->save();
            }
        }
        Delivery::query()->where('order_id', $order->id)->where('phase', 'expire')->where('status', Delivery::QUEUED)
            ->update(['status' => Delivery::CANCELLED]);
        foreach ($order->items()->with('product.commands')->get() as $item) {
            foreach (Entitlement::query()->where('order_item_id', $item->id)->where('status', 'active')->lockForUpdate()->get() as $entitlement) {
                $this->refundRenewals($entitlement);
                $entitlement->forceFill(['status' => 'revoked', 'auto_renew' => false])->save();
            }
            $jobs = $purchaseJobs->where('order_item_id', $item->id);
            if (! $ambiguous && $jobs->isNotEmpty() && $jobs->every(fn (Delivery $job) => $job->status === Delivery::DELIVERED)) {
                $this->enqueue($item, 'refund');
            }
        }
        if ($ambiguous) {
            $order->forceFill(['fulfillment_status' => 'manual_review', 'admin_note' => 'Refund requires reconciliation of commands already sent to the game server'])->save();

            return;
        }
        if ($purchaseJobs->isEmpty() && $order->fulfillment_status === 'in_chest') {
            // Nothing left the chest, so nothing has to be taken back in game.
            $order->forceFill(['fulfillment_status' => 'revoked'])->save();

            return;
        }
        $this->recompute($order);
    }

    /**
     * Renewal periods are paid separately by the entitlement owner (a gift
     * recipient may not be the buyer), so revoking the rank returns them.
     */
    private function refundRenewals(Entitlement $entitlement): void
    {
        $paid = -(int) WalletEntry::query()->where('user_id', $entitlement->user_id)->where('type', 'renewal')
            ->where('idempotency_key', 'like', "renew:{$entitlement->id}:%")->sum('amount_minor');
        if ($paid > 0) {
            $this->ledger->post($entitlement->user_id, $paid, 'refund', "entitlement:{$entitlement->id}:renewal-refund", note: 'Renewal periods returned after a refund');
        }
    }

    /** Expires timed entitlements whose period has ended. */
    public function expireDue(int $limit = 200): int
    {
        $count = 0;
        Entitlement::query()->where('status', 'active')->where('expires_at', '<=', now())->with('orderItem.product.commands')
            ->limit($limit)->get()->each(function (Entitlement $e) use (&$count) {
                DB::transaction(function () use ($e, &$count) {
                    $item = $e->orderItem;
                    if ($item === null) {
                        return;
                    }
                    $order = Order::query()->whereKey($item->order_id)->lockForUpdate()->firstOrFail();
                    $locked = Entitlement::query()->whereKey($e->id)->lockForUpdate()->first();
                    if ($locked === null || $locked->status !== 'active' || $locked->expires_at?->isFuture()) {
                        return;
                    }
                    $locked->forceFill(['status' => $order->status === Order::PAID ? 'expired' : 'revoked'])->save();
                    if ($order->status === Order::PAID) {
                        $jobs = Delivery::query()->where('order_item_id', $item->id)->where('phase', 'purchase')->lockForUpdate()->get();
                        if ($jobs->every(fn (Delivery $job) => $job->status === Delivery::DELIVERED)) {
                            $this->enqueue($item, 'expire');
                        } else {
                            $order->forceFill(['fulfillment_status' => 'manual_review', 'admin_note' => 'Entitlement expired before purchase delivery was confirmed'])->save();
                            Delivery::query()->where('order_item_id', $item->id)->where('phase', 'purchase')->where('status', Delivery::QUEUED)->update(['status' => Delivery::CANCELLED]);
                            Delivery::query()->where('order_item_id', $item->id)->where('phase', 'purchase')->where('status', Delivery::LEASED)->update(['status' => Delivery::MANUAL_REVIEW, 'last_error' => 'Entitlement expired while command execution was unconfirmed']);
                        }
                    }
                    $count++;
                });
            });

        return $count;
    }

    public function enqueue(OrderItem $item, string $phase): int
    {
        if ($phase === 'purchase') {
            return DB::transaction(function () use ($item, $phase) {
                $order = Order::whereKey($item->order_id)->lockForUpdate()->firstOrFail();
                if (! $this->canGrant($order)) {
                    return 0;
                }
                $current = $order->items()->whereKey($item->id)->firstOrFail();

                return $this->enqueueCommands($current, $phase);
            });
        }

        return $this->enqueueCommands($item, $phase);
    }

    private function enqueueCommands(OrderItem $item, string $phase): int
    {
        $created = 0;
        $order = $item->order;
        if (! $order || ! in_array($phase, ProductCommand::PHASES, true)) {
            return 0;
        }
        $frozen = $this->contracts->requiredFor($order);
        if ($frozen) {
            $contract = OrderContract::where('order_id', $order->id)->first();
            $commands = $contract && $this->contracts->validFor($order) ? ExecutionPlans::commands($contract, $item, $phase) : null;
            if ($commands === null) {
                $order->forceFill(['fulfillment_status' => 'manual_review', 'admin_note' => 'Original execution plan or its game server requires review'])->save();

                return 0;
            }
        } else {
            $commands = $item->product ? array_values(array_filter(ExecutionPlans::product($item->product), static fn (array $command) => $command['phase'] === $phase)) : [];
        }
        // Later phases address the player's current verified name for the same UUID.
        $playerName = $item->player_name;
        if ($phase !== 'purchase' && $item->player_uuid !== null) {
            $current = GameIdentity::query()->where('uuid', $item->player_uuid)->whereNotNull('verified_at')->value('username');
            if (is_string($current) && $current !== '') {
                $playerName = $current;
            }
        }
        foreach ($commands as $command) {
            try {
                $rendered = CommandTemplate::render($command['template'], [
                    'player' => $playerName,
                    'uuid' => $item->player_uuid,
                    'quantity' => $item->quantity,
                    'order' => $order->public_id,
                    'days' => $item->duration_days,
                ]);
                $status = Delivery::QUEUED;
                $error = null;
            } catch (CommerceException $e) {
                // Never guess: an unrenderable command goes to a human.
                $rendered = $command['template'];
                $status = Delivery::MANUAL_REVIEW;
                $error = $e->getMessage();
                Log::warning('fulfillment.render_failed', ['item' => $item->id, 'command' => $command['id']]);
            }

            $delivery = Delivery::query()->firstOrCreate(
                ['dedup_key' => "item:{$item->id}:cmd:{$command['id']}:{$phase}"],
                [
                    'order_id' => $item->order_id,
                    'order_item_id' => $item->id,
                    'product_command_id' => $frozen ? null : $command['id'],
                    'game_server_id' => $command['server_id'],
                    'user_id' => $order->user_id,
                    'phase' => $phase,
                    'player_uuid' => $item->player_uuid,
                    'player_name' => $playerName,
                    'command' => $rendered,
                    'require_online' => $command['require_online'],
                    'status' => $status,
                    'last_error' => $error,
                    'available_at' => now(),
                ],
            );
            if ($delivery->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    public function recompute(Order $order): void
    {
        if ($order->fulfillment_status === 'manual_review') {
            return;
        }
        $statuses = Delivery::query()->where('order_id', $order->id)->where('phase', 'purchase')->pluck('status');
        if ($statuses->isEmpty()) {
            return;
        }

        if (in_array($order->status, [Order::REFUNDED, Order::DISPUTED], true)) {
            $refunds = Delivery::query()->where('order_id', $order->id)->where('phase', 'refund')->pluck('status');
            $state = match (true) {
                $refunds->contains(Delivery::MANUAL_REVIEW) || $refunds->contains(Delivery::DEAD) => 'attention',
                $refunds->isNotEmpty() && ! $refunds->every(fn ($status) => in_array($status, [Delivery::DELIVERED, Delivery::CANCELLED], true)) => 'queued',
                $statuses->every(fn ($status) => $status === Delivery::CANCELLED) => 'cancelled',
                default => 'revoked',
            };
            $order->forceFill(['fulfillment_status' => $state])->save();

            return;
        }

        $state = match (true) {
            $statuses->every(fn ($s) => in_array($s, [Delivery::DELIVERED, Delivery::CANCELLED], true)) => 'delivered',
            $statuses->contains(Delivery::DEAD) || $statuses->contains(Delivery::MANUAL_REVIEW) => 'attention',
            $statuses->contains(Delivery::DELIVERED) => 'partial',
            default => 'queued',
        };

        if ($order->status === Order::PAID || $order->status === Order::REFUNDED || $order->status === Order::DISPUTED) {
            $order->forceFill(['fulfillment_status' => $state])->save();
        }
    }
}
