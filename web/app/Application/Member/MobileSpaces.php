<?php

declare(strict_types=1);

namespace App\Application\Member;

use App\Models\Setting;
use App\Support\Audit;
use App\Support\Settings;
use InvalidArgumentException;

/**
 * Interrupteurs serveur par espace de l'application mobile unique (P15-22, plan §8.1) : couper l'espace Pair ou l'espace
 * Admin **sans republier l'application** (revue Play, incident, fuite). Couper un espace :
 *  - le retire de `GET me` → `spaces` (le sélecteur d'espace de l'application ne le propose plus) ;
 *  - ferme son API aux appels **de l'application** (jeton Bearer) : `admin/*` (EnsureAdminMobile) et `pair/*`
 *    (EnsurePeerSpaceAccess) répondent 403 `space_disabled` ; le site web (session) n'est jamais touché ;
 *  - se lit dans `GET app/version` (`spaces_enabled`).
 * L'espace Membre ne se coupe pas (SOS, signalement, urgences : RG-01). Réglage `mobile_space_peer_enabled` /
 * `mobile_space_admin_enabled` (vrai par défaut) ; changé par `php artisan xomiton:mobile-space` (cPanel, SSH, cron) ;
 * journal d'audit `platform.mobile_space_switched`.
 */
final class MobileSpaces
{
    /** Espace => clé du réglage. */
    public const SETTINGS = [
        AccountSpaces::PEER => 'mobile_space_peer_enabled',
        AccountSpaces::ADMIN => 'mobile_space_admin_enabled',
    ];

    private function __construct() {}

    /** L'espace est-il ouvert à l'application mobile ? Membre : toujours. */
    public static function enabled(string $space): bool
    {
        $key = self::SETTINGS[$space] ?? null;

        return $key === null || Settings::bool($key, true);
    }

    /**
     * @return array{peer: bool, admin: bool}
     */
    public static function all(): array
    {
        return [
            AccountSpaces::PEER => self::enabled(AccountSpaces::PEER),
            AccountSpaces::ADMIN => self::enabled(AccountSpaces::ADMIN),
        ];
    }

    /**
     * @throws InvalidArgumentException espace inconnu ou non coupable
     */
    public static function set(string $space, bool $enabled, string $actor = 'système'): void
    {
        $key = self::SETTINGS[$space] ?? throw new InvalidArgumentException('Espace inconnu : '.$space);

        if (self::enabled($space) === $enabled && Setting::query()->where('key', $key)->exists()) {
            return;
        }

        Settings::set($key, $enabled);
        Audit::logWithoutRequest('platform.mobile_space_switched', null, sprintf('espace=%s ; état=%s', $space, $enabled ? 'ouvert' : 'coupé'), $actor);
    }
}
