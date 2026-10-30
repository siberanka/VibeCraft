<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Core\Theme\ThemeManager;
use App\Models\GameServer;
use App\Models\Media;
use App\Models\Post;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HomeStageTest extends TestCase
{
    public static function themes(): array
    {
        return array_map(fn (string $theme) => [$theme], ['atlas', 'forge', 'volt', 'nomad', 'mono']);
    }

    private function useTheme(string $theme): void
    {
        // The public server list is cached for a minute; every case starts from its own fixture.
        Cache::flush();
        app(Settings::class)->setMany(['theme.active' => $theme, 'site.java_address' => 'play.example.test']);
        app(ThemeManager::class)->use($theme);
    }

    private function server(array $status = []): GameServer
    {
        return GameServer::forceCreate([
            'name' => 'Survival', 'slug' => 'survival', 'java_address' => 'play.example.test',
            'query_host' => 'play.example.test', 'query_port' => 25565, 'show_on_site' => true,
            'bridge_enabled' => false, 'bridge_key_id' => 'stage-fixture', 'bridge_secret' => str_repeat('c', 64),
        ] + $status);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }

    #[DataProvider('themes')]
    public function test_every_theme_renders_one_stage_with_a_primary_java_control(string $theme): void
    {
        $this->useTheme($theme);
        $this->server();

        $xpath = $this->xpath($this->get('/')->assertOk()->getContent());

        $this->assertSame(1, $xpath->query('//section[contains(@class,"stage ")]')->length);
        $this->assertSame(1, $xpath->query('//h1')->length);
        $java = $xpath->query('//section[contains(@class,"stage ")]//div[contains(@class,"stage-join")]//button[@data-copy][1]');
        $this->assertGreaterThanOrEqual(1, $java->length);
        $this->assertSame('play.example.test', $java->item(0)?->getAttribute('data-copy'));
        $this->assertSame(1, $xpath->query('//div[contains(@class,"stage-strip")]')->length);
    }

    #[DataProvider('themes')]
    public function test_player_count_is_never_shown_before_a_status_measurement(string $theme): void
    {
        $this->useTheme($theme);
        $this->server();

        $xpath = $this->xpath($this->get('/')->assertOk()->getContent());

        $this->assertSame(0, $xpath->query('//*[contains(@class,"strip-number")]')->length);
        $this->assertSame(0, $xpath->query('//*[contains(@class,"world-meter")]')->length);
    }

    #[DataProvider('themes')]
    public function test_measured_status_shows_the_stored_count_and_a_bounded_meter(string $theme): void
    {
        $this->useTheme($theme);
        $this->server(['status_online' => true, 'status_players' => 37, 'status_max_players' => 20, 'status_checked_at' => now()]);

        $html = (string) $this->get('/')->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $this->assertSame('37', trim((string) $xpath->query('//*[contains(@class,"strip-number")]')->item(0)?->textContent));
        // More players than slots must not overflow the meter scale.
        $this->assertSame(1, $xpath->query('//*[contains(@class,"world-meter-fill") and contains(@class,"fill-10")]')->length);
        $this->assertStringNotContainsString(' style="', $html);
    }

    public function test_headline_is_the_server_name_unless_the_operator_sets_one(): void
    {
        $this->useTheme('volt');
        app(Settings::class)->setMany(['site.name' => 'Blockhaven', 'site.hero_title' => '']);

        $heading = fn () => trim((string) $this->xpath($this->get('/')->assertOk()->getContent())->query('//h1')->item(0)?->textContent);

        $this->assertSame('Blockhaven', $heading());
        app(Settings::class)->set('site.hero_title', 'Season 4 is live');
        // Request-scoped view data is rebuilt for the next request, as it is in production.
        $this->app->forgetScopedInstances();
        $this->assertSame('Season 4 is live', $heading());
    }

    public function test_operator_image_replaces_the_theme_scene_and_top_up_is_in_navigation(): void
    {
        $this->useTheme('nomad');
        $media = Media::query()->create([
            'disk' => 'local', 'path' => 'media/hero.png', 'original_name' => 'hero.png', 'mime' => 'image/png',
            'size' => 10, 'width' => 1600, 'height' => 900, 'sha256' => str_repeat('a', 64),
        ]);
        app(Settings::class)->setMany(['site.hero_media_id' => $media->id, 'store.wallet_label' => 'Kredi']);

        $xpath = $this->xpath($this->get('/')->assertOk()->getContent());

        $scene = $xpath->query('//section[contains(@class,"stage ")]//figure[contains(@class,"operator-scene")]//img');
        $this->assertSame(1, $scene->length);
        $this->assertStringContainsString('/media/'.$media->id, (string) $scene->item(0)?->getAttribute('src'));
        $this->assertSame(0, $xpath->query('//section[contains(@class,"stage ")]//img[contains(@src,"nomad-landscape")]')->length);
        $this->assertGreaterThanOrEqual(1, $xpath->query('//a[contains(@class,"nav-topup") and contains(@href,"#topup")]')->length);
    }

    public function test_news_archive_uses_the_picture_board_with_pagination(): void
    {
        $this->useTheme('atlas');
        foreach (range(1, 14) as $i) {
            Post::query()->create([
                'title' => "Update $i", 'slug' => "update-$i", 'body_markdown' => 'x', 'body_html' => '<p>x</p>',
                'status' => 'published', 'published_at' => now()->subMinutes($i),
            ]);
        }

        $xpath = $this->xpath($this->get('/news')->assertOk()->getContent());

        $this->assertSame(12, $xpath->query('//article[contains(@class,"news-tile")]')->length);
        $this->assertSame(1, $xpath->query('//article[contains(@class,"news-tile-lead")]')->length);
    }
}
