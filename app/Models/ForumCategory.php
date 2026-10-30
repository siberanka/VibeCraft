<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForumCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'staff_only_topics', 'sort', 'is_visible'];

    protected function casts(): array
    {
        return ['staff_only_topics' => 'boolean', 'is_visible' => 'boolean', 'sort' => 'integer'];
    }

    /** @return HasMany<ForumThread, $this> */
    public function threads(): HasMany
    {
        return $this->hasMany(ForumThread::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
