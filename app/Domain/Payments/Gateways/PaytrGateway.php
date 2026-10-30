<?php

namespace App\Domain\Payments\Gateways;

use App\Core\Settings\Settings;
use App\Domain\Commerce\CommerceException;
use App\Domain\Payments\InvalidWebhook;
use App\Domain\Payments\PaymentGateway;
use App\Domain\Payments\VerifiedEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * PayTR iFrame API (Türkiye). Token: base64(HMAC-SHA256(hash_str . merchant_salt, merchant_key)).
 * Callback: base64(HMAC-SHA256(merchant_oid . merchant_salt . status . total_amount, merchant_key)),
 * answered with the plain text "OK". Source: dev.paytr.com iFrame API steps 1–2 (checked 2026-10-24).
 */
class PaytrGateway implements PaymentGateway
{
    private const TOKEN_URL = 'https://www.paytr.com/odeme/api/get-token';

    public function __construct(private Settings $settings) {}

    public function id(): string
    {
        return 'paytr';
    }

    public function label(): string
    {
        return __('Card / EFT (PayTR)');
    }

    public function isAvailable(): bool
    {
        return $this->settings->bool('payments.paytr.enabled') && $this->acceptsWebhooks() && Money::currency() === 'TRY';
    }

    public function acceptsWebhooks(): bool
    {
        return (! app()->isProduction() || ! $this->settings->bool('payments.paytr.test_mode'))
            && $this->settings->get('payments.paytr.merchant_id') !== ''
            && $this->settings->hasSecret('payments.paytr.merchant_key')
            && $this->settings->hasSecret('payments.paytr.merchant_salt');
    }

    public function begin(Order $order, Payment $payment, Request $request): Response
    {
        $merchantId = (string) $this->settings->get('payments.paytr.merchant_id');
        $key = (string) $this->settings->secret('payments.paytr.merchant_key');
        $salt = (string) $this->settings->secret('payments.paytr.merchant_salt');
        $testMode = $this->settings->bool('payments.paytr.test_mode') ? '1' : '0';

        $basket = base64_encode((string) json_encode([[
            $order->kind === 'topup' ? 'Bakiye yükleme' : 'Sipariş '.$order->public_id,
            Money::toDecimalString($order->total_minor),
            1,
        ]]));

        $fields = [
            'merchant_id' => $merchantId,
            'user_ip' => (string) $request->ip(),
            'merchant_oid' => $payment->provider_reference,
            'email' => $order->user->email,
            'payment_amount' => (string) $order->total_minor,
            'user_basket' => $basket,
            'no_installment' => '1',
            'max_installment' => '0',
            'currency' => 'TL',
            'test_mode' => $testMode,
        ];
        $hashStr = $fields['merchant_id'].$fields['user_ip'].$fields['merchant_oid'].$fields['email'].$fields['payment_amount']
            .$fields['user_basket'].$fields['no_installment'].$fields['max_installment'].$fields['currency'].$fields['test_mode'];

        $response = Http::asForm()->timeout(20)->post(self::TOKEN_URL, $fields + [
            'paytr_token' => base64_encode(hash_hmac('sha256', $hashStr.$salt, $key, true)),
            'user_name' => $order->user->name,
            'user_address' => 'Dijital teslimat',
            'user_phone' => (string) ($this->settings->get('payments.paytr.contact_phone') ?: '0000000000'),
            'merchant_ok_url' => route('checkout.return', ['order' => $order->public_id]),
            'merchant_fail_url' => route('checkout.return', ['order' => $order->public_id, 'cancelled' => 1]),
            'timeout_limit' => (string) config('vibecraft.store.order_ttl_minutes'),
            'debug_on' => '0',
            'lang' => app()->getLocale() === 'en' ? 'en' : 'tr',
        ]);

        if ($response->json('status') !== 'success' || ! is_string($response->json('token'))) {
            Log::error('paytr.token_failed', ['reason' => $response->json('reason'), 'status' => $response->status()]);
            throw new CommerceException(__('The payment provider is not reachable. Please try again.'));
        }

        $payment->forceFill(['status' => 'pending'])->save();

        return response()->view('theme::checkout.paytr', [
            'order' => $order,
            'iframeUrl' => 'https://www.paytr.com/odeme/guvenli/'.rawurlencode((string) $response->json('token')),
        ]);
    }

    public function parseWebhook(Request $request): VerifiedEvent
    {
        $key = (string) $this->settings->secret('payments.paytr.merchant_key');
        $salt = (string) $this->settings->secret('payments.paytr.merchant_salt');
        $oid = $request->input('merchant_oid', '');
        $status = $request->input('status', '');
        $total = $request->input('total_amount', '');
        $signature = $request->input('hash', '');

        if ($key === '' || $salt === '' || ! is_string($oid) || ! preg_match('/^[A-Za-z0-9]{1,191}$/D', $oid)
            || ! is_string($status) || ! in_array($status, ['success', 'failed'], true) || ! is_string($total)
            || ! preg_match('/^[0-9]{1,13}$/D', $total) || ! is_string($signature)) {
            throw new InvalidWebhook('malformed callback');
        }

        $expected = base64_encode(hash_hmac('sha256', $oid.$salt.$status.$total, $key, true));
        if (! hash_equals($expected, $signature)) {
            throw new InvalidWebhook('bad hash');
        }

        // This integration disables installments. Only total_amount is covered by
        // the documented callback HMAC; payment_amount must never override it.

        return new VerifiedEvent(
            'paytr',
            $oid.':'.$status,
            'callback.'.$status,
            $status === 'success' ? VerifiedEvent::SUCCEEDED : VerifiedEvent::FAILED,
            $oid,
            $oid,
            (int) $total,
            'TRY',
            [
                'status' => $status,
                'payment_type' => is_string($request->input('payment_type')) ? mb_substr($request->input('payment_type'), 0, 30) : null,
                'reason' => is_string($request->input('failed_reason_msg')) ? mb_substr($request->input('failed_reason_msg'), 0, 255) : null,
                'test_mode' => is_string($request->input('test_mode')) ? mb_substr($request->input('test_mode'), 0, 1) : null,
            ],
        );
    }

    public function confirm(VerifiedEvent $event): VerifiedEvent
    {
        // The callback hash is PayTR's documented authenticity proof for this flow.
        return $event;
    }

    public function webhookResponse(bool $accepted): Response
    {
        // PayTR retries until it receives exactly "OK"; invalid hashes get a non-OK body.
        return response($accepted ? 'OK' : 'PAYTR notification failed', $accepted ? 200 : 400)->header('Content-Type', 'text/plain');
    }
}
