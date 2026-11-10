<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Core\Theme\ThemeManager;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPresentationTest extends TestCase
{
    public function test_html_errors_preserve_status_theme_and_recovery_without_exception_details(): void
    {
        Route::get('/__tests/failure/{status}', function (int $status) {
            abort($status, 'internal-fixture-detail', ['Retry-After' => '60']);
        });

        foreach (['atlas', 'forge', 'volt', 'nomad', 'mono', 'prism', 'arcade', 'terminal', 'brick', 'dusk'] as $theme) {
            app(Settings::class)->set('theme.active', $theme);
            app(ThemeManager::class)->use($theme);
            foreach ([403, 404, 419, 429, 500, 503] as $status) {
                $response = $this->get('/__tests/failure/'.$status);
                $response->assertStatus($status)
                    ->assertSee('data-error-code="'.$status.'"', false)
                    ->assertSee('data-theme="'.$theme.'"', false)
                    ->assertSee('name="robots" content="noindex, nofollow"', false)
                    ->assertDontSee('internal-fixture-detail')
                    ->assertHeader('Retry-After', '60')
                    ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
            }
        }
    }

    public function test_real_missing_page_uses_community_recovery_and_expired_page_never_reposts(): void
    {
        $this->get('/page-that-does-not-exist')->assertNotFound()
            ->assertSee('class="error-actions"', false)->assertSee('href="/tickets"', false);
        Route::get('/__tests/expired', fn () => abort(419));
        $this->get('/__tests/expired')->assertStatus(419)->assertSee('href="/login"', false)
            ->assertDontSee('<form', false)->assertDontSee('javascript:', false);
    }

    public function test_api_and_json_errors_keep_the_framework_contract(): void
    {
        Route::get('/api/__tests/failure', fn () => abort(429, 'fixture-json-contract', ['Retry-After' => '60']));
        $this->getJson('/api/__tests/failure')->assertStatus(429)->assertJsonPath('message', 'fixture-json-contract')
            ->assertHeader('Retry-After', '60')->assertDontSee('<!doctype', false);
    }
}
