<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class WalletEntry extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'amount_minor', 'balance_after_minor', 'type', 'idempotency_key', 'order_id', 'actor_id', 'note'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'balance_after_minor' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // Ledger rows are immutable; corrections are posted as new reversal entries.
        static::updating(fn () => throw new LogicException('Wallet entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Wallet entries are immutable.'));
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
