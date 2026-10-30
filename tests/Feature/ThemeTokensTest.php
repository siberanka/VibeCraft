<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Core\Theme\ThemeManager;
use Tests\TestCase;

class ThemeTokensTest extends TestCase
{
    private function owner()
    {
        app(Settings::class)->set('security.require_admin_2fa', false);

        return $this->actingAs($this->customer('owner'))->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_valid_colours_are_saved_and_reach_the_page_css(): void
    {
        $this->owner()->put(route('admin.themes.tokens.update', 'atlas'), ['tokens' => ['c-accent' => '#5DADE2', 'r-panel' => '8']])
            ->assertRedirect(route('admin.themes.tokens', 'atlas'));

        $css = app(ThemeManager::class)->cssVariables('atlas');
        $this->assertStringContainsString('--c-accent:#5dade2', $css);
        $this->assertStringContainsString('--r-panel:8px', $css);
    }

    public function test_injection_and_unreadable_combinations_are_refused(): void
    {
        $this->owner()->put(route('admin.themes.tokens.update', 'atlas'), ['tokens' => ['c-accent' => 'red;}body{background:url(//x)}']])
            ->assertSessionHasErrors('tokens.c-accent');
        $this->owner()->put(route('admin.themes.tokens.update', 'atlas'), ['tokens' => ['c-ink' => '#11161b']])
            ->assertSessionHasErrors('tokens.c-ink');

        $this->assertSame([], (array) app(Settings::class)->get('theme.tokens.atlas', []));
        $this->assertStringNotContainsString('url(', app(ThemeManager::class)->cssVariables('atlas'));
    }

    public function test_reset_restores_defaults_and_staff_without_permission_are_refused(): void
    {
        $this->owner()->put(route('admin.themes.tokens.update', 'volt'), ['tokens' => ['c-accent' => '#ffaa00']]);
        $this->owner()->post(route('admin.themes.tokens.reset', 'volt'))->assertRedirect();
        $this->assertSame([], (array) app(Settings::class)->get('theme.tokens.volt', []));

        $this->actingAs($this->customer('support'))->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('admin.themes.tokens.update', 'volt'), ['tokens' => ['c-accent' => '#ffaa00']])->assertForbidden();
        $this->owner()->get(route('admin.themes.tokens', 'unknown'))->assertNotFound();
    }
}
