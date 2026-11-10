<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Core\Theme\ThemeManager;
use App\Http\Controllers\AdminThemeTokenController;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ThemeCatalogTest extends TestCase
{
    public static function themes(): array
    {
        return array_map(fn (string $theme) => [$theme], ['atlas', 'forge', 'volt', 'nomad', 'mono', 'prism', 'arcade', 'terminal', 'brick', 'dusk']);
    }

    private function useTheme(string $theme): void
    {
        app(Settings::class)->set('theme.active', $theme);
        app(ThemeManager::class)->use($theme);
    }

    public function test_the_data_provider_lists_every_configured_theme(): void
    {
        $this->assertSame(array_column(self::themes(), 0), config('vibecraft.themes'));
    }

    #[DataProvider('themes')]
    public function test_every_theme_ships_a_complete_and_readable_package(string $theme): void
    {
        $manager = app(ThemeManager::class);
        $manifest = $manager->manifest($theme);

        $this->assertSame($theme, $manifest['id']);
        $this->assertNotSame('', (string) $manifest['name']);
        foreach (['theme.json', 'tokens.json', 'README.md', 'LICENSES.md', 'views/home.blade.php'] as $file) {
            $this->assertFileExists(resource_path("themes/$theme/$file"));
        }
        $this->assertFileExists(public_path("assets/themes/$theme.css"));
        $this->assertFileExists(public_path("assets/images/$theme-landscape.webp"));
        $this->assertFileExists(public_path("assets/images/$theme-landscape-768.webp"));

        $tokens = $manifest['tokens'];
        foreach ($tokens as $name => $value) {
            $this->assertTrue(ThemeManager::isValidToken($name, $value), "$theme $name");
        }
        // The same pairs the colour editor enforces must already hold for the defaults.
        foreach ([['c-ink', 'c-bg'], ['c-ink', 'c-surface'], ['c-accent-ink', 'c-accent'], ['c-muted', 'c-bg'], ['c-muted', 'c-surface']] as [$fore, $back]) {
            $this->assertGreaterThanOrEqual(4.5, AdminThemeTokenController::contrast($tokens[$fore], $tokens[$back]), "$theme $fore on $back");
        }
        $this->assertGreaterThanOrEqual(3.0, AdminThemeTokenController::contrast($tokens['c-focus'], $tokens['c-bg']), "$theme focus ring");
    }

    #[DataProvider('themes')]
    public function test_public_pages_render_in_every_theme(string $theme): void
    {
        $this->useTheme($theme);

        foreach (['/', '/store', '/servers', '/news', '/login', '/register'] as $path) {
            $this->get($path)->assertOk()->assertSee('data-theme="'.$theme.'"', false)
                ->assertSee('assets/themes/'.$theme.'.css', false);
        }
    }

    public function test_colour_scheme_and_stylesheet_order_follow_the_manifest(): void
    {
        $this->useTheme('brick');
        $html = (string) $this->get('/')->assertOk()->assertSee('name="color-scheme" content="light"', false)->getContent();
        // Late themes restyle shared components, so their sheet comes after the shared ones.
        $this->assertGreaterThan(strpos($html, 'assets/showcase.css'), strpos($html, 'assets/themes/brick.css'));
        $this->assertSame(1, substr_count($html, 'assets/themes/brick.css'));

        $this->app->forgetScopedInstances();
        $this->useTheme('atlas');
        $html = (string) $this->get('/')->assertOk()->assertSee('name="color-scheme" content="dark"', false)->getContent();
        $this->assertLessThan(strpos($html, 'assets/showcase.css'), strpos($html, 'assets/themes/atlas.css'));
        $this->assertSame(1, substr_count($html, 'assets/themes/atlas.css'));
    }

    public function test_pages_carry_one_inert_celebration_layer_and_every_theme_dresses_it(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();
        // The layer and the scroll meter are decoration: hidden from assistive technology and free of inline styles.
        $this->assertSame(1, substr_count($html, '<div class="theme-egg" aria-hidden="true">'));
        $this->assertSame(14, substr_count($html, '<i></i>'));
        $this->assertSame(1, substr_count($html, '<div class="scroll-meter" aria-hidden="true"></div>'));
        $this->assertStringNotContainsString(' style="', $html);

        foreach (config('vibecraft.themes') as $theme) {
            $css = (string) file_get_contents(public_path("assets/themes/$theme.css"));
            $this->assertMatchesRegularExpression('/\\.theme-egg|\\[data-egg\\]/', $css, "$theme has no celebration");
            // Each home section gets its own mark instead of one ornament repeated on every heading.
            foreach (['#worlds-heading', '#news-heading', '#store-heading'] as $heading) {
                $this->assertStringContainsString($heading, $css, "$theme $heading");
            }
        }
        $shared = (string) file_get_contents(public_path('assets/showcase.css'));
        $this->assertStringContainsString('@media (prefers-reduced-motion:no-preference) {'."\n".'    [data-egg] .theme-egg', $shared);
    }

    public function test_theme_stylesheets_reference_only_bundled_files(): void
    {
        foreach (config('vibecraft.themes') as $theme) {
            $css = (string) file_get_contents(public_path("assets/themes/$theme.css"));
            preg_match_all('/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/', $css, $matches);
            foreach ($matches[1] as $reference) {
                $this->assertStringStartsWith('../', $reference, "$theme: $reference");
                $this->assertFileExists(public_path('assets/themes/'.$reference), "$theme: $reference");
            }
            $this->assertStringNotContainsString('@import', $css);
            $this->assertStringNotContainsString('transition:all', str_replace(' ', '', $css));
        }
    }
}
