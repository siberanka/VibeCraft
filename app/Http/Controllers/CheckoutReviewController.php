<?php

namespace App\Http\Controllers;

use App\Core\Settings\Settings;
use App\Domain\Commerce\CommerceException;
use App\Domain\Commerce\GiftRecipient;
use App\Domain\Commerce\OrderReview;
use App\Domain\Commerce\Pricing;
use App\Domain\Content\WikiRenderer;
use App\Domain\Payments\Gateways;
use App\Models\CreditPackage;
use App\Support\Money;
use App\View\LegalSelection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutReviewController extends Controller
{
    public function __construct(private Settings $settings, private Gateways $gateways, private LegalSelection $selection) {}

    public function cart(Request $request, Pricing $pricing): View
    {
        $this->enabled();
        $methods = $this->methods(true);
        $data = $request->validate(['method' => ['nullable', Rule::in(array_keys($methods))], 'coupon' => ['nullable', 'string', 'max:40'], 'gift_to' => ['nullable', 'string', 'max:24']]);
        $method = $data['method'] ?? 'wallet';
        $coupon = $data['coupon'] ?? null;
        $giftTo = trim((string) ($data['gift_to'] ?? ''));
        $buyer = $request->user();
        $buyer->unsetRelation('identities');
        try {
            $recipient = GiftRecipient::resolve($buyer, $giftTo);
        } catch (CommerceException $exception) {
            throw ValidationException::withMessages(['gift_to' => $exception->getMessage()]);
        }
        try {
            $quote = $pricing->quote($buyer, $buyer->cartItems()->with('product.category')->get(), $coupon);
            $review = OrderReview::purchase($buyer, $quote, $method, ($recipient ?? $buyer)->primaryIdentity(), $recipient);
        } catch (CommerceException $exception) {
            throw ValidationException::withMessages(['checkout' => $exception->getMessage()]);
        }
        $rendered = [];
        foreach ($review->payload()['lines'] as $line) {
            try {
                $rendered[$line['product_id']] = $line['description_markdown'] === '' ? null : WikiRenderer::render($line['description_markdown'], app()->getLocale(), 'review-product-'.$line['product_id'].'-');
            } catch (ValidationException) {
                throw ValidationException::withMessages(['checkout' => __('A product description needs review before this order can be confirmed.')]);
            }
        }

        return view('theme::checkout.review', $this->selection->forForm($request, 'checkout', $review) + [
            'review' => $review, 'details' => $review->payload(), 'methodLabel' => $methods[$method], 'renderedDescriptions' => $rendered,
            'legalFormAction' => route('checkout.review'), 'legalQueryFields' => ['method' => $method, 'coupon' => $coupon ?? '', 'gift_to' => $giftTo],
            'confirmAction' => route('checkout.store'), 'postFields' => ['method' => $method, 'coupon' => $coupon ?? '', 'gift_to' => $giftTo],
            'returnUrl' => route('cart.index'), 'legalAgreementContext' => 'checkout',
        ]);
    }

    public function topup(Request $request): View
    {
        $this->enabled();
        $methods = $this->methods(false);
        $data = $request->validate(['method' => ['required', Rule::in(array_keys($methods))], 'amount' => ['nullable', 'required_without:package', 'string', 'max:15'], 'package' => ['nullable', 'integer']]);
        $package = ! filled($data['amount'] ?? null) && isset($data['package']) ? CreditPackage::query()->where('is_visible', true)->find($data['package']) : null;
        $amount = $package ? $package->price_minor : Money::parse((string) ($data['amount'] ?? ''));
        $min = (int) config('vibecraft.store.min_topup_minor');
        $max = (int) config('vibecraft.store.max_topup_minor');
        if ($amount === null || $amount < $min || $amount > $max) {
            throw ValidationException::withMessages(['amount' => __('Top-up amount must be between :min and :max.', ['min' => Money::format($min), 'max' => Money::format($max)])]);
        }
        $review = OrderReview::topup($request->user(), $amount, $data['method']);
        $fields = ['method' => $data['method'], 'amount' => Money::toDecimalString($amount)];

        return view('theme::checkout.review', $this->selection->forForm($request, 'topup', $review) + [
            'review' => $review, 'details' => $review->payload(), 'methodLabel' => $methods[$data['method']], 'renderedDescriptions' => [],
            'legalFormAction' => route('account.topup.review'), 'legalQueryFields' => $fields,
            'confirmAction' => route('account.topup'), 'postFields' => $fields,
            'returnUrl' => route('account.show'), 'legalAgreementContext' => 'topup',
        ]);
    }

    private function methods(bool $wallet): array
    {
        $methods = $wallet ? ['wallet' => __('Account balance')] : [];
        foreach ($this->gateways->available() as $gateway) {
            $methods[$gateway->id()] = $gateway->label();
        }

        return $methods;
    }

    private function enabled(): void
    {
        abort_unless($this->settings->bool('modules.store'), 404);
    }
}
