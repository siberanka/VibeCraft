<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffPosition extends Model
{
    public const MAX_QUESTIONS = 10;

    protected $fillable = ['name', 'slug', 'description', 'questions', 'is_open', 'sort'];

    protected function casts(): array
    {
        return ['is_open' => 'boolean', 'sort' => 'integer'];
    }

    /** @return list<string> */
    public function questionList(): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->questions) ?: []), fn ($q) => $q !== ''));

        return array_slice(array_map(fn ($q) => mb_substr($q, 0, 200), $lines), 0, self::MAX_QUESTIONS);
    }

    /** @return HasMany<StaffApplication, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(StaffApplication::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
