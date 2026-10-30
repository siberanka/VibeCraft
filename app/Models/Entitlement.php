<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $starts_at
 * @property Carbon|null $expires_at
 */
class Entitlement extends Model
{
    protected $fillable = ['user_id', 'product_id', 'order_item_id', 'starts_at', 'expires_at', 'status', 'auto_renew', 'renew_price_minor', 'renewals'];

    protected function casts(): array
    {
        return ['auto_renew' => 'boolean', 'renew_price_minor' => 'integer', 'renewals' => 'integer', 'starts_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
