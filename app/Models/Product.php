<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'name', 'slug', 'summary', 'description_markdown', 'description_html', 'image_media_id',
        'price_minor', 'compare_at_minor', 'stock', 'per_user_limit', 'max_quantity', 'requires_link',
        'duration_days', 'badge', 'is_visible', 'is_featured', 'sort', 'chest_delivery',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'compare_at_minor' => 'integer',
            'stock' => 'integer',
            'per_user_limit' => 'integer',
            'max_quantity' => 'integer',
            'duration_days' => 'integer',
            'requires_link' => 'boolean',
            'chest_delivery' => 'boolean',
            'is_visible' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Media, $this> */
    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_id');
    }

    /** @return HasMany<ProductCommand, $this> */
    public function commands(): HasMany
    {
        return $this->hasMany(ProductCommand::class)->orderBy('sort');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true)->whereHas('category', fn ($q) => $q->where('is_visible', true));
    }

    public function inStock(): bool
    {
        return $this->stock === null || $this->stock > 0;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
