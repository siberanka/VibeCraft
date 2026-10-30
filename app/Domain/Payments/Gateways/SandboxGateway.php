<?php

namespace App\Domain\Payments\Gateways;

use App\Core\Settings\Settings;
use App\Domain\Payments\InvalidWebhook;
use App\Domain\Payments\PaymentGateway;
use App\Domain\Payments\VerifiedEvent;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Local test gateway. It never moves real money and is unavailable in production
 * under any configuration. Its webhooks are HMAC-signed with a key derived from
 * APP_KEY, so the full inbox/idempotency path is exercised exactly like a real provider.
 */
class SandboxGateway implements PaymentGateway
{
    public function __construct(private Settings $settings) {}

    public function id(): string
    {
        return 'sandbox';
    }

    public function label(): string
    {
        return __('Test payment (sandbox)');
    }

    public function isAvailable(): bool
    {
        return ! app()->isProduction() && $this->settings->bool('payments.sandbox.enabled');
    }

    public function acceptsWebhooks(): bool
    {
        return $this->isAvailable();
    }

    public function begin(Order $order, Payment $payment, Request $request): Response
    {
        if (! $this->isAvailable()) {
            abort(404);
        }
        $payment->forceFill(['status' => 'pending'])->save();

        return redirect()->route('checkout.sandbox', ['reference' => $payment->provider_reference]);
    }

    public static function secret(): string
    {
        return hash_hmac('sha256', 'vibecraft-sandbox-webhook', (string) config('app.key'));
    }

    public static function sign(string $body, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, self::secret());
    }

    public function parseWebhook(Request $request): VerifiedEvent
    {
        if (! $this->isAvailable() || (string) config('app.key') === '') {
            throw new InvalidWebhook('sandbox unavailable');
        }
        $body = $request->getContent();
        $rawTimestamp = $request->header('X-Sandbox-Timestamp');
        if (! is_string($rawTimestamp) || ! preg_match('/^[0-9]{10}$/D', $rawTimestamp)) {
            throw new InvalidWebhook('bad sandbox timestamp');
        }
        $timestamp = (int) $rawTimestamp;
        $signature = (string) $request->header('X-Sandbox-Signature');

        if (abs(time() - $timestamp) > 300 || ! hash_equals(self::sign($body, $timestamp), $signature)) {
            throw new InvalidWebhook('bad sandbox signature');
        }

        $data = json_decode($body, true);
        if (! is_array($data) || ! is_string($data['id'] ?? null) || ! is_string($data['type'] ?? null)
            || ! is_string($data['reference'] ?? null) || (isset($data['amount']) && ! is_int($data['amount']))
            || (isset($data['currency']) && ! is_string($data['currency']))) {
            throw new InvalidWebhook('malformed sandbox event');
        }

        return new VerifiedEvent(
            'sandbox',
            (string) $data['id'],
            (string) $data['type'],
            match ($data['type']) {
                'payment.succeeded' => VerifiedEvent::SUCCEEDED,
                'payment.failed' => VerifiedEvent::FAILED,
                'payment.refunded' => VerifiedEvent::REFUNDED,
                default => VerifiedEvent::IGNORED,
            },
            (string) $data['reference'],
            'sbx_'.substr(hash('sha256', (string) $data['reference']), 0, 16),
            isset($data['amount']) ? (int) $data['amount'] : null,
            isset($data['currency']) ? (string) $data['currency'] : null,
            ['type' => $data['type']],
        );
    }

    public function confirm(VerifiedEvent $event): VerifiedEvent
    {
        return $event;
    }

    public function webhookResponse(bool $accepted): Response
    {
        return response()->json(['received' => $accepted], $accepted ? 200 : 400);
    }
}
