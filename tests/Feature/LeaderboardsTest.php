<?php

namespace Tests\Feature;

use App\Domain\Bridge\BridgeSignature;
use App\Models\GameServer;
use App\Models\PlayerStat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeaderboardsTest extends TestCase
{
    private function server(string $slug, string $secret): GameServer
    {
        $server = GameServer::forceCreate(['name' => ucfirst($slug), 'slug' => $slug, 'show_on_site' => true, 'bridge_enabled' => true,
            'bridge_key_id' => 'vck_'.Str::lower(Str::random(24)), 'bridge_secret' => $secret]);

        return $server;
    }

    private function signedStats(GameServer $server, string $secret, array $body)
    {
        $json = json_encode($body, JSON_THROW_ON_ERROR);
        $timestamp = time();
        $nonce = Str::random(32);

        return $this->call('POST', '/api/bridge/v1/stats', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_VC_KEY' => $server->bridge_key_id,
            'HTTP_X_VC_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_VC_NONCE' => $nonce,
            'HTTP_X_VC_SIGNATURE' => BridgeSignature::request($secret, 'POST', '/api/bridge/v1/stats', $timestamp, $nonce, $json),
        ], $json);
    }

    public function test_signed_server_reports_are_validated_and_ranked(): void
    {
        $secret = str_repeat('e', 64);
        $server = $this->server('survival', $secret);

        $this->signedStats($server, $secret, ['players' => [
            ['uuid' => '00000000-0000-4000-8000-000000000001', 'name' => 'Alex', 'stats' => ['kills' => 12, 'playtime_minutes' => 600]],
            ['uuid' => '00000000-0000-4000-8000-000000000002', 'name' => 'Steve', 'stats' => ['kills' => 30, 'coins' => 99, 'deaths' => -4]],
            ['uuid' => 'not-a-uuid', 'name' => 'Bad', 'stats' => ['kills' => 999]],
            ['uuid' => '00000000-0000-4000-8000-000000000003', 'name' => '<script>', 'stats' => ['kills' => 999]],
        ]])->assertOk()->assertJson(['stored' => 3]);

        $this->assertSame(3, PlayerStat::query()->count());
        Cache::flush();
        $html = $this->get(route('community.leaderboards'))->assertOk()->getContent();
        $kills = substr($html, (int) strpos($html, 'id="board-kills"'), 1500);
        $this->assertLessThan(strpos($kills, 'Alex'), strpos($kills, 'Steve'));
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_unsigned_or_foreign_reports_are_rejected(): void
    {
        $secret = str_repeat('e', 64);
        $server = $this->server('survival', $secret);

        $this->postJson('/api/bridge/v1/stats', ['players' => []])->assertStatus(401);
        $this->signedStats($server, str_repeat('f', 64), ['players' => [
            ['uuid' => '00000000-0000-4000-8000-000000000001', 'name' => 'Alex', 'stats' => ['kills' => 1]],
        ]])->assertStatus(401);
        $this->assertSame(0, PlayerStat::query()->count());
    }

    public function test_leaderboard_page_is_empty_without_reports(): void
    {
        $this->get(route('community.leaderboards'))->assertOk()->assertSee(__('No scores yet.'));
    }
}
