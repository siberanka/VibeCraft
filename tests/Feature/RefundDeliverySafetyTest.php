<?php

namespace Tests\Feature;

use App\Domain\Commerce\Checkout;
use App\Domain\Commerce\DeliveryQueue;
use App\Domain\Commerce\Fulfillment;
use App\Domain\Commerce\Ledger;
use App\Domain\Commerce\OrderLifecycle;
use App\Models\CartItem;
use App\Models\Delivery;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\ProductCommand;
use Tests\TestCase;

class RefundDeliverySafetyTest extends TestCase
{
    private function purchase(bool $timed = false): array
    {
        $server = GameServer::forceCreate(['name' => 'Refund fixture', 'slug' => 'refund-fixture',
            'bridge_key_id' => 'vck_'.bin2hex(random_bytes(12)), 'bridge_secret' => 'fixture-refund-key', 'bridge_enabled' => true]);
        $user = $this->customer();
        $product = $this->product(['duration_days' => $timed ? 1 : null]);
        foreach (['purchase', 'refund', 'expire'] as $phase) {
            ProductCommand::create(['product_id' => $product->id, 'game_server_id' => $server->id, 'phase' => $phase, 'command' => 'say fixture-'.$phase]);
        }
        CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);
        app(Ledger::class)->post($user->id, 10000, 'adjustment', 'fixture:refund-balance');

        return [app(Checkout::class)->placeOrder($user, null, 'wallet', null), $server];
    }

    public function test_refund_before_poll_cancels_purchase_and_does_not_send_compensation(): void
    {
        [$order, $server] = $this->purchase();
        app(OrderLifecycle::class)->markRefunded($order, 'wallet');
        $this->assertSame(Order::REFUNDED, $order->fresh()->status);
        $this->assertSame('cancelled', $order->fresh()->fulfillment_status);
        $this->assertSame(Delivery::CANCELLED, Delivery::where('phase', 'purchase')->sole()->status);
        $this->assertSame(0, Delivery::where('phase', 'refund')->count());
        $this->assertCount(0, app(DeliveryQueue::class)->lease($server, [], 25));
    }

    public function test_refund_during_a_lease_requires_manual_reconciliation_without_compensation(): void
    {
        [$order, $server] = $this->purchase();
        $queue = app(DeliveryQueue::class);
        $sent = $queue->lease($server, [], 25)->sole();
        app(OrderLifecycle::class)->markRefunded($order, 'wallet');
        $this->assertSame('manual_review', $order->fresh()->fulfillment_status);
        $this->assertSame(Delivery::MANUAL_REVIEW, $sent->fresh()->status);
        $this->assertSame(0, Delivery::where('phase', 'refund')->count());
        $late = $queue->acknowledge($server, $sent->public_id, $sent->lease_token, 'delivered');
        $this->assertSame(Delivery::MANUAL_REVIEW, $late->status);
        $this->assertNull($late->delivered_at);
        $this->assertCount(0, $queue->lease($server, [], 25));
    }

    public function test_confirmed_delivery_can_be_compensated_once_after_refund(): void
    {
        [$order, $server] = $this->purchase();
        $queue = app(DeliveryQueue::class);
        $sent = $queue->lease($server, [], 25)->sole();
        $queue->acknowledge($server, $sent->public_id, $sent->lease_token, 'delivered');
        $lifecycle = app(OrderLifecycle::class);
        $this->assertTrue($lifecycle->markRefunded($order, 'wallet'));
        $this->assertFalse($lifecycle->markRefunded($order, 'wallet'));
        $this->assertSame(1, Delivery::where('phase', 'refund')->count());
        $compensation = $queue->lease($server, [], 25)->sole();
        $this->assertSame('refund', $compensation->phase);
        $queue->acknowledge($server, $compensation->public_id, $compensation->lease_token, 'delivered');
        $this->assertSame('revoked', $order->fresh()->fulfillment_status);
        $this->assertCount(0, $queue->lease($server, [], 25));
    }

    public function test_success_after_an_expired_lease_does_not_silently_become_delivered(): void
    {
        [$order, $server] = $this->purchase();
        $queue = app(DeliveryQueue::class);
        $sent = $queue->lease($server, [], 25)->sole();
        $this->travel(121)->seconds();
        $late = $queue->acknowledge($server, $sent->public_id, $sent->lease_token, 'delivered');
        $this->assertSame(Delivery::MANUAL_REVIEW, $late->status);
        $this->assertSame('manual_review', $order->fresh()->fulfillment_status);
        $this->assertNull($late->delivered_at);
    }

    public function test_uncertain_execution_is_quarantined_without_requeueing_other_commands(): void
    {
        [$order, $server] = $this->purchase();
        $queue = app(DeliveryQueue::class);
        $sent = $queue->lease($server, [], 25)->sole();
        $uncertain = $queue->acknowledge($server, $sent->public_id, $sent->lease_token, 'uncertain');
        $this->assertSame(Delivery::MANUAL_REVIEW, $uncertain->status);
        $this->assertNull($uncertain->delivered_at);
        $this->assertSame('manual_review', $order->fresh()->fulfillment_status);
        $this->assertCount(0, $queue->lease($server, [], 25));
    }

    public function test_uncertain_outcome_after_expiry_still_requires_reconciliation(): void
    {
        [$order, $server] = $this->purchase();
        $queue = app(DeliveryQueue::class);
        $sent = $queue->lease($server, [], 25)->sole();
        $this->travel(121)->seconds();
        $late = $queue->acknowledge($server, $sent->public_id, $sent->lease_token, 'uncertain');
        $this->assertSame(Delivery::MANUAL_REVIEW, $late->status);
        $this->assertSame('manual_review', $order->fresh()->fulfillment_status);
        $this->assertNull($late->delivered_at);
    }

    public function test_refunded_entitlement_does_not_schedule_an_expiry_command(): void
    {
        [$order, $server] = $this->purchase(timed: true);
        app(OrderLifecycle::class)->markRefunded($order, 'wallet');
        $this->travel(2)->days();
        $this->assertSame(0, app(Fulfillment::class)->expireDue());
        $this->assertSame(0, Delivery::where('phase', 'expire')->count());
    }

    public function test_refund_while_expiry_command_is_in_flight_withholds_other_compensation(): void
    {
        [$order, $server] = $this->purchase(timed: true);
        $queue = app(DeliveryQueue::class);
        $purchase = $queue->lease($server, [], 25)->sole();
        $queue->acknowledge($server, $purchase->public_id, $purchase->lease_token, 'delivered');
        $this->travel(2)->days();
        $this->assertSame(1, app(Fulfillment::class)->expireDue());
        $expiry = $queue->lease($server, [], 25)->sole();
        $this->assertSame('expire', $expiry->phase);
        app(OrderLifecycle::class)->markRefunded($order, 'wallet');
        $this->assertSame('manual_review', $order->fresh()->fulfillment_status);
        $this->assertSame(Delivery::MANUAL_REVIEW, $expiry->fresh()->status);
        $this->assertSame(0, Delivery::where('phase', 'refund')->count());
    }
}
