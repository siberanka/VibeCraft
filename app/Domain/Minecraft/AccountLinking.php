<?php

namespace App\Domain\Minecraft;

use App\Core\Audit\AuditLogger;
use App\Domain\Commerce\CommerceException;
use App\Models\GameIdentity;
use App\Models\GameServer;
use App\Models\LinkCode;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Links a web account to a Minecraft identity. The player proves ownership by typing
 * a short-lived, single-use code in game; the server (authenticated via the signed
 * bridge) reports the UUID it observed. A player name alone is never accepted.
 */
class AccountLinking
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private AuditLogger $audit) {}

    public function issueCode(User $user): string
    {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        DB::transaction(function () use ($user, $code) {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            LinkCode::query()->where('user_id', $user->id)->whereNull('consumed_at')->delete();
            LinkCode::query()->create([
                'user_id' => $user->id,
                'code_hash' => hash('sha256', $code),
                'expires_at' => now()->addMinutes((int) config('vibecraft.bridge.link_code_ttl_minutes')),
            ]);
        });

        return $code;
    }

    public function consume(GameServer $server, string $code, string $uuid, string $name, string $edition = 'java', ?string $xuid = null): GameIdentity
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        $uuid = PlayerName::normalizeUuid($uuid) ?? '';
        if (! $server->bridge_enabled || strlen($code) !== 8 || $uuid === '' || ! PlayerName::isValid($name) || ! in_array($edition, ['java', 'bedrock'], true)
            || ($edition === 'java' && ! preg_match('/^[A-Za-z0-9_]{3,16}$/D', $name))
            || ($edition === 'bedrock' && ($xuid === null || ! preg_match('/^[1-9][0-9]{0,19}$/D', $xuid)
                || (strlen($xuid) === 20 && strcmp($xuid, '18446744073709551615') > 0)))) {
            throw new CommerceException('invalid_request');
        }

        try {
            return DB::transaction(function () use ($server, $code, $uuid, $name, $edition, $xuid) {
                /** @var LinkCode|null $candidate */
                $candidate = LinkCode::query()->where('code_hash', hash('sha256', $code))->first();
                if ($candidate === null) {
                    throw new CommerceException('invalid_code');
                }
                // Shared with code issuance, unlinking and checkout identity snapshots.
                User::query()->whereKey($candidate->user_id)->lockForUpdate()->firstOrFail();
                $link = LinkCode::query()->whereKey($candidate->id)->lockForUpdate()->first();
                if ($link === null || $link->consumed_at !== null || $link->expires_at->isPast()) {
                    throw new CommerceException('invalid_code');
                }

                $owner = GameIdentity::query()->where('edition', $edition)->where('uuid', $uuid)->first();
                if ($owner !== null && $owner->user_id !== $link->user_id) {
                    throw new CommerceException('already_linked_elsewhere');
                }
                if ($edition === 'bedrock') {
                    $xuidOwner = GameIdentity::query()->where('edition', 'bedrock')->where('xuid', $xuid)->first();
                    if ($xuidOwner !== null && ($xuidOwner->user_id !== $link->user_id || $xuidOwner->uuid !== $uuid)) {
                        throw new CommerceException('already_linked_elsewhere');
                    }
                }
                $existing = GameIdentity::query()->where('user_id', $link->user_id)->where('edition', $edition)->first();
                if ($existing !== null && ($existing->uuid !== $uuid || ($edition === 'bedrock' && $existing->xuid !== $xuid))) {
                    throw new CommerceException('account_has_other_identity');
                }

                $link->forceFill(['consumed_at' => now()])->save();

                $identity = GameIdentity::query()->updateOrCreate(
                    ['user_id' => $link->user_id, 'edition' => $edition],
                    [
                        'uuid' => $uuid,
                        'xuid' => $edition === 'bedrock' ? $xuid : null,
                        'username' => $name,
                        'verified_via' => 'in_game_code',
                        'verified_on_server_id' => $server->id,
                        'verified_at' => now(),
                    ],
                );

                $this->audit->log('identity.linked', $identity, ['uuid' => $uuid, 'name' => $name, 'server' => $server->id], $link->user_id);

                return $identity;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw new CommerceException('already_linked_elsewhere');
        }
    }

    public function unlink(User $user, string $edition): void
    {
        DB::transaction(function () use ($user, $edition) {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $identity = GameIdentity::query()->where('user_id', $user->id)->where('edition', $edition)->first();
            if ($identity !== null) {
                $identity->delete();
                $this->audit->log('identity.unlinked', $identity, ['uuid' => $identity->uuid], $user->id);
            }
        });
    }
}
