<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Punishment extends Model
{
    const CREATED_AT = null;

    protected $fillable = ['game_server_id', 'player_uuid', 'player_name', 'type', 'reason', 'staff', 'issued_at', 'expires_at', 'active'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'expires_at' => 'datetime', 'active' => 'boolean'];
    }

    /** @return BelongsTo<GameServer, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(GameServer::class, 'game_server_id');
    }
}
