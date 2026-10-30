<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Core\Theme\HomeLayout;
use App\Models\MenuItem;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    private function staff(string $role = 'admin')
    {
        app(Settings::class)->set('security.require_admin_2fa', false);

        return $this->actingAs($this->customer($role))->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_custom_css_is_served_from_this_site_and_kept_out_of_admin(): void
    {
        $this->staff()->put(route('admin.custom-css.update'), ['css' => '.stage-title { color: var(--c-accent); }'])->assertRedirect(route('admin.custom-css'));

        $css = (string) app(Settings::class)->get('appearance.custom_css');
        $version = substr(hash('sha256', $css), 0, 12);
        $this->get(route('home'))->assertSee(route('custom-css', ['v' => $version]), false);
        $this->staff()->get(route('admin.custom-css'))->assertOk()->assertDontSee(route('custom-css', ['v' => $version]), false);

        $response = $this->get(route('custom-css', ['v' => $version]))->assertOk();
        $this->assertStringStartsWith('text/css', (string) $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('.stage-title', $response->getContent());
    }

    public function test_unsafe_css_is_rejected_and_the_old_stylesheet_stays(): void
    {
        $this->staff()->put(route('admin.custom-css.update'), ['css' => '.a { color: red; }']);
        $this->staff()->put(route('admin.custom-css.update'), ['css' => '@import url(https://evil.example/x.css);'])->assertSessionHasErrors('css');

        $this->assertStringContainsString('color: red', (string) app(Settings::class)->get('appearance.custom_css'));
    }

    public function test_only_appearance_managers_reach_the_appearance_screens(): void
    {
        $this->staff('support')->get(route('admin.custom-css'))->assertForbidden();
        $this->staff('support')->post(route('admin.menus.store'), ['location' => 'header', 'label' => 'X', 'url' => '/x'])->assertForbidden();
        $this->assertSame(0, MenuItem::query()->count());
    }

    public function test_menu_links_render_and_unsafe_links_are_refused(): void
    {
        $this->staff()->post(route('admin.menus.store'), ['location' => 'header', 'label' => 'Rules', 'url' => '/rules'])->assertSessionHasNoErrors();
        $this->staff()->post(route('admin.menus.store'), ['location' => 'footer', 'label' => 'Wiki', 'url' => 'https://wiki.example.com', 'new_tab' => 1])->assertSessionHasNoErrors();
        $this->staff()->post(route('admin.menus.store'), ['location' => 'header', 'label' => 'Bad', 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
        $this->staff()->post(route('admin.menus.store'), ['location' => 'header', 'label' => 'Bad', 'url' => '//evil.example'])->assertSessionHasErrors('url');

        $html = $this->get(route('home'))->assertOk()->assertSee('href="/rules"', false)->assertDontSee('javascript:alert', false)->getContent();
        $this->assertMatchesRegularExpression('#href="https://wiki\.example\.com"\s+rel="noopener noreferrer"\s+target="_blank"#', $html);

        $item = MenuItem::query()->where('label', 'Rules')->sole();
        $this->staff()->put(route('admin.menus.update', $item), ['label' => 'Rules', 'url' => '/rules'])->assertSessionHasNoErrors();
        $this->app->forgetScopedInstances();
        $this->get(route('home'))->assertDontSee('href="/rules"', false);
        $this->staff()->delete(route('admin.menus.destroy', $item));
        $this->assertSame(1, MenuItem::query()->count());
    }

    public function test_home_sections_can_be_hidden_and_reordered_per_theme(): void
    {
        $this->staff()->put(route('admin.appearance.update'), [
            'theme' => 'atlas',
            'sections' => ['strip' => ['position' => 2, 'visible' => 1], 'worlds' => ['position' => 1, 'visible' => 1], 'news' => ['position' => 3], 'purchases' => ['position' => 4], 'store' => ['position' => 5, 'visible' => 1]],
            'nav_hidden' => ['servers'],
        ])->assertRedirect();

        $html = $this->get(route('home'))->assertOk()->assertDontSee('id="news-heading"', false)->getContent();
        $this->assertLessThan(strpos($html, 'class="stage-strip"'), strpos($html, 'id="worlds-heading"'));
        $this->assertStringNotContainsString('class="nav-link" href="'.route('servers.index').'"', $html);
        $this->assertSame(['worlds', 'strip', 'store'], app(HomeLayout::class)->visible('atlas'));
        $this->assertSame(['strip', 'news', 'worlds', 'purchases', 'store'], app(HomeLayout::class)->visible('forge'));
    }
}
