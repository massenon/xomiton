<?php

declare(strict_types=1);

namespace App\Application\Platform\Settings;

use App\Support\Settings;
use Throwable;

/**
 * Politique de mot de passe paramétrable (Q30, FR-AUTH-03, P13-04) : le plancher (`accounts.password_min_length`, 10,
 * majuscule, minuscule, chiffre) ne peut que se **durcir** — longueur supérieure, symbole obligatoire. L'expiration
 * (`password_expiry_days`) est enregistrée et lue ici ; son application à la connexion reste à livrer (plan §7).
 */
final class PasswordPolicy
{
    public static function floor(): int
    {
        return max(1, (int) config('xomiton.accounts.password_min_length', 10));
    }

    public static function minLength(): int
    {
        return max(self::floor(), self::int('password_min_length', self::floor()));
    }

    public static function requireSymbol(): bool
    {
        try {
            return Settings::bool('password_require_symbol', false);
        } catch (Throwable) {
            return false;
        }
    }

    public static function expiryDays(): int
    {
        return max(0, self::int('password_expiry_days', 0));
    }

    public static function hasSymbol(string $password): bool
    {
        return preg_match('/[^\p{L}\p{N}\s]/u', $password) === 1;
    }

    private static function int(string $key, int $default): int
    {
        try {
            return Settings::int($key, $default);
        } catch (Throwable) {
            // Base indisponible : le plancher de la configuration s'applique.
            return $default;
        }
    }
}
