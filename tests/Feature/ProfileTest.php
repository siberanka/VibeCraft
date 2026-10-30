<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Models\Badge;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    public function test_public_profile_shows_safe_fields_and_badges_only(): void
    {
        $member = $this->customer('moderator');
        $badge = Badge::query()->create(['name' => 'Builder', 'slug' => 'builder', 'color' => '#ff754a']);
        $badge->users()->attach($member->id, ['created_at' => now()]);

        $response = $this->get(route('profile.show', $member->name))->assertOk()->assertSee('Builder')->assertSee('tone-orange', false);
        $response->assertDontSee($member->email);
    }

    public function test_banned_and_malformed_profiles_are_not_found(): void
    {
        $member = $this->customer();
        $member->forceFill(['banned_at' => now()])->save();
        $this->get(route('profile.show', $member->name))->assertNotFound();
        $this->get('/player/'.urlencode('<script>'))->assertNotFound();
    }

    public function test_only_user_managers_grant_badges(): void
    {
        app(Settings::class)->set('security.require_admin_2fa', false);
        $badge = Badge::query()->create(['name' => 'VIP', 'slug' => 'vip']);
        $member = $this->customer();

        $this->actingAs($this->customer('support'))->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.users.badges', $member), ['badge' => $badge->id, 'action' => 'grant'])->assertForbidden();
        $this->actingAs($this->customer('admin'))->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.users.badges', $member), ['badge' => $badge->id, 'action' => 'grant'])->assertRedirect();

        $this->assertSame(1, $badge->users()->count());
    }
}
