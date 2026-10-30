<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RecentPurchasesTest extends TestCase
{
    private function paidPurchase(): void
    {
        $buyer = $this->customer();
        $order = Order::query()->create([
            'user_id' => $buyer->id, 'kind' => 'purchase', 'status' => Order::PAID, 'fulfillment_status' => 'delivered',
            'currency' => 'TRY', 'subtotal_minor' => 4321, 'total_minor' => 4321, 'paid_at' => now(),
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id, 'product_name' => 'Knight rank', 'unit_price_minor' => 4321, 'quantity' => 1,
            'total_minor' => 4321, 'player_name' => 'Steve_42', 'player_uuid' => '00000000-0000-4000-8000-000000000042',
        ]);
        Cache::flush();
    }

    public function test_recent_purchases_are_hidden_unless_the_operator_enables_them(): void
    {
        $this->paidPurchase();

        $this->get('/')->assertOk()->assertDontSee('Steve_42');
    }

    public function test_enabled_widget_shows_player_and_product_but_never_amounts_or_accounts(): void
    {
        $this->paidPurchase();
        app(Settings::class)->set('store.show_recent_purchases', true);

        $response = $this->get('/')->assertOk()->assertSee('Steve_42')->assertSee('Knight rank');
        $response->assertDontSee('43,21')->assertDontSee(Order::query()->sole()->user->email);
    }
}
