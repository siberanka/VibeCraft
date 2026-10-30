<?php

namespace Tests\Feature;

use App\Domain\Commerce\Checkout;
use App\Domain\Commerce\CommerceException;
use App\Domain\Commerce\Ledger;
use App\Domain\Commerce\OrderLifecycle;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\WalletEntry;
use Tests\TestCase;

class CommerceIntegrityTest extends TestCase
{
    public function test_wallet_postings_are_idempotent_and_do_not_allow_overspending(): void
    {
        $user = $this->customer();
        $ledger = app(Ledger::class);
        $credit = $ledger->post($user->id, 1000, 'adjustment', 'fixture:credit');
        $duplicate = $ledger->post($user->id, 1000, 'adjustment', 'fixture:credit');
        $this->assertSame($credit->id, $duplicate->id);
        $ledger->post($user->id, -700, 'purchase', 'fixture:spend');
        try {
            $ledger->post($user->id, -700, 'purchase', 'fixture:second-spend');
            $this->fail('An insufficient wallet must not be debited.');
        } catch (CommerceException) {
            $this->assertSame(300, $ledger->balance($user->id));
            $this->assertSame(2, WalletEntry::count());
            $this->assertSame([], $ledger->reconcile());
        }
    }

    public function test_idempotency_keys_cannot_refer_to_a_different_user_or_amount(): void
    {
        $one = $this->customer();
        $two = $this->customer();
        $ledger = app(Ledger::class);
        $ledger->post($one->id, 1000, 'adjustment', 'fixture:shared');
        $this->expectException(CommerceException::class);
        $ledger->post($two->id, 9999, 'adjustment', 'fixture:shared');
    }

    public function test_checkout_uses_database_prices_integer_discounts_and_reserves_stock_once(): void
    {
        $user = $this->customer();
        $product = $this->product();
        CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 3]);
        $coupon = Coupon::create(['code' => 'SEASON', 'type' => 'percent', 'value' => 1250, 'max_uses_per_user' => 1, 'is_active' => true]);
        app(Ledger::class)->post($user->id, 10000, 'adjustment', 'fixture:balance');
        $order = app(Checkout::class)->placeOrder($user, ' season ', 'wallet', '192.0.2.1');
        $this->assertSame(Order::PAID, $order->status);
        $this->assertSame(5997, $order->subtotal_minor);
        $this->assertSame(749, $order->discount_minor);
        $this->assertSame(5248, $order->total_minor);
        $this->assertSame(4752, app(Ledger::class)->balance($user->id));
        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(1, $coupon->fresh()->uses);
        try {
            app(Checkout::class)->placeOrder($user, null, 'wallet', null);
            $this->fail('The consumed cart must not create another order.');
        } catch (CommerceException) {
            $this->assertSame(1, Order::count());
            $this->assertSame(7, $product->fresh()->stock);
        }
    }

    public function test_failed_wallet_payment_rolls_back_order_stock_coupon_and_cart_deletion(): void
    {
        $user = $this->customer();
        $product = $this->product();
        CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);
        $coupon = Coupon::create(['code' => 'FIXTURE', 'type' => 'fixed', 'value' => 99, 'is_active' => true]);
        try {
            app(Checkout::class)->placeOrder($user, 'FIXTURE', 'wallet', null);
            $this->fail('An unfunded checkout must fail.');
        } catch (CommerceException) {
            $this->assertSame(0, Order::count());
            $this->assertSame(1, CartItem::count());
            $this->assertSame(10, $product->fresh()->stock);
            $this->assertSame(0, $coupon->fresh()->uses);
            $this->assertSame(0, WalletEntry::count());
        }
    }

    public function test_hidden_category_cannot_be_purchased_from_a_preexisting_cart(): void
    {
        $this->enableSandbox();
        $user = $this->customer();
        $product = $this->product();
        CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);
        $product->category->update(['is_visible' => false]);
        $this->expectException(CommerceException::class);
        app(Checkout::class)->placeOrder($user, null, 'sandbox', null);
    }

    public function test_quantity_over_product_limit_is_rejected_instead_of_silently_changed(): void
    {
        $this->enableSandbox();
        $user = $this->customer();
        $product = $this->product(['max_quantity' => 2]);
        CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 3]);
        $this->expectException(CommerceException::class);
        app(Checkout::class)->placeOrder($user, null, 'sandbox', null);
    }

    public function test_unavailable_payment_method_cannot_create_an_order(): void
    {
        $user = $this->customer();
        $product = $this->product();
        CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);
        $this->expectException(CommerceException::class);
        app(Checkout::class)->placeOrder($user, null, 'stripe', null);
    }

    public function test_expired_coupon_cannot_reserve_stock_or_create_an_order(): void
    {
        $this->enableSandbox();
        $user = $this->customer();
        $product = $this->product();
        CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);
        Coupon::create(['code' => 'EXPIRED', 'type' => 'fixed', 'value' => 100, 'is_active' => true, 'ends_at' => now()->subMinute()]);
        $this->expectException(CommerceException::class);
        app(Checkout::class)->placeOrder($user, 'EXPIRED', 'sandbox', null);
    }

    public function test_cancellation_releases_reservations_only_once(): void
    {
        $this->enableSandbox();
        $user = $this->customer();
        $product = $this->product();
        CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 2]);
        $coupon = Coupon::create(['code' => 'ONCE', 'type' => 'fixed', 'value' => 100, 'is_active' => true]);
        $order = app(Checkout::class)->placeOrder($user, 'ONCE', 'sandbox', null);
        $lifecycle = app(OrderLifecycle::class);
        $this->assertTrue($lifecycle->cancel($order, 'fixture'));
        $this->assertFalse($lifecycle->cancel($order, 'duplicate'));
        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(0, $coupon->fresh()->uses);
        $this->assertSame(0, CouponRedemption::where('order_id', $order->id)->count());
    }

    public function test_stale_hidden_cart_remains_readable_but_cannot_be_checked_out(): void
    {
        $user = $this->customer();
        $product = $this->product();
        CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);
        $product->category->update(['is_visible' => false]);
        $this->actingAs($user)->get('/cart')->assertOk()
            ->assertViewHas('cartError', fn ($error) => is_string($error) && $error !== '')
            ->assertViewHas('quote', fn ($quote) => $quote->isEmpty());
        $this->postJson('/checkout', ['method' => 'wallet', 'terms' => true])->assertUnprocessable()->assertJsonValidationErrors('checkout');
        $this->assertSame(0, Order::count());
        $this->assertSame(1, CartItem::count());
    }
}
