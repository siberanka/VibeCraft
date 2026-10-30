<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Content\WikiAudience;
use App\Domain\Content\WikiRenderer;
use App\Models\User;
use App\Models\WikiArticle;
use App\Models\WikiCategory;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WikiReaderAccessTest extends TestCase
{
    private function article(string $slug, array $attributes = []): WikiArticle
    {
        $category = WikiCategory::firstOrCreate(['locale' => 'tr', 'slug' => 'guide'], ['name' => 'Rehber', 'is_visible' => true]);
        $rendered = WikiRenderer::render('## Commands'."\n\n/spawn", 'tr');

        return WikiArticle::create(array_replace([
            'wiki_category_id' => $category->id, 'locale' => 'tr', 'title' => 'Guide '.$slug,
            'slug' => $slug, 'body_markdown' => "## Commands\n\n/spawn", 'body_html' => $rendered['html'],
            'table_of_contents' => $rendered['toc'], 'status' => 'published', 'published_at' => now()->subMinute(), 'sort' => 0,
        ], $attributes))->refresh();
    }

    private function editor(): User
    {
        app(Settings::class)->set('security.require_admin_2fa', false);
        $user = $this->customer('owner');
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        return $user;
    }

    private function payload(WikiArticle $article, array $overrides = []): array
    {
        return array_replace($article->only(['wiki_category_id', 'parent_id', 'locale', 'title', 'slug', 'summary', 'body_markdown', 'status', 'sort', 'revision']), $overrides);
    }

    public function test_guests_and_unverified_accounts_cannot_read_member_or_role_pages(): void
    {
        $this->article('public');
        $this->article('members', ['reader_access' => 'members']);
        $this->article('selected', ['reader_access' => 'roles', 'reader_roles' => ['member']]);
        $this->get('/wiki/public')->assertOk();
        foreach (['members', 'selected'] as $slug) {
            $this->get('/wiki/'.$slug.'?reader_access=public&role=member')->assertNotFound()->assertDontSee('Guide '.$slug);
        }
        $user = $this->customer();
        $user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($user);
        $this->get('/wiki/members')->assertNotFound();
        $this->get('/wiki/selected')->assertNotFound();
        $this->get('/wiki')->assertOk()->assertViewHas('articles', fn ($rows) => $rows->pluck('slug')->all() === ['public']);
    }

    public function test_verified_role_matches_are_exact_and_have_no_implicit_owner_bypass(): void
    {
        $this->article('members', ['reader_access' => 'members']);
        $article = $this->article('selected', ['reader_access' => 'roles', 'reader_roles' => ['moderator', 'support']]);
        $owner = $this->editor();
        $this->get('/wiki/members')->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->get('/wiki/selected')->assertNotFound();
        $this->get('/admin/wiki/articles/'.$article->id.'/edit')->assertOk();
        $owner->assignRole('support');
        $this->get('/wiki/selected')->assertOk();
        $owner->removeRole('support');
        $this->get('/wiki/selected')->assertNotFound();
        $this->actingAs($this->customer('moderator'));
        $this->get('/wiki/selected')->assertOk();
    }

    public function test_category_and_each_ancestor_require_their_own_matching_role(): void
    {
        $root = $this->article('root', ['reader_access' => 'roles', 'reader_roles' => ['support']]);
        $child = $this->article('child', ['parent_id' => $root->id, 'reader_access' => 'roles', 'reader_roles' => ['moderator']]);
        $leaf = $this->article('leaf', ['parent_id' => $child->id]);
        $root->category->update(['reader_access' => 'roles', 'reader_roles' => ['member']]);
        $user = $this->customer('member');
        $this->actingAs($user);
        $this->get('/wiki/leaf')->assertNotFound();
        $user->assignRole('moderator');
        $this->get('/wiki/leaf')->assertNotFound();
        $user->assignRole('support');
        $this->get('/wiki/leaf')->assertOk()->assertViewHas('ancestors', fn ($rows) => count($rows) === 2);
        $user->removeRole('member');
        $this->get('/wiki/leaf')->assertNotFound();
        $this->assertFalse(WikiArticle::published()->whereKey($leaf->id)->exists());
    }

    public function test_hidden_reader_content_is_absent_from_search_counts_navigation_related_and_subpages(): void
    {
        $public = $this->article('public');
        $this->article('private-child', ['parent_id' => $public->id, 'title' => 'SecretChildTitle', 'reader_access' => 'members']);
        $category = WikiCategory::create(['locale' => 'tr', 'slug' => 'private', 'name' => 'SecretCategoryName', 'description' => 'SecretCategoryDescription', 'is_visible' => true, 'reader_access' => 'members']);
        $this->article('private-category', ['wiki_category_id' => $category->id, 'title' => 'SecretCategoryArticle']);
        $this->get('/wiki')->assertOk()->assertDontSee('Secret')->assertViewHas('categories', fn ($rows) => $rows->count() === 1 && $rows->sole()->articles_count === 1);
        $this->get('/wiki?q=Secret')->assertOk()->assertDontSee('SecretChildTitle')->assertViewHas('articles', fn ($rows) => $rows->isEmpty());
        $this->get('/wiki?category=private')->assertNotFound()->assertDontSee('SecretCategory');
        $this->get('/wiki/public')->assertOk()->assertDontSee('Secret')->assertViewHas('childArticles', fn ($rows) => $rows->isEmpty())->assertViewHas('relatedArticles', fn ($rows) => $rows->isEmpty());
        $this->actingAs($this->customer());
        $this->get('/wiki')->assertOk()->assertSee('SecretCategoryName')->assertViewHas('articles', fn ($rows) => $rows->total() === 3);
        $this->get('/wiki/public')->assertOk()->assertViewHas('childArticles', fn ($rows) => $rows->count() === 1);
    }

    public function test_default_scope_and_banned_or_unverified_audiences_fail_closed(): void
    {
        $this->article('public');
        $this->article('private', ['reader_access' => 'members']);
        $this->article('unknown', ['reader_access' => 'invalid']);
        $this->article('empty', ['reader_access' => 'roles', 'reader_roles' => []]);
        $user = $this->customer();
        $this->assertSame(['private', 'public'], WikiArticle::published(null, WikiAudience::forUser($user))->orderBy('slug')->pluck('slug')->all());
        $user->forceFill(['banned_at' => now()])->save();
        $this->assertSame(['public'], WikiArticle::published(null, WikiAudience::forUser($user))->pluck('slug')->all());
        $this->assertSame(['public'], WikiArticle::published()->pluck('slug')->all());
    }

    public function test_role_validation_rejects_unknown_wrong_guard_duplicate_and_empty_selections(): void
    {
        $this->editor();
        $article = $this->article('guide');
        Role::create(['name' => 'api-only', 'guard_name' => 'api']);
        foreach ([[], ['missing'], ['api-only'], ['member', 'member'], [['member']]] as $roles) {
            $this->putJson('/admin/wiki/articles/'.$article->id, $this->payload($article, ['reader_access' => 'roles', 'reader_roles' => $roles]))->assertUnprocessable();
        }
        $this->putJson('/admin/wiki/articles/'.$article->id, $this->payload($article, ['reader_access' => 'bad']))->assertUnprocessable()->assertJsonValidationErrors('reader_access');
        $this->assertSame(1, $article->fresh()->revision);
        $this->assertSame('public', $article->fresh()->reader_access);
        $this->assertSame(0, $article->revisions()->count());
    }

    public function test_imported_non_web_role_membership_does_not_grant_page_access(): void
    {
        $role = Role::create(['name' => 'external-role', 'guard_name' => 'api']);
        $user = $this->customer();
        $user->roles()->attach($role->id);
        $article = $this->article('external', ['reader_access' => 'roles', 'reader_roles' => ['external-role']]);
        $this->actingAs($user)->get('/wiki/external')->assertNotFound();
        $this->assertFalse(WikiArticle::published(null, WikiAudience::forUser($user))->whereKey($article->id)->exists());
    }

    public function test_failed_empty_role_submission_does_not_reselect_previous_roles(): void
    {
        $this->editor();
        $article = $this->article('guide', ['reader_access' => 'roles', 'reader_roles' => ['member']]);
        $this->from('/admin/wiki/articles/'.$article->id.'/edit')->put('/admin/wiki/articles/'.$article->id, $this->payload($article, ['reader_access' => 'roles']))->assertRedirect()->assertSessionHasErrors('reader_roles');
        $response = $this->get('/admin/wiki/articles/'.$article->id.'/edit')->assertOk();
        $html = $response->getContent();
        $this->assertIsString($html);
        preg_match('/<input[^>]+name="reader_roles\[\]"[^>]+value="member"[^>]*>/', $html, $matched);
        $this->assertNotEmpty($matched);
        $this->assertStringNotContainsString('checked', $matched[0]);
        $this->assertSame(['member'], $article->fresh()->reader_roles);
    }

    public function test_native_role_form_saves_canonical_rules_and_rejects_stale_policy_updates(): void
    {
        $this->editor();
        $article = $this->article('guide');
        $this->get('/admin/wiki/articles/'.$article->id.'/edit')->assertOk()->assertSee('name="reader_roles[]"', false)->assertSee('<fieldset', false);
        $this->get('/admin/wiki/categories/'.$article->wiki_category_id.'/edit')->assertOk()->assertViewHas('fields', fn ($fields) => $fields['reader_roles']['type'] === 'checkboxes');
        $payload = $this->payload($article, ['reader_access' => 'roles', 'reader_roles' => ['support', 'member']]);
        $this->put('/admin/wiki/articles/'.$article->id, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(['member', 'support'], $article->fresh()->reader_roles);
        $this->putJson('/admin/wiki/articles/'.$article->id, array_replace($payload, ['reader_access' => 'public']))->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->assertSame('roles', $article->fresh()->reader_access);
        $this->assertSame(2, $article->revisions()->count());
    }

    public function test_legacy_omitted_fields_and_reviewed_restore_preserve_current_access(): void
    {
        $this->editor();
        $article = $this->article('guide', ['reader_access' => 'roles', 'reader_roles' => ['member']]);
        $this->put('/admin/wiki/articles/'.$article->id, $this->payload($article, ['title' => 'Updated']))->assertRedirect()->assertSessionHasNoErrors();
        $article->refresh();
        $this->assertSame('roles', $article->reader_access);
        $this->assertSame(['member'], $article->reader_roles);
        $this->get('/admin/wiki/articles/'.$article->id.'/edit?from_revision=1')->assertOk()->assertViewHas('fields', fn ($fields) => $fields['reader_access']['value'] === 'roles' && $fields['reader_roles']['value'] === ['member'] && $fields['status']['value'] === 'draft');
        $this->put('/admin/wiki/articles/'.$article->id, $this->payload($article, ['status' => 'draft', 'published_at' => null, 'from_revision' => 1]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('roles', $article->fresh()->reader_access);
        $this->assertSame(['member'], $article->fresh()->reader_roles);
    }

    public function test_category_rules_save_with_revision_protection_and_inactive_roles_are_cleared(): void
    {
        $this->editor();
        $category = $this->article('guide')->category;
        $payload = $category->only(['name', 'slug', 'locale', 'description', 'sort', 'is_visible', 'revision']) + ['reader_access' => 'roles', 'reader_roles' => ['member']];
        $this->put('/admin/wiki/categories/'.$category->id, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('roles', $category->fresh()->reader_access);
        $this->putJson('/admin/wiki/categories/'.$category->id, array_replace($payload, ['reader_access' => 'public']))->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->put('/admin/wiki/categories/'.$category->id, array_replace($payload, ['revision' => 2, 'reader_access' => 'members']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([], $category->fresh()->reader_roles);
        $this->get('/admin/wiki/categories')->assertOk();
    }
}
