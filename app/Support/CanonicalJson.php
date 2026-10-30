<?php

namespace App\Support;

use InvalidArgumentException;

/** Stable internal encoding of scalar facts; map key order is not contract content. */
final class CanonicalJson
{
    public static function encode(array $value): string
    {
        return json_encode(self::normalise($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function digest(array $value): string
    {
        return hash('sha256', self::encode($value));
    }

    private static function normalise(mixed $value): mixed
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value, SORT_STRING);
            }
            foreach ($value as $key => $child) {
                $value[$key] = self::normalise($child);
            }

            return $value;
        }
        if (is_object($value) || is_resource($value) || is_float($value)) {
            throw new InvalidArgumentException('Contract facts must contain only arrays, strings, integers, booleans and null.');
        }

        return $value;
    }
}
