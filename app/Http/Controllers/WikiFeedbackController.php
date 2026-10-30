<?php

namespace App\Http\Controllers;

use App\Core\Settings\Settings;
use App\Domain\Content\WikiAudience;
use App\Domain\Content\WikiTree;
use App\Models\WikiArticle;
use App\Models\WikiCategory;
use App\Models\WikiFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WikiFeedbackController extends Controller
{
    public function __construct(private Settings $settings, private WikiTree $tree) {}

    public function store(Request $request, string $slug): RedirectResponse
    {
        abort_unless($this->settings->bool('modules.wiki'), 404);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1', 'max:2147483647'], 'helpful' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $slug, $data) {
            $this->tree->lock();
            $article = WikiArticle::where('locale', app()->getLocale())->where('slug', $slug)->firstOrFail();
            WikiCategory::whereKey($article->wiki_category_id)->lockForUpdate()->firstOrFail();
            $article = WikiArticle::whereKey($article->id)->lockForUpdate()->firstOrFail();
            $audience = WikiAudience::forUser($request->user());
            abort_unless(WikiArticle::published(null, $audience)->whereKey($article->id)->exists(), 404);
            if ($article->revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => __('This guide changed. Reload it before leaving feedback.')]);
            }
            WikiFeedback::updateOrCreate([
                'wiki_article_id' => $article->id, 'user_id' => $request->user()->getAuthIdentifier(), 'revision' => $article->revision,
            ], ['helpful' => $request->boolean('helpful')]);
        }, 5);

        return redirect()->route('wiki.show', $slug)->with('status', __('Your feedback has been saved.'));
    }
}
