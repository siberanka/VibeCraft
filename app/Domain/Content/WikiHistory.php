<?php

namespace App\Domain\Content;

use App\Models\WikiArticle;
use App\Models\WikiRevision;

final class WikiHistory
{
    /** Called under the article row lock before changing existing content. */
    public function baseline(WikiArticle $article): void
    {
        if (! $article->revisions()->where('revision', $article->revision)->exists()) {
            WikiRevision::create($this->snapshot($article) + [
                'editor_id' => null, 'origin' => 'baseline', 'created_at' => $article->updated_at ?? now(),
            ]);
        }
    }

    /** Called in the same transaction as the new article version and audit record. */
    public function append(WikiArticle $article, int $editorId, ?int $basedOn = null): WikiRevision
    {
        return WikiRevision::create($this->snapshot($article) + [
            'editor_id' => $editorId, 'origin' => 'edit', 'based_on_revision' => $basedOn,
        ]);
    }

    private function snapshot(WikiArticle $article): array
    {
        return [
            'wiki_article_id' => $article->id, 'revision' => $article->revision,
            'locale' => $article->locale, 'title' => $article->title,
            'summary' => $article->summary, 'body_markdown' => $article->body_markdown,
            'faqs' => $article->faqs ?? [],
        ];
    }
}
