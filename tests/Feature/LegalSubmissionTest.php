<?php

namespace Tests\Feature;

use App\Domain\Legal\CountryCatalog;
use App\Domain\Legal\LegalSubmission;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LegalSubmissionTest extends TestCase
{
    private function input(array $overrides = []): array
    {
        return array_replace(['legal_country' => 'TR', 'legal_subdivision' => '', 'legal_locale' => 'tr', 'legal_document_ids' => ['terms' => 1, 'privacy' => 2, 'cookies' => 3], 'terms' => '1'], $overrides);
    }

    public function test_actual_country_codes_are_independent_of_language_and_currency(): void
    {
        $codes = CountryCatalog::options('tr');
        $this->assertArrayHasKey('TR', $codes);
        $this->assertArrayHasKey('GB', $codes);
        $this->assertArrayHasKey('US', $codes);
        $this->assertArrayHasKey('DE', $codes);
        $this->assertArrayNotHasKey('EU', $codes);
        $this->assertArrayNotHasKey('ZZ', $codes);
        $this->assertFalse(CountryCatalog::contains('UK'));
        $submission = LegalSubmission::fromInput($this->input(['legal_country' => ' gb ', 'currency' => 'TRY']), 'tr', 'registration');
        $this->assertSame('GB', $submission->country);
        $this->assertSame('tr', $submission->locale);
        $this->assertNull($submission->expectedTotalMinor);
    }

    public function test_legal_selection_requires_explicit_terms_and_current_display_language(): void
    {
        foreach ([[$this->input(['terms' => '0']), 'terms'], [$this->input(['legal_locale' => 'en']), 'legal_locale'], [$this->input(['legal_country' => 'ZZ']), 'legal_country'], [$this->input(['legal_document_ids' => ['terms' => 1, 'other' => 2]]), 'legal_document_ids']] as [$input, $field]) {
            try {
                LegalSubmission::fromInput($input, 'tr', 'registration');
                $this->fail('Invalid legal selection accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }
    }

    public function test_checkout_and_topup_require_a_price_to_compare_with_authoritative_quote(): void
    {
        foreach (['checkout', 'topup'] as $context) {
            try {
                LegalSubmission::fromInput($this->input(), 'tr', $context);
                $this->fail('No order price expectation was provided.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('expected_total_minor', $exception->errors());
            }
        }
        $submission = LegalSubmission::fromInput($this->input(['expected_total_minor' => '1999', 'expected_review_sha256' => str_repeat('a', 64)]), 'tr', 'checkout');
        $this->assertSame(1999, $submission->expectedTotalMinor);
        $this->assertSame(str_repeat('a', 64), $submission->expectedReviewSha256);
    }
}
