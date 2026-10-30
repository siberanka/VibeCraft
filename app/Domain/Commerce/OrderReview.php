<?php

namespace App\Domain\Commerce;

use App\Domain\Legal\LegalSubmission;
use App\Models\CreditPackage;
use App\Models\GameIdentity;
use App\Models\User;
use App\Support\CanonicalJson;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/** Immutable review of server-calculated facts; no client price, exchange rate or legal conclusion. */
final readonly class OrderReview
{
    public string $sha256;

    private function __construct(private array $data, private array $plans = [])
    {
        $encoded = CanonicalJson::encode($data);
        if (strlen($encoded) > 2_000_000) {
            throw new CommerceException(__('This order is too large to review. Reduce the number of products.'));
        }
        $this->sha256 = hash('sha256', $encoded);
    }

    public static function purchase(User $buyer, Quote $quote, string $method, ?GameIdentity $identity, ?User $recipient = null): self
    {
        self::method($method);
        if ($quote->isEmpty() || count($quote->lines) > 100 || $quote->couponError !== null) {
            throw new CommerceException($quote->couponError ?? __('Review a cart containing between one and one hundred products.'));
        }
        $target = null;
        if ($identity) {
            if (! $identity->exists || (int) $identity->user_id !== (int) ($recipient ?? $buyer)->id || $identity->getAttribute('verified_at') === null) {
                throw new CommerceException(__('Review the verified Minecraft account linked to your own account.'));
            }
            $target = ['id' => (int) $identity->id, 'edition' => (string) $identity->edition,
                'uuid' => $identity->uuid, 'username' => (string) $identity->username];
        }
        $lines = [];
        $subtotal = 0;
        $seen = [];
        $plans = [];
        foreach ($quote->lines as $line) {
            $product = $line['product'];
            $id = (int) $product->id;
            $quantity = $line['quantity'];
            if ($id < 1 || isset($seen[$id]) || $quantity < 1 || $quantity > Pricing::MAX_QUANTITY
                || $line['unit'] !== $product->price_minor || $line['unit'] < 0 || $line['unit'] > intdiv(Pricing::MAX_TOTAL_MINOR, $quantity)
                || $line['total'] !== $line['unit'] * $quantity) {
                throw new CommerceException(__('The order review could not be calculated. Reload your cart.'));
            }
            if ($product->requires_link && $target === null) {
                throw new CommerceException(__('Link your Minecraft account before buying :product.', ['product' => $product->name]));
            }
            $seen[$id] = true;
            $plans[$id] = ExecutionPlans::product($product);
            $subtotal += $line['total'];
            if ($subtotal > Pricing::MAX_TOTAL_MINOR) {
                throw new CommerceException(__('The cart total exceeds the supported limit.'));
            }
            $lines[] = ['product_id' => $id, 'name' => (string) $product->name, 'summary' => (string) $product->summary,
                'description_markdown' => (string) $product->description_markdown, 'duration_days' => $product->duration_days,
                'requires_link' => $product->requires_link, 'quantity' => $quantity, 'unit_price_minor' => $line['unit'], 'total_minor' => $line['total'],
                'delivery_definition_sha256' => CanonicalJson::digest($plans[$id])];
        }
        if ($quote->subtotal !== $subtotal || $quote->discount < 0 || $quote->discount > $subtotal) {
            throw new CommerceException(__('The order review could not be calculated. Reload your cart.'));
        }
        usort($lines, static fn (array $a, array $b) => $a['product_id'] <=> $b['product_id']);

        if ($recipient !== null && ((int) $recipient->id === (int) $buyer->id || $target === null)) {
            throw new CommerceException(__('Review the verified Minecraft account linked to your own account.'));
        }
        $data = ['format' => 1, 'context' => 'checkout', 'buyer_id' => (int) $buyer->id,
            'currency' => self::currency(), 'method' => $method, 'minecraft_target' => $target, 'lines' => $lines,
            'coupon' => $quote->coupon ? ['id' => (int) $quote->coupon->id, 'code' => (string) $quote->coupon->getAttribute('code')] : null,
            'subtotal_minor' => $subtotal, 'discount_minor' => $quote->discount, 'total_minor' => $quote->total()];
        if ($recipient !== null) {
            $data['gift_recipient'] = ['id' => (int) $recipient->id, 'name' => (string) $recipient->name];
        }

        return new self($data, $plans);
    }

    public static function topup(User $buyer, int $amountMinor, string $method): self
    {
        self::method($method);
        if ($method === 'wallet' || $amountMinor < 1 || $amountMinor > Pricing::MAX_TOTAL_MINOR) {
            throw new CommerceException(__('Enter a valid top-up amount and payment method.'));
        }

        return new self(['format' => 1, 'context' => 'topup', 'buyer_id' => (int) $buyer->id,
            'currency' => self::currency(), 'method' => $method, 'minecraft_target' => null, 'lines' => [],
            'coupon' => null, 'subtotal_minor' => $amountMinor, 'discount_minor' => 0, 'total_minor' => $amountMinor,
            'bonus_minor' => CreditPackage::bonusFor($amountMinor)]);
    }

    public function payload(): array
    {
        return $this->data;
    }

    /** Internal service use only; customer-facing payload contains a digest, never command text. */
    public function executionPlans(): array
    {
        return $this->plans;
    }

    public function assertMatches(LegalSubmission $submission): void
    {
        if ($submission->expectedTotalMinor !== $this->total()) {
            throw ValidationException::withMessages(['expected_total_minor' => __('The total changed. Review the order before confirming it.')]);
        }
        if ($submission->expectedReviewSha256 === null || ! hash_equals($this->sha256, $submission->expectedReviewSha256)) {
            throw ValidationException::withMessages(['expected_review_sha256' => __('Your cart or delivery details changed. Review the order again.')]);
        }
    }

    public function total(): int
    {
        return $this->data['total_minor'];
    }

    public function context(): string
    {
        return $this->data['context'];
    }

    private static function currency(): string
    {
        $currency = Money::currency();
        if (! preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw new CommerceException(__('The store currency is not configured correctly.'));
        }

        return $currency;
    }

    private static function method(string $method): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $method)) {
            throw new CommerceException(__('This payment method is not available.'));
        }
    }
}
