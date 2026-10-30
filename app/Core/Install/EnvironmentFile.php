<?php

namespace App\Core\Install;

use RuntimeException;

/**
 * Writes the installer's settings into the .env file. Values are validated by
 * the caller and quoted here; line breaks are refused so one value can never
 * inject another setting.
 */
final class EnvironmentFile
{
    public static function path(): string
    {
        return base_path('.env');
    }

    /**
     * Creates .env from .env.example with a fresh application key on the first
     * request of a new upload. Install-time drivers use files, because the
     * database is not configured yet.
     */
    public static function prepare(string $base): void
    {
        $env = $base.'/.env';
        $example = $base.'/.env.example';
        if (is_file($env) || ! is_file($example) || is_file($base.'/storage/app/installed.lock') || ! is_writable($base)) {
            return;
        }
        $https = (! empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
        $content = (string) file_get_contents($example);
        $content = self::apply($content, [
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'SESSION_DRIVER' => 'file',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_SECURE_COOKIE' => $https ? 'true' : 'false',
        ]);
        $temporary = $env.'.'.bin2hex(random_bytes(6)).'.tmp';
        if (file_put_contents($temporary, $content, LOCK_EX) === false) {
            return;
        }
        @chmod($temporary, 0600);
        // A concurrent first request may have created .env already; keep the first one.
        if (! is_file($env)) {
            rename($temporary, $env);
        } else {
            @unlink($temporary);
        }
    }

    /** @param array<string, string> $values */
    public static function write(array $values): void
    {
        $file = self::path();
        $current = is_file($file) ? (string) file_get_contents($file) : '';
        $updated = self::apply($current, $values);
        $temporary = $file.'.'.bin2hex(random_bytes(6)).'.tmp';
        if (file_put_contents($temporary, $updated, LOCK_EX) === false || ! rename($temporary, $file)) {
            @unlink($temporary);
            throw new RuntimeException('The .env file could not be written.');
        }
        @chmod($file, 0600);
    }

    public static function writable(): bool
    {
        $file = self::path();

        return is_file($file) ? is_writable($file) && is_writable(dirname($file)) : is_writable(dirname($file));
    }

    /** @param array<string, string> $values */
    public static function apply(string $content, array $values): string
    {
        foreach ($values as $key => $value) {
            if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) !== 1 || preg_match('/[\r\n\0]/', $value) === 1) {
                throw new RuntimeException('Invalid environment value.');
            }
            $line = $key.'='.self::quote($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $content = preg_match($pattern, $content) === 1
                ? (string) preg_replace_callback($pattern, fn () => $line, $content, 1)
                : rtrim($content, "\n")."\n".$line."\n";
        }

        return $content;
    }

    private static function quote(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+=-]+$/', $value) === 1) {
            return $value;
        }

        // Double quotes with escaped backslash, quote and dollar keep the value literal (no expansion).
        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }
}
