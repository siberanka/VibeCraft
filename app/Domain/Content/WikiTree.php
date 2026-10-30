<?php

namespace App\Domain\Content;

use App\Models\WikiArticle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class WikiTree
{
    public const MAX_LEVELS = WikiArticle::MAX_LEVELS;

    /** Serialize structural edits across workers before category/article locks. */
    public function lock(): void
    {
        if (DB::table('wiki_structure_locks')->where('id', 1)->lockForUpdate()->first() === null) {
            throw new RuntimeException('Wiki structure lock is unavailable.');
        }
    }

    public function validate(WikiArticle $article, int $categoryId, string $locale, ?int $parentId): void
    {
        $ancestors = 0;
        $visited = [];
        $next = $parentId;
        while ($next !== null) {
            if (isset($visited[$next]) || ($article->exists && $next === $article->id)) {
                throw ValidationException::withMessages(['parent_id' => __('Choose a parent outside this page and its subpages.')]);
            }
            $visited[$next] = true;
            $parent = WikiArticle::select(['id', 'parent_id', 'wiki_category_id', 'locale'])->find($next);
            if ($parent === null || (int) $parent->wiki_category_id !== $categoryId || $parent->locale !== $locale) {
                throw ValidationException::withMessages(['parent_id' => __('Choose a parent in the same category and language.')]);
            }
            $ancestors++;
            if ($ancestors >= self::MAX_LEVELS) {
                $this->tooDeep();
            }
            $next = $parent->parent_id;
        }
        if ($article->exists) {
            $children = WikiArticle::where('parent_id', $article->id);
            if ((clone $children)->where('locale', '!=', $locale)->exists()) {
                throw ValidationException::withMessages(['locale' => __('Reorganize the subpages before changing this page language.')]);
            }
            if ((clone $children)->where('wiki_category_id', '!=', $categoryId)->exists()) {
                throw ValidationException::withMessages(['wiki_category_id' => __('Reorganize the subpages before changing this page category.')]);
            }
        }
        if ($ancestors + $this->height($article) > self::MAX_LEVELS) {
            $this->tooDeep();
        }
    }

    /** Bounded relationship existence checks, independent of descendant count. */
    public function height(WikiArticle $article): int
    {
        if (! $article->exists || ! WikiArticle::where('parent_id', $article->id)->exists()) {
            return 1;
        }
        if (WikiArticle::whereHas('parent.parent', fn (Builder $query) => $query->where('parent_id', $article->id))->exists()) {
            $this->tooDeep();
        }

        return WikiArticle::whereHas('parent', fn (Builder $query) => $query->where('parent_id', $article->id))->exists() ? 3 : 2;
    }

    /** @return array<int|string, string> */
    public function options(?WikiArticle $article): array
    {
        $options = ['' => __('Top-level article')];
        if ($article === null) {
            return $options;
        }
        $height = $this->height($article);
        if ($height < self::MAX_LEVELS) {
            $query = WikiArticle::where('locale', $article->locale)->where('wiki_category_id', $article->wiki_category_id)
                ->whereKeyNot($article->id);
            if ($height === 2) {
                $query->whereNull('parent_id');
            } else {
                $query->where(fn (Builder $query) => $query->whereNull('parent_id')
                    ->orWhereHas('parent', fn (Builder $parent) => $parent->whereNull('parent_id')));
            }
            foreach ($query->orderBy('title')->limit(1000)->get(['id', 'title', 'slug']) as $candidate) {
                $options[$candidate->id] = Str::limit($candidate->title, 80).' / '.$candidate->slug;
            }
        }
        if ($article->parent_id !== null && ! isset($options[$article->parent_id])) {
            $parent = $article->parent;
            if ($parent !== null) {
                $options[$parent->id] = Str::limit($parent->title, 80).' / '.$parent->slug;
            }
        }

        return $options;
    }

    private function tooDeep(): never
    {
        throw ValidationException::withMessages(['parent_id' => __('Pages can be nested up to three levels. Reorganize the subpages first.')]);
    }
}
