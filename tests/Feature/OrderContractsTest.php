<?php

namespace Tests\Feature;

use App\Domain\Commerce\OrderConfirmation;
use App\Domain\Commerce\OrderContracts;
use App\Domain\Commerce\OrderReview;
use App\Domain\Commerce\Pricing;
use App\Domain\Legal\LegalConsumer;
use App\Domain\Legal\LegalPresentation;
use App\Domain\Legal\LegalPublisher;
use App\Models\CartItem;
use App\Models\LegalAcceptance;
use App\Models\Order;
use App\Models\OrderContract;
use App\Models\OrderContractDocument;
use App\Models\OrderItem;
use App\Support\CanonicalJson;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Tests\Concerns\CreatesLegalMarkets;
use Tests\TestCase;

class OrderContractsTest extends TestCase
{
    use CreatesLegalMarkets;

    private function fixture(bool $purchase = false): array
    {
        app()->setLocale('tr');
        $market = $this->reviewedLegalMarket();
        $buyer = $this->customer();
        $product = null;
        if ($purchase) {
            $product = $this->product();
            CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'quantity' => 1]);
            $items = CartItem::where('user_id', $buyer->id)->with('product.category')->get();
            $review = OrderReview::purchase($buyer, app(Pricing::class)->quote($buyer, $items), 'wallet', null);
        } else {
            $review = OrderReview::topup($buyer, 1000, 'sandbox');
        }
        $selection = app(LegalConsumer::class)->resolve('TR', '', 'tr', $review->context());
        $request = Request::create('/checkout', 'POST');
        $request->setLaravelSession(app('session')->driver());
        $request->setUserResolver(fn () => $buyer);
        $token = app(LegalPresentation::class)->issue($request, $selection, $review->context(), $review);
        $request->merge(['legal_country' => 'TR', 'legal_locale' => 'tr', 'legal_document_ids' => array_map(fn ($doc) => $doc->id, $selection['documents']),
            'terms' => 1, 'expected_total_minor' => $review->total(), 'expected_review_sha256' => $review->sha256, 'legal_review_token' => $token]);
        $confirmation = OrderConfirmation::fromRequest($request, $review->context());

        return [$buyer, $review, $confirmation, $market, $product];
    }

    public function test_a_contract_keeps_all_six_original_texts_and_trader_after_republication_and_profile_changes(): void
    {
        [$buyer, $review, $confirmation, $market] = $this->fixture();
        [$order, $contract] = DB::transaction(function () use ($buyer, $review, $confirmation) {
            $order = $this->pendingOrder($buyer);

            return [$order, app(OrderContracts::class)->capture($order, $buyer, $review, $confirmation)];
        });
        $this->assertTrue(app(OrderContracts::class)->validFor($order));
        $this->assertSame(6, $contract->documents()->count());
        $this->assertSame(6, LegalAcceptance::where('order_id', $order->id)->count());
        $original = $contract->fresh()->snapshot;
        $this->assertCount(6, $original['documents']);
        $this->assertSame($contract->snapshot_sha256, $contract->fresh()->contentDigest());
        $actor = $this->customer('owner');
        $policy = $market->policies()->where('kind', 'terms')->firstOrFail();
        $publisher = app(LegalPublisher::class);
        $policy = $publisher->saveDraft($market, $actor, ['kind' => 'terms', 'locale' => 'tr', 'revision' => 2, 'title' => 'New fixture terms', 'body_markdown' => 'Changed later, not the original order contract.']);
        $publisher->publish($policy, 3, $actor);
        $market->update(['trader' => array_replace($market->trader, ['name' => 'Changed Later Merchant'])]);
        $this->assertSame($original, $contract->fresh()->snapshot);
        $this->assertTrue(app(OrderContracts::class)->validFor($order));
        $this->assertSame('Synthetic QA Merchant', $original['trader']['name']);
    }

    public function test_json_map_order_is_not_content_but_broken_document_links_or_changed_order_totals_are_rejected(): void
    {
        [$buyer, $review, $confirmation] = $this->fixture();
        [$order, $contract] = DB::transaction(function () use ($buyer, $review, $confirmation) {
            $order = $this->pendingOrder($buyer);

            return [$order, app(OrderContracts::class)->capture($order, $buyer, $review, $confirmation)];
        });
        $snapshot = array_reverse($contract->snapshot, true);
        $snapshot['review'] = array_reverse($snapshot['review'], true);
        $snapshot['documents'][0] = array_reverse($snapshot['documents'][0], true);
        DB::table('order_contracts')->where('id', $contract->id)->update(['snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
        $this->assertTrue(app(OrderContracts::class)->validFor($order));
        $this->assertSame($contract->review_sha256, CanonicalJson::digest($snapshot['review']));
        $order->update(['total_minor' => 1001]);
        $this->assertFalse(app(OrderContracts::class)->validFor($order));
        $order->update(['total_minor' => 1000]);
        DB::table('order_contract_documents')->where('order_contract_id', $contract->id)->limit(1)->delete();
        $this->assertFalse(app(OrderContracts::class)->validFor($order));
    }

    public function test_same_confirmation_is_idempotent_for_its_order_but_cannot_create_another_order(): void
    {
        [$buyer, $review, $confirmation] = $this->fixture();
        $first = DB::transaction(function () use ($buyer, $review, $confirmation) {
            $order = $this->pendingOrder($buyer);
            $one = app(OrderContracts::class)->capture($order, $buyer, $review, $confirmation);
            $two = app(OrderContracts::class)->capture($order, $buyer, $review, $confirmation);
            $this->assertSame($one->id, $two->id);

            return $one;
        });
        try {
            DB::transaction(fn () => app(OrderContracts::class)->capture($this->pendingOrder($buyer), $buyer, $review, $confirmation));
            $this->fail('Confirmation created a second order contract.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('expected_review_sha256', $exception->errors());
        }
        $this->assertSame(1, Order::count());
        $this->assertSame(1, OrderContract::count());
        $this->assertSame(6, LegalAcceptance::count());
        $this->assertTrue(app(OrderContracts::class)->validFor($first->order));
    }

    public function test_partial_document_storage_failure_rolls_back_order_contract_and_all_acceptance_rows(): void
    {
        [$buyer, $review, $confirmation] = $this->fixture();
        $attempts = 0;
        Event::listen('eloquent.creating: '.OrderContractDocument::class, function () use (&$attempts): void {
            if (++$attempts === 2) {
                throw new RuntimeException('Synthetic contract document write failure.');
            }
        });
        try {
            DB::transaction(fn () => app(OrderContracts::class)->capture($this->pendingOrder($buyer), $buyer, $review, $confirmation));
            $this->fail('Partial order contract survived.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic contract document write failure.', $exception->getMessage());
        }
        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderContract::count());
        $this->assertSame(0, OrderContractDocument::count());
        $this->assertSame(0, LegalAcceptance::count());
    }

    public function test_purchased_items_must_match_the_review_even_when_the_total_matches(): void
    {
        [$buyer, $review, $confirmation, $market, $product] = $this->fixture(true);
        try {
            DB::transaction(function () use ($buyer, $review, $confirmation, $product) {
                $order = $this->pendingOrder($buyer, 1999, 'wallet', 'purchase');
                OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'Different product name',
                    'unit_price_minor' => 1999, 'quantity' => 1, 'total_minor' => 1999]);
                app(OrderContracts::class)->capture($order, $buyer, $review, $confirmation);
            });
            $this->fail('Different purchased product was bound to the review.');
        } catch (LogicException $exception) {
            $this->assertSame('The order items changed after review.', $exception->getMessage());
        }
        $this->assertSame(0, Order::count());
        $this->assertSame(0, LegalAcceptance::count());
    }

    public function test_contracts_are_immutable_and_their_original_evidence_has_a_database_reference(): void
    {
        [$buyer, $review, $confirmation] = $this->fixture();
        $contract = DB::transaction(fn () => app(OrderContracts::class)->capture($this->pendingOrder($buyer), $buyer, $review, $confirmation));
        foreach (['update', 'delete'] as $operation) {
            try {
                if ($operation === 'update') {
                    $contract->update(['context' => 'checkout']);
                } else {
                    $contract->delete();
                }
                $this->fail('Frozen contract mutation accepted.');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }
        $link = $contract->documents()->firstOrFail();
        try {
            DB::table('legal_acceptances')->where('id', $link->legal_acceptance_id)->delete();
            $this->fail('Referenced original evidence was deleted.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(6, LegalAcceptance::count());
    }

    public function test_valid_purchased_items_are_frozen_and_a_later_item_change_fails_even_at_the_same_order_total(): void
    {
        [$buyer, $review, $confirmation, $market, $product] = $this->fixture(true);
        [$order, $item] = DB::transaction(function () use ($buyer, $review, $confirmation, $product) {
            $order = $this->pendingOrder($buyer, 1999, 'wallet', 'purchase');
            $item = OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name,
                'unit_price_minor' => 1999, 'quantity' => 1, 'total_minor' => 1999]);
            app(OrderContracts::class)->capture($order, $buyer, $review, $confirmation);

            return [$order, $item];
        });
        $this->assertTrue(app(OrderContracts::class)->validFor($order));
        $item->update(['quantity' => 2]);
        $this->assertSame(1999, $order->fresh()->total_minor);
        $this->assertFalse(app(OrderContracts::class)->validFor($order));
    }
}
