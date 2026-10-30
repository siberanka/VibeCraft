<?php

namespace App\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\Settings;
use App\Core\Theme\ThemeManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminThemeTokenController extends Controller
{
    /** Text and button pairs that must stay readable (WCAG 2.2 AA, normal text). */
    private const CONTRAST_PAIRS = [['c-ink', 'c-bg'], ['c-ink', 'c-surface'], ['c-accent-ink', 'c-accent'], ['c-muted', 'c-bg']];

    public function __construct(private ThemeManager $themes, private Settings $settings) {}

    public function edit(string $theme): View
    {
        abort_unless(in_array($theme, $this->themes->available(), true), 404);

        return view('theme::admin.theme-tokens', [
            'theme' => $theme,
            'name' => $this->themes->manifest($theme)['name'] ?? $theme,
            'tokens' => $this->themes->tokens($theme),
            'defaults' => $this->themes->manifest($theme)['tokens'] ?? [],
        ]);
    }

    public function update(Request $request, string $theme, AuditLogger $audit): RedirectResponse
    {
        abort_unless(in_array($theme, $this->themes->available(), true), 404);
        $defaults = $this->themes->manifest($theme)['tokens'] ?? [];
        $input = $request->input('tokens', []);
        abort_unless(is_array($input), 422);

        $values = [];
        $errors = [];
        foreach ($defaults as $name => $default) {
            if (! array_key_exists($name, $input)) {
                continue;
            }
            $value = is_string($input[$name]) ? strtolower(trim($input[$name])) : '';
            if (str_starts_with($name, 'r-') && ctype_digit($value)) {
                $value .= 'px';
            }
            if (! ThemeManager::isValidToken($name, $value)) {
                $errors['tokens.'.$name] = __('This value is not allowed.');

                continue;
            }
            if ($value !== strtolower((string) $default)) {
                $values[$name] = $value;
            }
        }

        $effective = array_merge(array_map('strtolower', $defaults), $values);
        foreach (self::CONTRAST_PAIRS as [$fore, $back]) {
            if (isset($effective[$fore], $effective[$back]) && self::contrast($effective[$fore], $effective[$back]) < 4.5) {
                $errors['tokens.'.$fore] = __('Text colour :a on :b is too hard to read.', ['a' => $fore, 'b' => $back]);
            }
        }
        if ($errors !== []) {
            return back()->withErrors($errors)->withInput();
        }

        $this->settings->set("theme.tokens.$theme", $values);
        $audit->log('theme.tokens_updated', null, ['theme' => $theme, 'tokens' => array_keys($values)], $request->user()->id);

        return redirect()->route('admin.themes.tokens', $theme)->with('status', __('Saved.'));
    }

    public function reset(Request $request, string $theme, AuditLogger $audit): RedirectResponse
    {
        abort_unless(in_array($theme, $this->themes->available(), true), 404);
        $this->settings->set("theme.tokens.$theme", []);
        $audit->log('theme.tokens_reset', null, ['theme' => $theme], $request->user()->id);

        return redirect()->route('admin.themes.tokens', $theme)->with('status', __('Saved.'));
    }

    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(fn ($pair) => hexdec($pair) / 255, str_split(ltrim($hex, '#'), 2));
        [$r, $g, $b] = array_map(fn ($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, $channels);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
