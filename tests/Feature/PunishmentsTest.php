<?php

namespace Tests\Feature;

use App\Domain\Bridge\BridgeSignature;
use App\Models\GameServer;
use App\Models\Punishment;
use Illuminate\Support\Str;
use Tests\TestCase;

class PunishmentsTest extends TestCase
{
    private function report(GameServer $server, string $secret, array $bans)
    {
        $json = json_encode(['bans' => $bans], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $nonce = Str::random(32);

        return $this->call('POST', '/api/bridge/v1/punishments', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_VC_KEY' => $server->bridge_key_id, 'HTTP_X_VC_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_VC_NONCE' => $nonce, 'HTTP_X_VC_SIGNATURE' => BridgeSignature::request($secret, 'POST', '/api/bridge/v1/punishments', $timestamp, $nonce, $json),
        ], $json);
    }

    public function test_reports_replace_the_list_and_are_sanitised(): void
    {
        $secret = str_repeat('a', 64);
        $server = GameServer::forceCreate(['name' => 'Survival', 'slug' => 'survival', 'show_on_site' => true, 'bridge_enabled' => true,
            'bridge_key_id' => 'vck_'.Str::lower(Str::random(24)), 'bridge_secret' => $secret]);

        $this->report($server, $secret, [
            ['uuid' => '00000000-0000-4000-8000-000000000001', 'name' => 'Griefer', 'reason' => "§cGrief\nspawn <b>", 'staff' => 'Mod_1', 'issued_at' => time() - 60],
            ['uuid' => '00000000-0000-4000-8000-000000000002', 'name' => 'Cheater', 'reason' => 'Fly', 'expires_at' => time() + 3600],
            ['uuid' => 'bad', 'name' => 'Nope'],
        ])->assertOk()->assertJson(['stored' => 2]);

        $html = $this->get(route('community.punishments'))->assertOk()->getContent();
        $this->assertStringContainsString('Griefer', $html);
        $this->assertStringContainsString('Griefspawn &lt;b&gt;', $html);
        $this->assertStringNotContainsString('<b>', substr($html, (int) strpos($html, 'Griefer')));

        // Unbanned players disappear on the next complete report.
        $this->report($server, $secret, [['uuid' => '00000000-0000-4000-8000-000000000002', 'name' => 'Cheater', 'reason' => 'Fly']])->assertOk();
        $this->assertFalse(Punishment::query()->where('player_name', 'Griefer')->sole()->active);
        $this->get(route('community.punishments'))->assertDontSee('Griefer');
        $this->get(route('community.punishments', ['player' => 'Che']))->assertSee('Cheater');
    }

    public function test_unsigned_reports_are_rejected(): void
    {
        $this->postJson('/api/bridge/v1/punishments', ['bans' => []])->assertStatus(401);
        $this->assertSame(0, Punishment::query()->count());
    }
}
