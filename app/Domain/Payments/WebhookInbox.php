<?php

namespace App\Domain\Payments;

use App\Domain\Commerce\OrderLifecycle;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stores each verified provider event exactly once and applies it to the order.
 * Duplicate deliveries of the same event are acknowledged without side effects.
 */
class WebhookInbox
{
    public function __construct(private OrderLifecycle $lifecycle) {}

    private const REVERSALS = [VerifiedEvent::REFUNDED, VerifiedEvent::DISPUTED];

    /** @return string received|duplicate|processed|ignored|deferred|failed */
    public function accept(PaymentGateway $gateway, VerifiedEvent $event): string
    {
        if ($gateway->id() !== $event->provider) {
            throw new InvalidWebhook('provider mismatch');
        }
        try {
            $record = WebhookEvent::query()->create([
                'provider' => $event->provider,
                'event_id' => $event->eventId,
                'event_type' => $event->type,
                'payload' => [
                    'outcome' => $event->outcome,
                    'reference' => $event->reference,
                    'provider_payment_id' => $event->providerPaymentId,
                    'amount' => $event->amountMinor,
                    'currency' => $event->currency,
                    'data' => $event->payload,
                ],
                'status' => 'received',
            ]);
        } catch (UniqueConstraintViolationException) {
            return 'duplicate';
        }

        return $this->process($gateway, $record, $event);
    }

    /** Rebuilds the verified event from the normalized inbox record (for retries). */
    public static function rebuild(WebhookEvent $record): VerifiedEvent
    {
        $p = (array) $record->payload;

        return new VerifiedEvent(
            $record->provider,
            $record->event_id,
            (string) $record->event_type,
            (string) ($p['outcome'] ?? VerifiedEvent::IGNORED),
            $p['reference'] ?? null,
            $p['provider_payment_id'] ?? null,
            isset($p['amount']) ? (int) $p['amount'] : null,
            $p['currency'] ?? null,
            (array) ($p['data'] ?? []),
        );
    }

    public function process(PaymentGateway $gateway, WebhookEvent $record, VerifiedEvent $event): string
    {
        try {
            return DB::transaction(function () use ($gateway, $record, $event) {
                $record = WebhookEvent::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
                if ($record->provider !== $gateway->id() || $record->provider !== $event->provider || $record->event_id !== $event->eventId) {
                    throw new InvalidWebhook('inbox identity mismatch');
                }
                if (in_array($record->status, ['processed', 'ignored', 'deferred'], true)) {
                    return 'duplicate';
                }
                $record->increment('attempts');
                $payment = $event->reference !== null
                    ? Payment::query()->where('provider', $event->provider)->where('provider_reference', $event->reference)->first()
                    : null;

                if ($event->reference === null && $event->providerPaymentId !== null) {
                    $payment = Payment::query()->where('provider', $event->provider)->where('provider_payment_id', $event->providerPaymentId)->first();
                }

                if ($payment === null && in_array($event->outcome, self::REVERSALS, true) && $event->providerPaymentId !== null) {
                    // A reversal can arrive before the success that records the provider's payment id.
                    // Keep it so the success applies it instead of crediting a reversed payment.
                    return $this->defer($record);
                }
                if ($payment === null || $event->outcome === VerifiedEvent::IGNORED) {
                    $record->forceFill(['status' => 'ignored', 'processed_at' => now(), 'error' => $payment === null ? 'unknown payment reference' : null])->save();

                    return 'ignored';
                }

                // Match checkout's lock order: order first, then its payment attempt.
                $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
                $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
                switch ($event->outcome) {
                    case VerifiedEvent::SUCCEEDED:
                        $confirmed = $gateway->confirm($event);
                        if ($confirmed->provider !== $event->provider || $confirmed->reference !== $event->reference
                            || $confirmed->eventId !== $event->eventId) {
                            throw new InvalidWebhook('confirmation identity mismatch');
                        }
                        if ($confirmed->outcome !== VerifiedEvent::SUCCEEDED) {
                            $record->forceFill(['status' => 'ignored', 'processed_at' => now(), 'error' => 'not confirmed by provider API'])->save();

                            return 'ignored';
                        }
                        $this->lifecycle->markPaid($order, $event->provider, $confirmed->providerPaymentId, (int) $confirmed->amountMinor, (string) $confirmed->currency, $payment);
                        $this->applyDeferredReversals($order, $payment->fresh() ?? $payment);
                        break;
                    case VerifiedEvent::FAILED:
                        if (in_array($payment->status, ['created', 'pending'], true)) {
                            $payment->forceFill(['status' => 'failed', 'failure_reason' => mb_substr((string) ($event->payload['reason'] ?? 'failed'), 0, 255)])->save();
                        }
                        break;
                    case VerifiedEvent::REFUNDED:
                    case VerifiedEvent::DISPUTED:
                        if (in_array($order->status, [Order::PENDING, Order::CANCELLED], true)) {
                            return $this->defer($record);
                        }
                        $this->lifecycle->markRefunded($order, $event->provider.':'.$event->eventId, dispute: $event->outcome === VerifiedEvent::DISPUTED, payment: $payment);
                        break;
                }

                $record->forceFill(['status' => 'processed', 'processed_at' => now(), 'error' => null])->save();

                return 'processed';
            }, 3);
        } catch (Throwable $e) {
            Log::error('webhook.processing_failed', ['provider' => $event->provider, 'event' => $event->eventId, 'error_type' => get_class($e)]);
            // The failed transaction rolled back its attempt counter. Persist the
            // failure separately without overwriting a concurrent successful retry.
            WebhookEvent::query()->whereKey($record->id)->whereNotIn('status', ['processed', 'ignored'])
                ->update(['status' => 'failed', 'error' => 'processing_failed', 'attempts' => DB::raw('attempts + 1')]);

            return 'failed';
        }
    }

    private function defer(WebhookEvent $record): string
    {
        $record->forceFill(['status' => 'deferred', 'processed_at' => null, 'error' => 'waiting for the payment confirmation'])->save();

        return 'deferred';
    }

    /** Applies reversals that arrived before this payment was confirmed. */
    private function applyDeferredReversals(Order $order, Payment $payment): void
    {
        $pending = WebhookEvent::query()->where('provider', $payment->provider)->where('status', 'deferred')
            ->where(function ($query) use ($payment) {
                $query->where('payload->reference', $payment->provider_reference);
                if ($payment->provider_payment_id !== null) {
                    $query->orWhere('payload->provider_payment_id', $payment->provider_payment_id);
                }
            })->orderBy('id')->lockForUpdate()->get();
        foreach ($pending as $deferred) {
            $reversal = self::rebuild($deferred);
            if (! in_array($reversal->outcome, self::REVERSALS, true)) {
                continue;
            }
            $applied = $this->lifecycle->markRefunded(Order::query()->whereKey($order->id)->firstOrFail(), $reversal->provider.':'.$reversal->eventId,
                dispute: $reversal->outcome === VerifiedEvent::DISPUTED, payment: Payment::query()->whereKey($payment->id)->firstOrFail());
            if ($applied) {
                $deferred->forceFill(['status' => 'processed', 'processed_at' => now(), 'error' => null])->save();
            }
        }
    }
}
