<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCommand extends Model
{
    public const PHASES = ['purchase', 'expire', 'refund'];

    protected $fillable = ['product_id', 'game_server_id', 'phase', 'command', 'require_online', 'sort'];

    protected function casts(): array
    {
        return ['require_online' => 'boolean'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<GameServer, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(GameServer::class, 'game_server_id');
    }
}
