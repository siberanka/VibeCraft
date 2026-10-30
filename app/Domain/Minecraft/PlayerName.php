<?php

namespace App\Domain\Minecraft;

final class PlayerName
{
    /** Java names: 3–16 of [A-Za-z0-9_]. Floodgate may prefix Bedrock names with "." or "*" and allows spaces replaced by "_". */
    public static function isValid(string $name): bool
    {
        return (bool) preg_match('/^[.*]?[A-Za-z0-9_]{2,16}$/', $name);
    }

    public static function isValidUuid(string $uuid): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid);
    }

    public static function normalizeUuid(string $uuid): ?string
    {
        $hex = strtolower(str_replace('-', '', trim($uuid)));
        if (! preg_match('/^[0-9a-f]{32}$/', $hex)) {
            return null;
        }

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }
}
