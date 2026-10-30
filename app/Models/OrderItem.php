<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'product_name', 'unit_price_minor', 'quantity', 'total_minor', 'duration_days', 'player_uuid', 'player_name', 'held_in_chest', 'chest_claimed_at'];

    protected function casts(): array
    {
        return ['held_in_chest' => 'boolean', 'chest_claimed_at' => 'datetime', 'unit_price_minor' => 'integer', 'total_minor' => 'integer', 'quantity' => 'integer', 'duration_days' => 'integer'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
