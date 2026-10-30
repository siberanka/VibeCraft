<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Commerce\Checkout;
use App\Domain\Commerce\Fulfillment;
use App\Domain\Commerce\Ledger;
use App\Domain\Commerce\OrderContracts;
use App\Domain\Commerce\OrderLifecycle;
use App\Domain\Commerce\OrderReview;
use App\Domain\Commerce\Pricing;
use App\Domain\Legal\LegalPublisher;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Delivery;
use App\Models\Entitlement;
use App\Models\GameServer;
use App\Models\LegalAcceptance;
use App\Models\Order;
use App\Models\OrderContract;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductCommand;
use App\Models\User;
use App\Models\WalletEntry;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesLegalMarkets;
use Tests\Concerns\CreatesOrderConfirmations;
use Tests\TestCase;

class LegalCheckoutTest extends TestCase
{
    use CreatesLegalMarkets, CreatesOrderConfirmations;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('tr');
        app(Settings::class)->set('legal.enforce', true);
        $this->enableSandbox();
    }

    private function cartFixture(?string $coupon = null): array
    {
        $market = $this->reviewedLegalMarket();
        $buyer = $this->customer();
        $product = $this->product();
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'quantity' => 1]);
        app(Ledger::class)->post($buyer->id, 5000, 'adjustment', 'fixture-fund-'.$buyer->id);
        $items = CartItem::where('user_id', $buyer->id)->with('product.category')->get();
        $review = OrderReview::purchase($buyer, app(Pricing::class)->quote($buyer, $items, $coupon), 'wallet', null);

        return [$buyer, $product, $this->orderConfirmation($buyer, $review, $market), $market];
    }

    public function test_wallet_order_records_its_contract_before_debit_and_a_retry_does_not_charge_twice(): void
    {
        [$buyer, $product, $confirmation] = $this->cartFixture();
        $first = app(Checkout::class)->placeOrder($buyer, null, 'wallet', null, $confirmation);
        $this->assertSame(Order::PAID, $first->status);
        $this->assertTrue(app(OrderContracts::class)->validFor($first));
        $this->assertSame(6, LegalAcceptance::where('order_id', $first->id)->count());
        $this->assertSame(3001, app(Ledger::class)->balance($buyer->id));
        $second = app(Checkout::class)->placeOrder($buyer, null, 'wallet', null, $confirmation);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, WalletEntry::where('type', 'purchase')->count());
        $this->assertSame(9, $product->fresh()->stock);
    }

    public function test_changed_terms_roll_back_coupon_stock_cart_order_evidence_and_wallet_together(): void
    {
        $coupon = Coupon::create(['code' => 'REVIEWQA', 'type' => 'fixed', 'value' => 100, 'max_uses_per_user' => 1, 'is_active' => true]);
        [$buyer, $product, $confirmation, $market] = $this->cartFixture('REVIEWQA');
        $publisher = app(LegalPublisher::class);
        $policy = $market->policies()->where('kind', 'terms')->firstOrFail();
        $owner = User::role('owner')->firstOrFail();
        $policy = $publisher->saveDraft($market, $owner, ['kind' => 'terms', 'locale' => 'tr', 'revision' => 2, 'title' => 'Revised fixture terms', 'body_markdown' => 'New fixture terms after review.']);
        $publisher->publish($policy, 3, $owner);
        try {
            app(Checkout::class)->placeOrder($buyer, 'REVIEWQA', 'wallet', null, $confirmation);
            $this->fail('A stale contract reached payment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('legal_document_ids', $exception->errors());
        }
        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderContract::count());
        $this->assertSame(0, LegalAcceptance::count());
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(1, CartItem::where('user_id', $buyer->id)->count());
        $this->assertSame(0, $coupon->fresh()->uses);
        $this->assertSame(5000, app(Ledger::class)->balance($buyer->id));
    }

    public function test_same_total_changed_delivery_description_cannot_use_the_old_confirmation(): void
    {
        [$buyer, $product, $confirmation] = $this->cartFixture();
        $product->update(['description_markdown' => 'Different delivery restrictions.']);
        try {
            app(Checkout::class)->placeOrder($buyer, null, 'wallet', null, $confirmation);
            $this->fail('Changed product terms did not require another review.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('expected_review_sha256', $exception->errors());
        }
        $this->assertSame(0, Order::count());
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(5000, app(Ledger::class)->balance($buyer->id));
    }

    public function test_topup_confirmation_is_idempotent_then_payment_credits_and_refund_reverses_only_once(): void
    {
        $market = $this->reviewedLegalMarket();
        $buyer = $this->customer();
        $review = OrderReview::topup($buyer, 1000, 'sandbox');
        $confirmation = $this->orderConfirmation($buyer, $review, $market);
        $first = app(Checkout::class)->createTopup($buyer, 1000, 'sandbox', null, $confirmation);
        $second = app(Checkout::class)->createTopup($buyer, 1000, 'sandbox', null, $confirmation);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(0, app(Ledger::class)->balance($buyer->id));
        $payment = app(Checkout::class)->createPayment($first, 'sandbox');
        $lifecycle = app(OrderLifecycle::class);
        $this->assertTrue($lifecycle->markPaid($first, 'sandbox', 'fixture-payment', 1000, 'TRY', $payment));
        $this->assertFalse($lifecycle->markPaid($first, 'sandbox', 'fixture-payment', 1000, 'TRY', $payment));
        $this->assertSame(1000, app(Ledger::class)->balance($buyer->id));
        $this->assertTrue($lifecycle->markRefunded($first, 'fixture-refund', false, $payment));
        $this->assertFalse($lifecycle->markRefunded($first, 'fixture-refund', false, $payment));
        $this->assertSame(0, app(Ledger::class)->balance($buyer->id));
        $this->assertSame(1, WalletEntry::where('type', 'topup')->count());
        $this->assertSame(1, WalletEntry::where('type', 'reversal')->count());
    }

    public function test_late_legacy_topup_records_payment_truth_but_never_credits_or_claws_back_uncredited_money(): void
    {
        $buyer = $this->customer();
        $order = $this->pendingOrder($buyer);
        $payment = Payment::create(['order_id' => $order->id, 'provider' => 'sandbox', 'provider_reference' => 'fixture-legacy-topup',
            'amount_minor' => 1000, 'currency' => 'TRY', 'status' => 'pending']);
        $lifecycle = app(OrderLifecycle::class);
        $this->assertTrue($lifecycle->markPaid($order, 'sandbox', 'fixture-legacy-paid', 1000, 'TRY', $payment));
        $this->assertSame(Order::PAID, $order->fresh()->status);
        $this->assertSame('manual_review', $order->fresh()->fulfillment_status);
        $this->assertSame('succeeded', $payment->fresh()->status);
        $this->assertSame(0, app(Ledger::class)->balance($buyer->id));
        $this->assertTrue($lifecycle->markRefunded($order, 'fixture-legacy-refund', false, $payment));
        $this->assertSame(0, app(Ledger::class)->balance($buyer->id));
        $this->assertSame(0, WalletEntry::where('type', 'reversal')->count());
    }

    public function test_existing_legacy_payment_cannot_bypass_the_review_gate_on_the_http_pay_route(): void
    {
        $buyer = $this->customer();
        $order = $this->pendingOrder($buyer);
        try {
            app(Checkout::class)->createPayment($order, 'sandbox');
            $this->fail('Legacy order started a new payment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }
        Payment::create(['order_id' => $order->id, 'provider' => 'sandbox', 'provider_reference' => 'fixture-old-payment',
            'amount_minor' => 1000, 'currency' => 'TRY', 'status' => 'pending']);
        $this->actingAs($buyer)->post('/orders/'.$order->public_id.'/pay', ['method' => 'sandbox'])->assertSessionHasErrors('payment');
        $this->assertSame(1, Payment::count());
        $this->assertSame('pending', Payment::sole()->status);
        $this->assertSame(Order::PENDING, $order->fresh()->status);
    }

    public function test_direct_delivery_entry_points_cannot_grant_a_paid_order_without_its_required_contract(): void
    {
        $buyer = $this->customer();
        $product = $this->product(['duration_days' => 30]);
        $server = GameServer::forceCreate(['name' => 'Contract fixture', 'slug' => 'contract-fixture', 'java_address' => 'play.fixture.example.test',
            'query_host' => '127.0.0.1', 'query_port' => 25565, 'bridge_enabled' => false, 'bridge_key_id' => 'contract-fixture-key', 'bridge_secret' => str_repeat('q', 64)]);
        ProductCommand::create(['product_id' => $product->id, 'game_server_id' => $server->id, 'phase' => 'purchase', 'command' => 'say Contract fixture', 'sort' => 0]);
        $order = $this->pendingOrder($buyer, 1999, 'wallet', 'purchase');
        $order->forceFill(['status' => Order::PAID])->save();
        $item = OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name,
            'unit_price_minor' => 1999, 'quantity' => 1, 'total_minor' => 1999, 'duration_days' => 30]);
        app(Fulfillment::class)->schedule($order);
        $this->assertSame(0, app(Fulfillment::class)->enqueue($item, 'purchase'));
        $this->assertSame(0, Delivery::count());
        $this->assertSame(0, Entitlement::count());
        $this->assertSame('manual_review', $order->fresh()->fulfillment_status);
    }

    public function test_the_service_requires_confirmation_and_rechecks_verification_before_charging(): void
    {
        [$buyer, $product, $confirmation] = $this->cartFixture();
        try {
            app(Checkout::class)->placeOrder($buyer, null, 'wallet', null);
            $this->fail('Direct service skipped the review.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('checkout', $exception->errors());
        }
        $buyer->forceFill(['email_verified_at' => null])->save();
        try {
            app(Checkout::class)->placeOrder($buyer, null, 'wallet', null, $confirmation);
            $this->fail('Unverified account was charged.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('terms', $exception->errors());
        }
        $this->assertSame(0, Order::count());
        $this->assertSame(5000, app(Ledger::class)->balance($buyer->id));
    }
}
