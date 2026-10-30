<?php

namespace App\Support;

use NumberFormatter;

/**
 * Money is always an integer amount of minor units (kuruş, cents) in the store currency.
 */
final class Money
{
    public static function currency(): string
    {
        return strtoupper((string) config('vibecraft.store.currency', 'TRY'));
    }

    public static function format(int $minor, ?string $currency = null): string
    {
        $currency ??= self::currency();

        return self::formatForLocale($minor, $currency, app()->getLocale());
    }

    public static function formatForLocale(int $minor, string $currency, string $language): string
    {
        $locale = $language === 'tr' ? 'tr_TR' : 'en_US';

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($minor / 100, $currency);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        return number_format($minor / 100, 2, ',', '.').' '.$currency;
    }

    /** Parses user input such as "12,50" or "12.50" into minor units without floats. */
    public static function parse(string $input): ?int
    {
        $input = str_replace([' ', "\u{00A0}"], '', trim($input));
        if (! preg_match('/^(\d{1,9})(?:[.,](\d{1,2}))?$/', $input, $m)) {
            return null;
        }

        return ((int) $m[1]) * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    public static function toDecimalString(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);

        return $sign.intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
