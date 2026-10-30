<?php

namespace Tests\Feature;

use App\Domain\Commerce\OrderConfirmation;
use App\Domain\Commerce\OrderReview;
use App\Domain\Commerce\Pricing;
use App\Domain\Legal\LegalConsumer;
use App\Domain\Legal\LegalPresentation;
use App\Domain\Legal\LegalSubmission;
use App\Models\CartItem;
use App\Models\LegalAcceptance;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Concerns\CreatesLegalMarkets;
use Tests\TestCase;

class LegalCommercePresentationTest extends TestCase
{
    use CreatesLegalMarkets;

    private function checkoutFixture(): array
    {
        app()->setLocale('tr');
        $this->reviewedLegalMarket();
        $buyer = $this->customer();
        $product = $this->product();
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'quantity' => 1]);
        $items = CartItem::where('user_id', $buyer->id)->with('product.category')->get();
        $review = OrderReview::purchase($buyer, app(Pricing::class)->quote($buyer, $items), 'wallet', null);
        $selection = app(LegalConsumer::class)->resolve('TR', '', 'tr', 'checkout');
        $request = Request::create('/checkout', 'POST');
        $request->setLaravelSession(app('session')->driver());
        $request->setUserResolver(fn () => $buyer);
        $token = app(LegalPresentation::class)->issue($request, $selection, 'checkout', $review);
        $input = ['legal_country' => 'TR', 'legal_locale' => 'tr', 'legal_document_ids' => array_map(fn ($doc) => $doc->id, $selection['documents']),
            'terms' => 1, 'expected_total_minor' => $review->total(), 'expected_review_sha256' => $review->sha256, 'legal_review_token' => $token];
        $request->merge($input);

        return [$buyer, $product, $request, $input, $review, $selection];
    }

    public function test_presentation_binds_both_price_and_review_digest_without_creating_a_contract_or_order(): void
    {
        [$buyer, $product, $request, $input, $review] = $this->checkoutFixture();
        $presentation = app(LegalPresentation::class);
        $submission = LegalSubmission::fromInput($input, 'tr', 'checkout');
        $presentation->check($request, $submission, 'checkout');
        $review->assertMatches($submission);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, LegalAcceptance::count());
        foreach ([['expected_total_minor' => $review->total() + 1], ['expected_review_sha256' => str_repeat('0', 64)]] as $change) {
            try {
                $presentation->check($request, LegalSubmission::fromInput(array_replace($input, $change), 'tr', 'checkout'), 'checkout');
                $this->fail('Client altered facts without another displayed review.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('legal_document_ids', $exception->errors());
            }
        }
    }

    public function test_same_price_changed_product_text_requires_fresh_review_even_if_client_recalculates_the_hash(): void
    {
        [$buyer, $product, $request, $input, $oldReview, $selection] = $this->checkoutFixture();
        $product->update(['description_markdown' => 'Different delivery conditions.']);
        $items = CartItem::where('user_id', $buyer->id)->with('product.category')->get();
        $newReview = OrderReview::purchase($buyer, app(Pricing::class)->quote($buyer, $items), 'wallet', null);
        $this->assertSame($oldReview->total(), $newReview->total());
        try {
            $newReview->assertMatches(LegalSubmission::fromInput($input, 'tr', 'checkout'));
            $this->fail('Changed essential terms matched an old review.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('expected_review_sha256', $exception->errors());
        }
        $newInput = array_replace($input, ['expected_review_sha256' => $newReview->sha256]);
        $submission = LegalSubmission::fromInput($newInput, 'tr', 'checkout');
        $presentation = app(LegalPresentation::class);
        try {
            $presentation->check($request, $submission, 'checkout');
            $this->fail('A client-recomputed hash replaced the server presentation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('legal_document_ids', $exception->errors());
        }
        $request->merge(['legal_review_token' => $presentation->issue($request, $selection, 'checkout', $newReview)]);
        $presentation->check($request, $submission, 'checkout');
        $newReview->assertMatches($submission);
        $this->assertSame(0, Order::count());
    }

    public function test_review_cannot_be_reused_by_another_customer_or_as_a_different_purchase_context(): void
    {
        [$buyer, $product, $request, $input, $review, $selection] = $this->checkoutFixture();
        $other = $this->customer();
        $request->setUserResolver(fn () => $other);
        try {
            app(LegalPresentation::class)->check($request, LegalSubmission::fromInput($input, 'tr', 'checkout'), 'checkout');
            $this->fail('Another customer reused the old account review.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('legal_document_ids', $exception->errors());
        }
        $request->setUserResolver(fn () => $buyer);
        $this->expectException(LogicException::class);
        app(LegalPresentation::class)->issue($request, $selection, 'topup', $review);
    }

    public function test_confirmations_require_the_presented_http_form_and_have_a_stable_retry_reference(): void
    {
        [$buyer, $product, $request, $input, $review, $selection] = $this->checkoutFixture();
        $first = OrderConfirmation::fromRequest($request, 'checkout');
        $second = OrderConfirmation::fromRequest($request, 'checkout');
        $this->assertSame($first->referenceSha256, $second->referenceSha256);
        $this->assertSame(64, strlen($first->referenceSha256));
        $first->assertCustomer($buyer, 'checkout');
        $review->assertMatches($first->submission);
        $this->assertSame($buyer->id, $first->buyerId);
        $request->merge(['legal_review_token' => app(LegalPresentation::class)->issue($request, $selection, 'checkout', $review)]);
        $new = OrderConfirmation::fromRequest($request, 'checkout');
        $this->assertNotSame($first->referenceSha256, $new->referenceSha256);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, LegalAcceptance::count());
        $this->expectException(LogicException::class);
        $first->assertCustomer($this->customer(), 'checkout');
    }

    public function test_a_topup_confirmation_requires_its_own_review_even_for_the_same_amount(): void
    {
        [$buyer, $product, $request, $input, $review, $selection] = $this->checkoutFixture();
        try {
            OrderConfirmation::fromRequest($request, 'topup');
            $this->fail('Checkout presentation was reused for a wallet top-up.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('legal_document_ids', $exception->errors());
        }
        $topup = OrderReview::topup($buyer, $review->total(), 'sandbox');
        $token = app(LegalPresentation::class)->issue($request, $selection, 'topup', $topup);
        $request->merge(['expected_review_sha256' => $topup->sha256, 'legal_review_token' => $token]);
        $confirmation = OrderConfirmation::fromRequest($request, 'topup');
        $confirmation->assertCustomer($buyer, 'topup');
        $topup->assertMatches($confirmation->submission);
        $this->assertSame('topup', $confirmation->context);
        $this->expectException(LogicException::class);
        $confirmation->assertCustomer($buyer, 'checkout');
    }
}
