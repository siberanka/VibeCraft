<?php

namespace Tests\Feature;

use App\Domain\Commerce\Ledger;
use App\Domain\Commerce\Renewals;
use App\Models\Entitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\WalletEntry;
use Tests\TestCase;

class RenewalsTest extends TestCase
{
    /** @return array{0: User, 1: Entitlement} */
    private function rank(int $balance): array
    {
        $user = $this->customer();
        $product = $this->product(['price_minor' => 1500, 'duration_days' => 30]);
        $order = Order::query()->create(['user_id' => $user->id, 'kind' => 'purchase', 'status' => Order::PAID, 'fulfillment_status' => 'delivered', 'currency' => 'TRY', 'subtotal_minor' => 1500, 'total_minor' => 1500, 'paid_at' => now()]);
        $item = OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'unit_price_minor' => 1500, 'quantity' => 1, 'total_minor' => 1500, 'duration_days' => 30]);
        $entitlement = Entitlement::query()->create(['user_id' => $user->id, 'product_id' => $product->id, 'order_item_id' => $item->id, 'starts_at' => now()->subDays(30), 'expires_at' => now()->addMinute(), 'status' => 'active']);
        if ($balance > 0) {
            app(Ledger::class)->post($user->id, $balance, 'adjustment', 'fixture:'.$user->id);
        }

        return [$user, $entitlement];
    }

    public function test_due_rank_renews_once_per_period_at_the_agreed_price(): void
    {
        [$user, $entitlement] = $this->rank(5000);
        app(Renewals::class)->enable($user, $entitlement);
        $entitlement->orderItem->product->update(['price_minor' => 9999]);
        $this->travel(2)->minutes();
        $before = $entitlement->fresh()->expires_at;

        $this->assertSame(1, app(Renewals::class)->renewDue());
        $this->assertSame(0, app(Renewals::class)->renewDue());

        $this->assertSame(3500, app(Ledger::class)->balance($user->id));
        $this->assertTrue($entitlement->fresh()->expires_at->eq($before->copy()->addDays(30)));
        $this->assertSame(1, WalletEntry::query()->where('type', 'renewal')->count());
    }

    public function test_insufficient_balance_turns_renewal_off_and_lets_expiry_run(): void
    {
        [$user, $entitlement] = $this->rank(100);
        app(Renewals::class)->enable($user, $entitlement);
        $this->travel(2)->minutes();

        $this->assertSame(0, app(Renewals::class)->renewDue());
        $this->assertFalse($entitlement->fresh()->auto_renew);
        $this->assertSame(100, app(Ledger::class)->balance($user->id));
    }

    public function test_only_the_owner_can_switch_renewal(): void
    {
        [, $entitlement] = $this->rank(0);
        $this->actingAs($this->customer())->post(route('account.renewal', $entitlement), ['auto_renew' => 1])->assertNotFound();
        $this->assertFalse($entitlement->fresh()->auto_renew);
    }
}
