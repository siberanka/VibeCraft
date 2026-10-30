<?php

namespace App\Http\Controllers;

use App\Domain\Content\WikiRenderer;
use App\Models\WikiArticle;
use App\Models\WikiFeedback;
use App\Models\WikiRevision;
use Illuminate\View\View;

class AdminWikiHistoryController extends Controller
{
    public function index(WikiArticle $article): View
    {
        return view('theme::admin.wiki.history', [
            'article' => $article,
            'feedbackSummary' => WikiFeedback::where('wiki_article_id', $article->id)->where('revision', $article->revision)->selectRaw('helpful, COUNT(*) AS total')->groupBy('helpful')->pluck('total', 'helpful'),
            'revisions' => $article->revisions()->select(['id', 'wiki_article_id', 'revision', 'editor_id', 'locale', 'title', 'origin', 'based_on_revision', 'created_at'])
                ->with('editor:id,name')->orderByDesc('revision')->paginate(20),
        ]);
    }

    public function show(WikiArticle $article, WikiRevision $revision): View
    {
        abort_unless($revision->wiki_article_id === $article->id, 404);
        $revision->load('editor:id,name');

        return view('theme::admin.wiki.revision', [
            'article' => $article, 'revision' => $revision,
            'previewHtml' => WikiRenderer::render($revision->body_markdown, $revision->locale)['html'],
        ]);
    }
}
