<?php

namespace Tests\Feature;

use App\Domain\Commerce\Chest;
use App\Domain\Commerce\CommerceException;
use App\Domain\Commerce\Fulfillment;
use App\Domain\Commerce\OrderLifecycle;
use App\Models\Delivery;
use App\Models\Entitlement;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductCommand;
use App\Models\User;
use Tests\TestCase;

class ChestTest extends TestCase
{
    /** @return array{0: User, 1: Order, 2: OrderItem} */
    private function heldPurchase(): array
    {
        $server = GameServer::forceCreate(['name' => 'Survival', 'slug' => 'survival', 'show_on_site' => true, 'bridge_enabled' => true, 'bridge_key_id' => 'vck_'.str_repeat('c', 24), 'bridge_secret' => str_repeat('d', 64)]);
        $product = $this->product(['chest_delivery' => true, 'duration_days' => 30, 'requires_link' => false]);
        ProductCommand::query()->create(['product_id' => $product->id, 'game_server_id' => $server->id, 'phase' => 'purchase', 'command' => 'lp user {player} parent add knight']);
        $user = $this->customer();
        $order = Order::query()->create(['user_id' => $user->id, 'kind' => 'purchase', 'status' => Order::PENDING, 'fulfillment_status' => 'not_started', 'currency' => 'TRY', 'subtotal_minor' => 1000, 'total_minor' => 1000]);
        $item = OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'unit_price_minor' => 1000, 'quantity' => 1,
            'total_minor' => 1000, 'duration_days' => 30, 'player_name' => 'Steve', 'player_uuid' => '00000000-0000-4000-8000-000000000001', 'held_in_chest' => true]);

        return [$user, $order, $item];
    }

    private function pay(Order $order): void
    {
        $order->forceFill(['status' => Order::PAID, 'paid_at' => now()])->save();
        app(Fulfillment::class)->schedule($order->fresh());
    }

    public function test_paid_chest_items_wait_until_claimed_then_deliver_once(): void
    {
        [$user, $order, $item] = $this->heldPurchase();
        $this->pay($order);

        $this->assertSame('in_chest', $order->fresh()->fulfillment_status);
        $this->assertSame(0, Delivery::query()->count());
        $this->assertSame(0, Entitlement::query()->count());
        $this->assertCount(1, app(Chest::class)->contents($user));

        app(Chest::class)->claim($user, $item->id);

        $this->assertSame(1, Delivery::query()->where('order_item_id', $item->id)->count());
        $this->assertSame(1, Entitlement::query()->where('order_item_id', $item->id)->count());
        $this->assertCount(0, app(Chest::class)->contents($user));

        $this->expectException(CommerceException::class);
        try {
            app(Chest::class)->claim($user, $item->id);
        } finally {
            $this->assertSame(1, Delivery::query()->count());
        }
    }

    public function test_other_accounts_cannot_claim_and_unpaid_items_are_not_in_the_chest(): void
    {
        [$user, $order, $item] = $this->heldPurchase();
        $this->assertCount(0, app(Chest::class)->contents($user));

        $this->pay($order);
        $intruder = $this->customer();
        $this->actingAs($intruder)->post(route('chest.claim', $item->id))->assertRedirect(route('chest.index'))->assertSessionHasErrors('chest');
        $this->assertNull($item->fresh()->chest_claimed_at);
        $this->assertSame(0, Delivery::query()->count());
    }

    public function test_refunded_unclaimed_items_leave_the_chest_without_any_command(): void
    {
        [$user, $order, $item] = $this->heldPurchase();
        $this->pay($order);

        app(OrderLifecycle::class)->markRefunded($order->fresh(), 'test');

        $this->assertCount(0, app(Chest::class)->contents($user));
        $this->assertSame(0, Delivery::query()->count());
        $this->actingAs($user)->post(route('chest.claim', $item->id))->assertSessionHasErrors('chest');
    }
}
