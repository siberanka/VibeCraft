<?php

namespace App\Domain\Commerce;

use App\Models\Coupon;
use App\Models\Product;

final class Quote
{
    /**
     * @param  list<array{product: Product, quantity: int, unit: int, total: int}>  $lines
     */
    public function __construct(
        public readonly array $lines,
        public readonly int $subtotal,
        public readonly int $discount,
        public readonly ?Coupon $coupon,
        public readonly ?string $couponError = null,
    ) {}

    public function total(): int
    {
        return max(0, $this->subtotal - $this->discount);
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }
}
