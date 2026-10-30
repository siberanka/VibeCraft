<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Commerce\Checkout;
use App\Domain\Commerce\Ledger;
use App\Domain\Payments\Gateways\PaytrGateway;
use App\Domain\Payments\Gateways\SandboxGateway;
use App\Domain\Payments\Gateways\StripeGateway;
use App\Domain\Payments\InvalidWebhook;
use App\Domain\Payments\VerifiedEvent;
use App\Domain\Payments\WebhookInbox;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WalletEntry;
use App\Models\WebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class PaymentIntegrityTest extends TestCase
{
    public function test_webhook_acknowledgement_supports_the_base_response_contract(): void
    {
        $this->enableSandbox();
        $gateway = new class(app(Settings::class)) extends SandboxGateway
        {
            public function parseWebhook(Request $request): VerifiedEvent
            {
                return new VerifiedEvent('sandbox', 'plain-response', 'fixture.ignored', VerifiedEvent::IGNORED);
            }

            public function webhookResponse(bool $accepted): Response
            {
                return new Response('acknowledged', $accepted ? 202 : 400);
            }
        };
        $this->app->instance(SandboxGateway::class, $gateway);

        $this->post('/api/payments/sandbox/webhook')->assertStatus(202)
            ->assertHeader('X-VC-Webhook', 'ignored')->assertSee('acknowledged');
        $this->assertSame('ignored', WebhookEvent::sole()->status);
    }

    private function event(Payment $payment, string $id, string $outcome = VerifiedEvent::SUCCEEDED, ?int $amount = null, string $currency = 'TRY'): VerifiedEvent
    {
        return new VerifiedEvent($payment->provider, $id, 'payment.'.$outcome, $outcome,
            $payment->provider_reference, 'pay_'.$payment->id, $amount ?? $payment->amount_minor, $currency);
    }

    public function test_duplicate_and_distinct_success_events_credit_a_topup_only_once(): void
    {
        $this->enableSandbox();
        $user = $this->customer();
        $order = $this->pendingOrder($user);
        $payment = app(Checkout::class)->createPayment($order, 'sandbox');
        $inbox = app(WebhookInbox::class);
        $gateway = app(SandboxGateway::class);
        $event = $this->event($payment, 'fixture:success');
        $this->assertSame('processed', $inbox->accept($gateway, $event));
        $this->assertSame('duplicate', $inbox->accept($gateway, $event));
        $this->assertSame('processed', $inbox->accept($gateway, $this->event($payment, 'fixture:second-event')));
        $this->assertSame(Order::PAID, $order->fresh()->status);
        $this->assertSame(1000, app(Ledger::class)->balance($user->id));
        $this->assertSame(1, WalletEntry::count());
        $this->assertSame(2, WebhookEvent::count());
    }

    public function test_wrong_amount_or_currency_cannot_credit_a_wallet(): void
    {
        $this->enableSandbox();
        $user = $this->customer();
        foreach ([[999, 'TRY'], [1000, 'USD']] as $index => [$amount, $currency]) {
            $order = $this->pendingOrder($user);
            $payment = app(Checkout::class)->createPayment($order, 'sandbox');
            app(WebhookInbox::class)->accept(app(SandboxGateway::class), $this->event($payment, 'fixture:mismatch:'.$index, amount: $amount, currency: $currency));
            $this->assertSame(Order::PENDING, $order->fresh()->status);
            $this->assertSame('manual_review', $order->fresh()->fulfillment_status);
            $this->assertSame('failed', $payment->fresh()->status);
        }
        $this->assertSame(0, app(Ledger::class)->balance($user->id));
        $this->assertSame(0, WalletEntry::count());
    }

    public function test_refund_then_dispute_cannot_reverse_twice_or_be_reopened_by_success(): void
    {
        $this->enableSandbox();
        $user = $this->customer();
        $order = $this->pendingOrder($user);
        $payment = app(Checkout::class)->createPayment($order, 'sandbox');
        $inbox = app(WebhookInbox::class);
        $gateway = app(SandboxGateway::class);
        foreach ([VerifiedEvent::SUCCEEDED, VerifiedEvent::REFUNDED, VerifiedEvent::REFUNDED, VerifiedEvent::DISPUTED, VerifiedEvent::SUCCEEDED] as $index => $outcome) {
            $inbox->accept($gateway, $this->event($payment, 'fixture:lifecycle:'.$index, $outcome));
        }
        $this->assertSame(Order::DISPUTED, $order->fresh()->status);
        $this->assertSame(0, app(Ledger::class)->balance($user->id));
        $this->assertSame(2, WalletEntry::count());
    }

    public function test_refund_of_an_unsettled_attempt_cannot_refund_the_order(): void
    {
        $this->enableSandbox();
        $user = $this->customer();
        $order = $this->pendingOrder($user);
        $paid = app(Checkout::class)->createPayment($order, 'sandbox');
        $otherAttempt = app(Checkout::class)->createPayment($order, 'sandbox');
        $inbox = app(WebhookInbox::class);
        $gateway = app(SandboxGateway::class);
        $inbox->accept($gateway, $this->event($paid, 'fixture:paid'));
        $inbox->accept($gateway, $this->event($otherAttempt, 'fixture:unsettled-refund', VerifiedEvent::REFUNDED));
        $this->assertSame(Order::PAID, $order->fresh()->status);
        $this->assertSame('created', $otherAttempt->fresh()->status);
        $this->assertSame(1000, app(Ledger::class)->balance($user->id));
    }

    public function test_sandbox_cannot_be_enabled_in_production(): void
    {
        $this->enableSandbox();
        $this->app->instance('env', 'production');
        $this->assertFalse(app(SandboxGateway::class)->isAvailable());
    }

    public function test_signed_sandbox_webhook_exercises_http_and_inbox_deduplication(): void
    {
        $this->enableSandbox();
        $user = $this->customer();
        $payment = app(Checkout::class)->createPayment($this->pendingOrder($user), 'sandbox');
        $body = json_encode(['id' => 'fixture:http', 'type' => 'payment.succeeded', 'reference' => $payment->provider_reference, 'amount' => 1000, 'currency' => 'TRY'], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SANDBOX_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_SANDBOX_SIGNATURE' => SandboxGateway::sign($body, $timestamp)];
        $this->call('POST', '/api/payments/sandbox/webhook', [], [], [], $headers, $body)->assertOk();
        $this->call('POST', '/api/payments/sandbox/webhook', [], [], [], $headers, $body)->assertOk()->assertHeader('X-VC-Webhook', 'duplicate');
        $this->assertSame(1000, app(Ledger::class)->balance($user->id));
    }

    public function test_signed_but_malformed_sandbox_amount_is_rejected(): void
    {
        $this->enableSandbox();
        $body = json_encode(['id' => 'fixture:array', 'type' => 'payment.succeeded', 'reference' => ['bad'], 'amount' => '1000'], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $request = Request::create('/callback', 'POST', [], [], [], ['HTTP_X_SANDBOX_TIMESTAMP' => (string) $timestamp, 'HTTP_X_SANDBOX_SIGNATURE' => SandboxGateway::sign($body, $timestamp)], $body);
        $this->expectException(InvalidWebhook::class);
        app(SandboxGateway::class)->parseWebhook($request);
    }

    public function test_paytr_unsigned_payment_amount_cannot_override_signed_total(): void
    {
        $settings = app(Settings::class);
        $settings->setSecret('payments.paytr.merchant_key', 'fixture-paytr-key');
        $settings->setSecret('payments.paytr.merchant_salt', 'fixture-salt');
        $oid = 'VCFIXTURE';
        $request = Request::create('/callback', 'POST', ['merchant_oid' => $oid, 'status' => 'success', 'total_amount' => '100', 'payment_amount' => '1000',
            'hash' => base64_encode(hash_hmac('sha256', $oid.'fixture-salt'.'success'.'100', 'fixture-paytr-key', true))]);
        $event = app(PaytrGateway::class)->parseWebhook($request);
        $this->assertSame(100, $event->amountMinor);
    }

    public function test_stripe_confirmation_must_match_the_same_checkout_reference(): void
    {
        $settings = app(Settings::class);
        $settings->setSecret('payments.stripe.secret_key', 'sk_test_fixture');
        $settings->setSecret('payments.stripe.webhook_secret', 'whsec_fixture');
        $user = $this->customer();
        $order = $this->pendingOrder($user, provider: 'stripe');
        $payment = Payment::create(['order_id' => $order->id, 'provider' => 'stripe', 'provider_reference' => 'fixture:stripe', 'provider_payment_id' => 'cs_fixture', 'amount_minor' => 1000, 'currency' => 'TRY', 'status' => 'pending']);
        Http::fake(['https://api.stripe.com/v1/checkout/sessions/cs_fixture' => Http::response([
            'id' => 'cs_fixture', 'client_reference_id' => 'some-other-checkout', 'mode' => 'payment', 'livemode' => false,
            'payment_status' => 'paid', 'payment_intent' => 'pi_fixture', 'amount_total' => 1000, 'currency' => 'try',
        ])]);
        $event = new VerifiedEvent('stripe', 'evt_fixture', 'checkout.session.completed', VerifiedEvent::SUCCEEDED, $payment->provider_reference, 'cs_fixture', 1000, 'TRY');
        $this->assertSame('failed', app(WebhookInbox::class)->accept(app(StripeGateway::class), $event));
        $this->assertSame(Order::PENDING, $order->fresh()->status);
        $this->assertSame(0, WalletEntry::count());
        $this->assertSame(1, WebhookEvent::sole()->attempts);
    }

    public function test_live_stripe_event_cannot_be_processed_with_a_test_key(): void
    {
        $settings = app(Settings::class);
        $settings->setSecret('payments.stripe.secret_key', 'sk_test_fixture');
        $settings->setSecret('payments.stripe.webhook_secret', 'whsec_fixture');
        $body = json_encode(['id' => 'evt_wrong_mode', 'type' => 'checkout.session.completed', 'livemode' => true, 'data' => ['object' => []]], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_fixture');
        $request = Request::create('/callback', 'POST', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature], $body);
        $this->expectException(InvalidWebhook::class);
        app(StripeGateway::class)->parseWebhook($request);
    }

    public function test_crash_after_inbox_insert_is_recovered_by_retry_command_once(): void
    {
        $this->enableSandbox();
        $user = $this->customer();
        $payment = app(Checkout::class)->createPayment($this->pendingOrder($user), 'sandbox');
        $record = WebhookEvent::create(['provider' => 'sandbox', 'event_id' => 'fixture:crash-after-insert',
            'event_type' => 'payment.succeeded', 'status' => 'received', 'payload' => [
                'outcome' => VerifiedEvent::SUCCEEDED, 'reference' => $payment->provider_reference,
                'provider_payment_id' => 'fixture-crash-payment', 'amount' => 1000, 'currency' => 'TRY', 'data' => [],
            ]]);
        $this->artisan('vibecraft:webhooks-retry')->assertExitCode(0);
        $this->artisan('vibecraft:webhooks-retry')->assertExitCode(0);
        $this->assertSame('processed', $record->fresh()->status);
        $this->assertSame(1, $record->fresh()->attempts);
        $this->assertSame(1000, app(Ledger::class)->balance($user->id));
        $this->assertSame(1, WalletEntry::count());
    }

    public function test_stripe_availability_requires_keys_for_the_current_environment(): void
    {
        $settings = app(Settings::class);
        $settings->set('payments.stripe.enabled', true);
        $settings->setSecret('payments.stripe.webhook_secret', 'whsec_fixture');
        $settings->setSecret('payments.stripe.secret_key', 'sk_test_fixture');
        $this->assertTrue(app(StripeGateway::class)->isAvailable());
        $this->app->instance('env', 'production');
        $this->assertFalse(app(StripeGateway::class)->isAvailable());
        $settings->setSecret('payments.stripe.secret_key', 'rk_live_fixture');
        $this->assertTrue(app(StripeGateway::class)->isAvailable());
        $this->app->instance('env', 'testing');
        $this->assertFalse(app(StripeGateway::class)->isAvailable());
        Http::assertNothingSent();
    }

    public function test_paytr_test_mode_is_unavailable_in_production_and_currency_must_be_try(): void
    {
        $settings = app(Settings::class);
        $settings->setMany(['payments.paytr.enabled' => true, 'payments.paytr.merchant_id' => '123456', 'payments.paytr.test_mode' => true]);
        $settings->setSecret('payments.paytr.merchant_key', 'fixture-key');
        $settings->setSecret('payments.paytr.merchant_salt', 'fixture-salt');
        $this->assertTrue(app(PaytrGateway::class)->isAvailable());
        $this->app->instance('env', 'production');
        $this->assertFalse(app(PaytrGateway::class)->isAvailable());
        $settings->set('payments.paytr.test_mode', false);
        $this->assertTrue(app(PaytrGateway::class)->isAvailable());
        config(['vibecraft.store.currency' => 'USD']);
        $this->assertFalse(app(PaytrGateway::class)->isAvailable());
        Http::assertNothingSent();
    }
}
