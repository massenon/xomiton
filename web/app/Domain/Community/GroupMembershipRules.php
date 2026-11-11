<?php

declare(strict_types=1);

namespace App\Domain\Community;

use App\Enums\GroupPrivacy;
use App\Enums\GroupRole;
use App\Enums\MembershipStatus;

/**
 * Règles d'adhésion à un groupe (P8-02, FR-SOC-05), sans base de données : l'appelant fournit les faits.
 *
 *  - rejoindre un groupe public rend membre aussitôt ; un groupe privé crée une **demande** que valide l'équipe du
 *    groupe (propriétaire, admin ou modérateur·rice) ;
 *  - on ne quitte pas un groupe dont on est le ou la dernière admin (propriétaire comprise) : il faut d'abord désigner
 *    quelqu'un ; la propriété passe alors à une autre admin ;
 *  - gérer une personne (accepter, retirer, changer son rôle) : jamais la propriétaire ; la propriétaire gère tout le
 *    monde, une admin gère modérateur·rices et membres, un·e modérateur·rice gère les membres ;
 *  - changer un rôle : propriétaire ou admin seulement.
 */
final class GroupMembershipRules
{
    private function __construct() {}

    public static function joinStatus(GroupPrivacy $privacy): MembershipStatus
    {
        return $privacy === GroupPrivacy::Private ? MembershipStatus::Requested : MembershipStatus::Active;
    }

    public static function canLeave(bool $isAdmin, int $otherAdmins): bool
    {
        return ! $isAdmin || $otherAdmins > 0;
    }

    public static function canManage(bool $actorIsOwner, ?GroupRole $actorRole, bool $targetIsOwner, GroupRole $targetRole): bool
    {
        if ($targetIsOwner) {
            return false;
        }

        if ($actorIsOwner) {
            return true;
        }

        return match ($actorRole) {
            GroupRole::Admin => $targetRole !== GroupRole::Admin,
            GroupRole::Moderator => $targetRole === GroupRole::Member,
            default => false,
        };
    }

    public static function canChangeRole(bool $actorIsOwner, ?GroupRole $actorRole, bool $targetIsOwner, GroupRole $targetRole): bool
    {
        if ($targetIsOwner) {
            return false;
        }

        return $actorIsOwner || ($actorRole === GroupRole::Admin && $targetRole !== GroupRole::Admin);
    }
}
