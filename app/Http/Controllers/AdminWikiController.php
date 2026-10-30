<?php

namespace App\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Domain\Content\WikiFaq;
use App\Domain\Content\WikiHistory;
use App\Domain\Content\WikiReaderRules;
use App\Domain\Content\WikiRenderer;
use App\Domain\Content\WikiTree;
use App\Models\WikiArticle;
use App\Models\WikiCategory;
use App\Models\WikiRevision;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminWikiController extends Controller
{
    public function __construct(private AuditLogger $audit, private WikiHistory $history, private WikiTree $tree, private WikiReaderRules $readerRules) {}

    public function categories(): View
    {
        return view('theme::admin.index', [
            'title' => __('Wiki categories'), 'resource' => 'wiki/categories',
            'records' => WikiCategory::orderBy('locale')->orderBy('sort')->paginate(20),
            'columns' => ['name' => 'Name', 'locale' => 'Language', 'is_visible' => 'Visible', 'sort' => 'Sort order'],
            'createUrl' => route('admin.wiki.categories.create'), 'editBase' => url('/admin/wiki/categories'),
        ]);
    }

    public function articles(): View
    {
        return view('theme::admin.index', [
            'title' => __('Wiki articles'), 'resource' => 'wiki/articles',
            'records' => WikiArticle::with('category')->latest('updated_at')->paginate(20),
            'columns' => ['title' => 'Title', 'category.name' => 'Category', 'locale' => 'Language', 'status' => 'Status'],
            'createUrl' => route('admin.wiki.articles.create'), 'editBase' => url('/admin/wiki/articles'),
        ]);
    }

    public function categoryForm(?WikiCategory $category = null): View
    {
        return $this->form('categories', $category, [
            'name' => $this->field('Name', $category?->name),
            'slug' => array_replace($this->field('Slug', $category?->slug), ['max' => 100]),
            'locale' => $this->field('Language', $category->locale ?? app()->getLocale(), 'select', ['tr' => 'Türkçe', 'en' => 'English']),
            'description' => $this->field('Summary', $category?->description, 'textarea', required: false),
            'sort' => $this->field('Sort order', $category->sort ?? 0, 'number'),
            'is_visible' => $this->field('Visible', $category->is_visible ?? true, 'checkbox', required: false),
        ] + $this->readerRules->fields($category));
    }

    public function articleForm(Request $request, ?WikiArticle $article = null): View
    {
        $query = $request->validate(['from_revision' => ['nullable', 'integer', 'min:1', 'max:2147483647']]);
        $source = null;
        if (isset($query['from_revision'])) {
            abort_unless($article !== null, 404);
            $source = $article->revisions()->where('revision', $query['from_revision'])->firstOrFail();
        }
        $categoryRecords = WikiCategory::orderBy('locale')->orderBy('sort')->limit(1000)->get();
        if ($article && ! $categoryRecords->contains('id', $article->wiki_category_id)) {
            $categoryRecords->push($article->category);
        }
        $categories = $categoryRecords->mapWithKeys(fn (WikiCategory $category) => [$category->id => $category->name.' ('.$category->locale.')'])->all();

        return $this->form('articles', $article, [
            'title' => $this->field('Title', $source ? $source->title : $article?->title),
            'slug' => $this->field('Slug', $article?->slug),
            'locale' => $this->field('Language', $article->locale ?? app()->getLocale(), 'select', ['tr' => 'Türkçe', 'en' => 'English']),
            'wiki_category_id' => $this->field('Category', $article?->wiki_category_id, 'select', $categories),
            'parent_id' => $this->field('Parent page', $article?->parent_id, 'select', $this->tree->options($article), required: false)
                + ['disabled' => $article === null, 'hint' => $article ? __('Pages can be nested up to three levels. Save category or language changes at the top level first.') : __('Save the draft first, then choose a parent page when editing.')],
            'summary' => $this->field('Summary', $source ? $source->summary : $article?->summary, 'textarea', required: false),
            'body_markdown' => $this->field('Article text', $source ? $source->body_markdown : $article?->body_markdown, 'textarea'),
            'faqs' => ['label' => __('Frequently asked questions'), 'type' => 'faqs', 'value' => $source ? ($source->faqs ?? []) : ($article->faqs ?? [])],
            'status' => $this->field('Status', $source ? 'draft' : ($article->status ?? 'draft'), 'select', ['draft' => 'Draft', 'published' => 'Published']),
            'published_at' => $this->field('Publish at', $source ? null : $article?->published_at?->format('Y-m-d\TH:i'), 'datetime-local', required: false)
                + ['hint' => __('Timezone: :zone', ['zone' => config('app.timezone')])],
            'sort' => $this->field('Sort order', $article->sort ?? 0, 'number'),
        ] + $this->readerRules->fields($article), $source);
    }

    public function saveCategory(Request $request, ?WikiCategory $category = null): RedirectResponse
    {
        $this->normalizeSlug($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => $this->slugRules('wiki_categories', 100, $request, $category?->id),
            'locale' => ['required', 'string', Rule::in(config('vibecraft.locales'))],
            'description' => ['nullable', 'string', 'max:400'], 'is_visible' => ['boolean'],
            'sort' => ['required', 'integer', 'min:0', 'max:100000'],
            'revision' => [$category ? 'required' : 'nullable', 'integer', 'min:1', 'max:2147483647'],
        ] + $this->readerRules->validation());
        $data['is_visible'] = $request->boolean('is_visible');
        $expectedRevision = $data['revision'] ?? null;
        unset($data['revision']);
        try {
            DB::transaction(function () use ($data, $category, $request, $expectedRevision) {
                $record = $category ? WikiCategory::whereKey($category->id)->lockForUpdate()->firstOrFail() : new WikiCategory;
                $this->revision($record->exists, $record->revision, $expectedRevision);
                $readerBefore = $this->readerRules->summary($record);
                $data = $this->readerRules->normalize($data, $record);
                if ($record->exists && $record->articles()->where('locale', '!=', $data['locale'])->exists()) {
                    throw ValidationException::withMessages(['locale' => __('Move or translate the category articles before changing its language.')]);
                }
                $record->fill($data)->forceFill(['revision' => $record->exists ? $record->revision + 1 : 1])->save();
                $this->audit->log($category ? 'wiki.category.updated' : 'wiki.category.created', $record, [
                    'fields' => array_keys($data), 'readers_before' => $readerBefore, 'readers_after' => $this->readerRules->summary($record),
                ], $request->user()->getAuthIdentifier());
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => __('This slug already exists in the selected language.')]);
        }

        return redirect()->route('admin.wiki.categories')->with('status', __('Saved.'));
    }

    public function saveArticle(Request $request, ?WikiArticle $article = null): RedirectResponse
    {
        $this->normalizeSlug($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => $this->slugRules('wiki_articles', 180, $request, $article?->id),
            'locale' => ['required', 'string', Rule::in(config('vibecraft.locales'))],
            'wiki_category_id' => ['required', 'integer', 'exists:wiki_categories,id'],
            'parent_id' => $article ? ['nullable', 'integer', 'min:1', 'exists:wiki_articles,id'] : ['nullable', 'prohibited'],
            'summary' => ['nullable', 'string', 'max:400'],
            'body_markdown' => ['required', 'string', 'max:100000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'], 'sort' => ['required', 'integer', 'min:0', 'max:100000'],
            'revision' => [$article ? 'required' : 'nullable', 'integer', 'min:1', 'max:2147483647'],
            'from_revision' => ['nullable', 'integer', 'min:1', 'max:2147483647',
                Rule::exists('wiki_revisions', 'revision')->where('wiki_article_id', $article ? $article->id : 0)],
        ] + $this->readerRules->validation() + WikiFaq::validation());
        if (array_key_exists('faqs', $data)) {
            $data['faqs'] = WikiFaq::normalize($data['faqs']);
        }
        $rendered = WikiRenderer::render($data['body_markdown'], $data['locale']);
        $data['body_html'] = $rendered['html'];
        $data['table_of_contents'] = $rendered['toc'];
        $expectedRevision = $data['revision'] ?? null;
        $basedOn = isset($data['from_revision']) ? (int) $data['from_revision'] : null;
        unset($data['revision'], $data['from_revision']);
        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }
        try {
            DB::transaction(function () use ($data, $article, $request, $expectedRevision, $basedOn) {
                $this->tree->lock();
                $category = WikiCategory::whereKey($data['wiki_category_id'])->lockForUpdate()->firstOrFail();
                if ($category->locale !== $data['locale']) {
                    throw ValidationException::withMessages(['wiki_category_id' => __('Choose a category in the article language.')]);
                }
                $record = $article ? WikiArticle::whereKey($article->id)->lockForUpdate()->firstOrFail() : new WikiArticle;
                $this->revision($record->exists, $record->revision, $expectedRevision);
                $readerBefore = $this->readerRules->summary($record);
                $data = $this->readerRules->normalize($data, $record);
                $parentId = array_key_exists('parent_id', $data) ? ($data['parent_id'] === null ? null : (int) $data['parent_id']) : $record->parent_id;
                $this->tree->validate($record, (int) $data['wiki_category_id'], $data['locale'], $parentId);
                if ($record->exists) {
                    $this->history->baseline($record);
                }
                $record->fill($data)->forceFill([
                    'revision' => $record->exists ? $record->revision + 1 : 1,
                    'author_id' => $record->author_id ?? $request->user()->getAuthIdentifier(),
                ])->save();
                $this->history->append($record, (int) $request->user()->getAuthIdentifier(), $basedOn);
                $this->audit->log($article ? 'wiki.article.updated' : 'wiki.article.created', $record, [
                    'fields' => array_keys($data), 'based_on_revision' => $basedOn,
                    'readers_before' => $readerBefore, 'readers_after' => $this->readerRules->summary($record),
                ], $request->user()->getAuthIdentifier());
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            $collision = WikiArticle::where('locale', $data['locale'])->where('slug', $data['slug']);
            if ($article) {
                $collision->whereKeyNot($article->id);
            }
            if ($collision->exists()) {
                throw ValidationException::withMessages(['slug' => __('This slug already exists in the selected language.')]);
            }
            throw $exception;
        }

        return redirect()->route('admin.wiki.articles')->with('status', __('Saved.'));
    }

    private function normalizeSlug(Request $request): void
    {
        $slug = $request->input('slug');
        if (is_string($slug)) {
            $request->merge(['slug' => strtolower(trim($slug))]);
        }
    }

    private function slugRules(string $table, int $max, Request $request, ?int $id): array
    {
        $locale = $request->input('locale');

        return ['bail', 'required', 'string', 'alpha_dash:ascii', 'max:'.$max,
            Rule::unique($table, 'slug')->where('locale', is_string($locale) ? $locale : '')->ignore($id)];
    }

    private function revision(bool $exists, ?int $current, mixed $expected): void
    {
        if ($exists && $current !== (int) $expected) {
            throw ValidationException::withMessages(['revision' => __('Another editor updated this content. Reload the page before saving.')]);
        }
    }

    private function field(string $label, mixed $value, string $type = 'text', array $options = [], bool $required = true): array
    {
        $limits = match ($label) {
            'Name' => ['max' => 100], 'Title' => ['max' => 160], 'Slug' => ['max' => 180],
            'Summary' => ['max' => 400], 'Article text' => ['max' => 100000, 'rows' => 20],
            'Sort order' => ['min' => 0, 'max' => 100000], default => [],
        };

        return ['label' => __($label), 'value' => $value, 'type' => $type, 'options' => $options, 'required' => $required] + $limits;
    }

    private function form(string $kind, WikiCategory|WikiArticle|null $record, array $fields, ?WikiRevision $source = null): View
    {
        return view('theme::admin.form', [
            'title' => __($kind === 'articles' ? 'Wiki articles' : 'Wiki categories'),
            'resource' => 'wiki/'.$kind, 'record' => $record, 'fields' => $fields,
            'contentRevision' => $record?->revision,
            'restoreSourceRevision' => $source?->revision,
            'historyUrl' => $record instanceof WikiArticle ? route('admin.wiki.articles.history', $record) : null,
            'action' => $record ? route('admin.wiki.'.$kind.'.update', $record) : route('admin.wiki.'.$kind.'.store'),
            'method' => $record ? 'PUT' : 'POST',
        ]);
    }
}
