<?php

namespace Tests\Feature;

use App\Domain\Legal\LegalPublisher;
use App\Models\AuditLog;
use App\Models\LegalDocument;
use App\Models\LegalMarket;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LegalPublishingTest extends TestCase
{
    private function market(): LegalMarket
    {
        return LegalMarket::create(['country' => 'TR', 'locales' => ['tr', 'en'], 'trader' => [
            'name' => 'Fixture Merchant', 'address' => 'Example Street, Local QA',
            'email' => 'legal@fixture.example.test', 'phone' => '+900000000000',
        ]]);
    }

    private function draft(array $overrides = []): array
    {
        return array_replace(['kind' => 'terms', 'locale' => 'tr', 'title' => 'Synthetic terms', 'body_markdown' => 'Only a local test document, never published by an operator.'], $overrides);
    }

    public function test_drafts_do_not_publish_and_versions_keep_historical_content_and_trader_snapshot(): void
    {
        $publisher = app(LegalPublisher::class);
        $actor = $this->customer('owner');
        $market = $this->market();
        $policy = $publisher->saveDraft($market, $actor, $this->draft());
        $this->assertNull($policy->current_document_id);
        $this->assertSame(0, LegalDocument::count());
        $first = $publisher->publish($policy, 1, $actor);
        $this->assertSame(1, $first->version);
        $this->assertSame($first->id, $policy->fresh()->current_document_id);
        $changed = $publisher->saveDraft($market, $actor, $this->draft(['revision' => 2, 'body_markdown' => 'A reviewed second local test revision.']));
        $this->assertSame($first->id, $changed->current_document_id);
        $second = $publisher->publish($changed, 3, $actor);
        $this->assertSame(2, $second->version);
        $this->assertSame($second->id, $changed->fresh()->current_document_id);
        $this->assertSame($this->draft()['body_markdown'], $first->fresh()->body_markdown);
        $this->assertSame($first->document_sha256, $first->fresh()->contentDigest());
        $meta = AuditLog::where('action', 'legal.document.published')->latest('id')->firstOrFail()->getAttribute('meta');
        $this->assertEqualsCanonicalizing(['version', 'sha256'], array_keys($meta));
        $this->assertFalse($market->fresh()->is_enabled);
    }

    public function test_stale_editor_cannot_overwrite_or_publish_a_newer_draft(): void
    {
        $publisher = app(LegalPublisher::class);
        $actor = $this->customer('owner');
        $market = $this->market();
        $old = $publisher->saveDraft($market, $actor, $this->draft());
        $publisher->saveDraft($market, $actor, $this->draft(['revision' => 1, 'title' => 'Changed draft']));
        try {
            $publisher->saveDraft($market, $actor, $this->draft(['revision' => 1]));
            $this->fail('Stale draft overwrite accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('revision', $exception->errors());
        }
        try {
            $publisher->publish($old, 1, $actor);
            $this->fail('Stale publication accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('revision', $exception->errors());
        }
        $this->assertSame('Changed draft', $old->fresh()->title);
        $this->assertSame(0, LegalDocument::count());
    }

    public function test_placeholder_or_incomplete_trader_cannot_become_published_contract(): void
    {
        $publisher = app(LegalPublisher::class);
        $actor = $this->customer('owner');
        $market = $this->market();
        $policy = $publisher->saveDraft($market, $actor, $this->draft(['body_markdown' => 'Seller: {{ business_name }}']));
        try {
            $publisher->publish($policy, 1, $actor);
            $this->fail('Placeholder contract published.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('body_markdown', $exception->errors());
        }
        $market->update(['trader' => ['name' => 'Fixture Merchant']]);
        $policy = $publisher->saveDraft($market, $actor, $this->draft(['revision' => 1]));
        try {
            $publisher->publish($policy, 2, $actor);
            $this->fail('Incomplete seller published.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('trader.address', $exception->errors());
        }
        $this->assertSame(0, LegalDocument::count());
    }

    public function test_content_moderator_cannot_publish_legal_terms_with_content_permission(): void
    {
        $publisher = app(LegalPublisher::class);
        $this->expectException(HttpException::class);
        $publisher->saveDraft($this->market(), $this->customer('moderator'), $this->draft());
    }

    public function test_unchanged_publication_does_not_force_a_new_consumer_agreement(): void
    {
        $publisher = app(LegalPublisher::class);
        $actor = $this->customer('owner');
        $market = $this->market();
        $policy = $publisher->saveDraft($market, $actor, $this->draft());
        $first = $publisher->publish($policy, 1, $actor);
        $again = $publisher->publish($policy->fresh(), 2, $actor);
        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, LegalDocument::count());
        $this->assertSame(2, $policy->fresh()->revision);
    }

    public function test_unrenderable_draft_cannot_become_a_published_document(): void
    {
        $publisher = app(LegalPublisher::class);
        $actor = $this->customer('owner');
        $policy = $publisher->saveDraft($this->market(), $actor, $this->draft(['body_markdown' => str_repeat("## Section\n\nText\n\n", 201)]));
        try {
            $publisher->publish($policy, 1, $actor);
            $this->fail('A draft that cannot be displayed was published.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('body_markdown', $exception->errors());
        }
        $this->assertSame(0, LegalDocument::count());
        $this->assertNull($policy->fresh()->current_document_id);
    }

    public function test_document_insert_failure_keeps_the_previous_publication_and_the_reviewable_draft(): void
    {
        $publisher = app(LegalPublisher::class);
        $actor = $this->customer('owner');
        $market = $this->market();
        $policy = $publisher->saveDraft($market, $actor, $this->draft());
        $first = $publisher->publish($policy, 1, $actor);
        $policy = $publisher->saveDraft($market, $actor, $this->draft(['revision' => 2, 'body_markdown' => 'New draft retained for retry.']));
        Event::listen('eloquent.creating: '.LegalDocument::class, function (LegalDocument $document): void {
            $document->version = 1;
        });
        try {
            $publisher->publish($policy, 3, $actor);
            $this->fail('Duplicate stored version accepted.');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame($first->id, $policy->fresh()->current_document_id);
            $this->assertSame(3, $policy->fresh()->revision);
            $this->assertSame('New draft retained for retry.', $policy->fresh()->body_markdown);
            $this->assertSame(1, LegalDocument::count());
        }
    }
}
