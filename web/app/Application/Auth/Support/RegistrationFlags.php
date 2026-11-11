<?php

declare(strict_types=1);

namespace App\Application\Auth\Support;

use App\Enums\ConsentKind;
use App\Models\Consent;
use Illuminate\Support\Str;

/**
 * Signalement d'une inscription (D-MOB-17, tranché 2026-10-29) : deux signaux simples et déterministes, sans score ni
 * service externe. Un compte signalé attend la validation de l'équipe (file « Inscriptions ») ; les autres sont actifs
 * dès l'inscription (accès complet après confirmation de l'e-mail). Seuls les codes sont conservés (`users.registration_flags`).
 *  - `disposable_email` : domaine (ou sous-domaine) d'une liste configurable (`xomiton.accounts.registration_flags.disposable_domains`
 *    et `XOMITON_DISPOSABLE_DOMAINS`) ;
 *  - `repeated_fingerprint` : déjà `burst_max` inscriptions ou plus avec la même empreinte (IP + navigateur) pendant les
 *    `burst_minutes` dernières minutes.
 */
final class RegistrationFlags
{
    public const DISPOSABLE_EMAIL = 'disposable_email';

    public const REPEATED_FINGERPRINT = 'repeated_fingerprint';

    /** @var list<string> */
    public const ALL = [self::DISPOSABLE_EMAIL, self::REPEATED_FINGERPRINT];

    private function __construct() {}

    /**
     * @return list<string>
     */
    public static function evaluate(string $email, ?string $ip, ?string $userAgent): array
    {
        $flags = [];

        if (self::isDisposable($email)) {
            $flags[] = self::DISPOSABLE_EMAIL;
        }

        if (self::isRepeated($ip, $userAgent)) {
            $flags[] = self::REPEATED_FINGERPRINT;
        }

        return $flags;
    }

    public static function isDisposable(string $email): bool
    {
        $domain = Str::lower(trim(Str::after($email, '@')));

        if ($domain === '' || ! str_contains($email, '@')) {
            return false;
        }

        foreach (self::disposableDomains() as $listed) {
            if ($domain === $listed || str_ends_with($domain, '.'.$listed)) {
                return true;
            }
        }

        return false;
    }

    public static function isRepeated(?string $ip, ?string $userAgent): bool
    {
        if ($ip === null || trim($ip) === '') {
            return false;
        }

        $max = max(1, (int) config('xomiton.accounts.registration_flags.burst_max', 3));
        $minutes = max(1, (int) config('xomiton.accounts.registration_flags.burst_minutes', 60));

        // Une inscription = un consentement « conditions » horodaté avec l'IP et le navigateur (RegisterUser).
        $query = Consent::query()
            ->where('kind', ConsentKind::Terms)
            ->where('ip', Str::limit($ip, 45, ''))
            ->where('created_at', '>=', now()->subMinutes($minutes));

        $userAgent === null ? $query->whereNull('user_agent') : $query->where('user_agent', Str::limit($userAgent, 255, ''));

        return $query->count() >= $max;
    }

    /**
     * @param  string|null  $flags  valeur de `users.registration_flags`
     * @return list<string>
     */
    public static function parse(?string $flags): array
    {
        if ($flags === null || trim($flags) === '') {
            return [];
        }

        return array_values(array_intersect(self::ALL, array_map('trim', explode(',', $flags))));
    }

    /**
     * @return list<string>
     */
    private static function disposableDomains(): array
    {
        $configured = (array) config('xomiton.accounts.registration_flags.disposable_domains', []);
        $extra = array_filter(array_map('trim', explode(',', (string) config('xomiton.accounts.registration_flags.extra_domains', ''))));

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $domain): string => Str::lower(ltrim(trim((string) $domain), '@')),
            array_merge($configured, $extra),
        ))));
    }
}
