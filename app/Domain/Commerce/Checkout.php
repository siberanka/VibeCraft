<?php

namespace App\Domain\Commerce;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\Settings;
use App\Domain\Legal\LegalConsumer;
use App\Domain\Payments\Gateways;
use App\Models\CartItem;
use App\Models\CouponRedemption;
use App\Models\CreditPackage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates orders from the cart. Stock and coupon usage are reserved inside the same
 * transaction that creates the order and are released if the order is cancelled.
 */
class Checkout
{
    public function __construct(
        private Pricing $pricing,
        private Ledger $ledger,
        private OrderLifecycle $lifecycle,
        private AuditLogger $audit,
        private LegalConsumer $legal,
        private OrderContracts $contracts,
    ) {}

    public function placeOrder(User $user, ?string $couponCode, string $method, ?string $ip, ?OrderConfirmation $confirmation = null, ?string $giftTo = null): Order
    {
        $this->requireConfirmation($user, 'checkout', $confirmation);
        $this->assertMethod($method);
        if (! app(Settings::class)->bool('modules.store')) {
            throw new CommerceException(__('The store is currently closed.'));
        }
        $order = DB::transaction(function () use ($user, $couponCode, $method, $ip, $confirmation, $giftTo) {
            // The same user lock must be taken by cart writers. It also serializes
            // per-user limits and two simultaneous checkouts of the same cart.
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->isBanned()) {
                throw new CommerceException(__('This account is suspended.'));
            }
            if ($confirmation && ($prior = $this->contracts->previous($user, $confirmation, $method, $couponCode))) {
                return $prior;
            }
            $items = CartItem::query()->where('user_id', $user->id)->orderBy('product_id')->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw new CommerceException(__('Your cart is empty.'));
            }

            // Lock product rows in a stable order to avoid deadlocks between concurrent checkouts.
            $products = Product::query()->whereIn('id', $items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $items->each(fn (CartItem $i) => $i->setRelation('product', $products->get($i->product_id)));

            $quote = $this->pricing->quote($user, $items, $couponCode, lockCoupon: true);
            if ($couponCode !== null && trim($couponCode) !== '' && $quote->couponError !== null) {
                throw new CommerceException($quote->couponError);
            }
            if ($quote->isEmpty()) {
                throw new CommerceException(__('Your cart is empty.'));
            }

            $recipient = GiftRecipient::resolve($user, $giftTo);
            $identity = ($recipient ?? $user)->primaryIdentity();
            foreach ($quote->lines as $line) {
                $this->assertPurchasable($user, $line['product'], $line['quantity'], $identity !== null);
            }
            $review = $confirmation ? OrderReview::purchase($user, $quote, $method, $identity, $recipient) : null;
            if ($confirmation && $review) {
                $review->assertMatches($confirmation->submission);
            }

            $order = Order::query()->create([
                'user_id' => $user->id,
                'gift_recipient_id' => $recipient?->id,
                'kind' => 'purchase',
                'status' => Order::PENDING,
                'fulfillment_status' => 'not_started',
                'currency' => Money::currency(),
                'subtotal_minor' => $quote->subtotal,
                'discount_minor' => $quote->discount,
                'total_minor' => $quote->total(),
                'coupon_id' => $quote->coupon?->id,
                'payment_method' => $method,
                'ip' => $ip,
                'expires_at' => now()->addMinutes((int) config('vibecraft.store.order_ttl_minutes')),
            ]);

            foreach ($quote->lines as $line) {
                $product = $line['product'];
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'held_in_chest' => (bool) $product->chest_delivery,
                    'unit_price_minor' => $line['unit'],
                    'quantity' => $line['quantity'],
                    'total_minor' => $line['total'],
                    'duration_days' => $product->duration_days,
                    'player_uuid' => $identity?->uuid,
                    'player_name' => $identity?->username,
                ]);
                if ($product->stock !== null) {
                    $product->decrement('stock', $line['quantity']);
                }
            }

            if ($quote->coupon !== null) {
                $quote->coupon->increment('uses');
                CouponRedemption::query()->create([
                    'coupon_id' => $quote->coupon->id, 'user_id' => $user->id, 'order_id' => $order->id, 'discount_minor' => $quote->discount,
                ]);
            }

            CartItem::query()->where('user_id', $user->id)->delete();

            if ($confirmation) {
                $this->contracts->capture($order, $user, $review, $confirmation);
            }

            if ($method === 'wallet' || $order->total_minor === 0) {
                if ($order->total_minor > 0) {
                    $this->ledger->post($user->id, -$order->total_minor, 'purchase', "order:{$order->id}:pay", $order->id);
                }
                $this->lifecycle->markPaid($order, $order->total_minor === 0 ? 'free' : 'wallet', null, $order->total_minor, $order->currency);
            }

            return $order;
        }, 3);

        if ($order->wasRecentlyCreated) {
            $this->audit->log('order.created', $order, ['total' => $order->total_minor, 'method' => $method], $user->id);
        }

        return $order->fresh();
    }

    public function createTopup(User $user, int $amountMinor, string $method, ?string $ip, ?OrderConfirmation $confirmation = null): Order
    {
        $this->requireConfirmation($user, 'topup', $confirmation);
        $this->assertMethod($method, topup: true);
        if (! app(Settings::class)->bool('modules.store') || $user->isBanned()) {
            throw new CommerceException(__('The store is currently unavailable.'));
        }
        $min = (int) config('vibecraft.store.min_topup_minor');
        $max = (int) config('vibecraft.store.max_topup_minor');
        if ($amountMinor < $min || $amountMinor > $max) {
            throw new CommerceException(__('Top-up amount must be between :min and :max.', ['min' => Money::format($min), 'max' => Money::format($max)]));
        }

        return DB::transaction(function () use ($user, $amountMinor, $method, $ip, $confirmation) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->isBanned()) {
                throw new CommerceException(__('This account is suspended.'));
            }
            $review = $confirmation ? OrderReview::topup($user, $amountMinor, $method) : null;
            if ($confirmation && $review) {
                $review->assertMatches($confirmation->submission);
            }
            if ($confirmation && ($prior = $this->contracts->previous($user, $confirmation, $method))) {
                return $prior;
            }
            $order = Order::query()->create([
                'user_id' => $user->id,
                'kind' => 'topup',
                'status' => Order::PENDING,
                'fulfillment_status' => 'not_required',
                'currency' => Money::currency(),
                'subtotal_minor' => $amountMinor,
                'discount_minor' => 0,
                'total_minor' => $amountMinor,
                'bonus_minor' => $review ? (int) $review->payload()['bonus_minor'] : CreditPackage::bonusFor($amountMinor),
                'payment_method' => $method,
                'ip' => $ip,
                'expires_at' => now()->addMinutes((int) config('vibecraft.store.order_ttl_minutes')),
            ]);
            if ($confirmation) {
                $this->contracts->capture($order, $user, $review, $confirmation);
            }

            return $order->fresh();
        }, 3);
    }

    public function createPayment(Order $order, string $provider): Payment
    {
        $this->assertPaymentReview($order);
        if ($this->contracts->requiredFor($order) && $provider !== $order->payment_method) {
            throw ValidationException::withMessages(['payment' => __('Choose the payment method used in your order review.')]);
        }
        $this->assertMethod($provider, topup: true);
        if (! $order->isPending() || $order->total_minor <= 0) {
            throw new CommerceException(__('This order cannot start a payment.'));
        }

        return Payment::query()->create([
            'order_id' => $order->id,
            'provider' => $provider,
            // Alphanumeric reference: some providers (PayTR) reject separators.
            'provider_reference' => 'VC'.strtoupper(Str::random(4)).$order->public_id,
            'amount_minor' => $order->total_minor,
            'currency' => $order->currency,
            'status' => 'created',
        ]);
    }

    public function assertPaymentReview(Order $order): void
    {
        if ($this->contracts->requiredFor($order) && ! $this->contracts->validFor($order)) {
            throw ValidationException::withMessages(['payment' => __('This order needs a valid reviewed contract before payment.')]);
        }
    }

    private function requireConfirmation(User $buyer, string $context, ?OrderConfirmation $confirmation): void
    {
        if ($this->legal->enforced() && $confirmation === null) {
            throw ValidationException::withMessages(['checkout' => __('Review the current products, total and terms before placing an order.')]);
        }
        $confirmation?->assertCustomer($buyer, $context);
    }

    private function assertPurchasable(User $user, Product $product, int $qty, bool $hasIdentity): void
    {
        if (! $product->is_visible || ! $product->category?->is_visible) {
            throw new CommerceException(__(':product is no longer available.', ['product' => $product->name]));
        }
        if ($product->stock !== null && $product->stock < $qty) {
            throw new CommerceException(__(':product is out of stock.', ['product' => $product->name]));
        }
        if ($product->requires_link && ! $hasIdentity) {
            throw new CommerceException(__('Link your Minecraft account before buying :product.', ['product' => $product->name]));
        }
        if ($product->per_user_limit !== null) {
            $bought = OrderItem::query()
                ->where('product_id', $product->id)
                ->whereHas('order', fn ($q) => $q->where('user_id', $user->id)->whereIn('status', [Order::PENDING, Order::PAID]))
                ->sum('quantity');
            if ($bought + $qty > $product->per_user_limit) {
                throw new CommerceException(__('Purchase limit reached for :product.', ['product' => $product->name]));
            }
        }
    }

    private function assertMethod(string $method, bool $topup = false): void
    {
        if ($method === 'wallet' && ! $topup) {
            return;
        }
        $gateway = app(Gateways::class)->get($method);
        if ($gateway === null || ! $gateway->isAvailable()) {
            throw new CommerceException(__('This payment method is not available.'));
        }
    }
}
