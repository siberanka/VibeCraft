<?php

namespace App\Domain\Minecraft;

use App\Models\GameServer;
use App\Models\Punishment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors the ban list a game server reports about itself. The report is the
 * complete current list: entries missing from it are marked inactive.
 */
class Punishments
{
    /**
     * @param  array<int, mixed>  $bans  untrusted bridge input
     */
    public function replace(GameServer $server, array $bans): int
    {
        $rows = [];
        foreach ($bans as $ban) {
            if (! is_array($ban)) {
                continue;
            }
            $uuid = PlayerName::normalizeUuid((string) ($ban['uuid'] ?? ''));
            $name = (string) ($ban['name'] ?? '');
            if ($uuid === null || ! PlayerName::isValid($name)) {
                continue;
            }
            $rows[$uuid] = [
                'game_server_id' => $server->id, 'player_uuid' => $uuid, 'player_name' => $name, 'type' => 'ban',
                'reason' => self::text($ban['reason'] ?? null, 255),
                'staff' => self::text($ban['staff'] ?? null, 32),
                'issued_at' => self::time($ban['issued_at'] ?? null),
                'expires_at' => self::time($ban['expires_at'] ?? null),
                'active' => true,
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($server, $rows) {
            Punishment::query()->where('game_server_id', $server->id)->where('active', true)
                ->whereNotIn('player_uuid', array_keys($rows) ?: [''])->update(['active' => false, 'updated_at' => now()]);
            if ($rows !== []) {
                Punishment::query()->upsert(array_values($rows), ['game_server_id', 'player_uuid', 'type'], ['player_name', 'reason', 'staff', 'issued_at', 'expires_at', 'active', 'updated_at']);
            }
        });

        return count($rows);
    }

    private static function text(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $clean = trim((string) preg_replace('/[\x00-\x1F\x7F]|\x{00A7}./u', '', $value));

        return $clean === '' ? null : mb_substr($clean, 0, $max);
    }

    private static function time(mixed $value): ?Carbon
    {
        return is_int($value) && $value > 0 && $value < 32503680000 ? Carbon::createFromTimestamp($value) : null;
    }
}
