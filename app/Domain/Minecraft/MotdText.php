<?php

namespace App\Domain\Minecraft;

final class MotdText
{
    /** Flattens a chat component (string or JSON tree) into plain text without § codes. */
    public static function plain(mixed $component, int $depth = 0): string
    {
        if ($depth > 16) {
            return '';
        }
        if (is_string($component)) {
            return self::stripCodes($component);
        }
        if (! is_array($component)) {
            return '';
        }

        $text = isset($component['text']) && is_string($component['text']) ? $component['text'] : '';
        foreach ((array) ($component['extra'] ?? []) as $child) {
            $text .= self::plain($child, $depth + 1);
        }

        return self::stripCodes($text);
    }

    public static function stripCodes(string $s): string
    {
        return trim((string) preg_replace('/\x{00A7}[0-9A-FK-ORXa-fk-orx]/u', '', $s));
    }
}
