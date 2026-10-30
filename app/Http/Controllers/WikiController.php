<?php

namespace App\Http\Controllers;

use App\Core\Settings\Settings;
use App\Domain\Content\WikiAudience;
use App\Domain\Content\WikiFaq;
use App\Models\WikiArticle;
use App\Models\WikiCategory;
use App\Models\WikiFeedback;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WikiController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function index(Request $request): View
    {
        abort_unless($this->settings->bool('modules.wiki'), 404);
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'alpha_dash:ascii', 'max:100'],
        ]);
        $search = trim($data['q'] ?? '');
        $audience = WikiAudience::forUser($request->user());
        $selected = isset($data['category']) && $data['category'] !== ''
            ? WikiCategory::visible(null, $audience)->where('slug', $data['category'])->firstOrFail()
            : null;
        $query = WikiArticle::published(null, $audience)->select(['id', 'wiki_category_id', 'parent_id', 'locale', 'title', 'slug', 'summary', 'updated_at'])
            ->with(['category', 'parent:id,title,slug'])->when($selected, fn (Builder $query) => $query->where('wiki_category_id', $selected->id));
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(function (Builder $query) use ($pattern) {
                $query->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("summary LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("body_markdown LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        return view('theme::wiki.index', [
            'articles' => $query->orderBy('sort')->orderBy('title')->paginate(12)->withQueryString(),
            'categories' => $this->categories($audience), 'selectedCategory' => $selected, 'search' => $search,
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        abort_unless($this->settings->bool('modules.wiki'), 404);
        $audience = WikiAudience::forUser($request->user());
        $article = WikiArticle::published(null, $audience)->with('category')->where('slug', $slug)->firstOrFail();
        $ancestors = [];
        $cursor = $article;
        for ($level = 0; $level < 2 && $cursor->parent_id !== null; $level++) {
            $cursor = WikiArticle::published(null, $audience)->select(['id', 'parent_id', 'slug', 'title'])->findOrFail($cursor->parent_id);
            array_unshift($ancestors, $cursor);
        }
        $children = WikiArticle::published(null, $audience)->select(['id', 'parent_id', 'slug', 'title', 'summary'])
            ->where('parent_id', $article->id)->orderBy('sort')->orderBy('title')->limit(20)->get();
        $related = WikiArticle::published(null, $audience)->select(['id', 'wiki_category_id', 'locale', 'title', 'slug'])
            ->where('wiki_category_id', $article->wiki_category_id)->whereKeyNot($article->id)
            ->whereNotIn('id', array_map(fn (WikiArticle $ancestor) => $ancestor->id, $ancestors))
            ->where(fn (Builder $query) => $query->whereNull('parent_id')->orWhere('parent_id', '!=', $article->id))
            ->orderBy('sort')->orderBy('title')->limit(8)->get();

        return view('theme::wiki.show', [
            'article' => $article, 'categories' => $this->categories($audience), 'selectedCategory' => $article->category,
            'relatedArticles' => $related, 'ancestors' => $ancestors, 'childArticles' => $children,
            'faqs' => WikiFaq::entries($article->faqs),
            'faqSchema' => WikiArticle::published()->whereKey($article->id)->exists(),
            'feedbackVote' => $request->user()?->hasVerifiedEmail()
                ? WikiFeedback::where('wiki_article_id', $article->id)->where('revision', $article->revision)->where('user_id', $request->user()->id)->first()?->helpful : null,
        ]);
    }

    private function categories(WikiAudience $audience): Collection
    {
        return WikiCategory::visible(null, $audience)
            ->withCount(['articles' => function (Builder $query) use ($audience) {
                /** @var Builder<WikiArticle> $query */
                $query->published(null, $audience);
            }])
            ->orderBy('sort')->orderBy('name')->limit(100)->get();
    }
}
