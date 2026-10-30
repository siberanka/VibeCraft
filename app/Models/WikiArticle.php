<?php

namespace App\Models;

use App\Domain\Content\WikiAudience;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $wiki_category_id
 * @property int|null $parent_id
 * @property int|null $author_id
 * @property string $locale
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property string $body_markdown
 * @property string $body_html
 * @property list<array{question:string,answer:string}>|null $faqs
 * @property list<array{id:string,label:string,level:int}> $table_of_contents
 * @property string $status
 * @property Carbon|null $published_at
 * @property Carbon|null $updated_at
 * @property int $sort
 * @property int $revision
 * @property string $reader_access
 * @property list<string>|null $reader_roles
 * @property-read WikiCategory $category
 * @property-read WikiArticle|null $parent
 */
class WikiArticle extends Model
{
    public const MAX_LEVELS = 3;

    protected $fillable = [
        'wiki_category_id', 'parent_id', 'locale', 'title', 'slug', 'summary', 'body_markdown',
        'body_html', 'table_of_contents', 'status', 'published_at', 'sort',
        'reader_access', 'reader_roles', 'faqs',
    ];

    protected $attributes = ['reader_access' => 'public'];

    protected function casts(): array
    {
        return ['parent_id' => 'integer', 'table_of_contents' => 'array', 'published_at' => 'datetime', 'sort' => 'integer', 'revision' => 'integer', 'reader_roles' => 'array', 'faqs' => 'array'];
    }

    /** @return BelongsTo<WikiCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(WikiCategory::class, 'wiki_category_id');
    }

    /** @return HasMany<WikiRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(WikiRevision::class);
    }

    /** @return BelongsTo<WikiArticle, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<WikiArticle, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @param Builder<WikiArticle> $query
     * @return Builder<WikiArticle>
     */
    public function scopePublished(Builder $query, ?string $locale = null, ?WikiAudience $audience = null): Builder
    {
        $locale ??= app()->getLocale();

        self::constrainVisibleBranch($query, $locale, self::MAX_LEVELS - 1, $audience ?? WikiAudience::guest());

        return $query;
    }

    private static function constrainVisibleBranch(Builder $query, string $locale, int $remainingParents, WikiAudience $audience): void
    {
        $categoryColumn = $query->qualifyColumn('wiki_category_id');
        $query->where($query->qualifyColumn('locale'), $locale)->where($query->qualifyColumn('status'), 'published')
            ->where($query->qualifyColumn('published_at'), '<=', now())
            ->whereHas('category', function (Builder $category) use ($locale, $audience) {
                $category->where('locale', $locale)->where('is_visible', true);
                $audience->constrain($category);
            })
            ->where(function (Builder $branch) use ($locale, $remainingParents, $categoryColumn, $audience) {
                $branch->whereNull($branch->qualifyColumn('parent_id'));
                if ($remainingParents > 0) {
                    $branch->orWhereHas('parent', function (Builder $parent) use ($locale, $remainingParents, $categoryColumn, $audience) {
                        $parent->whereColumn($parent->qualifyColumn('wiki_category_id'), $categoryColumn);
                        self::constrainVisibleBranch($parent, $locale, $remainingParents - 1, $audience);
                    });
                }
            });
        $audience->constrain($query);
    }
}
