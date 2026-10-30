<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Commerce\Checkout;
use App\Domain\Commerce\Chest;
use App\Domain\Commerce\CommerceException;
use App\Domain\Commerce\Ledger;
use App\Models\CartItem;
use App\Models\Entitlement;
use App\Models\GameIdentity;
use App\Models\User;
use App\Notifications\PlayerNotice;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GiftPurchaseTest extends TestCase
{
    private function linked(User $user, string $uuid, string $name): GameIdentity
    {
        return GameIdentity::create(['user_id' => $user->id, 'edition' => 'java', 'uuid' => $uuid, 'username' => $name, 'verified_via' => 'bridge', 'verified_at' => now()]);
    }

    private function buyerWithCart(array $product = []): array
    {
        $buyer = $this->customer();
        $this->linked($buyer, '11111111-1111-1111-1111-111111111111', 'BuyerOne');
        $recipient = $this->customer();
        $this->linked($recipient, '22222222-2222-2222-2222-222222222222', 'FriendTwo');
        $item = $this->product(['requires_link' => true] + $product);
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $item->id, 'quantity' => 1]);
        app(Ledger::class)->post($buyer->id, 5000, 'adjustment', 'gift-fixture-'.$buyer->id);

        return [$buyer->fresh(), $recipient, $item];
    }

    public function test_gift_is_reviewed_delivered_to_the_recipient_and_announced(): void
    {
        [$buyer, $recipient, $item] = $this->buyerWithCart(['duration_days' => 30]);
        $this->actingAs($buyer);
        $review = $this->get('/checkout/review?method=wallet&gift_to='.$recipient->name)->assertOk()->assertSee($recipient->name)->assertSee('FriendTwo');
        $this->assertSame(['id' => $recipient->id, 'name' => $recipient->name], $review->viewData('details')['gift_recipient']);
        $this->assertSame('FriendTwo', $review->viewData('details')['minecraft_target']['username']);

        $order = app(Checkout::class)->placeOrder($buyer, null, 'wallet', null, null, $recipient->name);
        $this->assertSame($recipient->id, $order->gift_recipient_id);
        $this->assertSame($buyer->id, $order->user_id);
        $this->assertSame('22222222-2222-2222-2222-222222222222', $order->items()->sole()->player_uuid);
        $this->assertSame($recipient->id, Entitlement::query()->sole()->user_id);
        $this->assertSame(5000 - $item->price_minor, app(Ledger::class)->balance($buyer->id));
        Notification::assertSentTo($recipient, PlayerNotice::class, fn (PlayerNotice $notice) => $notice->kind === 'gift_received' && $notice->params['player'] === $buyer->name);
    }

    public function test_invalid_gift_targets_are_rejected_before_an_order_exists(): void
    {
        [$buyer, $recipient] = $this->buyerWithCart();
        $loner = $this->customer();
        foreach ([$buyer->name, 'nobody_here', $loner->name, '<b>x</b>'] as $target) {
            try {
                app(Checkout::class)->placeOrder($buyer, null, 'wallet', null, null, $target);
                $this->fail('gift accepted: '.$target);
            } catch (CommerceException) {
            }
        }
        $this->actingAs($buyer)->get('/checkout/review?method=wallet&gift_to='.$loner->name)->assertSessionHasErrors('gift_to');
        app(Settings::class)->set('store.gifts', false);
        $this->expectException(CommerceException::class);
        app(Checkout::class)->placeOrder($buyer, null, 'wallet', null, null, $recipient->name);
    }

    public function test_gifted_chest_items_belong_to_the_recipient(): void
    {
        [$buyer, $recipient] = $this->buyerWithCart(['chest_delivery' => true]);
        $order = app(Checkout::class)->placeOrder($buyer, null, 'wallet', null, null, $recipient->name);
        $chest = app(Chest::class);

        $this->assertCount(0, $chest->contents($buyer));
        $items = $chest->contents($recipient);
        $this->assertCount(1, $items);
        $this->expectException(CommerceException::class);
        try {
            $chest->claim($buyer, $items->first()->id);
        } finally {
            $this->assertNotNull($chest->claim($recipient, $items->first()->id)->chest_claimed_at);
            $this->assertSame($order->id, $items->first()->order_id);
        }
    }
}
