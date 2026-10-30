<?php

namespace Tests\Concerns;

use App\Domain\Legal\LegalMarkets;
use App\Domain\Legal\LegalPublisher;
use App\Models\LegalMarket;

trait CreatesLegalMarkets
{
    protected function reviewedLegalMarket(string $country = 'TR', string $locale = 'tr', string $subdivision = '', array $additionalLocales = []): LegalMarket
    {
        $actor = $this->customer('owner');
        $locales = array_values(array_unique([$locale, ...$additionalLocales]));
        $market = LegalMarket::create(['country' => $country, 'subdivision' => $subdivision, 'locales' => $locales, 'trader' => [
            'name' => 'Synthetic QA Merchant', 'address' => 'Isolated Fixture Street',
            'email' => 'legal@fixture.example.test', 'phone' => '+900000000000',
        ]]);
        $publisher = app(LegalPublisher::class);
        foreach ($locales as $locale) {
            foreach (LegalPublisher::KINDS as $kind) {
                $policy = $publisher->saveDraft($market, $actor, ['kind' => $kind, 'locale' => $locale, 'title' => 'Fixture '.$kind,
                    'body_markdown' => "## Synthetic document\n\nLocal functional fixture only.\n\n[Contact](https://fixture.example.test/contact)"]);
                $publisher->publish($policy, 1, $actor);
            }
        }

        return app(LegalMarkets::class)->enable($market, $actor, 1, true);
    }
}
