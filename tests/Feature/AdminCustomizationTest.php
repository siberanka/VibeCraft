<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Core\Theme\ThemeManager;
use App\Domain\Media\MediaLibrary;
use App\Http\Controllers\AdminThemeTokenController;
use App\Models\Media;
use Tests\TestCase;

class AdminCustomizationTest extends TestCase
{
    private function staff(string $role = 'owner')
    {
        app(Settings::class)->set('security.require_admin_2fa', false);

        return $this->actingAs($this->customer($role))->withSession(['auth.password_confirmed_at' => time()]);
    }

    private function upload(): Media
    {
        return Media::query()->create([
            'disk' => 'local', 'path' => 'media/own.png', 'original_name' => 'own.png', 'mime' => 'image/png',
            'size' => 10, 'width' => 1600, 'height' => 900, 'sha256' => str_repeat('b', 64),
        ]);
    }

    public function test_administration_menu_is_grouped_and_shows_only_the_current_area(): void
    {
        $html = (string) $this->staff()->get('/admin/products')->assertOk()->getContent();

        foreach (['Store', 'Content', 'Community', 'Appearance', 'System'] as $area) {
            $this->assertStringContainsString('class="admin-area"', $html);
            $this->assertMatchesRegularExpression('/class="admin-area"[^>]*>\s*'.preg_quote(__($area), '/').'\s*</', $html);
        }
        // Store is open: its pages are listed; pages of other areas are not.
        $this->assertMatchesRegularExpression('#class="admin-area" href="[^"]*/admin/products"\s+aria-current="true"#', $html);
        $this->assertStringContainsString('/admin/coupons', $html);
        $this->assertStringNotContainsString('/admin/custom-css', $html);
        $this->assertStringNotContainsString('/admin/audit"', $html);
    }

    public function test_menu_hides_areas_the_role_cannot_open(): void
    {
        $html = (string) $this->staff('support')->get('/admin/tickets')->assertOk()->getContent();

        $this->assertStringContainsString('/admin/users', $html);
        $this->assertStringNotContainsString('/admin/themes', $html);
        $this->assertStringNotContainsString('/admin/settings', $html);
        $this->assertStringNotContainsString('/admin/products', $html);
        $this->assertStringContainsString('/admin/orders', $html);
    }

    public function test_every_ready_palette_is_readable_and_changes_only_known_colours(): void
    {
        $manager = app(ThemeManager::class);
        foreach (config('vibecraft.themes') as $theme) {
            $palettes = $manager->palettes($theme);
            $this->assertGreaterThanOrEqual(3, count($palettes), $theme);
            foreach ($palettes as $key => $palette) {
                $effective = array_merge($manager->manifest($theme)['tokens'], $palette['tokens']);
                foreach ([['c-ink', 'c-bg'], ['c-ink', 'c-surface'], ['c-accent-ink', 'c-accent'], ['c-muted', 'c-bg'], ['c-muted', 'c-surface']] as [$fore, $back]) {
                    $this->assertGreaterThanOrEqual(4.5, AdminThemeTokenController::contrast($effective[$fore], $effective[$back]), "$theme/$key $fore on $back");
                }
                foreach (array_keys($palette['tokens']) as $name) {
                    $this->assertStringStartsWith('c-', $name);
                }
            }
        }
    }

    public function test_a_ready_palette_is_applied_marked_and_can_be_restored(): void
    {
        $this->staff()->get(route('admin.themes.tokens', 'atlas'))->assertOk()
            ->assertSee(__('Ready palettes'))->assertSee('name="palette" value="ember"', false);

        $this->staff()->post(route('admin.themes.palette', 'atlas'), ['palette' => 'ember'])->assertRedirect(route('admin.themes.tokens', 'atlas'));
        $this->app->forgetScopedInstances();
        $this->assertStringContainsString('--c-accent:#ff8a4c', app(ThemeManager::class)->cssVariables('atlas'));
        $html = (string) $this->staff()->get(route('admin.themes.tokens', 'atlas'))->getContent();
        $this->assertMatchesRegularExpression('#name="palette" value="ember">\s*<button class="palette-option" type="submit"\s+aria-pressed="true"#', $html);

        $this->staff()->post(route('admin.themes.palette', 'atlas'), ['palette' => 'does-not-exist'])->assertSessionHasErrors('palette');
        $this->staff()->post(route('admin.themes.tokens.reset', 'atlas'))->assertRedirect();
        $this->app->forgetScopedInstances();
        $this->assertStringContainsString('--c-accent:#3db7a6', app(ThemeManager::class)->cssVariables('atlas'));
        $this->actingAs($this->customer())->post(route('admin.themes.palette', 'atlas'), ['palette' => 'ember'])->assertForbidden();
    }

    public function test_home_artwork_can_be_replaced_removed_and_restored_per_theme(): void
    {
        $media = $this->upload();
        app(Settings::class)->set('theme.active', 'atlas');
        $sections = ['strip' => ['position' => 1, 'visible' => 1], 'worlds' => ['position' => 2, 'visible' => 1], 'news' => ['position' => 3, 'visible' => 1], 'purchases' => ['position' => 4, 'visible' => 1], 'store' => ['position' => 5, 'visible' => 1]];
        $save = fn (array $art) => $this->staff()->put(route('admin.appearance.update'), ['theme' => 'atlas', 'sections' => $sections] + $art);

        $save(['art_mode' => 'own'])->assertSessionHasErrors('art_media_id');
        $save(['art_mode' => 'own', 'art_media_id' => $media->id])->assertRedirect();
        $this->app->forgetScopedInstances();
        $this->get('/')->assertOk()->assertSee('/media/'.$media->id, false)->assertDontSee('atlas-landscape.webp', false);
        $this->assertContains(__('Home artwork').': Atlas', app(MediaLibrary::class)->usages($media));
        $this->assertSame(1, app(MediaLibrary::class)->useCounts([$media->id])[$media->id]);

        $save(['art_mode' => 'none'])->assertRedirect();
        $this->app->forgetScopedInstances();
        $this->get('/')->assertOk()->assertDontSee('class="world-scene', false);
        $this->assertSame([], app(MediaLibrary::class)->usages($media));

        $save(['art_mode' => 'default'])->assertRedirect();
        $this->app->forgetScopedInstances();
        $this->get('/')->assertOk()->assertSee('atlas-landscape.webp', false);
        // The choice belongs to one theme; another theme keeps its bundled picture.
        $save(['art_mode' => 'none']);
        $this->assertSame('default', app(ThemeManager::class)->art('forge')['mode']);
    }

    public function test_theme_picker_shows_a_preview_card_for_every_theme(): void
    {
        $html = (string) $this->staff()->get(route('admin.themes'))->assertOk()->getContent();

        foreach (config('vibecraft.themes') as $theme) {
            $this->assertStringContainsString('assets/images/'.$theme.'-landscape-768.webp', $html);
            $this->assertStringContainsString('id="theme-'.$theme.'"', $html);
        }
        $this->assertSame(1, substr_count($html, 'theme-card is-active'));
        $this->assertStringNotContainsString(' style="', $html);
    }
}
