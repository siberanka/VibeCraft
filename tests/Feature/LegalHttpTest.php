<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Legal\LegalPublisher;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\LegalMarket;
use App\Models\Page;
use Tests\Concerns\CreatesLegalMarkets;
use Tests\TestCase;

class LegalHttpTest extends TestCase
{
    use CreatesLegalMarkets;

    public function test_footer_and_country_portal_use_only_the_exact_reviewed_language_and_subdivision(): void
    {
        app(Settings::class)->set('legal.market_selection', 'customer');
        $market = $this->reviewedLegalMarket();
        $this->get('/')->assertOk()->assertSee(route('legal.index'), false)->assertSee(route('legal.cookies'), false);
        $this->get('/legal')->assertOk()->assertViewHas('documents', fn ($rows) => $rows->isEmpty());
        $this->get('/legal?country=TR')->assertOk()->assertViewHas('documents', fn ($rows) => $rows->count() === 6)
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->get('/legal?country=TR&kind=privacy')->assertOk()->assertViewHas('documents', fn ($rows) => $rows->count() === 1 && $rows->first()->kind === 'privacy');
        $this->get('/legal?country=TR&subdivision=34')->assertOk()->assertViewHas('documents', fn ($rows) => $rows->isEmpty());
        $this->get('/legal?country=DE')->assertOk()->assertViewHas('documents', fn ($rows) => $rows->isEmpty());
        $this->withSession(['locale' => 'en'])->get('/legal?country=TR')->assertOk()->assertViewHas('documents', fn ($rows) => $rows->isEmpty());
        $market->forceFill(['is_enabled' => false])->save();
        $this->withSession(['locale' => 'tr'])->get('/legal?country=TR')->assertOk()->assertViewHas('documents', fn ($rows) => $rows->isEmpty());
    }

    public function test_published_text_is_rendered_and_downloaded_with_its_frozen_trader_and_archived_access_is_personal(): void
    {
        $market = $this->reviewedLegalMarket();
        $policy = $market->policies()->where('kind', 'terms')->firstOrFail();
        $old = $policy->currentDocument;
        $owner = $this->customer('owner');
        $this->get('/legal/documents/'.$old->id)->assertOk()->assertSee('Synthetic QA Merchant')->assertSee('section-synthetic-document');
        $this->get('/legal/documents/'.$old->id.'?download=1')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="terms-TR-tr-v1.txt"')->assertSee('Synthetic QA Merchant')->assertSee($old->body_markdown);
        $reader = $this->customer();
        LegalAcceptance::create(['legal_document_id' => $old->id, 'user_id' => $reader->id, 'context' => 'registration', 'action' => 'accepted', 'recorded_at' => now()]);
        $publisher = app(LegalPublisher::class);
        $new = $publisher->saveDraft($market, $owner, ['kind' => 'terms', 'locale' => 'tr', 'revision' => 2, 'title' => 'New fixture terms', 'body_markdown' => 'Changed test publication.']);
        $publisher->publish($new, 3, $owner);
        $this->get('/legal/documents/'.$old->id)->assertNotFound();
        $this->actingAs($owner)->get('/legal/documents/'.$old->id.'?download=1')->assertNotFound();
        $this->actingAs($reader)->get('/legal/documents/'.$old->id)->assertOk()->assertSee('Fixture terms');
        $this->get('/legal/documents/'.$old->id.'?download=1')->assertOk()->assertSee('Synthetic QA Merchant');
    }

    public function test_cookie_inventory_contains_names_and_purposes_without_optional_consent_claims(): void
    {
        $this->get('/legal/cookies')->assertOk()->assertSee('XSRF-TOKEN')->assertSee((string) config('session.cookie'))
            ->assertSee(route('legal.index', ['kind' => 'privacy']), false)->assertDontSee('name="analytics"', false)
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_a_published_title_is_escaped_once_in_the_shared_page_title(): void
    {
        $market = $this->reviewedLegalMarket();
        $actor = $this->customer('owner');
        $publisher = app(LegalPublisher::class);
        $policy = $publisher->saveDraft($market, $actor, ['kind' => 'terms', 'locale' => 'tr', 'revision' => 2,
            'title' => 'Rights & privacy <example>', 'body_markdown' => 'Synthetic publication text.']);
        $document = $publisher->publish($policy, 3, $actor);
        $this->get('/legal/documents/'.$document->id)->assertOk()->assertSee('<title>Rights &amp; privacy &lt;example&gt;', false)->assertDontSee('Rights &amp;amp;', false);
    }

    public function test_shared_metadata_preserves_literal_entities_and_quotes_without_rendering_markup(): void
    {
        Page::create(['title' => 'Fixture & <sample> "quoted" &amp;', 'slug' => 'metadata-fixture',
            'meta_description' => 'Description "><script>bad</script> &amp;', 'body_markdown' => 'Fixture', 'body_html' => '<p>Fixture</p>', 'status' => 'published']);
        $this->get('/pages/metadata-fixture')->assertOk()
            ->assertSee('<title>Fixture &amp; &lt;sample&gt; &quot;quoted&quot; &amp;amp;', false)
            ->assertSee('<meta name="description" content="Description &quot;&gt;&lt;script&gt;bad&lt;/script&gt; &amp;amp;">', false)
            ->assertDontSee('<script>bad</script>', false);
    }

    public function test_site_metadata_fallback_escapes_raw_settings_once(): void
    {
        app(Settings::class)->setMany(['site.name' => 'Name & <sample> &amp;', 'site.description' => 'Summary "quoted" &amp;']);
        $this->get('/')->assertOk()->assertSee('<title>Name &amp; &lt;sample&gt; &amp;amp;</title>', false)
            ->assertSee('<meta name="description" content="Summary &quot;quoted&quot; &amp;amp;">', false);
    }

    public function test_legal_management_requires_the_settings_permission_and_fresh_staff_challenge(): void
    {
        $this->get('/admin/legal')->assertRedirect(route('login'));
        app(Settings::class)->set('security.require_admin_2fa', false);
        $this->actingAs($this->customer('moderator'))->withSession(['auth.password_confirmed_at' => time()])->get('/admin/legal')->assertForbidden();
        $this->actingAs($this->customer('owner'))->withSession(['auth.password_confirmed_at' => 0])->get('/admin/legal')->assertRedirect(route('password.confirm'));
        $this->withSession(['auth.password_confirmed_at' => time()])->get('/admin/legal')->assertOk();
    }

    public function test_staff_can_save_preview_publish_and_enable_only_explicitly_reviewed_versions(): void
    {
        app(Settings::class)->set('security.require_admin_2fa', false);
        $owner = $this->customer('owner');
        $this->actingAs($owner)->withSession(['auth.password_confirmed_at' => time()]);
        $this->get('/admin/legal/markets/create')->assertOk()->assertSee('name="trader_name"', false);
        $this->post('/admin/legal/markets', ['country' => 'TR', 'locales' => ['tr'], 'trader_name' => 'Synthetic Merchant',
            'trader_address' => 'QA Street', 'trader_email' => 'legal@fixture.example.test', 'trader_phone' => '+900000000000'])->assertSessionHasNoErrors()->assertRedirect();
        $market = LegalMarket::sole();
        $this->assertFalse($market->is_enabled);
        $base = '/admin/legal/markets/'.$market->id;
        $this->get($base.'/edit')->assertOk();
        $this->get($base.'/documents')->assertOk();
        $this->post($base.'/enable', ['revision' => 1])->assertSessionHasErrors('review_confirmation');
        $this->post($base.'/enable', ['revision' => 1, 'review_confirmation' => 1])->assertSessionHasErrors('legal_country');
        foreach (LegalPublisher::KINDS as $kind) {
            $this->get($base.'/policies/create?kind='.$kind.'&locale=tr')->assertOk();
            $this->post($base.'/policies', ['kind' => $kind, 'locale' => 'tr', 'title' => 'Fixture '.$kind, 'body_markdown' => '## Reviewed text'."\n\nSynthetic reviewed content."])->assertSessionHasNoErrors()->assertRedirect();
            $policy = $market->policies()->where('kind', $kind)->firstOrFail();
            $this->get($base.'/policies/'.$policy->id.'/edit')->assertOk();
            $this->get($base.'/policies/'.$policy->id.'/preview')->assertOk()->assertSee('section-reviewed-text');
            $this->post($base.'/policies/'.$policy->id.'/publish', ['revision' => 1])->assertSessionHasErrors('publication_confirmation');
            $this->post($base.'/policies/'.$policy->id.'/publish', ['revision' => 1, 'publication_confirmation' => 1])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertSame(6, LegalDocument::count());
        $this->post($base.'/enable', ['revision' => 1, 'review_confirmation' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($market->fresh()->is_enabled);
        $other = LegalMarket::create(['country' => 'DE', 'locales' => ['tr'], 'trader' => []]);
        $policy = $market->policies()->firstOrFail();
        $this->get('/admin/legal/markets/'.$other->id.'/policies/'.$policy->id.'/preview')->assertNotFound();
        $this->post('/admin/legal/markets/'.$other->id.'/policies/'.$policy->id.'/publish', ['revision' => 2, 'publication_confirmation' => 1])->assertNotFound();
    }
}
