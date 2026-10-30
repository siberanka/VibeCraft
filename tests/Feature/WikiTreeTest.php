<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Content\WikiRenderer;
use App\Models\WikiArticle;
use App\Models\WikiCategory;
use Tests\TestCase;

class WikiTreeTest extends TestCase
{
    private function editor(): void
    {
        app(Settings::class)->set('security.require_admin_2fa', false);
        $this->actingAs($this->customer('owner'))->withSession(['auth.password_confirmed_at' => time()]);
    }

    private function article(string $slug, array $attributes = []): WikiArticle
    {
        $category = WikiCategory::firstOrCreate(['locale' => 'tr', 'slug' => 'guide'], ['name' => 'Rehber', 'is_visible' => true]);
        $body = WikiRenderer::render('## Commands'."\n\n".'/spawn', 'tr');

        return WikiArticle::create(array_replace([
            'wiki_category_id' => $category->id, 'locale' => 'tr', 'title' => 'Guide '.$slug,
            'slug' => $slug, 'body_markdown' => "## Commands\n\n/spawn", 'body_html' => $body['html'],
            'table_of_contents' => $body['toc'], 'status' => 'published', 'published_at' => now()->subMinute(), 'sort' => 0,
        ], $attributes))->refresh();
    }

    private function payload(WikiArticle $article, array $overrides = []): array
    {
        return array_replace($article->only(['wiki_category_id', 'parent_id', 'locale', 'title', 'slug', 'summary', 'body_markdown', 'status', 'sort', 'revision']), $overrides);
    }

    public function test_three_levels_have_visible_breadcrumbs_subpages_and_index_context_without_url_changes(): void
    {
        $root = $this->article('root');
        $child = $this->article('child', ['parent_id' => $root->id]);
        $leaf = $this->article('leaf', ['parent_id' => $child->id]);
        $this->get('/wiki/root')->assertOk()->assertViewHas('childArticles', fn ($rows) => $rows->pluck('id')->all() === [$child->id]);
        $this->get('/wiki/leaf')->assertOk()->assertViewHas('ancestors', fn ($rows) => array_map(fn ($row) => $row->id, $rows) === [$root->id, $child->id])
            ->assertSee('href="'.route('wiki.show', 'root').'"', false)->assertSee('href="'.route('wiki.show', 'child').'"', false);
        $this->get('/wiki')->assertOk()->assertSee('Guide leaf')->assertSee('Üst sayfa');
        $this->assertSame('leaf', $leaf->fresh()->slug);
    }

    public function test_draft_future_or_hidden_ancestor_closes_children_search_counts_related_and_direct_urls(): void
    {
        $root = $this->article('root', ['status' => 'draft']);
        $child = $this->article('child', ['parent_id' => $root->id, 'title' => 'PrivateChildTitle']);
        $leaf = $this->article('leaf', ['parent_id' => $child->id, 'title' => 'PrivateLeafTitle']);
        $public = $this->article('public');
        $this->get('/wiki')->assertOk()->assertDontSee('PrivateChildTitle')->assertDontSee('PrivateLeafTitle')
            ->assertViewHas('categories', fn ($rows) => $rows->sole()->articles_count === 1);
        $this->get('/wiki?q=PrivateChildTitle')->assertOk()->assertViewHas('articles', fn ($rows) => $rows->isEmpty());
        $this->get('/wiki/public')->assertOk()->assertViewHas('relatedArticles', fn ($rows) => $rows->isEmpty());
        $this->get('/wiki/child')->assertNotFound();
        $this->get('/wiki/leaf')->assertNotFound();
        $root->update(['status' => 'published', 'published_at' => now()->addHour()]);
        $this->get('/wiki/leaf')->assertNotFound();
        $root->update(['published_at' => now()->subMinute()]);
        $this->get('/wiki/leaf')->assertOk();
        $root->category->update(['is_visible' => false]);
        $this->get('/wiki/leaf')->assertNotFound();
        $this->assertNotNull($public->id);
    }

    public function test_public_scope_rejects_cycles_cross_category_and_more_than_three_levels_even_for_imported_rows(): void
    {
        $root = $this->article('root');
        $child = $this->article('child', ['parent_id' => $root->id]);
        $leaf = $this->article('leaf', ['parent_id' => $child->id]);
        $this->article('too-deep', ['parent_id' => $leaf->id]);
        $this->get('/wiki/too-deep')->assertNotFound();
        $category = WikiCategory::create(['locale' => 'tr', 'slug' => 'other', 'name' => 'Other', 'is_visible' => true]);
        $this->article('cross-category', ['parent_id' => $root->id, 'wiki_category_id' => $category->id]);
        $this->get('/wiki/cross-category')->assertNotFound();
        $root->update(['parent_id' => $child->id]);
        $this->get('/wiki/root')->assertNotFound();
        $this->get('/wiki/child')->assertNotFound();
    }

    public function test_editor_cannot_make_self_or_descendant_cycles_or_a_fourth_level(): void
    {
        $this->editor();
        $root = $this->article('root');
        $child = $this->article('child', ['parent_id' => $root->id]);
        $leaf = $this->article('leaf', ['parent_id' => $child->id]);
        $this->putJson('/admin/wiki/articles/'.$root->id, $this->payload($root, ['parent_id' => $root->id]))->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $this->putJson('/admin/wiki/articles/'.$root->id, $this->payload($root, ['parent_id' => $leaf->id]))->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $other = $this->article('other');
        $this->putJson('/admin/wiki/articles/'.$other->id, $this->payload($other, ['parent_id' => $leaf->id]))->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $this->assertNull($root->fresh()->parent_id);
        $this->assertSame(0, $root->revisions()->count());
    }

    public function test_reparenting_checks_existing_subtree_height_and_supports_a_valid_family_move(): void
    {
        $this->editor();
        $root = $this->article('root');
        $child = $this->article('child', ['parent_id' => $root->id]);
        $target = $this->article('target');
        $targetChild = $this->article('target-child', ['parent_id' => $target->id]);
        $this->putJson('/admin/wiki/articles/'.$root->id, $this->payload($root, ['parent_id' => $targetChild->id]))->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $this->put('/admin/wiki/articles/'.$root->id, $this->payload($root, ['parent_id' => $target->id]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($target->id, $root->fresh()->parent_id);
        $this->get('/wiki/child')->assertOk()->assertViewHas('ancestors', fn ($rows) => array_map(fn ($row) => $row->id, $rows) === [$target->id, $root->id]);
        $this->assertSame($root->id, $child->fresh()->parent_id);
    }

    public function test_parent_and_child_category_language_must_remain_consistent(): void
    {
        $this->editor();
        $root = $this->article('root');
        $child = $this->article('child', ['parent_id' => $root->id]);
        $otherCategory = WikiCategory::create(['locale' => 'tr', 'slug' => 'other', 'name' => 'Other', 'is_visible' => true]);
        $other = $this->article('other', ['wiki_category_id' => $otherCategory->id]);
        $this->putJson('/admin/wiki/articles/'.$child->id, $this->payload($child, ['parent_id' => $other->id]))->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $this->putJson('/admin/wiki/articles/'.$root->id, $this->payload($root, ['wiki_category_id' => $otherCategory->id]))->assertUnprocessable()->assertJsonValidationErrors('wiki_category_id');
        $enCategory = WikiCategory::create(['locale' => 'en', 'slug' => 'en', 'name' => 'English', 'is_visible' => true]);
        $this->putJson('/admin/wiki/articles/'.$root->id, $this->payload($root, ['locale' => 'en', 'wiki_category_id' => $enCategory->id]))->assertUnprocessable()->assertJsonValidationErrors('locale');
    }

    public function test_creation_starts_top_level_and_parent_options_respect_family_height(): void
    {
        $this->editor();
        $root = $this->article('root');
        $child = $this->article('child', ['parent_id' => $root->id]);
        $this->article('leaf', ['parent_id' => $child->id]);
        $this->get('/admin/wiki/articles/'.$root->id.'/edit')->assertOk()->assertViewHas('fields', fn ($fields) => count($fields['parent_id']['options']) === 1);
        $this->postJson('/admin/wiki/articles', $this->payload($root, ['slug' => 'new-child', 'parent_id' => $root->id]))->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $this->post('/admin/wiki/articles', $this->payload($root, ['slug' => 'new-root', 'parent_id' => null, 'status' => 'draft']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull(WikiArticle::where('slug', 'new-root')->sole()->parent_id);
    }

    public function test_reviewed_history_restore_preserves_current_parent_and_published_parent_visibility(): void
    {
        $this->editor();
        $root = $this->article('root');
        $child = $this->article('child');
        $this->put('/admin/wiki/articles/'.$child->id, $this->payload($child, ['parent_id' => $root->id]))->assertRedirect()->assertSessionHasNoErrors();
        $this->get('/admin/wiki/articles/'.$child->id.'/edit?from_revision=1')->assertOk()
            ->assertViewHas('fields', fn ($fields) => $fields['parent_id']['value'] === $root->id);
        $this->put('/admin/wiki/articles/'.$child->id, $this->payload($child->fresh(), ['status' => 'draft', 'published_at' => null, 'from_revision' => 1]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($root->id, $child->fresh()->parent_id);
        $this->assertSame(3, $child->fresh()->revision);
    }
}
