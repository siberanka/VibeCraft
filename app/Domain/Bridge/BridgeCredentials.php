<?php

namespace App\Domain\Bridge;

use App\Models\GameServer;
use Illuminate\Support\Str;

final class BridgeCredentials
{
    /** @return array{key_id: string, secret: string} */
    public static function generate(): array
    {
        return [
            'key_id' => 'vck_'.Str::lower(Str::random(24)),
            'secret' => bin2hex(random_bytes(32)),
        ];
    }

    /** Rotates credentials; the old secret stops working immediately. Returns the new plain secret once. */
    public static function rotate(GameServer $server): string
    {
        $credentials = self::generate();
        $server->forceFill(['bridge_key_id' => $credentials['key_id'], 'bridge_secret' => $credentials['secret']])->save();

        return $credentials['secret'];
    }
}
