<?php

namespace App\View;

use App\Core\Settings\Settings;
use App\Core\Theme\HomeLayout;
use App\Core\Theme\ThemeManager;
use App\Models\GameServer;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

/**
 * Shares site-wide data with every themed and admin view (computed once per request).
 */
class SiteComposer
{
    private ?array $shared = null;

    public function __construct(private Settings $settings, private ThemeManager $themes, private HomeLayout $layout) {}

    public function compose(View $view): void
    {
        $view->with($this->shared ??= $this->build());
    }

    private function build(): array
    {
        $s = $this->settings;

        try {
            $serverRows = Cache::remember('vibecraft.public_servers.v2', 60, fn () => GameServer::query()
                ->where('show_on_site', true)->orderBy('sort')->orderBy('id')
                ->get(['id', 'name', 'slug', 'description', 'java_address', 'bedrock_address', 'bedrock_port', 'status_online', 'status_players', 'status_max_players', 'status_version', 'status_checked_at'])->toArray());
            $pageRows = Cache::remember('vibecraft.nav_pages.v2', 300, fn () => Page::query()->published()->where('show_in_nav', true)->orderBy('nav_order')->get(['title', 'slug'])->toArray());
            $menuRows = Cache::remember(MenuItem::CACHE_KEY, 300, fn () => MenuItem::query()->where('is_visible', true)->orderBy('sort')->orderBy('id')->get(['location', 'label', 'url', 'new_tab'])->toArray());
            $servers = GameServer::hydrate($serverRows);
            $navPages = Page::hydrate($pageRows);
        } catch (Throwable) {
            $servers = collect();
            $navPages = collect();
            $menuRows = [];
        }
        $customCss = (string) $s->get('appearance.custom_css');

        $online = $servers->sum(fn ($srv) => $srv->status_online ? (int) $srv->status_players : 0);

        return [
            'site' => [
                'name' => (string) $s->get('site.name'),
                'hero_title' => (string) $s->get('site.hero_title'),
                'tagline' => (string) $s->get('site.tagline'),
                'description' => (string) $s->get('site.description'),
                'java_address' => (string) $s->get('site.java_address'),
                'bedrock_address' => (string) $s->get('site.bedrock_address'),
                'bedrock_port' => (int) $s->get('site.bedrock_port'),
                'footer_note' => (string) $s->get('site.footer_note'),
                'announcement' => (string) $s->get('site.announcement'),
                'season_label' => (string) $s->get('site.season_label'),
                'season_ends_at' => $s->get('site.season_ends_at'),
                'logo' => $s->get('site.logo_media_id') ? route('media.show', ['media' => $s->get('site.logo_media_id')]) : null,
                'gifts' => $s->bool('store.gifts'),
                'hero' => $s->get('site.hero_media_id') ? route('media.show', ['media' => $s->get('site.hero_media_id')]) : null,
                'discord' => (string) $s->get('social.discord_invite'),
                'social' => array_filter([
                    'YouTube' => (string) $s->get('social.youtube'),
                    'Instagram' => (string) $s->get('social.instagram'),
                    'TikTok' => (string) $s->get('social.tiktok'),
                    'X' => (string) $s->get('social.x'),
                ]),
                'wallet_label' => (string) $s->get('store.wallet_label'),
                'modules' => [
                    'store' => $s->bool('modules.store'),
                    'news' => $s->bool('modules.news'),
                    'tickets' => $s->bool('modules.tickets'),
                    'wiki' => $s->bool('modules.wiki'),
                ],
                'registration' => $s->bool('auth.registration'),
                'menus' => [
                    'header' => array_values(array_filter($menuRows, fn ($row) => $row['location'] === 'header')),
                    'footer' => array_values(array_filter($menuRows, fn ($row) => $row['location'] === 'footer')),
                ],
                'nav_hidden' => $this->layout->hiddenNavigation(),
                'custom_css' => $customCss !== '' ? substr(hash('sha256', $customCss), 0, 12) : null,
            ],
            'publicServers' => $servers,
            'onlineTotal' => $online,
            'navPages' => $navPages,
            'themeId' => $this->themes->active(),
            'homeSections' => $this->layout->visible($this->themes->active()),
            'themeCss' => $this->themes->cssVariables(),
        ];
    }

    /** Test helper: forget the per-request cache. */
    public static function reset(): void
    {
        // Composers are request-scoped; no process-global data is retained.
    }
}
