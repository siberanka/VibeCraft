<?php

namespace App\Support;

/**
 * Links staff may place in site menus: a same-site path ("/rules") or an https
 * address. Protocol-relative, credentialed, scripted and whitespace-bearing
 * values are refused so a menu entry can never become a script or redirect sink.
 */
final class SiteLink
{
    public static function valid(mixed $value): bool
    {
        if (! is_string($value) || $value === '' || strlen($value) > 255 || preg_match('/[\x00-\x20\x7F"\'<>\\\\`]/', $value) === 1) {
            return false;
        }
        if (str_starts_with($value, '/')) {
            return ! str_starts_with($value, '//');
        }
        if (! str_starts_with($value, 'https://') || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($value);

        return is_array($parts) && ($parts['scheme'] ?? '') === 'https' && ($parts['host'] ?? '') !== '' && ! isset($parts['user']) && ! isset($parts['pass']);
    }

    public static function external(string $value): bool
    {
        return str_starts_with($value, 'https://');
    }
}
