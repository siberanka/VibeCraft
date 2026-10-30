<?php

namespace App\Http\Controllers;

use App\Core\Settings\Settings;
use App\Domain\Commerce\Checkout;
use App\Domain\Commerce\CommerceException;
use App\Domain\Commerce\OrderConfirmation;
use App\Domain\Commerce\OrderContracts;
use App\Domain\Commerce\OrderLifecycle;
use App\Domain\Commerce\Pricing;
use App\Domain\Commerce\Quote;
use App\Domain\Legal\LegalConsumer;
use App\Domain\Legal\LegalPresentation;
use App\Domain\Payments\Gateways;
use App\Domain\Payments\VerifiedEvent;
use App\Domain\Payments\WebhookInbox;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class CommerceController extends Controller
{
    public function __construct(private Settings $settings, private Gateways $gateways, private LegalConsumer $legal, private OrderContracts $contracts) {}

    private function enabled(): void
    {
        abort_unless($this->settings->bool('modules.store'), 404);
    }

    public function catalog(Request $request)
    {
        $this->enabled();
        $filters = $request->validate(['category' => ['nullable', 'string', 'max:100'], 'q' => ['nullable', 'string', 'max:80']]);
        $products = Product::visible()->with('category')->when($filters['category'] ?? null, fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%'))
            ->orderBy('sort')->orderBy('id')->paginate(12)->withQueryString();

        return view('theme::store.index', ['products' => $products, 'categories' => Category::where('is_visible', true)->orderBy('sort')->get()]);
    }

    public function product(Product $product)
    {
        $this->enabled();
        abort_unless(Product::visible()->whereKey($product->id)->exists(), 404);

        return view('theme::store.product', ['product' => $product->load('category')]);
    }

    public function cart(Request $request, Pricing $pricing)
    {
        $this->enabled();
        $items = $request->user()->cartItems()->with('product.category')->get();
        $cartError = null;
        try {
            $quote = $pricing->quote($request->user(), $items);
        } catch (CommerceException $e) {
            $quote = new Quote([], 0, 0, null);
            $cartError = $e->getMessage();
        }

        return view('theme::cart.index', ['items' => $items, 'quote' => $quote, 'cartError' => $cartError, 'gateways' => $this->gateways->available()]);
    }

    public function add(Request $request)
    {
        $this->enabled();
        $data = $request->validate(['product_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:1', 'max:100']]);
        $product = Product::visible()->findOrFail($data['product_id']);
        DB::transaction(function () use ($request, $product, $data) {
            $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $item = CartItem::firstOrNew(['user_id' => $request->user()->id, 'product_id' => $product->id]);
            $item->quantity = min($product->max_quantity, ($item->exists ? $item->quantity : 0) + $data['quantity']);
            $item->save();
        });

        return redirect()->route('cart.index')->with('status', __('Added to cart.'));
    }

    public function update(Request $request, CartItem $item)
    {
        $this->enabled();
        abort_unless($item->user_id === $request->user()->id, 404);
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:'.max(1, (int) $item->product->max_quantity)]]);
        DB::transaction(function () use ($request, $item, $data) {
            $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $item->update($data);
        });

        return back();
    }

    public function remove(Request $request, CartItem $item)
    {
        abort_unless($item->user_id === $request->user()->id, 404);
        DB::transaction(function () use ($request, $item) {
            $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $item->delete();
        });

        return back();
    }

    private function method(Request $request, bool $wallet = true): string
    {
        $ids = array_map(fn ($g) => $g->id(), $this->gateways->available());
        if ($wallet) {
            $ids[] = 'wallet';
        }

        return $request->validate(['method' => ['required', Rule::in($ids)]])['method'];
    }

    public function checkout(Request $request, Checkout $checkout)
    {
        $this->enabled();
        $method = $this->method($request);
        $data = $request->validate(['coupon' => ['nullable', 'string', 'max:40'], 'gift_to' => ['nullable', 'string', 'max:24'], 'terms' => ['accepted']]);
        $confirmation = $this->legal->enforced() || $request->filled('legal_review_token') ? OrderConfirmation::fromRequest($request, 'checkout') : null;
        try {
            $order = $checkout->placeOrder($request->user(), $data['coupon'] ?? null, $method, $request->ip(), $confirmation, $data['gift_to'] ?? null);
        } catch (CommerceException $e) {
            throw ValidationException::withMessages(['checkout' => $e->getMessage()]);
        }

        if ($confirmation) {
            app(LegalPresentation::class)->forget($request, 'checkout');
        }

        return redirect()->route('orders.show', $order);
    }

    public function topup(Request $request, Checkout $checkout)
    {
        $this->enabled();
        $method = $this->method($request, false);
        $data = $request->validate(['amount' => ['required', 'string', 'max:15']]);
        $minor = Money::parse($data['amount']);
        if ($minor === null) {
            throw ValidationException::withMessages(['amount' => __('Enter a valid amount.')]);
        }
        $confirmation = $this->legal->enforced() || $request->filled('legal_review_token') ? OrderConfirmation::fromRequest($request, 'topup') : null;
        try {
            $order = $checkout->createTopup($request->user(), $minor, $method, $request->ip(), $confirmation);
        } catch (CommerceException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        if ($confirmation) {
            app(LegalPresentation::class)->forget($request, 'topup');
        }

        return redirect()->route('orders.show', $order);
    }

    public function orders(Request $request)
    {
        return view('theme::orders.index', ['orders' => $request->user()->orders()->latest('id')->paginate(15)]);
    }

    private function owns(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 404);
    }

    public function order(Request $request, Order $order)
    {
        $this->owns($request, $order);
        $required = $this->contracts->requiredFor($order);
        $ready = ! $required || $this->contracts->validFor($order);
        $gateways = array_values(array_filter($this->gateways->available(), fn ($gateway) => ! $required || $gateway->id() === $order->payment_method));

        return view('theme::orders.show', ['order' => $order->load(['items', 'payments', 'deliveries']), 'gateways' => $gateways,
            'paymentReviewReady' => $ready, 'originalContract' => $this->contracts->original($order)]);
    }

    public function returned(Request $request, Order $order)
    {
        $this->owns($request, $order);

        // Browser return parameters are never payment evidence.
        return redirect()->route('orders.show', $order);
    }

    public function pay(Request $request, Order $order, Checkout $checkout): Response
    {
        $this->enabled();
        $this->owns($request, $order);
        abort_unless($order->isPending() && $order->expires_at?->isFuture(), 409);
        $gateway = $this->gateways->get($this->method($request, false));
        $payment = DB::transaction(function () use ($order, $gateway, $checkout) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isPending() && $locked->expires_at?->isFuture(), 409);
            $checkout->assertPaymentReview($locked);
            if ($this->contracts->requiredFor($locked) && $gateway->id() !== $locked->payment_method) {
                throw ValidationException::withMessages(['payment' => __('Choose the payment method used in your order review.')]);
            }

            return $locked->payments()->where('provider', $gateway->id())->whereIn('status', ['created', 'pending'])->latest('id')->first()
                ?? $checkout->createPayment($locked, $gateway->id());
        });
        try {
            return $gateway->begin($order, $payment, $request);
        } catch (CommerceException $e) {
            throw ValidationException::withMessages(['payment' => $e->getMessage()]);
        }
    }

    public function cancel(Request $request, Order $order, OrderLifecycle $lifecycle)
    {
        $this->owns($request, $order);
        $lifecycle->cancel($order, 'customer_cancelled');

        return back();
    }

    private function sandboxPayment(Request $request, string $reference): Payment
    {
        abort_if(app()->isProduction(), 404);
        abort_unless($this->gateways->get('sandbox')?->isAvailable(), 404);

        return Payment::where('provider', 'sandbox')->where('provider_reference', $reference)
            ->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->id))->firstOrFail();
    }

    public function sandbox(Request $request, string $reference)
    {
        $payment = $this->sandboxPayment($request, $reference);

        return view('theme::checkout.sandbox', ['payment' => $payment, 'order' => $payment->order]);
    }

    public function sandboxConfirm(Request $request, string $reference, WebhookInbox $inbox)
    {
        $payment = $this->sandboxPayment($request, $reference);
        abort_unless($payment->order->isPending(), 409);
        $event = new VerifiedEvent('sandbox', 'local_'.Str::ulid(), 'payment.succeeded', VerifiedEvent::SUCCEEDED,
            $reference, 'sbx_'.substr(hash('sha256', $reference), 0, 16), $payment->amount_minor, $payment->currency, []);
        $inbox->accept($this->gateways->get('sandbox'), $event);

        return redirect()->route('orders.show', $payment->order);
    }
}
