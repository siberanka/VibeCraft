<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'min_subtotal_minor', 'max_discount_minor', 'max_uses', 'max_uses_per_user',
        'category_ids', 'product_ids', 'starts_at', 'ends_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'min_subtotal_minor' => 'integer',
            'max_discount_minor' => 'integer',
            'category_ids' => 'array',
            'product_ids' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected function code(): Attribute
    {
        return Attribute::set(fn (string $value) => mb_strtoupper(trim($value)));
    }
}
