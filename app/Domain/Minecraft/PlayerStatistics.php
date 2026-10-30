<?php

namespace App\Domain\Minecraft;

use App\Models\GameServer;
use App\Models\PlayerStat;
use Illuminate\Support\Facades\Cache;

/**
 * Stores statistics a game server reports about its own online players and serves
 * leaderboards. A server can only write rows under its own id; names and UUIDs
 * are validated like any other bridge input.
 */
class PlayerStatistics
{
    private const MAX_VALUE = 9_000_000_000_000;

    /**
     * @param  array<int, mixed>  $players  untrusted bridge input
     */
    public function record(GameServer $server, array $players): int
    {
        $rows = [];
        $now = now();
        foreach ($players as $player) {
            if (! is_array($player)) {
                continue;
            }
            $uuid = PlayerName::normalizeUuid((string) ($player['uuid'] ?? ''));
            $name = (string) ($player['name'] ?? '');
            if ($uuid === null || ! PlayerName::isValid($name)) {
                continue;
            }
            foreach ((array) ($player['stats'] ?? []) as $stat => $value) {
                if (! in_array($stat, PlayerStat::STATS, true) || ! is_int($value) || $value < 0 || $value > self::MAX_VALUE) {
                    continue;
                }
                $rows[] = ['game_server_id' => $server->id, 'player_uuid' => $uuid, 'player_name' => $name, 'stat' => $stat, 'value' => $value, 'updated_at' => $now];
            }
        }
        if ($rows !== []) {
            PlayerStat::query()->upsert($rows, ['game_server_id', 'player_uuid', 'stat'], ['player_name', 'value', 'updated_at']);
            Cache::forget('vibecraft.leaderboards.'.$server->id);
        }

        return count($rows);
    }

    /**
     * @return array<string, list<array{name: string, uuid: string, value: int}>>
     */
    public function leaderboards(GameServer $server, int $limit = 10): array
    {
        return Cache::remember('vibecraft.leaderboards.'.$server->id, 120, function () use ($server, $limit) {
            $boards = [];
            foreach (PlayerStat::STATS as $stat) {
                $boards[$stat] = PlayerStat::query()->where('game_server_id', $server->id)->where('stat', $stat)->where('value', '>', 0)
                    ->orderByDesc('value')->orderBy('player_name')->limit($limit)->get(['player_name', 'player_uuid', 'value'])
                    ->map(fn (PlayerStat $row) => ['name' => $row->player_name, 'uuid' => $row->player_uuid, 'value' => $row->value])->all();
            }

            return $boards;
        });
    }
}
