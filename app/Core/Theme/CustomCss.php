<?php

namespace App\Core\Theme;

/**
 * Staff-written CSS for the public theme. The stored source is compiled once on
 * save: comments are removed and the remaining text is checked, so the exact
 * bytes that are served are the bytes that were checked. Only same-site images,
 * a short list of at-rules and no escapes, imports or scripted values pass.
 */
final class CustomCss
{
    public const MAX_BYTES = 20000;

    private const AT_RULES = ['media', 'supports', 'keyframes', '-webkit-keyframes', 'container', 'layer', 'font-feature-values'];

    private const FUNCTIONS = ['expression', 'image', 'image-set', '-webkit-image-set', 'cross-fade', '-webkit-cross-fade', 'element', '-moz-element', 'src', 'paint'];

    /** @return array{css: string, errors: list<string>} */
    public static function compile(string $source): array
    {
        $source = str_replace(["\r\n", "\r"], "\n", $source);
        if ($source === '' || trim($source) === '') {
            return ['css' => '', 'errors' => []];
        }
        if (! mb_check_encoding($source, 'UTF-8')) {
            return ['css' => '', 'errors' => [__('The CSS must be valid UTF-8 text.')]];
        }
        if (strlen($source) > self::MAX_BYTES) {
            return ['css' => '', 'errors' => [__('The CSS must not be larger than :size KB.', ['size' => intdiv(self::MAX_BYTES, 1000)])]];
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $source) === 1) {
            return ['css' => '', 'errors' => [__('The CSS contains control characters.')]];
        }

        $css = (string) preg_replace('#/\*.*?\*/#s', '', $source);
        $errors = [];
        if (str_contains($css, '/*')) {
            $errors[] = __('A comment is not closed.');
        }
        if (str_contains($css, '<')) {
            $errors[] = __('The character < is not allowed.');
        }
        if (str_contains($css, '\\')) {
            $errors[] = __('Backslash escapes are not allowed.');
        }
        if (preg_match('/(java|vb)script\s*:|(?<![\w-])behavior\s*:|-moz-binding/i', $css) === 1) {
            $errors[] = __('Scripted values are not allowed.');
        }

        preg_match_all('/@([A-Za-z-]+)/', $css, $atRules);
        foreach (array_unique(array_map('strtolower', $atRules[1])) as $rule) {
            if (! in_array($rule, self::AT_RULES, true)) {
                $errors[] = __('The rule @:rule is not allowed.', ['rule' => $rule]);
            }
        }

        preg_match_all('/(?<![\w-])([A-Za-z-]+)\s*\(/', $css, $calls);
        foreach (array_unique(array_map('strtolower', $calls[1])) as $function) {
            if (in_array($function, self::FUNCTIONS, true)) {
                $errors[] = __('The function :name() is not allowed.', ['name' => $function]);
            }
        }

        $opened = preg_match_all('/(?<![\w-])url\s*\(/i', $css);
        preg_match_all('/(?<![\w-])url\s*\(\s*([^)]*)\)/i', $css, $urls);
        if ($opened !== count($urls[1])) {
            $errors[] = __('An image address is not closed.');
        }
        foreach ($urls[1] as $target) {
            $target = trim($target, " \n\t\"'");
            if (preg_match('#^/(media|assets)/[A-Za-z0-9._/-]+$#', $target) !== 1 || str_contains($target, '..')) {
                $errors[] = __('Images must come from this site (/media/… or /assets/…).');

                break;
            }
        }

        if (! self::balanced($css)) {
            $errors[] = __('Braces or quotes are not balanced.');
        }

        return $errors === [] ? ['css' => trim($css)."\n", 'errors' => []] : ['css' => '', 'errors' => array_values(array_unique($errors))];
    }

    private static function balanced(string $css): bool
    {
        $depth = 0;
        $quote = null;
        foreach (str_split($css) as $char) {
            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                } elseif ($char === "\n") {
                    return false;
                }

                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}' && --$depth < 0) {
                return false;
            }
        }

        return $depth === 0 && $quote === null;
    }
}
