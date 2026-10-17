<?php

declare(strict_types=1);

namespace App\Rules;

use App\Domain\Auth\PasswordStrength;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Mot de passe « Fort » au minimum (Q30 a) : ≥ 10 caractères, majuscule, minuscule, chiffre — équivalent de
 * Password::min(10)->mixedCase()->numbers()->uncompromised(), avec un message par règle manquante (lang/fr/validation.php).
 * Le contrôle « compromis » (Have I Been Pwned, k-anonymat) est désactivé en environnement de test.
 */
final class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.strong_password.too_short')->translate(['min' => PasswordStrength::MIN_LENGTH]);

            return;
        }

        $checks = PasswordStrength::checks($value);

        if (! $checks['length']) {
            $fail('validation.strong_password.too_short')->translate(['min' => PasswordStrength::MIN_LENGTH]);
        }

        if (! $checks['uppercase']) {
            $fail('validation.strong_password.uppercase')->translate();
        }

        if (! $checks['lowercase']) {
            $fail('validation.strong_password.lowercase')->translate();
        }

        if (! $checks['digit']) {
            $fail('validation.strong_password.digit')->translate();
        }

        if (PasswordStrength::score($value)->isAcceptable() && self::checksCompromised() && $this->isCompromised($value)) {
            $fail('validation.strong_password.compromised')->translate();
        }
    }

    /** Le contrôle en ligne est coupé en test (aucun appel réseau) ; désactivable par `xomiton.accounts.check_compromised`. */
    public static function checksCompromised(): bool
    {
        if (app()->environment('testing')) {
            return false;
        }

        return (bool) config('xomiton.accounts.check_compromised', true);
    }

    private function isCompromised(string $password): bool
    {
        return Validator::make(['password' => $password], ['password' => [Password::min(1)->uncompromised()]])->fails();
    }
}
