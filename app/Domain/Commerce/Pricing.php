<?php

namespace App\Domain\Commerce;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Server-side price calculation. Prices always come from the database at the
 * moment of calculation — never from the client.
 */
class Pricing
{
    public const MAX_QUANTITY = 100;

    public const MAX_TOTAL_MINOR = 9_000_000_000_000;

    /** @param Collection<int, CartItem> $items */
    public function quote(User $user, Collection $items, ?string $couponCode = null, bool $lockCoupon = false): Quote
    {
        $lines = [];
        $subtotal = 0;
        foreach ($items as $item) {
            $product = $item->product;
            if ($product === null || ! $product->is_visible || ! $product->category?->is_visible) {
                throw new CommerceException(__('A product in your cart is no longer available.'));
            }
            $qty = $item->quantity;
            if ($qty < 1 || $qty > min(self::MAX_QUANTITY, (int) $product->max_quantity)) {
                throw new CommerceException(__('The product quantity is not valid.'));
            }
            if ($product->price_minor < 0 || $product->price_minor > intdiv(self::MAX_TOTAL_MINOR, $qty)) {
                throw new CommerceException(__('The product price is not valid.'));
            }
            $total = $product->price_minor * $qty;
            $lines[] = ['product' => $product, 'quantity' => $qty, 'unit' => $product->price_minor, 'total' => $total];
            $subtotal += $total;
            if ($subtotal > self::MAX_TOTAL_MINOR) {
                throw new CommerceException(__('The cart total exceeds the supported limit.'));
            }
        }

        $coupon = null;
        $discount = 0;
        $error = null;
        $code = $couponCode !== null ? mb_strtoupper(trim($couponCode)) : '';
        if ($code !== '') {
            $query = Coupon::query()->where('code', $code);
            if ($lockCoupon) {
                $query->lockForUpdate();
            }
            $coupon = $query->first();
            [$discount, $error] = $coupon === null
                ? [0, __('Coupon code is not valid.')]
                : $this->couponDiscount($coupon, $user, $lines, $subtotal);
            if ($error !== null) {
                $coupon = null;
            }
        }

        return new Quote($lines, $subtotal, $discount, $coupon, $error);
    }

    /**
     * @param  list<array{product: \App\Models\Product, quantity: int, unit: int, total: int}>  $lines
     * @return array{0: int, 1: ?string}
     */
    public function couponDiscount(Coupon $coupon, User $user, array $lines, int $subtotal): array
    {
        if (! in_array($coupon->type, ['fixed', 'percent'], true) || $coupon->value < 0 || ($coupon->type === 'percent' && $coupon->value > 10000)) {
            return [0, __('Coupon code is not valid.')];
        }
        $now = now();
        if (! $coupon->is_active || ($coupon->starts_at && $coupon->starts_at->gt($now)) || ($coupon->ends_at && $coupon->ends_at->lt($now))) {
            return [0, __('Coupon code is not active.')];
        }
        if ($coupon->max_uses !== null && $coupon->uses >= $coupon->max_uses) {
            return [0, __('Coupon code has reached its usage limit.')];
        }
        $usedByUser = CouponRedemption::query()
            ->where('coupon_id', $coupon->id)
            ->where('user_id', $user->id)
            ->whereHas('order', fn ($q) => $q->whereIn('status', [Order::PENDING, Order::PAID]))
            ->count();
        if ($usedByUser >= max(1, (int) $coupon->max_uses_per_user)) {
            return [0, __('You have already used this coupon.')];
        }
        if ($subtotal < $coupon->min_subtotal_minor) {
            return [0, __('Cart total is below the coupon minimum.')];
        }

        $categoryIds = array_map('intval', (array) ($coupon->category_ids ?? []));
        $productIds = array_map('intval', (array) ($coupon->product_ids ?? []));
        $eligible = 0;
        foreach ($lines as $line) {
            $p = $line['product'];
            $restricted = $categoryIds !== [] || $productIds !== [];
            if (! $restricted || in_array($p->id, $productIds, true) || in_array($p->category_id, $categoryIds, true)) {
                $eligible += $line['total'];
            }
        }
        if ($eligible === 0) {
            return [0, __('Coupon code does not apply to these products.')];
        }

        $discount = $coupon->type === 'percent'
            ? intdiv($eligible * max(0, min(10000, $coupon->value)), 10000)
            : max(0, $coupon->value);
        if ($coupon->max_discount_minor !== null) {
            $discount = min($discount, max(0, $coupon->max_discount_minor));
        }

        return [min($discount, $eligible), null];
    }
}
