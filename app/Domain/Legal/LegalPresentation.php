<?php

namespace App\Domain\Legal;

use App\Core\Settings\Settings;
use App\Domain\Commerce\OrderReview;
use App\Models\LegalDocument;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LogicException;

/** Confirms the form presented these versions; never claims the visitor read them. */
final class LegalPresentation
{
    public function issue(Request $request, array $selection, string $context, ?OrderReview $review = null): string
    {
        LegalConsumer::kinds($context);
        if (($context === 'registration' && $review !== null) || ($context !== 'registration' && ($review === null || $review->context() !== $context))) {
            throw new LogicException('The agreement form must include its matching business review.');
        }
        if ($review && (! $request->user() || (int) $request->user()->id !== $review->payload()['buyer_id'])) {
            throw new LogicException('The business review must belong to the current customer.');
        }
        $token = bin2hex(random_bytes(32));
        $ids = array_map(static fn (LegalDocument $document) => $document->id, $selection['documents']);
        ksort($ids, SORT_STRING);
        $request->session()->put('legal.presented.'.$context, [
            'token_hash' => hash('sha256', $token), 'country' => $selection['market']->country,
            'subdivision' => $selection['market']->subdivision, 'locale' => app()->getLocale(),
            'documents' => $ids, 'at' => time(),
            'expected_total_minor' => $review?->total(), 'expected_review_sha256' => $review?->sha256,
            'buyer_id' => $review ? $review->payload()['buyer_id'] : null,
            'scope_revision' => (int) app(Settings::class)->get('legal.selection_revision'),
        ]);

        return $token;
    }

    public function check(Request $request, LegalSubmission $submission, string $context): void
    {
        $record = $request->session()->get('legal.presented.'.$context);
        $token = $request->input('legal_review_token');
        $ids = $submission->documentIds;
        $customer = $request->user();
        ksort($ids, SORT_STRING);
        if (! is_array($record) || ! is_string($token) || strlen($token) !== 64
            || ! hash_equals($record['token_hash'], hash('sha256', $token))
            || $record['country'] !== $submission->country || $record['subdivision'] !== $submission->subdivision
            || $record['locale'] !== $submission->locale || $record['documents'] !== $ids
            || ($record['expected_total_minor'] ?? null) !== $submission->expectedTotalMinor
            || ($record['expected_review_sha256'] ?? null) !== $submission->expectedReviewSha256
            || (($record['buyer_id'] ?? null) !== null && (! $customer || (int) $customer->id !== $record['buyer_id']))
            || ($record['scope_revision'] ?? 1) !== $submission->scopeRevision
            || $record['at'] > time() || $record['at'] < time() - 3600) {
            throw ValidationException::withMessages(['legal_document_ids' => __('Review the current documents on this form before continuing.')]);
        }
    }

    public function forget(Request $request, string $context): void
    {
        $request->session()->forget('legal.presented.'.$context);
    }
}
