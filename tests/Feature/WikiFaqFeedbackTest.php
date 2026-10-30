<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Content\WikiRenderer;
use App\Models\WikiArticle;
use App\Models\WikiCategory;
use App\Models\WikiFeedback;
use App\Models\WikiRevision;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WikiFaqFeedbackTest extends TestCase
{
    private function article(string $slug = 'guide', array $attributes = []): WikiArticle
    {
        $category = WikiCategory::firstOrCreate(['locale' => 'tr', 'slug' => 'guide'], ['name' => 'Rehber', 'is_visible' => true]);
        $rendered = WikiRenderer::render('## Commands'."\n\n/spawn", 'tr');

        return WikiArticle::create(array_replace([
            'wiki_category_id' => $category->id, 'locale' => 'tr', 'title' => 'Guide '.$slug,
            'slug' => $slug, 'body_markdown' => "## Commands\n\n/spawn", 'body_html' => $rendered['html'],
            'table_of_contents' => $rendered['toc'], 'status' => 'published', 'published_at' => now()->subMinute(), 'sort' => 0,
            'faqs' => [['question' => 'How do I get home?', 'answer' => 'Use /spawn.']],
        ], $attributes))->refresh();
    }

    private function editor(): void
    {
        app(Settings::class)->set('security.require_admin_2fa', false);
        $this->actingAs($this->customer('owner'))->withSession(['auth.password_confirmed_at' => time()]);
    }

    private function payload(WikiArticle $article, array $overrides = []): array
    {
        return array_replace($article->only(['wiki_category_id', 'parent_id', 'locale', 'title', 'slug', 'summary', 'body_markdown', 'status', 'sort', 'revision']), $overrides);
    }

    public function test_actual_visible_faq_pairs_use_semantic_markup_without_scripts_or_raw_html(): void
    {
        $article = $this->article(attributes: ['faqs' => [['question' => 'Can <script>alert(1)</script> run?', 'answer' => '</script><img src=x onerror=alert(1)>'."\nUse /spawn."]]]);
        $this->get('/wiki/guide')->assertOk()->assertSee('itemtype="https://schema.org/FAQPage"', false)->assertSee('itemprop="acceptedAnswer"', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<img src=x', false)->assertDontSee('<script>alert(1)', false);
        $article->update(['faqs' => []]);
        $this->get('/wiki/guide')->assertOk()->assertDontSee('https://schema.org/FAQPage')->assertDontSee('knowledge-faq"', false);
    }

    public function test_member_only_category_or_ancestor_disables_public_faq_markup_even_for_allowed_readers(): void
    {
        $root = $this->article('root', ['reader_access' => 'members']);
        $child = $this->article('child', ['parent_id' => $root->id]);
        $this->get('/wiki/child')->assertNotFound()->assertDontSee('Use /spawn.');
        $this->actingAs($this->customer())->get('/wiki/child')->assertOk()->assertSee('How do I get home?')->assertDontSee('schema.org/FAQPage')->assertDontSee('schema.org/Question');
        $root->update(['reader_access' => 'public']);
        $child->category->update(['reader_access' => 'roles', 'reader_roles' => ['member']]);
        $this->get('/wiki/child')->assertOk()->assertDontSee('schema.org/FAQPage');
    }

    public function test_faq_validation_normalizes_blank_rows_and_rejects_half_rows_limits_and_bad_shapes(): void
    {
        $this->editor();
        $article = $this->article();
        foreach ([[['question' => 'Only question', 'answer' => '']], [['question' => '', 'answer' => 'Only answer']], [['question' => str_repeat('x', 161), 'answer' => 'yes']], [['question' => 'Q', 'answer' => str_repeat('x', 2001)]], array_fill(0, 11, ['question' => 'Q', 'answer' => 'A']), ['bad' => ['question' => 'Q', 'answer' => 'A']], [['question' => 'Q', 'answer' => 'A', 'html' => 'bad']]] as $faqs) {
            $this->putJson('/admin/wiki/articles/'.$article->id, $this->payload($article, ['faqs' => $faqs]))->assertUnprocessable();
        }
        $this->assertSame(1, $article->fresh()->revision);
        $this->put('/admin/wiki/articles/'.$article->id, $this->payload($article, ['faqs' => [['question' => ' ', 'answer' => ' '], ['question' => ' New question ', 'answer' => ' New answer ']]]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([['question' => 'New question', 'answer' => 'New answer']], $article->fresh()->faqs);
    }

    public function test_faq_snapshots_preview_and_reviewed_restore_preserve_current_acl_and_old_content(): void
    {
        $this->editor();
        $article = $this->article(attributes: ['reader_access' => 'members']);
        $old = $article->faqs;
        $this->put('/admin/wiki/articles/'.$article->id, $this->payload($article, ['faqs' => [['question' => 'New?', 'answer' => 'New answer.']]]))->assertRedirect()->assertSessionHasNoErrors();
        $snapshot = WikiRevision::where('wiki_article_id', $article->id)->where('revision', 1)->sole();
        $this->assertSame($old, $snapshot->faqs);
        $this->get('/admin/wiki/articles/'.$article->id.'/history/'.$snapshot->id)->assertOk()->assertSee('How do I get home?')->assertDontSee('schema.org/FAQPage');
        $this->get('/admin/wiki/articles/'.$article->id.'/edit?from_revision=1')->assertOk()->assertViewHas('fields', fn ($fields) => $fields['faqs']['value'] === $old && $fields['reader_access']['value'] === 'members');
        $this->put('/admin/wiki/articles/'.$article->id, $this->payload($article->fresh(), ['status' => 'draft', 'from_revision' => 1, 'faqs' => $old]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($old, $article->fresh()->faqs);
        $this->assertSame('members', $article->fresh()->reader_access);
        $this->assertSame(3, $article->revisions()->count());
    }

    public function test_omitted_legacy_fields_preserve_faqs_but_explicit_empty_removes_them(): void
    {
        $this->editor();
        $article = $this->article();
        $this->put('/admin/wiki/articles/'.$article->id, $this->payload($article, ['title' => 'Changed']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertCount(1, $article->fresh()->faqs);
        $this->put('/admin/wiki/articles/'.$article->id, $this->payload($article->fresh(), ['faqs' => []]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([], $article->fresh()->faqs);
    }

    public function test_feedback_requires_verified_reader_even_when_global_verification_is_optional(): void
    {
        $article = $this->article();
        $data = ['revision' => $article->revision, 'helpful' => 1];
        $this->post('/wiki/guide/feedback', $data)->assertRedirect(route('login'));
        app(Settings::class)->set('auth.require_email_verification', false);
        $user = $this->customer();
        $user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($user)->post('/wiki/guide/feedback', $data)->assertRedirect(route('verification.notice'));
        $this->assertSame(0, WikiFeedback::count());
    }

    public function test_feedback_uses_actual_actor_deduplicates_and_changes_only_current_revision_vote(): void
    {
        $article = $this->article();
        $user = $this->customer();
        $other = $this->customer();
        $this->actingAs($user);
        $data = ['revision' => 1, 'helpful' => 1, 'user_id' => $other->id];
        $this->post('/wiki/guide/feedback', $data)->assertRedirect(route('wiki.show', 'guide'))->assertSessionHasNoErrors();
        $this->post('/wiki/guide/feedback', $data)->assertRedirect();
        $this->post('/wiki/guide/feedback', array_replace($data, ['helpful' => 0]))->assertRedirect();
        $vote = WikiFeedback::sole();
        $this->assertSame($user->id, $vote->user_id);
        $this->assertFalse($vote->helpful);
        $this->get('/wiki/guide')->assertOk()->assertViewHas('feedbackVote', false)->assertDontSee($other->name);
        $article->forceFill(['revision' => 2])->save();
        $this->postJson('/wiki/guide/feedback', $data)->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->get('/wiki/guide')->assertOk()->assertViewHas('feedbackVote', null);
        $this->post('/wiki/guide/feedback', ['revision' => 2, 'helpful' => 1])->assertRedirect();
        $this->assertSame(2, WikiFeedback::count());
    }

    public function test_feedback_rechecks_category_ancestors_role_and_module_visibility(): void
    {
        $root = $this->article('root', ['reader_access' => 'roles', 'reader_roles' => ['support']]);
        $child = $this->article('child', ['parent_id' => $root->id]);
        $user = $this->customer('member');
        $this->actingAs($user);
        $data = ['revision' => 1, 'helpful' => 1];
        $this->post('/wiki/child/feedback', $data)->assertNotFound();
        $user->assignRole('support');
        $this->post('/wiki/child/feedback', $data)->assertRedirect();
        $child->category->update(['is_visible' => false]);
        $this->post('/wiki/child/feedback', $data)->assertNotFound();
        $child->category->update(['is_visible' => true]);
        app(Settings::class)->set('modules.wiki', false);
        $this->post('/wiki/child/feedback', $data)->assertNotFound();
        $this->assertSame(1, WikiFeedback::count());
    }

    public function test_feedback_validation_rate_limit_and_account_deletion_are_bounded(): void
    {
        $article = $this->article();
        $user = $this->customer();
        $this->actingAs($user);
        Cache::flush();
        $this->postJson('/wiki/guide/feedback', ['revision' => 1, 'helpful' => ['yes']])->assertUnprocessable();
        for ($i = 0; $i < 19; $i++) {
            $this->post('/wiki/guide/feedback', ['revision' => 1, 'helpful' => 1])->assertRedirect()->assertHeader('X-RateLimit-Limit', '20')->assertHeader('X-RateLimit-Remaining', (string) (18 - $i));
        }
        $this->post('/wiki/guide/feedback', ['revision' => 1, 'helpful' => 1])->assertTooManyRequests();
        $this->assertSame(1, WikiFeedback::count());
        DB::table('users')->where('id', $user->id)->delete();
        $this->assertSame(0, WikiFeedback::count());
    }

    public function test_staff_summary_is_current_revision_only_and_readers_never_receive_totals(): void
    {
        $article = $this->article();
        $user = $this->customer();
        WikiFeedback::create(['wiki_article_id' => $article->id, 'user_id' => $user->id, 'revision' => 1, 'helpful' => true]);
        WikiFeedback::create(['wiki_article_id' => $article->id, 'user_id' => $user->id, 'revision' => 2, 'helpful' => false]);
        $this->get('/wiki/guide')->assertOk()->assertDontSee('Bu sürümün geri bildirimi')->assertDontSee($user->name);
        $this->editor();
        $this->get('/admin/wiki/articles/'.$article->id.'/history')->assertOk()->assertSee('1 yararlı, 0 henüz değil');
    }
}
