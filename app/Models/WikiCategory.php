<?php

namespace App\Models;

use App\Domain\Content\WikiAudience;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $locale
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_visible
 * @property int $sort
 * @property int $revision
 * @property string $reader_access
 * @property list<string>|null $reader_roles
 */
class WikiCategory extends Model
{
    protected $fillable = ['locale', 'name', 'slug', 'description', 'is_visible', 'sort', 'reader_access', 'reader_roles'];

    protected $attributes = ['reader_access' => 'public'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'sort' => 'integer', 'revision' => 'integer', 'reader_roles' => 'array'];
    }

    /** @return HasMany<WikiArticle, $this> */
    public function articles(): HasMany
    {
        return $this->hasMany(WikiArticle::class);
    }

    /** @param Builder<WikiCategory> $query
     * @return Builder<WikiCategory>
     */
    public function scopeVisible(Builder $query, ?string $locale = null, ?WikiAudience $audience = null): Builder
    {
        $query->where($query->qualifyColumn('locale'), $locale ?? app()->getLocale())
            ->where($query->qualifyColumn('is_visible'), true);
        ($audience ?? WikiAudience::guest())->constrain($query);

        return $query;
    }
}
