<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Badge extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'description', 'sort'];

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['granted_by', 'created_at']);
    }

    /** The colour is used only through a fixed allowlist of classes, never as inline CSS. */
    public function tone(): string
    {
        $palette = ['#3db7a6' => 'teal', '#ff754a' => 'orange', '#d4b273' => 'gold', '#8e7cc3' => 'violet', '#e05a6b' => 'red', '#5dade2' => 'blue', '#87d5ad' => 'green'];

        return $palette[strtolower($this->color)] ?? 'teal';
    }
}
