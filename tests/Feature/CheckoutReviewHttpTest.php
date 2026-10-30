<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Commerce\Ledger;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\LegalAcceptance;
use App\Models\Order;
use App\Models\OrderContract;
use Tests\Concerns\CreatesLegalMarkets;
use Tests\TestCase;

class CheckoutReviewHttpTest extends TestCase
{
    use CreatesLegalMarkets;

    private function buyer(bool $cart = true, bool $bilingual = false): array
    {
        $market = $this->reviewedLegalMarket('TR', 'tr', '', $bilingual ? ['en'] : []);
        $buyer = $this->customer();
        app(Settings::class)->set('legal.enforce', true);
        $this->enableSandbox();
        $this->actingAs($buyer);
        if ($cart) {
            $product = $this->product(['summary' => 'Synthetic reviewed product', 'description_markdown' => "## Delivery\n\nOnly a local fixture."]);
            CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'quantity' => 1]);
            app(Ledger::class)->post($buyer->id, 5000, 'adjustment', 'review-http-fixture');
        }

        return [$buyer, $market];
    }

    private function payload($response, string $method): array
    {
        $selection = $response->viewData('legalSelection');
        $review = $response->viewData('review');

        return ['method' => $method, 'terms' => 1, 'legal_country' => $selection['market']->country, 'legal_subdivision' => $selection['market']->subdivision,
            'legal_locale' => $selection['documents']['terms']->locale, 'legal_document_ids' => array_map(fn ($doc) => $doc->id, $selection['documents']),
            'expected_total_minor' => $review->total(), 'expected_review_sha256' => $review->sha256, 'legal_review_token' => $response->viewData('legalReviewToken')];
    }

    public function test_cart_review_shows_actual_coupon_total_and_six_automatic_documents_before_any_order(): void
    {
        [$buyer] = $this->buyer();
        Coupon::create(['code' => 'HTTPQA', 'type' => 'fixed', 'value' => 100, 'max_uses_per_user' => 1, 'is_active' => true]);
        $this->get('/cart')->assertOk()->assertSee(route('checkout.review'), false)->assertDontSee('name="terms"', false);
        $response = $this->get('/checkout/review?method=wallet&coupon=HTTPQA')->assertOk()->assertDontSee('name="country"', false)
            ->assertSee('name="expected_review_sha256"', false)->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->assertSame(1899, $response->viewData('review')->total());
        $this->assertCount(6, $response->viewData('legalSelection')['documents']);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, LegalAcceptance::count());
        $this->post('/checkout', $this->payload($response, 'wallet') + ['coupon' => 'HTTPQA'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(Order::PAID, Order::sole()->status);
        $this->assertSame(3101, app(Ledger::class)->balance($buyer->id));
        $this->assertSame(1, OrderContract::count());
    }

    public function test_topup_review_keeps_amount_method_and_language_automatic_until_explicit_confirmation(): void
    {
        [$buyer] = $this->buyer(false, true);
        $buyer->update(['locale' => 'en']);
        $response = $this->get('/account/topup/review?amount=10.00&method=sandbox')->assertOk()->assertDontSee('name="country"', false);
        $this->assertSame(1000, $response->viewData('review')->total());
        $this->assertSame('en', $response->viewData('legalSelection')['documents']['terms']->locale);
        $this->assertSame('TR', $response->viewData('legalCountry'));
        $this->assertSame('TRY', $response->viewData('details')['currency']);
        $this->assertSame(0, Order::count());
        $this->post('/account/topup', $this->payload($response, 'sandbox') + ['amount' => '10.00'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(Order::PENDING, Order::sole()->status);
        $this->assertSame(0, app(Ledger::class)->balance($buyer->id));
        $this->assertSame(6, LegalAcceptance::count());
    }

    public function test_reviewed_amount_cannot_be_changed_at_confirmation_and_an_unsupported_profile_has_no_confirm_form(): void
    {
        [$buyer, $market] = $this->buyer(false);
        $response = $this->get('/account/topup/review?amount=10.00&method=sandbox')->assertOk();
        $this->post('/account/topup', $this->payload($response, 'sandbox') + ['amount' => '20.00'])->assertSessionHasErrors('expected_total_minor');
        $this->assertSame(0, Order::count());
        $market->forceFill(['is_enabled' => false])->save();
        $this->get('/account/topup/review?amount=10.00&method=sandbox')->assertOk()->assertViewHas('legalSelection', null)->assertDontSee('name="terms"', false);
    }
}
