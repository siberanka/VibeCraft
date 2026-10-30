<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Bridge\BridgeSignature;
use App\Domain\Commerce\Checkout;
use App\Domain\Commerce\DeliveryQueue;
use App\Domain\Commerce\Fulfillment;
use App\Domain\Commerce\Ledger;
use App\Domain\Commerce\OrderLifecycle;
use App\Domain\Commerce\Renewals;
use App\Domain\Content\Markdown;
use App\Domain\Payments\Gateways\StripeGateway;
use App\Domain\Payments\VerifiedEvent;
use App\Domain\Payments\WebhookInbox;
use App\Models\CartItem;
use App\Models\Delivery;
use App\Models\Entitlement;
use App\Models\GameIdentity;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductCommand;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\PlayerNotice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Regression tests for the issues confirmed by security audit run 2. */
class SecurityHardeningTest extends TestCase
{
    private function timedRank(User $user, int $quantity): Entitlement
    {
        $product = $this->product(['price_minor' => 1500, 'duration_days' => 30, 'max_quantity' => 12]);
        $order = Order::query()->create(['user_id' => $user->id, 'kind' => 'purchase', 'status' => Order::PAID, 'fulfillment_status' => 'delivered', 'currency' => 'TRY', 'subtotal_minor' => 1500 * $quantity, 'total_minor' => 1500 * $quantity, 'paid_at' => now()]);
        $item = OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'unit_price_minor' => 1500, 'quantity' => $quantity, 'total_minor' => 1500 * $quantity, 'duration_days' => 30]);

        return Entitlement::query()->create(['user_id' => $user->id, 'product_id' => $product->id, 'order_item_id' => $item->id, 'starts_at' => now()->subDays(30), 'expires_at' => now()->addMinute(), 'status' => 'active']);
    }

    public function test_bundle_renewal_charges_every_period_it_extends_and_stops_for_suspended_owners(): void
    {
        $user = $this->customer();
        app(Ledger::class)->post($user->id, 20000, 'adjustment', 'fixture:bundle');
        $rank = $this->timedRank($user, 3);
        app(Renewals::class)->enable($user, $rank);
        $this->assertSame(4500, $rank->fresh()->renew_price_minor);
        $this->travel(2)->minutes();
        $before = $rank->fresh()->expires_at;
        $this->assertSame(1, app(Renewals::class)->renewDue());
        $this->assertSame(15500, app(Ledger::class)->balance($user->id));
        $this->assertTrue($rank->fresh()->expires_at->eq($before->copy()->addDays(90)));

        $other = $this->customer();
        app(Ledger::class)->post($other->id, 5000, 'adjustment', 'fixture:banned');
        $banned = $this->timedRank($other, 1);
        app(Renewals::class)->enable($other, $banned);
        $other->forceFill(['banned_at' => now()])->save();
        $this->travel(2)->minutes();
        app(Renewals::class)->renewDue();
        $this->assertFalse($banned->fresh()->auto_renew);
        $this->assertSame(5000, app(Ledger::class)->balance($other->id));
    }

    private function stripe(): array
    {
        $settings = app(Settings::class);
        $settings->setSecret('payments.stripe.secret_key', 'sk_test_fixture');
        $settings->setSecret('payments.stripe.webhook_secret', 'whsec_fixture');
        $user = $this->customer();
        $order = $this->pendingOrder($user, provider: 'stripe');
        $payment = Payment::create(['order_id' => $order->id, 'provider' => 'stripe', 'provider_reference' => 'fixture:hardening',
            'provider_payment_id' => 'cs_fixture_hardening', 'amount_minor' => 1000, 'currency' => 'TRY', 'status' => 'pending']);
        Http::fake(['https://api.stripe.com/v1/checkout/sessions/cs_fixture_hardening' => Http::response([
            'id' => 'cs_fixture_hardening', 'client_reference_id' => 'fixture:hardening', 'mode' => 'payment', 'livemode' => false,
            'payment_status' => 'paid', 'payment_intent' => 'pi_fixture_hardening', 'amount_total' => 1000, 'currency' => 'try',
        ])]);

        return [$user, $order, $payment];
    }

    private function signedStripe(array $event): Request
    {
        $body = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = time();

        return Request::create('/api/payments/stripe/webhook', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_fixture')], $body);
    }

    public function test_reversal_before_payment_confirmation_is_applied_when_the_payment_succeeds(): void
    {
        app(Settings::class)->set('payments.stripe.enabled', true);
        [$user, $order] = $this->stripe();
        $gateway = app(StripeGateway::class);
        $inbox = app(WebhookInbox::class);
        $dispute = $gateway->parseWebhook($this->signedStripe(['id' => 'evt_early_dispute', 'type' => 'charge.dispute.created', 'livemode' => false,
            'data' => ['object' => ['id' => 'dp_fixture', 'object' => 'dispute', 'payment_intent' => 'pi_fixture_hardening', 'currency' => 'try']]]));
        $this->assertSame('deferred', $inbox->accept($gateway, $dispute));

        $success = new VerifiedEvent('stripe', 'evt_late_success', 'checkout.session.completed', VerifiedEvent::SUCCEEDED, 'fixture:hardening', 'cs_fixture_hardening', 1000, 'TRY');
        $this->assertSame('processed', $inbox->accept($gateway, $success));
        $this->assertSame(Order::DISPUTED, $order->fresh()->status);
        $this->assertSame('processed', WebhookEvent::query()->where('event_id', 'evt_early_dispute')->value('status'));
        $this->assertSame(0, app(Ledger::class)->balance($user->id));
    }

    public function test_signed_webhooks_still_apply_after_the_method_is_switched_off(): void
    {
        [, $order, $payment] = $this->stripe();
        $order->forceFill(['status' => Order::PAID, 'paid_at' => now()])->save();
        $payment->forceFill(['status' => 'succeeded', 'provider_payment_id' => 'pi_fixture_hardening'])->save();
        app(Settings::class)->set('payments.stripe.enabled', false);
        $request = $this->signedStripe(['id' => 'evt_off_dispute', 'type' => 'charge.dispute.created', 'livemode' => false,
            'data' => ['object' => ['id' => 'dp_off', 'object' => 'dispute', 'payment_intent' => 'pi_fixture_hardening', 'currency' => 'try']]]);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $request->headers->get('Stripe-Signature')];

        $this->call('POST', '/api/payments/stripe/webhook', [], [], [], $server, $request->getContent())->assertOk();
        $this->assertSame(Order::DISPUTED, $order->fresh()->status);
    }

    public function test_bridge_input_comes_only_from_the_signed_json_body(): void
    {
        $server = GameServer::forceCreate(['name' => 'Hardening', 'slug' => 'hardening-'.Str::lower(Str::random(6)),
            'bridge_key_id' => 'vck_'.bin2hex(random_bytes(12)), 'bridge_secret' => 'fixture-hardening-key', 'bridge_enabled' => true]);
        $signed = function (string $body, string $nonce, string $type = 'application/json') use ($server) {
            $timestamp = time();

            return ['CONTENT_TYPE' => $type, 'HTTP_X_VC_KEY' => $server->bridge_key_id, 'HTTP_X_VC_TIMESTAMP' => (string) $timestamp, 'HTTP_X_VC_NONCE' => $nonce,
                'HTTP_X_VC_SIGNATURE' => BridgeSignature::request($server->bridge_secret, 'POST', '/api/bridge/v1/link', $timestamp, $nonce, $body)];
        };
        $this->call('POST', '/api/bridge/v1/link?code=ABCDEFGH&uuid=x', [], [], [], $signed('{}', 'hardening_query_0001'), '{}')
            ->assertUnauthorized()->assertJsonPath('reason', 'unsigned_input');
        $this->call('POST', '/api/bridge/v1/link', [], [], [], $signed('code=ABCDEFGH', 'hardening_form_00001', 'application/x-www-form-urlencoded'), 'code=ABCDEFGH')
            ->assertUnauthorized()->assertJsonPath('reason', 'unsigned_input');
        $this->call('POST', '/api/bridge/v1/link', [], [], [], $signed('{}', 'hardening_empty_0001'), '{}')->assertUnprocessable();
    }

    private function deliveredPurchase(): array
    {
        $server = GameServer::forceCreate(['name' => 'Hold fixture', 'slug' => 'hold-'.Str::lower(Str::random(6)),
            'bridge_key_id' => 'vck_'.bin2hex(random_bytes(12)), 'bridge_secret' => 'fixture-hold-key', 'bridge_enabled' => true]);
        $user = $this->customer();
        GameIdentity::create(['user_id' => $user->id, 'edition' => 'java', 'uuid' => '33333333-3333-3333-3333-333333333333', 'username' => 'OldName', 'verified_via' => 'bridge', 'verified_at' => now()]);
        $product = $this->product(['duration_days' => 1, 'requires_link' => true]);
        foreach (['purchase', 'expire', 'refund'] as $phase) {
            ProductCommand::create(['product_id' => $product->id, 'game_server_id' => $server->id, 'phase' => $phase, 'command' => 'lp user {player} '.$phase]);
        }
        CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);
        app(Ledger::class)->post($user->id, 10000, 'adjustment', 'fixture:hold-'.$user->id);
        $order = app(Checkout::class)->placeOrder($user->fresh(), null, 'wallet', null);
        $purchase = Delivery::query()->where('order_id', $order->id)->where('phase', 'purchase')->sole();
        $purchase->forceFill(['status' => Delivery::DELIVERED, 'delivered_at' => now()])->save();

        return [$order, $server, $user];
    }

    public function test_review_hold_never_cancels_expiry_and_uses_the_current_player_name(): void
    {
        [$order, $server, $user] = $this->deliveredPurchase();
        GameIdentity::query()->where('user_id', $user->id)->update(['username' => 'NewName']);
        $order->forceFill(['fulfillment_status' => 'manual_review'])->save();
        $this->travel(2)->days();
        app(Fulfillment::class)->expireDue();

        $leased = app(DeliveryQueue::class)->lease($server, [], 25);
        $this->assertCount(1, $leased);
        $this->assertSame('expire', $leased->first()->phase);
        $this->assertSame('lp user NewName expire', $leased->first()->command);
    }

    public function test_review_hold_moves_a_sent_purchase_to_review_instead_of_cancelling_it(): void
    {
        [$order, $server] = $this->deliveredPurchase();
        $purchase = Delivery::query()->where('order_id', $order->id)->where('phase', 'purchase')->sole();
        $purchase->forceFill(['status' => Delivery::LEASED, 'attempts' => 1, 'lease_expires_at' => now()->subMinute()])->save();
        $order->forceFill(['fulfillment_status' => 'manual_review'])->save();

        $this->assertCount(0, app(DeliveryQueue::class)->lease($server, [], 25));
        $this->assertSame(Delivery::MANUAL_REVIEW, $purchase->fresh()->status);
    }

    public function test_refunding_a_gift_returns_the_renewals_the_recipient_paid_for(): void
    {
        $recipient = $this->customer();
        app(Ledger::class)->post($recipient->id, 5000, 'adjustment', 'fixture:recipient');
        $rank = $this->timedRank($recipient, 1);
        app(Renewals::class)->enable($recipient, $rank);
        $this->travel(2)->minutes();
        app(Renewals::class)->renewDue();
        $this->assertSame(3500, app(Ledger::class)->balance($recipient->id));

        $order = Order::query()->findOrFail($rank->orderItem->order_id);
        $order->forceFill(['payment_method' => 'wallet', 'user_id' => $this->customer()->id, 'gift_recipient_id' => $recipient->id])->save();
        app(OrderLifecycle::class)->markRefunded($order->fresh(), 'fixture-refund');
        $this->assertSame('revoked', $rank->fresh()->status);
        $this->assertSame(5000, app(Ledger::class)->balance($recipient->id));
    }

    public function test_password_checks_are_rate_limited(): void
    {
        $this->actingAs($this->customer());
        for ($i = 0; $i < 5; $i++) {
            $this->post('/user/confirm-password', ['password' => 'wrong-guess-'.$i])->assertStatus(302);
        }
        $this->post('/user/confirm-password', ['password' => 'wrong-guess-final'])->assertStatus(429);
    }

    public function test_ticket_subjects_are_never_used_as_translation_keys(): void
    {
        app(Settings::class)->set('security.require_admin_2fa', false);
        $member = $this->customer();
        Ticket::create(['user_id' => $member->id, 'subject' => 'validation', 'category' => 'general', 'status' => 'open']);
        $this->actingAs($this->customer('admin'))->withSession(['auth.password_confirmed_at' => time()])
            ->get('/admin/tickets')->assertOk()->assertSee('validation');
    }

    public function test_embedded_requests_cannot_change_account_state(): void
    {
        $user = $this->customer();
        $user->notify(new PlayerNotice('order_paid', ['order' => 'X'], '/'));
        $this->actingAs($user)->get('/account/notifications', ['Sec-Fetch-Dest' => 'image'])->assertForbidden();
        $this->actingAs($user)->get('/account/discord/callback?state=forged', ['Sec-Fetch-Dest' => 'image'])->assertForbidden();

        $settings = app(Settings::class);
        $settings->set('discord.client_id', '123456789');
        $settings->setSecret('discord.client_secret', 'fixture-secret');
        $this->actingAs($user)->withSession(['discord_link' => ['state' => 'genuine', 'verifier' => str_repeat('v', 64), 'at' => time()]])
            ->get('/account/discord/callback?state=forged&code=x')->assertRedirect()->assertSessionHas('discord_link');

        $this->assertSame('<p>x</p>', trim(Markdown::render('![x](/account/discord?i=1)')));
        $this->assertStringContainsString('<img src="/media/12?v=abc"', Markdown::render('![a](/media/12?v=abc)'));
        $this->assertStringNotContainsString('<img', Markdown::render('![a](/media/12?next=/account)'));
    }

    public function test_host_port_must_match_the_configured_site(): void
    {
        $this->get('https://vibecraft.example:8443/')->assertStatus(400);
        $this->get('https://vibecraft.example/')->assertOk();
    }
}
