<?php

namespace Tests\Feature;

use App\Domain\Commerce\CommerceException;
use App\Domain\Commerce\OrderReview;
use App\Domain\Commerce\Pricing;
use App\Domain\Commerce\Quote;
use App\Models\CartItem;
use App\Models\GameIdentity;
use App\Models\User;
use Tests\TestCase;

class OrderReviewTest extends TestCase
{
    private function review(User $buyer): OrderReview
    {
        $items = CartItem::where('user_id', $buyer->id)->with('product.category')->get();

        return OrderReview::purchase($buyer, app(Pricing::class)->quote($buyer, $items), 'wallet', null);
    }

    public function test_equal_total_product_or_description_changes_require_a_new_review(): void
    {
        $buyer = $this->customer();
        $first = $this->product(['price_minor' => 1000, 'description_markdown' => 'First product conditions.']);
        $second = $this->product(['price_minor' => 1000, 'description_markdown' => 'Different product conditions.']);
        $item = CartItem::create(['user_id' => $buyer->id, 'product_id' => $first->id, 'quantity' => 1]);
        $original = $this->review($buyer);
        $item->update(['product_id' => $second->id]);
        $changed = $this->review($buyer);
        $this->assertSame($original->total(), $changed->total());
        $this->assertNotSame($original->sha256, $changed->sha256);
        $second->update(['description_markdown' => 'New delivery restrictions.']);
        $this->assertNotSame($changed->sha256, $this->review($buyer)->sha256);
        $this->assertSame('First product conditions.', $original->payload()['lines'][0]['description_markdown']);
        $copy = $original->payload();
        $copy['lines'][0]['name'] = 'Changed outside the review';
        $this->assertNotSame($copy, $original->payload());
    }

    public function test_cosmetic_cart_order_and_available_stock_do_not_change_the_reviewed_contract_facts(): void
    {
        $buyer = $this->customer();
        foreach ([1000, 2000] as $price) {
            $product = $this->product(['price_minor' => $price]);
            CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'quantity' => 1]);
        }
        $items = CartItem::where('user_id', $buyer->id)->with('product.category')->get();
        $pricing = app(Pricing::class);
        $first = OrderReview::purchase($buyer, $pricing->quote($buyer, $items), 'wallet', null);
        $second = OrderReview::purchase($buyer, $pricing->quote($buyer, $items->reverse()), 'wallet', null);
        $this->assertSame($first->sha256, $second->sha256);
        $items->first()->product->update(['stock' => 9]);
        $this->assertSame($first->sha256, $this->review($buyer)->sha256);
        $this->assertSame(3000, $first->total());
    }

    public function test_inconsistent_price_arithmetic_cannot_become_a_review(): void
    {
        $buyer = $this->customer();
        $product = $this->product(['price_minor' => 1000]);
        foreach ([new Quote([['product' => $product, 'quantity' => 2, 'unit' => 1000, 'total' => 1000]], 1000, 0, null),
            new Quote([['product' => $product, 'quantity' => 1, 'unit' => 1000, 'total' => 1000]], 1001, 0, null),
            new Quote([['product' => $product, 'quantity' => 1, 'unit' => 1000, 'total' => 1000]], 1000, 1001, null)] as $quote) {
            try {
                OrderReview::purchase($buyer, $quote, 'wallet', null);
                $this->fail('Inconsistent quote accepted.');
            } catch (CommerceException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_minecraft_target_must_belong_to_the_buyer_and_is_part_of_the_review(): void
    {
        $buyer = $this->customer();
        $other = $this->customer();
        $product = $this->product(['requires_link' => true]);
        $quote = new Quote([['product' => $product, 'quantity' => 1, 'unit' => 1999, 'total' => 1999]], 1999, 0, null);
        $identity = GameIdentity::create(['user_id' => $other->id, 'edition' => 'java', 'uuid' => '12345678-1234-1234-1234-123456789abc', 'username' => 'FixturePlayer', 'verified_via' => 'bridge', 'verified_at' => now()]);
        try {
            OrderReview::purchase($buyer, $quote, 'wallet', $identity);
            $this->fail('Another account target was reviewed.');
        } catch (CommerceException) {
            $this->addToAssertionCount(1);
        }
        $identity->update(['user_id' => $buyer->id]);
        $first = OrderReview::purchase($buyer, $quote, 'wallet', $identity);
        $identity->update(['username' => 'UpdatedFixturePlayer']);
        $this->assertNotSame($first->sha256, OrderReview::purchase($buyer, $quote, 'wallet', $identity)->sha256);
        $this->assertArrayNotHasKey('verified_via', $first->payload()['minecraft_target']);
        $this->assertSame('FixturePlayer', $first->payload()['minecraft_target']['username']);
    }

    public function test_topup_review_binds_amount_provider_currency_and_customer_without_fabricating_an_exchange(): void
    {
        $buyer = $this->customer();
        $first = OrderReview::topup($buyer, 1234, 'sandbox');
        $this->assertSame('topup', $first->context());
        $this->assertSame(1234, $first->total());
        $this->assertSame('TRY', $first->payload()['currency']);
        $this->assertNotSame($first->sha256, OrderReview::topup($buyer, 1235, 'sandbox')->sha256);
        $this->assertNotSame($first->sha256, OrderReview::topup($buyer, 1234, 'stripe')->sha256);
        $this->assertNotSame($first->sha256, OrderReview::topup($this->customer(), 1234, 'sandbox')->sha256);
        config(['vibecraft.store.currency' => 'USD']);
        $this->assertNotSame($first->sha256, OrderReview::topup($buyer, 1234, 'sandbox')->sha256);
        $this->assertSame('TRY', $first->payload()['currency']);
        $this->expectException(CommerceException::class);
        OrderReview::topup($buyer, 1234, 'wallet');
    }
}
