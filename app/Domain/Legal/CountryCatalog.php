<?php

namespace App\Domain\Legal;

use Locale;
use ResourceBundle;
use RuntimeException;

final class CountryCatalog
{
    /** ICU also names unions and reserved regions, which are not customer country codes. */
    private const RESERVED = ['AC', 'CP', 'DG', 'EA', 'EU', 'EZ', 'IC', 'QO', 'TA', 'UN', 'XA', 'XB', 'XK', 'ZZ'];

    /** Labels use the installed ICU data; there is no geolocation or network lookup. */
    public static function options(?string $locale = null): array
    {
        $bundle = ResourceBundle::create('en', 'ICUDATA-region');
        $countries = $bundle?->get('Countries');
        if (! $countries instanceof ResourceBundle) {
            throw new RuntimeException('The installed ICU country catalogue is unavailable.');
        }
        $options = [];
        foreach ($countries as $code => $label) {
            if (! is_string($code) || ! preg_match('/^[A-Z]{2}$/D', $code) || in_array($code, self::RESERVED, true)) {
                continue;
            }
            $name = Locale::getDisplayRegion('und_'.$code, $locale ?? app()->getLocale());
            $options[$code] = is_string($name) && $name !== '' ? $name : (is_string($label) ? $label : $code);
        }
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    public static function contains(string $country): bool
    {
        return array_key_exists($country, self::options('en'));
    }
}
