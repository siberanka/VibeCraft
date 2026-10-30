<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Legal\LegalPublisher;
use App\Models\LegalAcceptance;
use App\Models\LegalMarket;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\Concerns\CreatesLegalMarkets;
use Tests\TestCase;

class LegalRegistrationTest extends TestCase
{
    use CreatesLegalMarkets;

    private function displayedPayload(LegalMarket $market): array
    {
        $response = $this->get('/register?country='.$market->country.'&subdivision='.$market->subdivision)->assertOk();
        $selection = $response->viewData('legalSelection');
        $this->assertNotNull($selection);

        return ['name' => 'FixtureNewMember', 'email' => 'new-member@fixture.example.test', 'password' => 'SyntheticPassword123',
            'password_confirmation' => 'SyntheticPassword123', 'terms' => '1', 'legal_country' => $market->country,
            'legal_subdivision' => $market->subdivision, 'legal_locale' => $selection['documents']['terms']->locale,
            'legal_document_ids' => array_map(fn ($document) => $document->id, $selection['documents']), 'legal_review_token' => $response->viewData('legalReviewToken')];
    }

    private function enforce(): void
    {
        app(Settings::class)->set('legal.market_selection', 'customer');
        RateLimiter::clear('auth-form:register:127.0.0.1');
        app(Settings::class)->set('legal.enforce', true);
        app(Settings::class)->set('auth.registration', true);
    }

    public function test_registration_requires_a_real_supported_country_before_personal_data_fields_are_shown(): void
    {
        $this->enforce();
        $this->reviewedLegalMarket();
        $this->get('/register')->assertOk()->assertDontSee('name="email"', false)->assertSee('name="country"', false);
        $this->get('/register?country=DE')->assertOk()->assertDontSee('name="email"', false)->assertViewHas('legalSelection', null);
        $this->withSession(['locale' => 'en'])->get('/register?country=TR')->assertOk()->assertDontSee('name="email"', false);
        $this->withSession(['locale' => 'tr'])->get('/register?country=TR')->assertOk()->assertSee('name="email"', false)
            ->assertSee('name="legal_document_ids[privacy]"', false)->assertDontSee('name="marketing"', false)
            ->assertDontSee('checked', false)->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_registration_records_contract_acceptance_and_distinct_notice_presentation_atomically(): void
    {
        $this->enforce();
        $payload = $this->displayedPayload($this->reviewedLegalMarket());
        $this->post('/register', $payload)->assertSessionHasNoErrors()->assertRedirect();
        $user = User::where('email', $payload['email'])->sole();
        $records = LegalAcceptance::where('user_id', $user->id)->with('document')->get();
        $this->assertCount(3, $records);
        $this->assertTrue($user->hasRole('member'));
        $this->assertSame('accepted', $records->firstWhere('document.kind', 'terms')->action);
        $this->assertSame('presented', $records->firstWhere('document.kind', 'privacy')->action);
        $this->assertSame('presented', $records->firstWhere('document.kind', 'cookies')->action);
        $this->assertTrue($records->every(fn ($record) => $record->context === 'registration' && $record->order_id === null && $record->document_sha256 === $record->document->contentDigest()));
        $this->assertNull(session('legal.presented.registration'));
    }

    public function test_missing_acceptance_or_unpresented_versions_cannot_create_an_account(): void
    {
        $this->enforce();
        $market = $this->reviewedLegalMarket();
        $payload = $this->displayedPayload($market);
        $before = User::count();
        $withoutTerms = $payload;
        unset($withoutTerms['terms']);
        $this->post('/register', $withoutTerms)->assertSessionHasErrors('terms');
        $this->post('/register', array_replace($payload, ['legal_review_token' => str_repeat('0', 64)]))->assertSessionHasErrors('legal_document_ids');
        $this->post('/register', array_replace($payload, ['legal_country' => 'DE']))->assertSessionHasErrors('legal_document_ids');
        $this->assertSame($before, User::count());
        $this->assertSame(0, LegalAcceptance::count());
    }

    public function test_english_registration_uses_english_versions_without_changing_the_selected_country(): void
    {
        $this->enforce();
        $market = $this->reviewedLegalMarket('TR', 'en');
        $this->withSession(['locale' => 'en']);
        $payload = $this->displayedPayload($market);
        $this->assertSame('en', $payload['legal_locale']);
        $this->post('/register', $payload)->assertSessionHasNoErrors()->assertRedirect();
        $user = User::where('email', $payload['email'])->sole();
        $this->assertSame('en', $user->locale);
        $records = LegalAcceptance::where('user_id', $user->id)->with('document')->get();
        $this->assertCount(3, $records);
        $this->assertTrue($records->every(fn ($record) => $record->document->country === 'TR' && $record->document->locale === 'en'));
    }

    public function test_republication_or_market_pause_after_display_rolls_back_the_new_account(): void
    {
        $this->enforce();
        $market = $this->reviewedLegalMarket();
        $payload = $this->displayedPayload($market);
        $before = User::count();
        $owner = User::role('owner')->firstOrFail();
        $policy = $market->policies()->where('kind', 'privacy')->firstOrFail();
        $publisher = app(LegalPublisher::class);
        $policy = $publisher->saveDraft($market, $owner, ['kind' => 'privacy', 'locale' => 'tr', 'revision' => 2, 'title' => 'Revised fixture privacy', 'body_markdown' => 'Updated synthetic notice.']);
        $publisher->publish($policy, 3, $owner);
        $this->post('/register', $payload)->assertSessionHasErrors('legal_document_ids');
        $this->assertSame($before, User::count());
        $payload = $this->displayedPayload($market);
        $market->forceFill(['is_enabled' => false])->save();
        $this->post('/register', $payload)->assertSessionHasErrors('legal_country');
        $this->assertSame($before, User::count());
        $this->assertSame(0, LegalAcceptance::count());
    }

    public function test_an_expired_form_requires_fresh_document_presentation_before_account_creation(): void
    {
        $this->enforce();
        $market = $this->reviewedLegalMarket();
        $payload = $this->displayedPayload($market);
        $before = User::count();
        $record = session('legal.presented.registration');
        $record['at'] = time() - 3601;
        $this->withSession(['legal.presented.registration' => $record])->post('/register', $payload)->assertSessionHasErrors('legal_document_ids');
        $this->assertSame($before, User::count());
        $this->assertSame(0, LegalAcceptance::count());
        $payload = $this->displayedPayload($market);
        $this->post('/register', $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($before + 1, User::count());
        $this->assertSame(3, LegalAcceptance::count());
    }

    public function test_legal_storage_failure_rolls_back_the_account_and_all_partial_notices(): void
    {
        $this->enforce();
        $payload = $this->displayedPayload($this->reviewedLegalMarket());
        $before = User::count();
        Event::listen('eloquent.creating: '.LegalAcceptance::class, function (LegalAcceptance $record): void {
            if ($record->action === 'presented') {
                throw new RuntimeException('Synthetic notice storage failure.');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->post('/register', $payload);
            $this->fail('Account survived failed legal evidence storage.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic notice storage failure.', $exception->getMessage());
        }
        $this->assertSame($before, User::count());
        $this->assertSame(0, LegalAcceptance::count());
        $this->assertGuest();
    }
}
