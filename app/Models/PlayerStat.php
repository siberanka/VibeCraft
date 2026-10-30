<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerStat extends Model
{
    public const STATS = ['playtime_minutes', 'kills', 'deaths', 'mob_kills'];

    const CREATED_AT = null;

    protected $fillable = ['game_server_id', 'player_uuid', 'player_name', 'stat', 'value'];

    protected function casts(): array
    {
        return ['value' => 'integer'];
    }
}
