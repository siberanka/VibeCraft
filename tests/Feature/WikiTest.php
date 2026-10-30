<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Content\WikiRenderer;
use App\Models\User;
use App\Models\WikiArticle;
use App\Models\WikiCategory;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WikiTest extends TestCase
{
    private function category(array $attributes = []): WikiCategory
    {
        return WikiCategory::create(array_replace([
            'locale' => 'tr', 'name' => 'Başlangıç', 'slug' => 'baslangic', 'sort' => 0, 'is_visible' => true,
        ], $attributes));
    }

    private function article(WikiCategory $category, array $attributes = []): WikiArticle
    {
        $body = "## İlk adım\n\nSunucuya hoş geldin.\n\n### Komutlar\n\n`/spawn`";
        $rendered = WikiRenderer::render($body);

        return WikiArticle::create(array_replace([
            'wiki_category_id' => $category->id, 'locale' => $category->locale,
            'title' => 'İlk macera', 'slug' => 'ilk-macera', 'summary' => 'Sunucuya katılma rehberi.',
            'body_markdown' => $body, 'body_html' => $rendered['html'], 'table_of_contents' => $rendered['toc'],
            'status' => 'published', 'published_at' => now()->subMinute(), 'sort' => 0,
        ], $attributes))->refresh();
    }

    private function editor(): User
    {
        app(Settings::class)->set('security.require_admin_2fa', false);
        $user = $this->customer('owner');
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        return $user;
    }

    private function payload(WikiArticle $article): array
    {
        return $article->only(['title', 'slug', 'locale', 'wiki_category_id', 'summary', 'body_markdown', 'status', 'sort', 'revision']);
    }

    public function test_published_guides_are_browsable_with_cached_toc_and_one_page_heading(): void
    {
        $article = $this->article($this->category());
        $this->get('/wiki')->assertOk()->assertSee('İlk macera')->assertSee('Başlangıç');
        $response = $this->get('/wiki/'.$article->slug)->assertOk()->assertSee('id="section-ilk-adim"', false)
            ->assertSee('href="#section-komutlar"', false)->assertSee('/spawn');
        $this->assertSame(1, preg_match_all('/<h1(?:\s|>)/', $response->getContent()));
    }

    public function test_draft_future_hidden_category_and_other_language_never_leak_to_public_lists_or_direct_urls(): void
    {
        $category = $this->category();
        $this->article($category, ['title' => 'SecretDraft', 'slug' => 'draft', 'status' => 'draft']);
        $this->article($category, ['title' => 'SecretFuture', 'slug' => 'future', 'published_at' => now()->addDay()]);
        $this->article($this->category(['slug' => 'hidden', 'is_visible' => false]), ['title' => 'SecretHidden', 'slug' => 'hidden']);
        $this->article($this->category(['locale' => 'en', 'slug' => 'start']), ['title' => 'EnglishOnly', 'slug' => 'english']);
        $this->get('/wiki')->assertOk()->assertDontSee('SecretDraft')->assertDontSee('SecretFuture')->assertDontSee('SecretHidden')->assertDontSee('EnglishOnly');
        foreach (['draft', 'future', 'hidden', 'english'] as $slug) {
            $this->get('/wiki/'.$slug)->assertNotFound();
        }
        $this->withSession(['locale' => 'en'])->get('/wiki/english')->assertOk()->assertSee('EnglishOnly');
    }

    public function test_module_toggle_hides_public_routes_and_navigation_without_erasing_articles(): void
    {
        $article = $this->article($this->category());
        app(Settings::class)->set('modules.wiki', false);
        $this->get('/wiki')->assertNotFound();
        $this->get('/wiki/'.$article->slug)->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('href="'.route('wiki.index').'"', false);
        $this->assertDatabaseCount('wiki_articles', 1);
        app(Settings::class)->set('modules.wiki', true);
        $this->get('/wiki/'.$article->slug)->assertOk();
    }

    public function test_shared_slug_resolves_only_the_current_language_and_category_counts_exclude_unpublished_articles(): void
    {
        $category = $this->category();
        $this->article($category, ['title' => 'Türkçe rehber', 'slug' => 'guide']);
        $this->article($category, ['title' => 'Unpublished', 'slug' => 'draft-guide', 'status' => 'draft']);
        $this->article($this->category(['locale' => 'en', 'slug' => 'start']), ['title' => 'English guide', 'slug' => 'guide']);
        $this->get('/wiki/guide')->assertOk()->assertSee('Türkçe rehber')->assertDontSee('English guide');
        $this->get('/wiki')->assertViewHas('categories', fn ($categories) => $categories->sole()->articles_count === 1);
        $this->withSession(['locale' => 'en'])->get('/wiki/guide')->assertOk()->assertSee('English guide')->assertDontSee('Türkçe rehber');
    }

    public function test_public_pagination_preserves_search_and_category_and_editors_can_open_both_creation_forms(): void
    {
        $category = $this->category();
        foreach (range(1, 13) as $number) {
            $this->article($category, ['title' => 'Page guide '.$number, 'slug' => 'page-'.$number]);
        }
        $this->get('/wiki?'.http_build_query(['q' => 'Page guide', 'category' => $category->slug]))
            ->assertOk()->assertViewHas('articles', fn ($articles) => $articles->count() === 12 && $articles->total() === 13)
            ->assertSee('page=2', false)->assertSee('category=baslangic', false);
        $this->editor();
        $this->get('/admin/wiki/categories/create')->assertOk()->assertSee('name="slug"', false);
        $this->get('/admin/wiki/articles/create')->assertOk()->assertSee('name="body_markdown"', false);
    }

    public function test_edit_form_preserves_its_current_category_beyond_the_bounded_directory(): void
    {
        $this->editor();
        $category = $this->category(['sort' => 100000]);
        $article = $this->article($category);
        WikiCategory::insert(array_map(fn ($number) => [
            'locale' => 'tr', 'name' => 'Directory '.$number, 'slug' => 'directory-'.$number,
            'sort' => $number, 'is_visible' => true,
        ], range(1, 1000)));
        $this->get('/admin/wiki/articles/'.$article->id.'/edit')->assertOk()
            ->assertViewHas('fields', fn ($fields) => array_key_exists($category->id, $fields['wiki_category_id']['options']))
            ->assertSee('Başlangıç (tr)');
    }

    public function test_literal_search_and_category_filter_do_not_expand_wildcards(): void
    {
        $category = $this->category();
        $this->article($category, ['title' => '100%_! Guide', 'slug' => 'literal']);
        $this->article($category, ['title' => '100xx Guide', 'slug' => 'ordinary']);
        $this->article($this->category(['slug' => 'other']), ['title' => '100%_! Other', 'slug' => 'other']);
        $this->get('/wiki?'.http_build_query(['q' => '%_!', 'category' => $category->slug]))->assertOk()
            ->assertSee('100%_! Guide')->assertDontSee('100xx Guide')->assertDontSee('100%_! Other');
        $this->get('/wiki?category=missing')->assertNotFound();
        $this->getJson('/wiki?q[]=bad')->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    public function test_renderer_preserves_unicode_escapes_raw_html_and_allocates_globally_unique_anchors(): void
    {
        $result = WikiRenderer::render("# Guide\n\n## Guide\n\n## Guide-2\n\n### Kömür ve taş\n\n<script>alert(1)</script>\n\n[bad](javascript:alert(1))");
        $this->assertSame(['section-guide', 'section-guide-2', 'section-guide-2-2', 'section-komur-ve-tas'], array_column($result['toc'], 'id'));
        $this->assertStringNotContainsString('<h1', $result['html']);
        $this->assertStringNotContainsString('<script>', $result['html']);
        $this->assertStringNotContainsString('href="javascript:', $result['html']);
        $this->assertSame('Kömür ve taş', $result['toc'][3]['label']);
        $table = WikiRenderer::render("| Command | Meaning |\n| --- | --- |\n| /spawn | Home |\n");
        $this->assertStringContainsString('class="table-wrap" tabindex="0" role="region"', $table['html']);
        $englishTable = WikiRenderer::render("| Command | Meaning |\n| --- | --- |\n| /spawn | Home |\n", 'en');
        $this->assertStringContainsString('aria-label="Article table"', $englishTable['html']);
    }

    public function test_renderer_bounds_heading_work(): void
    {
        $this->expectException(ValidationException::class);
        WikiRenderer::render(str_repeat("## Heading\n\n", 201));
    }

    public function test_staff_content_permission_and_existing_authentication_guards_apply(): void
    {
        app(Settings::class)->set('security.require_admin_2fa', false);
        $this->actingAs($this->customer('support'))->withSession(['auth.password_confirmed_at' => time()]);
        $this->get('/admin/wiki/articles')->assertForbidden();
        $this->post('/admin/wiki/categories', [])->assertForbidden();
        app(Settings::class)->set('security.require_admin_2fa', true);
        $this->actingAs($this->customer('owner'))->get('/admin/wiki/articles')->assertRedirect(route('account.security'));
    }

    public function test_editor_creates_server_rendered_content_and_ignores_client_generated_fields(): void
    {
        $editor = $this->editor();
        $category = $this->category();
        $this->post('/admin/wiki/articles', [
            'title' => 'Build guide', 'slug' => ' Build-GUIDE ', 'locale' => 'tr', 'wiki_category_id' => $category->id,
            'body_markdown' => "# Build\n\nReal body", 'status' => 'published', 'sort' => 1,
            'body_html' => '<script>ignored</script>', 'table_of_contents' => [['id' => 'bad']], 'author_id' => 999,
        ])->assertRedirect(route('admin.wiki.articles'));
        $article = WikiArticle::sole();
        $this->assertSame('build-guide', $article->slug);
        $this->assertSame($editor->id, $article->author_id);
        $this->assertSame('section-build', $article->table_of_contents[0]['id']);
        $this->assertStringNotContainsString('ignored', $article->body_html);
        $this->assertNotNull($article->published_at);
        $this->get('/admin/wiki/articles/'.$article->id.'/edit')->assertOk()->assertSee('name="revision" value="1"', false);
    }

    public function test_stale_article_revision_cannot_silently_overwrite_a_saved_edit(): void
    {
        $this->editor();
        $article = $this->article($this->category());
        $payload = $this->payload($article);
        $this->put('/admin/wiki/articles/'.$article->id, array_replace($payload, ['title' => 'First edit']))->assertRedirect()->assertSessionHasNoErrors();
        $this->putJson('/admin/wiki/articles/'.$article->id, array_replace($payload, ['title' => 'Stale edit']))
            ->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->assertSame('First edit', $article->fresh()->title);
        $this->assertSame(2, $article->fresh()->revision);
    }

    public function test_categories_enforce_language_and_revision_while_same_slug_can_exist_in_different_locales(): void
    {
        $this->editor();
        $category = $this->category();
        $article = $this->article($category);
        $other = $this->category(['locale' => 'en', 'slug' => 'baslangic']);
        $this->putJson('/admin/wiki/articles/'.$article->id, array_replace($this->payload($article), ['wiki_category_id' => $other->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('wiki_category_id');
        $payload = ['name' => 'Changed', 'slug' => $category->slug, 'locale' => 'en', 'sort' => 0, 'revision' => 1, 'is_visible' => true];
        $this->putJson('/admin/wiki/categories/'.$category->id, array_replace($payload, ['slug' => 'different']))
            ->assertUnprocessable()->assertJsonValidationErrors('locale');
        $payload['locale'] = 'tr';
        $this->put('/admin/wiki/categories/'.$category->id, $payload)->assertRedirect();
        $this->putJson('/admin/wiki/categories/'.$category->id, $payload)->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->postJson('/admin/wiki/categories', $payload)->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->assertSame('Changed', $category->fresh()->name);
    }
}
