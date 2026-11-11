<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Moyen de recontact (règle `phone_or_email`, prototype signalement.php l.142) : numéro béninois (PhoneBj) ou adresse
 * e-mail valide. Message : lang/fr/validation.php `phone_or_email`.
 */
final class PhoneOrEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('validation.phone_or_email')->translate();
        }
    }

    public static function isValid(string $value): bool
    {
        $value = trim($value);

        return $value !== '' && (filter_var($value, FILTER_VALIDATE_EMAIL) !== false || PhoneBj::isValid($value));
    }
}
