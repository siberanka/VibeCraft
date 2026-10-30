<?php

namespace App\View;

use App\Domain\Content\WikiRenderer;
use App\Domain\Legal\CountryCatalog;
use App\Models\LegalDocument;

final class LegalView
{
    public static function labels(): array
    {
        return ['terms' => __('Community terms'), 'privacy' => __('Privacy information'), 'cookies' => __('Cookie notice'), 'sales' => __('Terms of sale'), 'precontract' => __('Pre-contract information'), 'withdrawal' => __('Cancellation and withdrawal')];
    }

    public static function document(LegalDocument $document): array
    {
        abort_if($document->published_at === null || $document->published_at->isFuture() || ! hash_equals($document->document_sha256, $document->contentDigest()), 404);

        return ['document' => $document, 'rendered' => WikiRenderer::render($document->body_markdown, $document->locale), 'countryName' => CountryCatalog::options()[$document->country] ?? $document->country];
    }
}
