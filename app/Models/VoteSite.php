<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoteSite extends Model
{
    protected $fillable = ['name', 'url', 'cooldown_hours', 'sort', 'is_visible'];

    protected function casts(): array
    {
        return ['cooldown_hours' => 'integer', 'sort' => 'integer', 'is_visible' => 'boolean'];
    }
}
