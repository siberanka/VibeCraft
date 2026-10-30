<?php

namespace App\View;

use App\Domain\Commerce\OrderReview;
use App\Domain\Legal\CountryCatalog;
use App\Domain\Legal\LegalConsumer;
use App\Domain\Legal\LegalPresentation;
use App\Domain\Legal\LegalScope;
use App\Models\LegalMarket;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class LegalSelection
{
    public function __construct(private LegalConsumer $consumer, private LegalPresentation $presentation, private LegalScope $scope) {}

    public function forForm(Request $request, string $context, ?OrderReview $review = null): array
    {
        $countries = CountryCatalog::options();
        $chooses = $this->scope->customerChooses();
        $data = $chooses ? validator([
            'country' => $request->query('country', $request->old('legal_country', '')),
            'subdivision' => $request->query('subdivision', $request->old('legal_subdivision', '')),
        ], ['country' => ['nullable', 'string', Rule::in(array_keys($countries))], 'subdivision' => ['nullable', 'string', 'max:8', 'regex:/^[A-Z0-9-]*$/D']])->validate() : [];
        if (! $chooses && ($market = $this->scope->defaultMarket())) {
            $data = ['country' => $market->country, 'subdivision' => $market->subdivision];
        }
        $country = $data['country'] ?? '';
        $subdivision = $data['subdivision'] ?? '';
        $profiles = $country !== '' ? LegalMarket::where('country', $country)->where('is_enabled', true)->whereNotNull('reviewed_at')->orderBy('subdivision')->limit(256)->get() : collect();
        $selection = null;
        if ($country !== '') {
            try {
                $selection = $this->consumer->resolve($country, $subdivision, app()->getLocale(), $context);
            } catch (ValidationException) {
                // An unavailable market remains closed; never select a weaker alternative.
            }
        }
        $token = null;
        if ($selection) {
            $token = $this->presentation->issue($request, $selection, $context, $review);
        } else {
            $this->presentation->forget($request, $context);
        }

        return ['legalCountries' => $countries, 'legalCountry' => $country, 'legalSubdivision' => $subdivision,
            'legalProfiles' => $profiles, 'legalSelection' => $selection, 'legalReviewToken' => $token, 'legalKinds' => LegalView::labels(), 'legalRequiresMarketChoice' => $chooses];
    }
}
