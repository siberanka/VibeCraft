<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $bridge_last_seen_at
 * @property Carbon|null $status_checked_at
 */
class GameServer extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'java_address', 'bedrock_address', 'bedrock_port',
        'query_host', 'query_port', 'query_edition', 'show_on_site', 'sort', 'bridge_enabled',
    ];

    protected $hidden = ['bridge_secret'];

    protected function casts(): array
    {
        return [
            'bridge_secret' => 'encrypted',
            'bridge_enabled' => 'boolean',
            'show_on_site' => 'boolean',
            'status_online' => 'boolean',
            'bridge_last_seen_at' => 'datetime',
            'status_checked_at' => 'datetime',
        ];
    }

    /** @return HasMany<Delivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function bridgeIsHealthy(): bool
    {
        return $this->bridge_last_seen_at !== null && $this->bridge_last_seen_at->gt(now()->subMinutes(3));
    }
}
