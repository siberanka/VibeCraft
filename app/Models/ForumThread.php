<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForumThread extends Model
{
    use HasUlids;

    protected $fillable = ['forum_category_id', 'user_id', 'title', 'pinned', 'locked', 'hidden', 'replies', 'last_post_at'];

    protected function casts(): array
    {
        return ['pinned' => 'boolean', 'locked' => 'boolean', 'hidden' => 'boolean', 'replies' => 'integer', 'last_post_at' => 'datetime'];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<ForumCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ForumCategory::class, 'forum_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<ForumPost, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(ForumPost::class);
    }
}
