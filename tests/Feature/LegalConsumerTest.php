<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Legal\LegalConsumer;
use App\Domain\Legal\LegalMarkets;
use App\Domain\Legal\LegalPublisher;
use App\Domain\Legal\LegalSubmission;
use App\Models\LegalAcceptance;
use App\Models\LegalMarket;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LegalConsumerTest extends TestCase
{
    private function preparedMarket(): array
    {
        $actor = $this->customer('owner');
        $market = app(LegalMarkets::class)->save(null, $actor, [
            'country' => 'TR', 'locales' => ['tr'], 'trader_name' => 'Synthetic Merchant',
            'trader_address' => 'Example Street, Local QA', 'trader_email' => 'legal@fixture.example.test', 'trader_phone' => '+900000000000',
        ]);
        $documents = [];
        foreach (LegalPublisher::KINDS as $kind) {
            $policy = app(LegalPublisher::class)->saveDraft($market, $actor, ['kind' => $kind, 'locale' => 'tr', 'title' => 'Fixture '.$kind, 'body_markdown' => 'Synthetic local policy; never used as actual consumer terms.']);
            $documents[$kind] = app(LegalPublisher::class)->publish($policy, 1, $actor)->id;
        }
        $market = app(LegalMarkets::class)->enable($market, $actor, 1, true);

        return [$market, $documents, $actor];
    }

    private function submission(array $documents, string $context, ?int $total = null): LegalSubmission
    {
        $required = array_intersect_key($documents, array_flip(LegalConsumer::kinds($context)));

        return LegalSubmission::fromInput(['legal_country' => 'TR', 'legal_locale' => 'tr', 'legal_document_ids' => $required, 'terms' => '1', 'expected_total_minor' => $total, 'expected_review_sha256' => $context === 'registration' ? null : str_repeat('0', 64)], 'tr', $context);
    }

    public function test_only_testing_profile_can_disable_policy_enforcement(): void
    {
        app(Settings::class)->set('legal.enforce', false);
        $this->assertFalse(app(LegalConsumer::class)->enforced());
        app()->instance('env', 'production');
        $this->assertTrue(app(LegalConsumer::class)->enforced());
    }

    public function test_market_cannot_enable_until_all_localized_documents_are_published_and_review_is_explicit(): void
    {
        $actor = $this->customer('owner');
        $market = LegalMarket::create(['country' => 'TR', 'locales' => ['tr'], 'trader' => ['name' => 'Synthetic Merchant']]);
        try {
            app(LegalMarkets::class)->enable($market, $actor, 1, false);
            $this->fail('Review was assumed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('review_confirmation', $exception->errors());
        }
        try {
            app(LegalMarkets::class)->enable($market, $actor, 1, true);
            $this->fail('Missing publications were accepted.');
        } catch (ValidationException) {
            $this->assertFalse($market->fresh()->is_enabled);
        }
    }

    public function test_registration_records_contract_acceptance_and_notice_delivery_as_different_actions(): void
    {
        [$market, $documents] = $this->preparedMarket();
        $buyer = $this->customer();
        $submission = $this->submission($documents, 'registration');
        DB::transaction(fn () => app(LegalConsumer::class)->record($submission, $buyer, 'registration'));
        $this->assertSame(3, LegalAcceptance::count());
        $this->assertSame('accepted', LegalAcceptance::where('legal_document_id', $documents['terms'])->sole()->action);
        $this->assertSame('presented', LegalAcceptance::where('legal_document_id', $documents['privacy'])->sole()->action);
        $this->assertSame('presented', LegalAcceptance::where('legal_document_id', $documents['cookies'])->sole()->action);
        $this->assertSame($buyer->id, LegalAcceptance::firstOrFail()->user_id);
        $this->assertTrue($market->is_enabled);
    }

    public function test_changed_versions_and_changed_merchant_facts_fail_before_any_evidence_is_recorded(): void
    {
        [$market, $documents, $actor] = $this->preparedMarket();
        $submission = $this->submission($documents, 'registration');
        $policy = $market->policies()->where('kind', 'terms')->sole();
        $policy = app(LegalPublisher::class)->saveDraft($market, $actor, ['kind' => 'terms', 'locale' => 'tr', 'title' => 'New fixture terms', 'body_markdown' => 'A different synthetic document.', 'revision' => $policy->revision]);
        app(LegalPublisher::class)->publish($policy, $policy->revision, $actor);
        $buyer = $this->customer();
        try {
            DB::transaction(fn () => app(LegalConsumer::class)->record($submission, $buyer, 'registration'));
            $this->fail('Stale contract was recorded.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('legal_document_ids', $exception->errors());
        }
        $market->update(['trader' => ['name' => 'A different seller']]);
        try {
            app(LegalConsumer::class)->resolve('TR', '', 'tr', 'registration');
            $this->fail('Changed seller facts were silently applied.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('legal_country', $exception->errors());
        }
        $this->assertSame(0, LegalAcceptance::count());
    }

    public function test_order_total_and_actor_are_checked_and_repeated_confirmation_is_idempotent(): void
    {
        [, $documents] = $this->preparedMarket();
        $buyer = $this->customer();
        $order = $this->pendingOrder($buyer, 1999, 'sandbox', 'purchase');
        $wrongPrice = $this->submission($documents, 'checkout', 999);
        try {
            DB::transaction(fn () => app(LegalConsumer::class)->record($wrongPrice, $buyer, 'checkout', $order));
            $this->fail('Changed price was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('expected_total_minor', $exception->errors());
        }
        $submission = $this->submission($documents, 'checkout', 1999);
        DB::transaction(fn () => app(LegalConsumer::class)->record($submission, $buyer, 'checkout', $order));
        DB::transaction(fn () => app(LegalConsumer::class)->record($submission, $buyer, 'checkout', $order));
        $this->assertSame(6, LegalAcceptance::count());
        $this->assertSame(0, LegalAcceptance::where('user_id', '!=', $buyer->id)->count());
    }

    public function test_market_edits_pause_new_agreements_and_published_country_cannot_be_repurposed(): void
    {
        [$market, , $actor] = $this->preparedMarket();
        try {
            app(LegalMarkets::class)->save($market, $actor, ['country' => 'GB', 'locales' => ['tr'], 'revision' => $market->revision]);
            $this->fail('Published documents moved country.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('country', $exception->errors());
        }
        $updated = app(LegalMarkets::class)->save($market, $actor, ['country' => 'TR', 'locales' => ['tr'], 'revision' => $market->revision, 'trader_name' => 'Changed seller']);
        $this->assertFalse($updated->is_enabled);
        $this->assertNull($updated->reviewed_at);
        $this->assertSame($market->revision + 1, $updated->revision);
    }
}
