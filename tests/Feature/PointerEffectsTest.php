<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Core\Theme\ThemeManager;
use Tests\TestCase;

class PointerEffectsTest extends TestCase
{
    private const SECTIONS = ['strip' => ['position' => 1, 'visible' => 1], 'worlds' => ['position' => 2, 'visible' => 1], 'news' => ['position' => 3, 'visible' => 1], 'purchases' => ['position' => 4, 'visible' => 1], 'store' => ['position' => 5, 'visible' => 1]];

    private function owner()
    {
        app(Settings::class)->set('security.require_admin_2fa', false);

        return $this->actingAs($this->customer('owner'))->withSession(['auth.password_confirmed_at' => time()]);
    }

    private function save(string $theme, array $pointer)
    {
        return $this->owner()->put(route('admin.appearance.update'), ['theme' => $theme, 'sections' => self::SECTIONS] + $pointer);
    }

    private function useTheme(string $theme): void
    {
        app(Settings::class)->set('theme.active', $theme);
        app(ThemeManager::class)->use($theme);
        $this->app->forgetScopedInstances();
    }

    public function test_every_theme_has_its_own_default_effect_and_the_script_draws_each_one(): void
    {
        $defaults = array_map(fn ($theme) => app(ThemeManager::class)->pointer($theme)['default'], config('vibecraft.themes'));

        // One theme, one effect: no two themes share a default.
        $this->assertSame(count($defaults), count(array_unique($defaults)));
        $this->assertEqualsCanonicalizing(ThemeManager::POINTER_EFFECTS, $defaults);
        $script = (string) file_get_contents(public_path('assets/pointer-fx.js'));
        foreach (ThemeManager::POINTER_EFFECTS as $effect) {
            $this->assertMatchesRegularExpression('/^    '.$effect.': \{$/m', $script, "recipe $effect");
        }
    }

    public function test_public_pages_announce_the_theme_effect_and_the_admin_panel_never_does(): void
    {
        $this->useTheme('forge');
        $this->get('/')->assertOk()->assertSee('data-pointer="embers" data-pointer-strength="normal" data-pointer-touch', false);

        $this->owner()->get('/admin')->assertOk()->assertDontSee('data-pointer=', false);
    }

    public function test_owner_can_switch_off_choose_tune_and_restore_the_effect_per_theme(): void
    {
        $this->useTheme('arcade');

        $this->save('arcade', ['pointer_effect' => 'neon', 'pointer_strength' => 'lively', 'pointer_touch' => '0'])->assertRedirect();
        $this->useTheme('arcade');
        $html = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-pointer="neon" data-pointer-strength="lively"', $html);
        $this->assertStringNotContainsString('data-pointer-touch', $html);

        $this->save('arcade', ['pointer_effect' => 'off', 'pointer_strength' => 'normal', 'pointer_touch' => '1'])->assertRedirect();
        $this->useTheme('arcade');
        $this->get('/')->assertOk()->assertDontSee('data-pointer=', false);
        // The choice belongs to one theme only.
        $this->assertSame('ring', app(ThemeManager::class)->pointer('mono')['effect']);

        $this->save('arcade', ['pointer_effect' => 'default', 'pointer_strength' => 'subtle', 'pointer_touch' => '1'])->assertRedirect();
        $this->useTheme('arcade');
        $this->get('/')->assertOk()->assertSee('data-pointer="pixels" data-pointer-strength="subtle" data-pointer-touch', false);
    }

    public function test_unknown_effects_and_strengths_are_refused(): void
    {
        $this->save('atlas', ['pointer_effect' => 'confetti-cannon'])->assertSessionHasErrors('pointer_effect');
        $this->save('atlas', ['pointer_effect' => 'ripple', 'pointer_strength' => 'max'])->assertSessionHasErrors('pointer_strength');
        $this->assertSame('default', app(ThemeManager::class)->pointer('atlas')['choice']);
        $this->actingAs($this->customer())->put(route('admin.appearance.update'), ['theme' => 'atlas', 'sections' => self::SECTIONS, 'pointer_effect' => 'off'])->assertForbidden();
    }

    public function test_theme_settings_offer_every_effect_with_a_live_preview_pad(): void
    {
        $html = (string) $this->owner()->get(route('admin.appearance', ['theme' => 'dusk']))->assertOk()->getContent();

        $this->assertStringContainsString('data-pointer-preview data-pointer-default="neon"', $html);
        foreach (ThemeManager::POINTER_EFFECTS as $effect) {
            $this->assertStringContainsString('<option value="'.$effect.'"', $html);
        }
        $this->assertStringContainsString('<option value="off"', $html);
        $this->assertStringNotContainsString(' style="', $html);
    }
}
