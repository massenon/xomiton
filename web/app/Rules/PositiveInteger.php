<?php

declare(strict_types=1);

namespace App\Rules;

use App\Domain\Cases\ValidationRuleKey;
use App\Rules\Concerns\ConsultsCatalog;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Entier positif ou nul (règle `positive_int` du catalogue : effectifs, seuils, cibles d'indicateurs ; prototype
 * `/^\d+$/`). Règle désactivée dans l'administration : aucun contrôle. Message : lang/fr/validation.php `positive_int`.
 */
final class PositiveInteger implements ValidationRule
{
    use ConsultsCatalog;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::catalogAllows(ValidationRuleKey::PositiveInt)) {
            return;
        }

        if (! self::isValid($value)) {
            $fail('validation.positive_int')->translate();
        }
    }

    public static function isValid(mixed $value): bool
    {
        if (is_int($value)) {
            return $value >= 0;
        }

        return is_string($value) && preg_match('/^\d{1,9}$/', trim($value)) === 1;
    }
}
