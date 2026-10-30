<?php

namespace Tests\Feature;

use App\Domain\Minecraft\BedrockPinger;
use App\Domain\Minecraft\JavaPinger;
use App\Domain\Minecraft\StatusRefresher;
use App\Domain\Minecraft\StatusResult;
use App\Models\GameServer;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;
use UnexpectedValueException;

class StatusPersistenceTest extends TestCase
{
    public function test_malformed_peer_is_recorded_offline_and_next_peer_can_refresh(): void
    {
        $servers = collect(['Malformed peer', 'Healthy peer'])->map(fn ($name) => GameServer::forceCreate([
            'name' => $name, 'slug' => Str::lower(Str::random(12)), 'query_host' => 'fixture.example',
            'query_port' => 25565, 'query_edition' => 'java', 'bridge_key_id' => 'vck_'.bin2hex(random_bytes(12)),
            'bridge_secret' => 'fixture-only-secret',
        ]));
        $java = Mockery::mock(JavaPinger::class);
        $java->shouldReceive('ping')->once()->andReturnUsing(fn () => new StatusResult(true, players: -1));
        $java->shouldReceive('ping')->once()->andReturn(new StatusResult(true, players: 2, maxPlayers: 20, version: 'Fixture', motd: 'Local fixture'));
        $refresher = new StatusRefresher($java, Mockery::mock(BedrockPinger::class));

        $this->assertFalse($refresher->refresh($servers[0])->online);
        $this->assertNotNull($servers[0]->fresh()->status_checked_at);
        $this->assertNull($servers[0]->fresh()->status_players);
        $this->assertTrue($refresher->refresh($servers[1])->online);
        $this->assertSame(2, $servers[1]->fresh()->status_players);
    }

    public function test_invalid_remote_text_and_unsigned_overflow_cannot_reach_persistence(): void
    {
        foreach ([fn () => new StatusResult(true, players: 4_294_967_296), fn () => new StatusResult(true, version: "\xff"), fn () => new StatusResult(true, latencyMs: -1)] as $create) {
            try {
                $create();
                $this->fail('Malformed remote status was accepted.');
            } catch (UnexpectedValueException) {
                $this->assertTrue(true);
            }
        }
        $offline = StatusResult::offline("\xff");
        $this->assertFalse($offline->online);
        $this->assertTrue(mb_check_encoding($offline->error, 'UTF-8'));
    }
}
