<?php

namespace App\Domain\Legal;

use App\Core\Settings\Settings;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final readonly class LegalSubmission
{
    /** @param array<string,int> $documentIds */
    private function __construct(
        public string $country,
        public string $subdivision,
        public string $locale,
        public array $documentIds,
        public ?int $expectedTotalMinor,
        public ?string $expectedReviewSha256,
        public int $scopeRevision,
    ) {}

    /** Input is still rechecked against current locked publications at transaction time. */
    public static function fromInput(array $input, string $locale, string $context): self
    {
        if (! in_array($context, ['registration', 'checkout', 'topup'], true)) {
            throw new InvalidArgumentException('Unknown legal submission context.');
        }
        $input['legal_country'] = is_string($input['legal_country'] ?? null) ? strtoupper(trim($input['legal_country'])) : ($input['legal_country'] ?? null);
        $input['legal_subdivision'] = is_string($input['legal_subdivision'] ?? null) ? strtoupper(trim($input['legal_subdivision'])) : ($input['legal_subdivision'] ?? '');
        $data = Validator::make($input, [
            'legal_country' => ['required', 'string', Rule::in(array_keys(CountryCatalog::options('en')))],
            'legal_subdivision' => ['nullable', 'string', 'max:8', 'regex:/^[A-Z0-9-]*$/D'],
            'legal_locale' => ['required', 'string', Rule::in([$locale])],
            'legal_document_ids' => ['required', 'array:terms,privacy,cookies,sales,precontract,withdrawal', 'min:1', 'max:6'],
            'legal_document_ids.*' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'expected_total_minor' => [Rule::requiredIf($context !== 'registration'), 'nullable', 'integer', 'min:0', 'max:9000000000000'],
            'expected_review_sha256' => [Rule::requiredIf($context !== 'registration'), 'nullable', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'terms' => ['accepted'],
        ])->validate();

        return new self(
            $data['legal_country'], $data['legal_subdivision'] ?? '', $locale,
            array_map(static fn ($id) => (int) $id, $data['legal_document_ids']),
            isset($data['expected_total_minor']) ? (int) $data['expected_total_minor'] : null,
            $context !== 'registration' ? $data['expected_review_sha256'] : null,
            (int) app(Settings::class)->get('legal.selection_revision'),
        );
    }
}
