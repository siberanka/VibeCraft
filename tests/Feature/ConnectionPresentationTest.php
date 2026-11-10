<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Core\Theme\ThemeManager;
use App\Models\GameServer;
use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConnectionPresentationTest extends TestCase
{
    public static function themes(): array
    {
        return array_map(fn (string $theme) => [$theme], ['atlas', 'forge', 'volt', 'nomad', 'mono', 'prism', 'arcade', 'terminal', 'brick', 'dusk']);
    }

    #[DataProvider('themes')]
    public function test_bedrock_address_and_custom_port_are_separate_in_every_theme(string $theme): void
    {
        app(Settings::class)->setMany([
            'theme.active' => $theme,
            'site.java_address' => 'play.example.test:25570',
            'site.bedrock_address' => 'bedrock.example.test',
            'site.bedrock_port' => 19199,
        ]);
        app(ThemeManager::class)->use($theme);
        GameServer::forceCreate([
            'name' => 'Survival', 'slug' => 'survival',
            'java_address' => 'play.example.test:25570',
            'bedrock_address' => 'bedrock.example.test', 'bedrock_port' => 19199,
            'query_host' => 'play.example.test', 'query_port' => 25570,
            'show_on_site' => true, 'bridge_enabled' => false,
            'bridge_key_id' => 'connection-fixture', 'bridge_secret' => str_repeat('b', 64),
        ]);

        foreach (['/', '/servers'] as $path) {
            $response = $this->get($path)->assertOk();
            $document = new DOMDocument;
            $document->loadHTML($response->getContent(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($document);
            $bedrock = $xpath->query('//button[@data-copy][contains(@aria-label,"Bedrock")]');
            $this->assertGreaterThanOrEqual(1, $bedrock->length);
            foreach ($bedrock as $button) {
                $this->assertInstanceOf(DOMElement::class, $button);
                $this->assertSame('bedrock.example.test', $button->getAttribute('data-copy'));
                $this->assertSame('Port 19199', $button->getAttribute('aria-description'));
                $this->assertStringContainsString('Port 19199', $button->textContent);
            }
            $java = $xpath->query('//button[@data-copy][contains(@aria-label,"Java")]');
            $this->assertGreaterThanOrEqual(1, $java->length);
            foreach ($java as $button) {
                $this->assertSame('play.example.test:25570', $button->getAttribute('data-copy'));
            }
        }
    }
}
