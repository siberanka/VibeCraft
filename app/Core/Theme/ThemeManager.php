<?php

namespace App\Core\Theme;

use App\Core\Settings\Settings;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;

/**
 * Resolves the active theme and exposes its manifest and design tokens.
 *
 * Views are resolved through the "theme::" namespace: the active theme's own
 * views first, then the shared themed views in resources/views/themed. Themes
 * contain Blade templates and static assets only — no PHP classes, no routes,
 * no database access.
 */
class ThemeManager
{
    private ?string $active = null;

    /** @var array<string, array<string, mixed>> */
    private array $manifests = [];

    public function __construct(private Settings $settings) {}

    /** @return list<string> */
    public function available(): array
    {
        return config('vibecraft.themes');
    }

    public function active(): string
    {
        if ($this->active !== null) {
            return $this->active;
        }

        $id = (string) $this->settings->get('theme.active');

        return $this->active = in_array($id, $this->available(), true) ? $id : 'atlas';
    }

    public function use(string $id): void
    {
        if (! in_array($id, $this->available(), true)) {
            throw new InvalidArgumentException("Unknown theme [$id].");
        }

        $this->active = $id;
        $this->registerViews();
    }

    public function registerViews(): void
    {
        View::replaceNamespace('theme', [
            resource_path('themes/'.$this->active().'/views'),
            resource_path('views/themed'),
        ]);
    }

    /** @return array<string, mixed> */
    public function manifest(?string $id = null): array
    {
        $id ??= $this->active();

        return $this->manifests[$id] ??= json_decode(
            (string) file_get_contents(resource_path("themes/$id/theme.json")), true, 16, JSON_THROW_ON_ERROR
        );
    }

    /** Browser colour scheme of the theme, so native controls and scrollbars match it. */
    public function scheme(?string $id = null): string
    {
        return ($this->manifest($id)['scheme'] ?? 'dark') === 'light' ? 'light' : 'dark';
    }

    /**
     * Themes that restyle shared components load their stylesheet after the shared ones,
     * so their rules win at equal specificity. The first five themes keep the early position.
     */
    public function loadsStylesheetLast(?string $id = null): bool
    {
        return ($this->manifest($id)['stylesheet'] ?? 'early') === 'late';
    }

    /**
     * Effective tokens: manifest defaults overridden by validated admin values.
     *
     * @return array<string, string>
     */
    public function tokens(?string $id = null): array
    {
        $id ??= $this->active();
        $defaults = $this->manifest($id)['tokens'] ?? [];
        $overrides = (array) $this->settings->get("theme.tokens.$id", []);

        $tokens = [];
        foreach ($defaults as $name => $default) {
            $value = $overrides[$name] ?? $default;
            $tokens[$name] = self::isValidToken($name, (string) $value) ? (string) $value : (string) $default;
        }

        return $tokens;
    }

    public function cssVariables(?string $id = null): string
    {
        $lines = [];
        foreach ($this->tokens($id) as $name => $value) {
            $lines[] = '--'.$name.':'.$value;
        }

        return ':root{'.implode(';', $lines).'}';
    }

    /**
     * Tokens are emitted inside a <style> element, so only strictly shaped values are
     * accepted: hex colours and small pixel lengths. Anything else (url(), expressions,
     * braces, semicolons) is rejected.
     */
    public static function isValidToken(string $name, string $value): bool
    {
        if (! preg_match('/^[a-z][a-z0-9-]{1,40}$/', $name)) {
            return false;
        }
        if (str_starts_with($name, 'c-')) {
            return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $value);
        }
        if (str_starts_with($name, 'r-')) {
            return (bool) preg_match('/^(?:[0-9]|[12][0-9]|3[0-2])px$/', $value);
        }

        return false;
    }
}
