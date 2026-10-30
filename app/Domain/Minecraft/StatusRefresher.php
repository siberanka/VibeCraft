<?php

namespace App\Domain\Minecraft;

use App\Models\GameServer;
use Throwable;

class StatusRefresher
{
    public function __construct(private JavaPinger $java, private BedrockPinger $bedrock) {}

    public function refresh(GameServer $server, ?float $timeout = null): StatusResult
    {
        if (! $server->query_host) {
            return StatusResult::offline('no query host');
        }

        $timeout ??= (float) config('vibecraft.status.timeout_seconds', 3);

        try {
            $result = $server->query_edition === 'bedrock'
                ? $this->bedrock->ping($server->query_host, (int) $server->query_port, $timeout)
                : $this->java->ping($server->query_host, (int) $server->query_port, $timeout);
        } catch (Throwable $e) {
            $result = StatusResult::offline(mb_substr($e->getMessage(), 0, 200));
        }

        $server->forceFill([
            'status_online' => $result->online,
            'status_players' => $result->players,
            'status_max_players' => $result->maxPlayers,
            'status_version' => $result->version,
            'status_motd' => $result->motd,
            'status_latency_ms' => $result->latencyMs,
            'status_error' => $result->error ? mb_substr($result->error, 0, 255) : null,
            'status_checked_at' => now(),
        ])->save();

        return $result;
    }
}
