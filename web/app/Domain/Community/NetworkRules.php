<?php

declare(strict_types=1);

namespace App\Domain\Community;

use App\Enums\InvitationStatus;
use App\Enums\ProfileVisibility;
use Carbon\CarbonInterface;

/**
 * Règles de « Qui suivre et invitations » (P8-06, who-to-follow.php), sans base de données : l'appelant fournit les
 * faits.
 *
 *  - **Suivre** (abonnement à sens unique, `follows`) : seulement un profil ouvert au réseau (`public`, `community`).
 *    Un profil « connexions » (`followers`) ou privé ne se suit pas d'un geste : suivre donnerait accès à ce qu'il réserve
 *    à ses abonné·es ; il faut une invitation acceptée (consentement) ;
 *  - **Suggestions** : profils ouverts au réseau et `searchable` (« trouvable par recherche », P8-09) ;
 *  - **Inviter** : une invitation acceptée relie les deux comptes (abonnement dans les deux sens : « Vous êtes
 *    connecté·es ») ; une invitation refusée ne peut pas être renvoyée par la même personne avant le délai
 *    `network.reinvite_after_refusal_days` ;
 *  - **Rôle public** : badge du prototype (x_role_label : « Pair animateur·rice » pour `pair`, « Équipe Xomiton » pour
 *    `admin` et `superadmin`), `member` pour tout autre rôle (`moderateur` sans badge, comme les forums) ; calculé sur
 *    **l'ensemble** des rôles du compte, équipe > pair > membre (une pair validée garde `membre`, ValidatePeerAccount) ;
 *  - **Zone** : affichée (et filtrable) seulement si la personne l'a rendue visible (`profiles.show_zone`).
 */
final class NetworkRules
{
    public const ROLE_MEMBER = 'member';

    public const ROLE_PEER = 'peer';

    public const ROLE_TEAM = 'team';

    public const ROLES = [self::ROLE_MEMBER, self::ROLE_PEER, self::ROLE_TEAM];

    /** Rôles techniques (spatie) de chaque rôle public. */
    public const ROLE_NAMES = [
        self::ROLE_PEER => ['pair'],
        self::ROLE_TEAM => ['admin', 'superadmin'],
    ];

    /** Rôles jamais proposés en suggestion (prototype : superadmin écarté). */
    public const HIDDEN_ROLES = ['superadmin'];

    /** Visibilités qu'un geste « Suivre » ou une suggestion peuvent atteindre. */
    public const OPEN_VISIBILITIES = [ProfileVisibility::Public, ProfileVisibility::Community];

    private function __construct() {}

    public static function isOpen(?ProfileVisibility $visibility): bool
    {
        return in_array($visibility ?? ProfileVisibility::Community, self::OPEN_VISIBILITIES, true);
    }

    public static function followable(?ProfileVisibility $visibility): bool
    {
        return self::isOpen($visibility);
    }

    public static function suggestible(?ProfileVisibility $visibility, ?bool $searchable): bool
    {
        return self::isOpen($visibility) && ($searchable ?? true);
    }

    /**
     * @param  list<string>|string|null  $roleNames  rôles techniques du compte (tous)
     */
    public static function publicRole(array|string|null $roleNames): string
    {
        $names = is_array($roleNames) ? $roleNames : [$roleNames];

        foreach ([self::ROLE_TEAM, self::ROLE_PEER] as $role) {
            if (array_intersect($names, self::ROLE_NAMES[$role]) !== []) {
                return $role;
            }
        }

        return self::ROLE_MEMBER;
    }

    public static function showsZone(?bool $showZone): bool
    {
        return $showZone ?? true;
    }

    /**
     * Une invitation refusée bloque-t-elle encore un nouvel envoi de la même personne ?
     */
    public static function refusalStillHolds(?InvitationStatus $status, ?CarbonInterface $respondedAt, CarbonInterface $now, int $days): bool
    {
        if ($status !== InvitationStatus::Refused) {
            return false;
        }

        return $respondedAt === null || $respondedAt->greaterThan($now->copy()->subDays(max(0, $days)));
    }
}
