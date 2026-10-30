<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Commerce\Checkout;
use App\Domain\Commerce\Fulfillment;
use App\Domain\Commerce\OrderContracts;
use App\Domain\Commerce\OrderLifecycle;
use App\Domain\Commerce\OrderReview;
use App\Domain\Commerce\Pricing;
use App\Models\CartItem;
use App\Models\Delivery;
use App\Models\Entitlement;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\OrderContract;
use App\Models\ProductCommand;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesLegalMarkets;
use Tests\Concerns\CreatesOrderConfirmations;
use Tests\TestCase;

class ExecutionPlansTest extends TestCase
{
    use CreatesLegalMarkets, CreatesOrderConfirmations;

    private function reviewed(): array
    {
        app()->setLocale('tr');
        app(Settings::class)->set('legal.enforce', true);
        $this->enableSandbox();
        $market = $this->reviewedLegalMarket();
        $buyer = $this->customer();
        $server = GameServer::forceCreate(['name' => 'Original server', 'slug' => 'original-fixture', 'bridge_enabled' => true,
            'bridge_key_id' => 'offline-plan-fixture', 'bridge_secret' => str_repeat('f', 64)]);
        $product = $this->product(['duration_days' => 1]);
        foreach (ProductCommand::PHASES as $phase) {
            ProductCommand::create(['product_id' => $product->id, 'game_server_id' => $server->id, 'phase' => $phase,
                'command' => 'say ORIGINAL-'.$phase, 'require_online' => false, 'sort' => 0]);
        }
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'quantity' => 1]);
        $items = CartItem::where('user_id', $buyer->id)->with('product.category')->get();
        $review = OrderReview::purchase($buyer, app(Pricing::class)->quote($buyer, $items), 'sandbox', null);

        return [$buyer, $product, $server, $review, $this->orderConfirmation($buyer, $review, $market)];
    }

    private function pending(): array
    {
        [$buyer, $product, $server, $review, $confirmation] = $this->reviewed();
        $order = app(Checkout::class)->placeOrder($buyer, null, 'sandbox', null, $confirmation);

        return [$order, $product, $server, $review];
    }

    private function pay(Order $order): void
    {
        $this->assertTrue(app(OrderLifecycle::class)->markPaid($order, 'sandbox', 'offline-fixture', $order->total_minor, $order->currency));
    }

    public function test_definition_changes_between_review_and_confirmation_require_a_new_review_without_consuming_cart(): void
    {
        [$buyer, $product, $server, $review, $confirmation] = $this->reviewed();
        $product->commands()->where('phase', 'purchase')->update(['command' => 'say CHANGED']);
        try {
            app(Checkout::class)->placeOrder($buyer, null, 'sandbox', null, $confirmation);
            $this->fail('A changed execution definition reused an old review.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('expected_review_sha256', $exception->errors());
        }
        $this->assertSame(0, Order::count());
        $this->assertSame(1, CartItem::count());
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_late_payment_uses_encrypted_original_plan_after_command_deletion_without_disclosing_it(): void
    {
        [$order, $product, $server, $review] = $this->pending();
        $contract = OrderContract::where('order_id', $order->id)->sole();
        $this->assertArrayNotHasKey('execution_plan', $contract->toArray());
        $this->assertStringNotContainsString('ORIGINAL-purchase', DB::table('order_contracts')->where('id', $contract->id)->value('execution_plan'));
        $this->assertStringNotContainsString('ORIGINAL-purchase', $contract->receipt_text);
        $this->assertStringNotContainsString('ORIGINAL-purchase', json_encode($review->payload(), JSON_THROW_ON_ERROR));
        $product->commands()->delete();
        ProductCommand::create(['product_id' => $product->id, 'game_server_id' => $server->id, 'phase' => 'purchase',
            'command' => 'say REPLACEMENT', 'require_online' => true]);
        $this->pay($order);
        $job = Delivery::sole();
        $this->assertSame('say ORIGINAL-purchase', $job->command);
        $this->assertFalse($job->require_online);
        $this->assertNull($job->product_command_id);
        app(Fulfillment::class)->schedule($order->fresh());
        $this->assertSame(1, Delivery::count());
        $this->assertTrue(app(OrderContracts::class)->validFor($order));
    }

    public function test_refund_uses_original_compensation_after_catalogue_commands_change(): void
    {
        [$order, $product] = $this->pending();
        $this->pay($order);
        Delivery::sole()->forceFill(['status' => Delivery::DELIVERED, 'delivered_at' => now()])->save();
        $product->commands()->update(['command' => 'say NEW-CATALOGUE']);
        $this->assertTrue(app(OrderLifecycle::class)->markRefunded($order, 'offline-fixture'));
        $this->assertSame('say ORIGINAL-refund', Delivery::where('phase', 'refund')->sole()->command);
        $this->assertSame('revoked', Entitlement::sole()->status);
    }

    public function test_expiry_uses_original_removal_after_original_command_rows_are_deleted(): void
    {
        [$order, $product] = $this->pending();
        $this->pay($order);
        Delivery::sole()->forceFill(['status' => Delivery::DELIVERED, 'delivered_at' => now()])->save();
        $product->commands()->delete();
        Entitlement::sole()->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->assertSame(1, app(Fulfillment::class)->expireDue());
        $this->assertSame('say ORIGINAL-expire', Delivery::where('phase', 'expire')->sole()->command);
    }

    public function test_missing_original_server_holds_delivery_without_switching_to_another_server(): void
    {
        [$order, $product, $server] = $this->pending();
        $product->commands()->delete();
        $server->delete();
        $this->pay($order);
        $this->assertSame(Order::PAID, $order->fresh()->status);
        $this->assertSame('manual_review', $order->fresh()->fulfillment_status);
        $this->assertSame(0, Delivery::count());
    }

    public function test_unreadable_private_plan_holds_grants_and_preserves_original_customer_copy(): void
    {
        [$order] = $this->pending();
        DB::table('order_contracts')->where('order_id', $order->id)->update(['execution_plan' => 'unreadable-fixture']);
        $this->pay($order);
        $this->assertSame('manual_review', $order->fresh()->fulfillment_status);
        $this->assertSame(0, Delivery::count());
        $this->assertSame(0, Entitlement::count());
        $this->assertNotNull(app(OrderContracts::class)->original($order));
    }
}
