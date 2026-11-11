<?php

declare(strict_types=1);

namespace App\Rules;

use App\Domain\Cases\ValidationRuleKey;
use App\Rules\Concerns\ConsultsCatalog;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;

/**
 * Adresse e-mail valide (règle `email` du catalogue : inscription, annuaire) : structure nom@domaine.tld selon la
 * RFC (`email:rfc`). Règle désactivée dans l'administration : la valeur doit rester un texte, sans contrôle de structure.
 * Message : lang/fr/validation.php `email`.
 */
final class EmailAddress implements ValidationRule
{
    use ConsultsCatalog;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.email')->translate();

            return;
        }

        if (! self::catalogAllows(ValidationRuleKey::Email)) {
            return;
        }

        if (! self::isValid($value)) {
            $fail('validation.email')->translate();
        }
    }

    public static function isValid(string $value): bool
    {
        return Validator::make(['email' => $value], ['email' => ['email:rfc']])->passes();
    }
}
