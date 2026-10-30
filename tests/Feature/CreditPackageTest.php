<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Commerce\Ledger;
use App\Domain\Commerce\OrderLifecycle;
use App\Models\CreditPackage;
use App\Models\Order;
use App\Models\WalletEntry;
use Tests\Concerns\CreatesLegalMarkets;
use Tests\TestCase;

class CreditPackageTest extends TestCase
{
    use CreatesLegalMarkets;

    private function buyer()
    {
        $this->reviewedLegalMarket('TR', 'tr');
        $buyer = $this->customer();
        app(Settings::class)->set('legal.enforce', true);
        $this->enableSandbox();
        $this->actingAs($buyer);

        return $buyer;
    }

    private function payload($response): array
    {
        $selection = $response->viewData('legalSelection');
        $review = $response->viewData('review');

        return ['method' => 'sandbox', 'terms' => 1, 'legal_country' => $selection['market']->country, 'legal_subdivision' => $selection['market']->subdivision,
            'legal_locale' => $selection['documents']['terms']->locale, 'legal_document_ids' => array_map(fn ($doc) => $doc->id, $selection['documents']),
            'expected_total_minor' => $review->total(), 'expected_review_sha256' => $review->sha256, 'legal_review_token' => $response->viewData('legalReviewToken')];
    }

    public function test_package_bonus_is_reviewed_credited_once_and_reversed_on_refund(): void
    {
        $buyer = $this->buyer();
        $package = CreditPackage::query()->create(['name' => 'Starter', 'price_minor' => 10000, 'bonus_minor' => 1500]);
        $this->get(route('account.show'))->assertOk()->assertSee('Starter');

        $response = $this->get('/account/topup/review?package='.$package->id.'&method=sandbox')->assertOk();
        $this->assertSame(1500, $response->viewData('details')['bonus_minor']);
        $this->assertSame(10000, $response->viewData('review')->total());
        $this->post('/account/topup', $this->payload($response) + ['amount' => '100.00'])->assertSessionHasNoErrors()->assertRedirect();

        $order = Order::sole();
        $this->assertSame(1500, $order->bonus_minor);
        $lifecycle = app(OrderLifecycle::class);
        $this->assertTrue($lifecycle->markPaid($order, 'sandbox', 'fixture-pay', 10000, 'TRY'));
        $this->assertFalse($lifecycle->markPaid($order->fresh(), 'sandbox', 'fixture-pay', 10000, 'TRY'));
        $this->assertSame(11500, app(Ledger::class)->balance($buyer->id));
        $this->assertSame(1, WalletEntry::query()->where('type', 'bonus')->count());

        $this->assertTrue($lifecycle->markRefunded($order->fresh(), 'fixture-refund'));
        $this->assertSame(0, app(Ledger::class)->balance($buyer->id));
    }

    public function test_free_amounts_and_hidden_packages_get_no_bonus(): void
    {
        $this->buyer();
        CreditPackage::query()->create(['name' => 'Hidden', 'price_minor' => 5000, 'bonus_minor' => 900, 'is_visible' => false]);
        CreditPackage::query()->create(['name' => 'Big', 'price_minor' => 20000, 'bonus_minor' => 4000]);

        $this->assertSame(0, $this->get('/account/topup/review?amount=50.00&method=sandbox')->assertOk()->viewData('details')['bonus_minor']);
        $this->assertSame(0, $this->get('/account/topup/review?amount=199.99&method=sandbox')->assertOk()->viewData('details')['bonus_minor']);
        $this->assertSame(4000, $this->get('/account/topup/review?amount=200.00&method=sandbox')->assertOk()->viewData('details')['bonus_minor']);
        $this->get('/account/topup/review?method=sandbox')->assertSessionHasErrors('amount');
    }

    public function test_a_bonus_changed_after_review_requires_a_new_review(): void
    {
        $this->buyer();
        $package = CreditPackage::query()->create(['name' => 'Starter', 'price_minor' => 10000, 'bonus_minor' => 1000]);
        $response = $this->get('/account/topup/review?package='.$package->id.'&method=sandbox')->assertOk();
        $package->update(['bonus_minor' => 3000]);

        $this->post('/account/topup', $this->payload($response) + ['amount' => '100.00'])->assertSessionHasErrors('expected_review_sha256');
        $this->assertSame(0, Order::count());
    }
}
