<?php

namespace Tests\Concerns;

use App\Domain\Commerce\OrderConfirmation;
use App\Domain\Commerce\OrderReview;
use App\Domain\Legal\LegalConsumer;
use App\Domain\Legal\LegalPresentation;
use App\Models\LegalMarket;
use App\Models\User;
use Illuminate\Http\Request;

trait CreatesOrderConfirmations
{
    protected function orderConfirmation(User $buyer, OrderReview $review, LegalMarket $market): OrderConfirmation
    {
        $locale = app()->getLocale();
        $selection = app(LegalConsumer::class)->resolve($market->country, $market->subdivision, $locale, $review->context());
        $request = Request::create('/checkout', 'POST');
        $request->setLaravelSession(app('session')->driver());
        $request->setUserResolver(fn () => $buyer);
        $token = app(LegalPresentation::class)->issue($request, $selection, $review->context(), $review);
        $request->merge(['legal_country' => $market->country, 'legal_subdivision' => $market->subdivision, 'legal_locale' => $locale,
            'legal_document_ids' => array_map(fn ($doc) => $doc->id, $selection['documents']), 'terms' => 1,
            'expected_total_minor' => $review->total(), 'expected_review_sha256' => $review->sha256, 'legal_review_token' => $token]);

        return OrderConfirmation::fromRequest($request, $review->context());
    }
}
