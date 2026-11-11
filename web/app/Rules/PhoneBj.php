<?php

declare(strict_types=1);

namespace App\Rules;

use App\Domain\Cases\ValidationRuleKey;
use App\Rules\Concerns\ConsultsCatalog;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Numéro de téléphone béninois (règle `phone_bj` du catalogue) : 10 chiffres commençant par 01 (plan 2024) ou
 * 8 chiffres (ancien plan), indicatif +229 / 00229 facultatif, espaces, points, tirets et parenthèses tolérés.
 * `normalize()` renvoie la forme canonique « +229 01 XX XX XX XX ». Règle `phone_bj` désactivée dans l'administration
 * (P11-05) : tout texte est accepté, et `format()` le garde tel quel au lieu de le perdre.
 */
final class PhoneBj implements ValidationRule
{
    use ConsultsCatalog;

    public const COUNTRY_CODE = '229';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.phone_bj')->translate();

            return;
        }

        if (! self::catalogAllows(ValidationRuleKey::PhoneBj)) {
            return;
        }

        if (self::normalize($value) === null) {
            $fail('validation.phone_bj')->translate();
        }
    }

    /**
     * Valeur à enregistrer après validation : forme canonique d'un numéro béninois, sinon le texte saisi (règle
     * désactivée par l'administration), jamais une perte.
     */
    public static function format(string $value): string
    {
        return self::normalize($value) ?? trim($value);
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
