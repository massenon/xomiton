<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Support;

/**
 * Les quatre phases de l'inscription (D4) : seule la première est obligatoire ; les suivantes ont « Passer ».
 * Chaque phase est un écran et une route (`inscription.<phase>`), le stepper affiche « Étape n sur 4 ».
 */
final class RegistrationSteps
{
    /** @var list<string> */
    public const STEPS = ['account', 'profile', 'security', 'consents'];

    /** @var array<string, string> phase → nom de route */
    public const ROUTES = [
        'account' => 'inscription',
        'profile' => 'inscription.profil',
        'security' => 'inscription.securite',
        'consents' => 'inscription.consentements',
    ];

    private function __construct() {}

    public static function number(string $step): int
    {
        $index = array_search($step, self::STEPS, true);

        return $index === false ? 1 : $index + 1;
    }

    public static function next(string $step): ?string
    {
        $index = array_search($step, self::STEPS, true);

        return $index === false ? null : (self::STEPS[$index + 1] ?? null);
    }

    public static function previous(string $step): ?string
    {
        $index = array_search($step, self::STEPS, true);

        return $index === false || $index === 0 ? null : self::STEPS[$index - 1];
    }

    public static function isOptional(string $step): bool
    {
        return $step !== 'account';
    }

    /** @return list<string> Libellés traduits, dans l'ordre. */
    public static function labels(): array
    {
        return array_map(static fn (string $step): string => (string) __('auth.register.steps.'.$step), self::STEPS);
    }

    /** Route de la phase suivante, ou null quand l'inscription est terminée. */
    public static function nextRoute(string $step): ?string
    {
        $next = self::next($step);

        return $next === null ? null : route(self::ROUTES[$next]);
    }
}
