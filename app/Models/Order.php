<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $paid_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $refunded_at
 * @property Carbon|null $expires_at
 */
class Order extends Model
{
    use HasUlids;

    public const PENDING = 'pending_payment';

    public const PAID = 'paid';

    public const CANCELLED = 'cancelled';

    public const REFUNDED = 'refunded';

    public const DISPUTED = 'disputed';

    protected $fillable = [
        'user_id', 'gift_recipient_id', 'kind', 'status', 'fulfillment_status', 'currency', 'subtotal_minor', 'discount_minor',
        'total_minor', 'bonus_minor', 'coupon_id', 'payment_method', 'ip', 'paid_at', 'cancelled_at', 'refunded_at', 'expires_at', 'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'total_minor' => 'integer',
            'bonus_minor' => 'integer',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** Only the public ULID is generated; the numeric id stays the primary key. */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<User, $this> */
    public function giftRecipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gift_recipient_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<Delivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }
}
