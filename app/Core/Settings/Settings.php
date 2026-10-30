<?php

namespace App\Core\Settings;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Database-backed settings with defaults. Secrets are stored encrypted with the
 * application key and are only readable through secret().
 */
class Settings
{
    private const CACHE_KEY = 'vibecraft.settings.v1';

    /** @var array<string, array{value: mixed, encrypted: bool}>|null */
    private ?array $loaded = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->all()[$key] ?? null;
        if ($row === null || $row['encrypted']) {
            return $default ?? SettingsDefaults::get($key);
        }

        return $row['value'];
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function secret(string $key): ?string
    {
        $row = $this->all()[$key] ?? null;
        if ($row === null || ! $row['encrypted'] || $row['value'] === null || $row['value'] === '') {
            return null;
        }

        try {
            return Crypt::decryptString((string) $row['value']);
        } catch (DecryptException) {
            Log::warning('settings.secret_undecryptable', ['key' => $key]);

            return null;
        }
    }

    public function hasSecret(string $key): bool
    {
        return $this->secret($key) !== null;
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => json_encode($value), 'is_encrypted' => false]);
        $this->flush();
    }

    /** @param array<string, mixed> $values */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => json_encode($value), 'is_encrypted' => false]);
        }
        $this->flush();
    }

    public function setSecret(string $key, ?string $plain): void
    {
        if ($plain === null || $plain === '') {
            Setting::query()->whereKey($key)->delete();
        } else {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => Crypt::encryptString($plain), 'is_encrypted' => true]);
        }
        $this->flush();
    }

    public function flush(): void
    {
        $this->loaded = null;
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, array{value: mixed, encrypted: bool}> */
    private function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        try {
            // Security toggles and secret rotation must be visible on the next
            // request. A process/shared cache can repopulate stale rows during
            // a transaction; this small table is loaded once per request instead.
            return $this->loaded = Setting::query()->get()->mapWithKeys(fn (Setting $s) => [$s->key => [
                'value' => $s->is_encrypted ? $s->value : json_decode((string) $s->value, true),
                'encrypted' => $s->is_encrypted,
            ]])->all();
        } catch (Throwable) {
            // Database not reachable yet (installer): fall back to defaults.
            return $this->loaded = [];
        }
    }
}
