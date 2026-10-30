<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Commerce\Checkout;
use App\Domain\Commerce\OrderReview;
use App\Domain\Legal\LegalScope;
use App\Models\LegalAcceptance;
use App\Models\Order;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesLegalMarkets;
use Tests\Concerns\CreatesOrderConfirmations;
use Tests\TestCase;

class LegalScopeTest extends TestCase
{
    use CreatesLegalMarkets, CreatesOrderConfirmations;

    public function test_one_reviewed_profile_is_automatic_and_only_the_document_language_changes(): void
    {
        $this->reviewedLegalMarket('TR', 'tr', '', ['en']);
        $this->get('/register')->assertOk()->assertSee('name="email"', false)->assertDontSee('name="country"', false)
            ->assertViewHas('legalCountry', 'TR')->assertViewHas('legalSelection', fn ($selection) => $selection['documents']['terms']->locale === 'tr');
        $this->withSession(['locale' => 'en'])->get('/register?country=DE')->assertOk()->assertDontSee('name="country"', false)
            ->assertViewHas('legalCountry', 'TR')->assertViewHas('legalSelection', fn ($selection) => $selection['documents']['terms']->locale === 'en');
        $this->get('/legal')->assertOk()->assertDontSee('name="country"', false)->assertViewHas('documents', fn ($rows) => $rows->count() === 6 && $rows->every(fn ($doc) => $doc->locale === 'en'));
    }

    public function test_multiple_profiles_need_an_operator_default_and_language_never_selects_another_country(): void
    {
        $turkish = $this->reviewedLegalMarket();
        $this->reviewedLegalMarket('DE', 'en');
        $this->get('/register')->assertOk()->assertDontSee('name="email"', false)->assertDontSee('name="country"', false);
        app(LegalScope::class)->configure($this->customer('owner'), ['selection_mode' => 'operator', 'default_market_id' => $turkish->id, 'revision' => 1]);
        $this->get('/register?country=DE')->assertOk()->assertViewHas('legalCountry', 'TR')->assertSee('name="email"', false);
        $this->withSession(['locale' => 'en'])->get('/register')->assertOk()->assertDontSee('name="email"', false)->assertViewHas('legalSelection', null);
    }

    public function test_pausing_the_configured_default_does_not_fall_back_to_another_enabled_profile(): void
    {
        $first = $this->reviewedLegalMarket();
        $this->reviewedLegalMarket('DE');
        app(LegalScope::class)->configure($this->customer('owner'), ['selection_mode' => 'operator', 'default_market_id' => $first->id, 'revision' => 1]);
        $first->forceFill(['is_enabled' => false])->save();
        $this->get('/register')->assertOk()->assertViewHas('legalSelection', null)->assertDontSee('name="email"', false);
        $this->get('/legal')->assertOk()->assertViewHas('documents', fn ($rows) => $rows->isEmpty());
    }

    public function test_staff_can_configure_scope_but_stale_preference_updates_and_moderators_are_rejected(): void
    {
        $market = $this->reviewedLegalMarket();
        app(Settings::class)->set('security.require_admin_2fa', false);
        $this->actingAs($this->customer('owner'))->withSession(['auth.password_confirmed_at' => time()]);
        $this->post('/admin/legal/preferences', ['selection_mode' => 'operator', 'default_market_id' => $market->id, 'revision' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/admin/legal/preferences', ['selection_mode' => 'customer', 'default_market_id' => 0, 'revision' => 1])->assertSessionHasErrors('revision');
        $this->assertSame('operator', app(Settings::class)->get('legal.market_selection'));
        $this->assertSame(2, app(Settings::class)->get('legal.selection_revision'));
        $this->actingAs($this->customer('moderator'))->post('/admin/legal/preferences', ['selection_mode' => 'customer', 'revision' => 2])->assertForbidden();
    }

    public function test_an_in_flight_confirmation_cannot_skip_changed_document_configuration(): void
    {
        $market = $this->reviewedLegalMarket();
        $buyer = $this->customer();
        app()->setLocale('tr');
        $this->enableSandbox();
        app(Settings::class)->set('legal.enforce', true);
        $confirmation = $this->orderConfirmation($buyer, OrderReview::topup($buyer, 1000, 'sandbox'), $market);
        app(LegalScope::class)->configure($this->customer('owner'), ['selection_mode' => 'customer', 'revision' => 1]);
        try {
            app(Checkout::class)->createTopup($buyer, 1000, 'sandbox', null, $confirmation);
            $this->fail('An in-flight old form ignored changed document settings.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('legal_document_ids', $exception->errors());
        }
        $this->assertSame(0, Order::count());
        $this->assertSame(0, LegalAcceptance::count());
    }
}
