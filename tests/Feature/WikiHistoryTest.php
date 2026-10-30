<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Content\WikiRenderer;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WikiArticle;
use App\Models\WikiCategory;
use App\Models\WikiRevision;
use LogicException;
use Tests\TestCase;

class WikiHistoryTest extends TestCase
{
    private function editor(): User
    {
        app(Settings::class)->set('security.require_admin_2fa', false);
        $user = $this->customer('owner');
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        return $user;
    }

    private function article(string $slug = 'guide'): WikiArticle
    {
        $category = WikiCategory::firstOrCreate(['locale' => 'tr', 'slug' => 'start'], ['name' => 'Başlangıç', 'sort' => 0, 'is_visible' => true]);
        $markdown = "## Birinci adım\n\nÖzgün rehber metni.";
        $rendered = WikiRenderer::render($markdown, 'tr');

        return WikiArticle::create([
            'wiki_category_id' => $category->id, 'locale' => 'tr', 'title' => 'İlk rehber', 'slug' => $slug,
            'summary' => 'İlk özet', 'body_markdown' => $markdown, 'body_html' => $rendered['html'],
            'table_of_contents' => $rendered['toc'], 'status' => 'published', 'published_at' => now()->subMinute(), 'sort' => 0,
        ])->refresh();
    }

    private function payload(WikiArticle $article, array $overrides = []): array
    {
        return array_replace($article->only(['wiki_category_id', 'locale', 'title', 'slug', 'summary', 'body_markdown', 'status', 'sort', 'revision']), $overrides);
    }

    private function snapshot(WikiArticle $article, int $number, array $overrides = []): WikiRevision
    {
        return WikiRevision::create(array_replace([
            'wiki_article_id' => $article->id, 'revision' => $number, 'locale' => $article->locale,
            'title' => $article->title, 'summary' => $article->summary,
            'body_markdown' => $article->body_markdown, 'origin' => 'baseline',
        ], $overrides));
    }

    public function test_first_editor_save_and_later_edits_append_actor_attributed_snapshots(): void
    {
        $editor = $this->editor();
        $fixture = $this->article();
        $this->post('/admin/wiki/articles', $this->payload($fixture, ['slug' => 'created-guide']))->assertRedirect()->assertSessionHasNoErrors();
        $article = WikiArticle::where('slug', 'created-guide')->sole();
        $first = $article->revisions()->sole();
        $this->assertSame(1, $first->revision);
        $this->assertSame($editor->id, $first->editor_id);
        $this->assertSame('edit', $first->origin);
        $this->put('/admin/wiki/articles/'.$article->id, $this->payload($article, ['title' => 'İkinci rehber', 'body_markdown' => 'Yeni metin']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('İlk rehber', $first->fresh()->title);
        $this->assertSame('İkinci rehber', $article->revisions()->where('revision', 2)->sole()->title);
        $this->assertSame(2, $article->revisions()->count());
    }

    public function test_missing_legacy_current_content_is_captured_as_an_unknown_editor_baseline(): void
    {
        $this->editor();
        $article = $this->article();
        $this->put('/admin/wiki/articles/'.$article->id, $this->payload($article, ['title' => 'Saved edit']))->assertRedirect()->assertSessionHasNoErrors();
        $first = $article->revisions()->where('revision', 1)->sole();
        $this->assertSame('baseline', $first->origin);
        $this->assertNull($first->editor_id);
        $this->assertSame('İlk rehber', $first->title);
        $this->assertSame(2, $article->revisions()->count());
    }

    public function test_markdown_indentation_and_trailing_newlines_survive_editor_and_history(): void
    {
        $this->editor();
        $article = $this->article();
        $source = "    /spawn\n    /help\n\n";
        $this->put('/admin/wiki/articles/'.$article->id, $this->payload($article, ['body_markdown' => $source]))->assertRedirect()->assertSessionHasNoErrors();
        $saved = $article->fresh();
        $this->assertSame($source, $saved->body_markdown);
        $this->assertSame($source, $saved->revisions()->where('revision', 2)->sole()->body_markdown);
        $this->assertStringContainsString('<pre><code>/spawn', $saved->body_html);
    }

    public function test_reviewed_restore_is_read_only_until_save_and_creates_a_new_draft_revision(): void
    {
        $this->editor();
        $article = $this->article();
        $first = $this->snapshot($article, 1);
        $this->put('/admin/wiki/articles/'.$article->id, $this->payload($article, ['title' => 'Current title', 'slug' => 'current-location', 'body_markdown' => 'Current text']))->assertRedirect();
        $article->refresh();
        $this->get('/admin/wiki/articles/'.$article->id.'/edit?from_revision=1')->assertOk()
            ->assertViewHas('fields', fn ($fields) => $fields['title']['value'] === $first->title
                && $fields['status']['value'] === 'draft' && $fields['slug']['value'] === 'current-location'
                && $fields['published_at']['value'] === null)
            ->assertSee('name="revision" value="2"', false)->assertSee('name="from_revision" value="1"', false)
            ->assertSee('name="published_at" type="datetime-local" value=""', false);
        $this->assertSame('Current title', $article->fresh()->title);
        $this->assertSame(2, $article->revisions()->count());
        $this->put('/admin/wiki/articles/'.$article->id, $this->payload($article, [
            'title' => $first->title, 'summary' => $first->summary, 'body_markdown' => $first->body_markdown,
            'status' => 'draft', 'published_at' => null, 'from_revision' => 1,
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $restored = $article->fresh();
        $this->assertSame(3, $restored->revision);
        $this->assertSame('current-location', $restored->slug);
        $this->assertSame($first->body_markdown, $restored->body_markdown);
        $this->assertSame('draft', $restored->status);
        $this->assertNull($restored->published_at);
        $this->assertSame(1, $restored->revisions()->where('revision', 3)->sole()->based_on_revision);
        $this->assertSame(3, $restored->revisions()->count());
        $this->get('/wiki/current-location')->assertNotFound();
    }

    public function test_history_and_restore_references_are_scoped_to_the_article(): void
    {
        $this->editor();
        $article = $this->article();
        $other = $this->article('other');
        $foreign = $this->snapshot($other, 9);
        $this->get('/admin/wiki/articles/'.$article->id.'/history/'.$foreign->id)->assertNotFound();
        $this->get('/admin/wiki/articles/'.$article->id.'/edit?from_revision=9')->assertNotFound();
        $this->putJson('/admin/wiki/articles/'.$article->id, $this->payload($article, ['from_revision' => 9]))->assertUnprocessable()->assertJsonValidationErrors('from_revision');
        $this->assertSame(1, $article->fresh()->revision);
        $this->assertSame(0, $article->revisions()->count());
    }

    public function test_history_requires_content_permission_and_current_staff_authentication(): void
    {
        $article = $this->article();
        $revision = $this->snapshot($article, 1);
        $path = '/admin/wiki/articles/'.$article->id.'/history';
        $this->get($path)->assertRedirect(route('login'));
        app(Settings::class)->set('security.require_admin_2fa', false);
        $this->actingAs($this->customer('support'))->withSession(['auth.password_confirmed_at' => time()]);
        $this->get($path)->assertForbidden();
        $this->get($path.'/'.$revision->id)->assertForbidden();
        $this->get('/admin/wiki/articles/'.$article->id.'/edit?from_revision=1')->assertForbidden();
        app(Settings::class)->set('security.require_admin_2fa', true);
        $this->actingAs($this->customer('owner'))->get($path)->assertRedirect(route('account.security'));
    }

    public function test_stale_restore_never_appends_history_and_html_errors_keep_the_expected_revision_until_reload(): void
    {
        $this->editor();
        $article = $this->article();
        $this->snapshot($article, 1);
        $stale = $this->payload($article, ['title' => 'Stale title', 'from_revision' => 1, 'status' => 'draft']);
        $this->put('/admin/wiki/articles/'.$article->id, $this->payload($article, ['title' => 'Concurrent title']))->assertRedirect();
        $edit = '/admin/wiki/articles/'.$article->id.'/edit?from_revision=1';
        $this->from($edit)->put('/admin/wiki/articles/'.$article->id, $stale)->assertSessionHasErrors('revision');
        $this->get($edit)->assertOk()->assertSee('name="revision" value="1"', false);
        $this->get($edit)->assertOk()->assertSee('name="revision" value="2"', false);
        $this->assertSame('Concurrent title', $article->fresh()->title);
        $this->assertSame(2, $article->revisions()->count());
    }

    public function test_history_insert_failure_rolls_back_the_article_baseline_and_audit(): void
    {
        $this->editor();
        $article = $this->article();
        $this->snapshot($article, 2, ['title' => 'Reserved failure fixture']);
        $auditCount = AuditLog::count();
        $this->put('/admin/wiki/articles/'.$article->id, $this->payload($article, ['title' => 'Must roll back']))->assertServerError();
        $this->assertSame('İlk rehber', $article->fresh()->title);
        $this->assertSame(1, $article->fresh()->revision);
        $this->assertSame([2], $article->revisions()->pluck('revision')->all());
        $this->assertSame($auditCount, AuditLog::count());
    }

    public function test_saved_revision_content_rejects_model_mutation_and_preview_is_sanitized(): void
    {
        $this->editor();
        $article = $this->article();
        $revision = $this->snapshot($article, 1, ['body_markdown' => "# Saved title\n\n<script>badHistory()</script>\n\n[unsafe](javascript:alert(1))"]);
        foreach (['update', 'delete'] as $action) {
            try {
                $action === 'update' ? $revision->update(['title' => 'Tampered']) : $revision->delete();
                $this->fail('Revision mutation must be refused');
            } catch (LogicException) {
                $this->assertSame('İlk rehber', $revision->fresh()->title);
            }
            $revision->refresh();
        }
        $this->get('/admin/wiki/articles/'.$article->id.'/history/'.$revision->id)->assertOk()
            ->assertDontSee('<script>badHistory()', false)->assertDontSee('href="javascript:', false)
            ->assertSee('id="section-saved-title"', false);
    }

    public function test_history_paginates_and_can_render_an_empty_baseline_state(): void
    {
        $this->editor();
        $article = $this->article();
        $path = '/admin/wiki/articles/'.$article->id.'/history';
        $this->get($path)->assertOk()->assertViewHas('revisions', fn ($rows) => $rows->isEmpty());
        foreach (range(1, 21) as $number) {
            $this->snapshot($article, $number);
        }
        $this->get($path)->assertOk()->assertViewHas('revisions', fn ($rows) => $rows->count() === 20
            && $rows->total() === 21 && $rows->first()->revision === 21)->assertSee('page=2', false);
    }

    public function test_saved_content_declares_its_language_separately_from_the_staff_interface(): void
    {
        $this->editor();
        $category = WikiCategory::create(['locale' => 'en', 'slug' => 'english', 'name' => 'English', 'is_visible' => true]);
        $article = $this->article();
        $this->put('/admin/wiki/articles/'.$article->id, $this->payload($article, ['locale' => 'en', 'wiki_category_id' => $category->id, 'title' => 'English guide', 'body_markdown' => '## Getting started']))->assertRedirect()->assertSessionHasNoErrors();
        $revision = $article->revisions()->where('revision', 2)->sole();
        $this->get('/admin/wiki/articles/'.$article->id.'/history/'.$revision->id)->assertOk()
            ->assertSee('<html lang="tr"', false)->assertSee('class="prose knowledge-prose" lang="en"', false);
        $this->get('/admin/wiki/articles/'.$article->id.'/history')->assertOk()->assertSee('lang="en" href=', false);
    }
}
