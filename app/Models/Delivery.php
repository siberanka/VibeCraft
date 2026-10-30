<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $available_at
 * @property Carbon|null $lease_expires_at
 * @property Carbon|null $delivered_at
 * @property string|null $lease_token
 */
class Delivery extends Model
{
    use HasUlids;

    public const QUEUED = 'queued';

    public const LEASED = 'leased';

    public const DELIVERED = 'delivered';

    public const DEAD = 'dead';

    public const CANCELLED = 'cancelled';

    public const MANUAL_REVIEW = 'manual_review';

    protected $fillable = [
        'order_id', 'order_item_id', 'product_command_id', 'game_server_id', 'user_id', 'phase', 'player_uuid', 'player_name',
        'command', 'require_online', 'status', 'attempts', 'max_attempts', 'available_at', 'lease_expires_at', 'delivered_at', 'last_error', 'dedup_key',
    ];

    protected $hidden = ['lease_token'];

    protected function casts(): array
    {
        return [
            'require_online' => 'boolean',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'available_at' => 'datetime',
            'lease_expires_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /** @return BelongsTo<GameServer, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(GameServer::class, 'game_server_id');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
