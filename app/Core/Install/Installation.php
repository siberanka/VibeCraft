<?php

namespace App\Core\Install;

final class Installation
{
    private static ?bool $installed = null;

    public static function isInstalled(): bool
    {
        return self::$installed ??= is_file(config('vibecraft.install_lock'));
    }

    public static function markInstalled(): void
    {
        $file = config('vibecraft.install_lock');
        $written = file_put_contents($file, json_encode([
            'installed_at' => now()->toIso8601String(),
            'version' => config('vibecraft.version'),
        ]), LOCK_EX);
        if ($written === false) {
            throw new \RuntimeException('Installation marker could not be written.');
        }
        chmod($file, 0600);
        self::$installed = true;
    }

    /** Test helper. */
    public static function forget(): void
    {
        self::$installed = null;
    }
}
