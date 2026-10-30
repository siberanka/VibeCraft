<?php

namespace App\Domain\Payments\Gateways;

use App\Core\Settings\Settings;
use App\Domain\Commerce\CommerceException;
use App\Domain\Payments\InvalidWebhook;
use App\Domain\Payments\PaymentGateway;
use App\Domain\Payments\VerifiedEvent;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Stripe Checkout (hosted). Webhooks are verified with the documented
 * "Stripe-Signature: t=…,v1=…" HMAC-SHA256 scheme (5 minute tolerance), and every
 * success is re-checked against the Checkout Session API before it is applied.
 */
class StripeGateway implements PaymentGateway
{
    private const API = 'https://api.stripe.com/v1';

    private const TOLERANCE = 300;

    public function __construct(private Settings $settings) {}

    public function id(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return __('Card (Stripe)');
    }

    public function isAvailable(): bool
    {
        return $this->settings->bool('payments.stripe.enabled') && $this->acceptsWebhooks();
    }

    public function acceptsWebhooks(): bool
    {
        $key = (string) $this->settings->secret('payments.stripe.secret_key');
        $live = str_starts_with($key, 'sk_live_') || str_starts_with($key, 'rk_live_');
        $test = str_starts_with($key, 'sk_test_') || str_starts_with($key, 'rk_test_');

        return (app()->isProduction() ? $live : $test) && $this->settings->hasSecret('payments.stripe.webhook_secret');
    }

    public function begin(Order $order, Payment $payment, Request $request): Response
    {
        $response = Http::asForm()
            ->withToken((string) $this->settings->secret('payments.stripe.secret_key'))
            ->withHeaders(['Idempotency-Key' => $payment->provider_reference])
            ->timeout(15)
            ->post(self::API.'/checkout/sessions', [
                'mode' => 'payment',
                'client_reference_id' => $payment->provider_reference,
                'customer_email' => $order->user->email,
                'success_url' => route('checkout.return', ['order' => $order->public_id]),
                'cancel_url' => route('checkout.return', ['order' => $order->public_id, 'cancelled' => 1]),
                'expires_at' => now()->addMinutes(max(30, (int) config('vibecraft.store.order_ttl_minutes')))->timestamp,
                'metadata' => ['payment_reference' => $payment->provider_reference, 'order' => $order->public_id],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($order->currency),
                        'unit_amount' => $order->total_minor,
                        'product_data' => ['name' => $order->kind === 'topup' ? __('Balance top-up') : __('Order :id', ['id' => $order->public_id])],
                    ],
                ]],
            ]);

        if (! $response->successful() || ! is_string($response->json('url')) || ! is_string($response->json('id'))
            || ! str_starts_with($response->json('url'), 'https://checkout.stripe.com/')) {
            Log::error('stripe.session_failed', ['status' => $response->status(), 'error' => $response->json('error.message')]);
            throw new CommerceException(__('The payment provider is not reachable. Please try again.'));
        }

        // The session id is what webhooks carry; keep our own reference in metadata.
        $payment->forceFill(['status' => 'pending', 'provider_payment_id' => $response->json('id')])->save();

        return redirect()->away((string) $response->json('url'));
    }

    public function parseWebhook(Request $request): VerifiedEvent
    {
        $secret = (string) $this->settings->secret('payments.stripe.webhook_secret');
        $payload = $request->getContent();
        self::verifySignature($payload, (string) $request->header('Stripe-Signature'), $secret, self::TOLERANCE);

        $event = json_decode($payload, true);
        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['type'] ?? null)
            || ! is_bool($event['livemode'] ?? null) || $event['livemode'] !== $this->liveMode()) {
            throw new InvalidWebhook('malformed event');
        }

        $object = $event['data']['object'] ?? [];
        if (! is_array($object) || (isset($object['metadata']) && ! is_array($object['metadata']))) {
            throw new InvalidWebhook('malformed object');
        }
        foreach (['id', 'client_reference_id', 'payment_intent', 'currency'] as $field) {
            if (isset($object[$field]) && ! is_string($object[$field])) {
                throw new InvalidWebhook('invalid object field');
            }
        }
        if (isset($object['amount_total']) && ! is_int($object['amount_total'])) {
            throw new InvalidWebhook('invalid amount');
        }
        if (isset($object['metadata']['payment_reference']) && ! is_string($object['metadata']['payment_reference'])) {
            throw new InvalidWebhook('invalid reference');
        }
        $type = (string) $event['type'];

        return match ($type) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => new VerifiedEvent(
                'stripe', $event['id'], $type,
                ($object['payment_status'] ?? null) === 'paid' ? VerifiedEvent::SUCCEEDED : VerifiedEvent::IGNORED,
                $object['client_reference_id'] ?? ($object['metadata']['payment_reference'] ?? null),
                $object['id'] ?? null,
                isset($object['amount_total']) ? (int) $object['amount_total'] : null,
                isset($object['currency']) ? strtoupper((string) $object['currency']) : null,
                ['type' => $type, 'session' => $object['id'] ?? null],
            ),
            'checkout.session.async_payment_failed', 'checkout.session.expired' => new VerifiedEvent(
                'stripe', $event['id'], $type, VerifiedEvent::FAILED,
                $object['client_reference_id'] ?? null, $object['id'] ?? null, payload: ['type' => $type, 'reason' => $type],
            ),
            'charge.refunded', 'charge.dispute.created' => $this->chargeEvent($event, $object),
            default => new VerifiedEvent('stripe', $event['id'], $type, VerifiedEvent::IGNORED, payload: ['type' => $type]),
        };
    }

    /**
     * @throws InvalidWebhook
     */
    public static function verifySignature(string $payload, string $header, string $secret, int $tolerance, ?int $now = null): void
    {
        if ($secret === '' || $header === '') {
            throw new InvalidWebhook('missing signature');
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') {
                if ($timestamp !== null || ! preg_match('/^[0-9]{10}$/D', $v)) {
                    throw new InvalidWebhook('malformed timestamp');
                }
                $timestamp = (int) $v;
            } elseif ($k === 'v1') {
                if (preg_match('/^[0-9a-f]{64}$/D', $v)) {
                    $signatures[] = $v; // other schemes (v0) are ignored to prevent downgrade
                }
            }
        }

        if ($timestamp === null || $signatures === []) {
            throw new InvalidWebhook('malformed signature header');
        }
        if (abs(($now ?? time()) - $timestamp) > $tolerance) {
            throw new InvalidWebhook('timestamp outside tolerance');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return;
            }
        }

        throw new InvalidWebhook('signature mismatch');
    }

    public function confirm(VerifiedEvent $event): VerifiedEvent
    {
        if ($event->providerPaymentId === null || $event->reference === null) {
            return new VerifiedEvent('stripe', $event->eventId, $event->type, VerifiedEvent::IGNORED);
        }

        try {
            $session = Http::withToken((string) $this->settings->secret('payments.stripe.secret_key'))
                ->timeout(15)->get(self::API.'/checkout/sessions/'.rawurlencode($event->providerPaymentId))->throw()->json();
        } catch (Throwable) {
            // Leave the inbox entry failed; the retry command will process it again.
            throw new CommerceException('Stripe confirmation failed');
        }

        if (! is_array($session) || ($session['id'] ?? null) !== $event->providerPaymentId
            || ($session['client_reference_id'] ?? null) !== $event->reference
            || ($session['mode'] ?? null) !== 'payment' || ($session['livemode'] ?? null) !== $this->liveMode()
            || ! is_int($session['amount_total'] ?? null) || ! is_string($session['currency'] ?? null)
            || ! is_string($session['payment_intent'] ?? null)) {
            throw new CommerceException('Stripe confirmation did not match the requested payment');
        }

        if (($session['payment_status'] ?? null) !== 'paid') {
            throw new CommerceException('Stripe has not confirmed the payment as paid');
        }

        return new VerifiedEvent(
            'stripe', $event->eventId, $event->type, VerifiedEvent::SUCCEEDED, $event->reference,
            // @phpstan-ignore nullCoalesce.offset (Preserve the provider payload fallback after validation.)
            (string) ($session['payment_intent'] ?? $session['id']),
            // @phpstan-ignore nullCoalesce.offset (Preserve the provider payload fallback after validation.)
            (int) ($session['amount_total'] ?? -1),
            // @phpstan-ignore nullCoalesce.offset (Preserve the provider payload fallback after validation.)
            strtoupper((string) ($session['currency'] ?? '')),
        );
    }

    public function webhookResponse(bool $accepted): Response
    {
        return response()->json(['received' => $accepted], $accepted ? 200 : 400);
    }

    private function liveMode(): bool
    {
        $key = (string) $this->settings->secret('payments.stripe.secret_key');
        if (! str_starts_with($key, 'sk_live_') && ! str_starts_with($key, 'sk_test_')
            && ! str_starts_with($key, 'rk_live_') && ! str_starts_with($key, 'rk_test_')) {
            throw new InvalidWebhook('Stripe API key mode is not configured');
        }

        return str_starts_with($key, 'sk_live_') || str_starts_with($key, 'rk_live_');
    }

    /** @param array<string, mixed> $event @param array<string, mixed> $object */
    private function chargeEvent(array $event, array $object): VerifiedEvent
    {
        $type = (string) $event['type'];
        $paymentIntent = $object['payment_intent'] ?? null;
        $refundedFully = $type === 'charge.refunded' && ($object['refunded'] ?? false) === true;

        return new VerifiedEvent(
            'stripe', $event['id'], $type,
            $type === 'charge.dispute.created' ? VerifiedEvent::DISPUTED : ($refundedFully ? VerifiedEvent::REFUNDED : VerifiedEvent::IGNORED),
            null, is_string($paymentIntent) ? $paymentIntent : null,
            payload: ['type' => $type, 'payment_intent' => $paymentIntent],
        );
    }
}
