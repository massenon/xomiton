<?php

declare(strict_types=1);

namespace App\Domain\Account;

use App\Enums\ProfileVisibility;

/**
 * Qui voit le profil d'un compte (et donc la liste de ses publications, `GET users/{id}/posts`, P6-08), sans base de
 * données : l'appelant fournit les faits. Même lecture de `profiles.visibility` que les albums (Album::scopeVisibleTo) :
 *
 *  - la personne voit toujours son propre profil ;
 *  - un compte inaccessible (suspendu, anonymisé) ou un blocage, dans un sens ou dans l'autre, le retire (FR-SOC-04) ;
 *  - `public` et `community` : tout compte du réseau ; `followers` : ses abonné·es ; `private` : personne d'autre.
 */
final class ProfileAudience
{
    private function __construct() {}

    public static function allows(
        ?ProfileVisibility $visibility,
        bool $isSelf,
        bool $isReachable = true,
        bool $followsOwner = false,
        bool $blocked = false,
    ): bool {
        if ($isSelf) {
            return true;
        }

        if (! $isReachable || $blocked) {
            return false;
        }

        return match ($visibility ?? ProfileVisibility::Community) {
            ProfileVisibility::Public, ProfileVisibility::Community => true,
            ProfileVisibility::Followers => $followsOwner,
            ProfileVisibility::Private => false,
        };
    }
}
