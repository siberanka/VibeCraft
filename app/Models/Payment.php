<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['order_id', 'provider', 'provider_reference', 'provider_payment_id', 'amount_minor', 'currency', 'status', 'failure_reason', 'meta', 'succeeded_at'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'amount_minor' => 'integer', 'succeeded_at' => 'datetime'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
