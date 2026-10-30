<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Models\SocialIdentity;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscordLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(Settings::class)->set('discord.client_id', '123456789012345678');
        app(Settings::class)->setSecret('discord.client_secret', 'fixture-secret');
    }

    private function begin($user): string
    {
        $location = $this->actingAs($user)->get(route('discord.redirect'))->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame('identify', $query['scope']);

        return (string) $query['state'];
    }

    public function test_link_requires_matching_state_and_stores_the_discord_id(): void
    {
        Http::fake([
            'discord.com/api/oauth2/token' => Http::response(['access_token' => 'fixture-token']),
            'discord.com/api/users/@me' => Http::response(['id' => '80351110224678912', 'username' => 'steve', 'global_name' => 'Steve']),
        ]);
        $user = $this->customer();

        $state = $this->begin($user);
        $this->actingAs($user)->get(route('discord.callback', ['state' => 'wrong', 'code' => 'abc']))->assertSessionHasErrors('discord');
        $this->assertSame(0, SocialIdentity::query()->count());

        $state = $this->begin($user);
        $this->actingAs($user)->get(route('discord.callback', ['state' => $state, 'code' => 'abc']))->assertSessionHasNoErrors();
        $this->assertSame('80351110224678912', SocialIdentity::query()->sole()->provider_user_id);
        Http::assertSent(fn ($request) => $request->url() === 'https://discord.com/api/oauth2/token' && $request['code_verifier'] !== null);
    }

    public function test_a_discord_account_cannot_be_linked_to_two_website_accounts(): void
    {
        Http::fake([
            'discord.com/api/oauth2/token' => Http::response(['access_token' => 'fixture-token']),
            'discord.com/api/users/@me' => Http::response(['id' => '80351110224678912', 'username' => 'steve']),
        ]);
        $first = $this->customer();
        $this->actingAs($first)->get(route('discord.callback', ['state' => $this->begin($first), 'code' => 'abc']));
        $second = $this->customer();
        $this->actingAs($second)->get(route('discord.callback', ['state' => $this->begin($second), 'code' => 'abc']))->assertSessionHasErrors('discord');

        $this->assertSame($first->id, SocialIdentity::query()->sole()->user_id);
    }

    public function test_guests_are_sent_to_login_and_unconfigured_sites_hide_the_flow(): void
    {
        $this->get(route('discord.redirect'))->assertRedirect(route('login'));
        app(Settings::class)->setSecret('discord.client_secret', null);
        $this->actingAs($this->customer())->get(route('discord.redirect'))->assertNotFound();
    }
}
