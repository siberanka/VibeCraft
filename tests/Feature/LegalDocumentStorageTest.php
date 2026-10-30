<?php

namespace Tests\Feature;

use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\LegalMarket;
use App\Models\LegalPolicy;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class LegalDocumentStorageTest extends TestCase
{
    private function document(): LegalDocument
    {
        $market = LegalMarket::create(['country' => 'TR', 'trader' => ['name' => 'Fixture Trader', 'address' => 'Example Street, Local QA', 'email' => 'contact@fixture.example.test', 'phone' => '+900000000000'], 'locales' => ['tr', 'en']]);
        $policy = LegalPolicy::create(['legal_market_id' => $market->id, 'kind' => 'terms', 'locale' => 'tr', 'title' => 'Fixture terms', 'body_markdown' => 'Synthetic document; never a merchant policy.']);

        return LegalDocument::create(['legal_policy_id' => $policy->id, 'version' => 1, 'country' => 'TR', 'kind' => 'terms', 'locale' => 'tr', 'title' => $policy->title, 'body_markdown' => $policy->body_markdown, 'trader_snapshot' => $market->trader, 'published_at' => now()])->refresh();
    }

    public function test_published_content_and_trader_identity_remain_stable_after_draft_changes(): void
    {
        $document = $this->document();
        $policy = $document->policy;
        $policy->forceFill(['current_document_id' => $document->id])->save();
        $digest = $document->document_sha256;
        $policy->update(['body_markdown' => 'New draft not published.']);
        $policy->market->update(['trader' => ['name' => 'Different trader']]);
        $this->assertSame('Synthetic document; never a merchant policy.', $document->fresh()->body_markdown);
        $this->assertSame('Fixture Trader', $document->fresh()->trader_snapshot['name']);
        $this->assertSame($digest, $document->fresh()->contentDigest());
        $this->assertFalse($policy->market->fresh()->is_enabled);
    }

    public function test_model_operations_cannot_replace_published_content_or_erase_evidence(): void
    {
        $document = $this->document();
        foreach (['update', 'delete'] as $operation) {
            try {
                $operation === 'update' ? $document->update(['title' => 'Changed']) : $document->delete();
                $this->fail('Published document operation must be rejected.');
            } catch (LogicException) {
                $this->assertSame('Fixture terms', $document->fresh()->title);
            }
        }
        $acceptance = LegalAcceptance::create(['legal_document_id' => $document->id, 'user_id' => $this->customer()->id, 'context' => 'registration', 'action' => 'accepted', 'recorded_at' => now()]);
        $this->assertSame($document->document_sha256, $acceptance->document_sha256);
        $this->expectException(LogicException::class);
        $acceptance->delete();
    }

    public function test_digest_survives_database_json_key_order_and_includes_actual_scope(): void
    {
        $document = $this->document();
        $digest = $document->document_sha256;
        $document->trader_snapshot = array_reverse($document->trader_snapshot, true);
        $this->assertSame($digest, $document->contentDigest());
        $document->country = 'GB';
        $this->assertNotSame($digest, $document->contentDigest());
        $this->assertSame($digest, $document->fresh()->contentDigest());
    }

    public function test_evidence_rejects_imported_content_that_no_longer_matches_its_digest(): void
    {
        $document = $this->document();
        DB::table('legal_documents')->where('id', $document->id)->update(['body_markdown' => 'Inconsistent imported content']);
        $this->expectException(LogicException::class);
        LegalAcceptance::create(['legal_document_id' => $document->id, 'user_id' => $this->customer()->id, 'context' => 'registration', 'action' => 'presented', 'recorded_at' => now()]);
    }

    public function test_account_deletion_removes_actor_link_without_claiming_notice_was_consent(): void
    {
        $document = $this->document();
        $user = $this->customer();
        $acceptance = LegalAcceptance::create(['legal_document_id' => $document->id, 'user_id' => $user->id, 'context' => 'registration', 'action' => 'presented', 'recorded_at' => now()]);
        DB::table('users')->where('id', $user->id)->delete();
        $this->assertNull($acceptance->fresh()->user_id);
        $this->assertSame('presented', $acceptance->fresh()->action);
        $this->assertSame($document->document_sha256, $acceptance->fresh()->document_sha256);
    }
}
