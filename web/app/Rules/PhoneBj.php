<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Numéro de téléphone béninois (règle `phone_bj` du catalogue) : 10 chiffres commençant par 01 (plan 2024) ou
 * 8 chiffres (ancien plan), indicatif +229 / 00229 facultatif, espaces, points, tirets et parenthèses tolérés.
 * `normalize()` renvoie la forme canonique « +229 01 XX XX XX XX ».
 */
final class PhoneBj implements ValidationRule
{
    public const COUNTRY_CODE = '229';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::normalize($value) === null) {
            $fail('validation.phone_bj')->translate();
        }
    }

    public static function isValid(string $value): bool
    {
        return self::normalize($value) !== null;
    }

    /**
     * Forme canonique « +229 01 97 38 88 47 », ou null si le numéro n'est pas béninois.
     */
    public static function normalize(string $value): ?string
    {
        $digits = preg_replace('/[\s.\-()]/', '', trim($value)) ?? '';

        if ($digits === '' || preg_match('/^\+?\d+$/', $digits) !== 1) {
            return null;
        }

        $digits = ltrim($digits, '+');

        foreach (['00'.self::COUNTRY_CODE, self::COUNTRY_CODE] as $prefix) {
            if (str_starts_with($digits, $prefix) && in_array(strlen($digits) - strlen($prefix), [8, 10], true)) {
                $digits = substr($digits, strlen($prefix));

                break;
            }
        }

        if (strlen($digits) === 8) {
            $digits = '01'.$digits;
        }

        if (strlen($digits) !== 10 || ! str_starts_with($digits, '01')) {
            return null;
        }

        return '+'.self::COUNTRY_CODE.' '.implode(' ', str_split($digits, 2));
    }
}
