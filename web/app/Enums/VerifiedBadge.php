<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Badge vérifié par l'administration (P18-09, Q97 ; DATABASE_DICTIONARY.md `user_badges.badge`) : « Membre vérifié »
 * pour un·e membre, « Pair animateur·rice » pour un·e pair·e approuvé·e (rôle `pair`). Le client a écrit « Pair
 * éducateur » ; le libellé suit la décision de vocabulaire du 11/11/2026 (« pair animateur·rice » partout).
 */
enum VerifiedBadge: string
{
    case MemberVerified = 'membre_verifie';
    case PeerFacilitator = 'pair_animateur';

    public function label(): string
    {
        return (string) __('enums/identity.verified_badge.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::MemberVerified => 'patch-check-fill',
            self::PeerFacilitator => 'award-fill',
        };
    }
}
