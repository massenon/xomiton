<?php

declare(strict_types=1);

namespace App\Application\Admin\Users;

use App\Domain\Account\Exceptions\AccountActionRefused;
use App\Enums\VerifiedBadge;
use App\Models\User;
use App\Models\UserBadge;
use App\Support\Audit;
use Illuminate\Support\Facades\Gate;

/**
 * Badges vérifiés par l'administration (P18-09, Q97) : attribués et retirés **à la main** par un compte `badges.manage`
 * (admin, superadmin ; UserPolicy::manageBadges) dans la fiche du compte, jamais par une règle automatique ni par la
 * personne elle-même. « Membre vérifié » pour un compte actif qui n'est pas pair·e ; « Pair animateur·rice » pour un·e
 * pair·e approuvé·e (rôle `pair`, compte actif). Journal `badges.granted` / `badges.revoked` (badge et pseudo).
 * Affichage (for()) : à côté du pseudo sur le profil, dans le fil et les forums (AuthorPayload) — seulement les badges
 * encore valables (un « Pair animateur·rice » disparaît si le rôle pair est retiré, un compte suspendu ou banni n'en
 * montre aucun). Jamais de classement ni de compteur public.
 */
final class VerifiedBadges
{
    public function grant(User $target, VerifiedBadge $badge, User $by): void
    {
        Gate::forUser($by)->authorize('manageBadges', $target);

        if (! self::eligible($target, $badge)) {
            throw new AccountActionRefused('badge_not_eligible', ['badge' => $badge->label()]);
        }

        $created = UserBadge::query()->firstOrCreate(
            ['user_id' => $target->getKey(), 'badge' => $badge->value],
            ['granted_by' => $by->getKey(), 'granted_at' => now()],
        );

        if ($created->wasRecentlyCreated) {
            $target->unsetRelation('badges');
            Audit::log('badges.granted', $target, sprintf('badge=%s ; pseudo=%s', $badge->value, $target->pseudo), $by);
        }
    }

    public function revoke(User $target, VerifiedBadge $badge, User $by): void
    {
        Gate::forUser($by)->authorize('manageBadges', $target);

        $deleted = UserBadge::query()->where('user_id', $target->getKey())->where('badge', $badge->value)->delete();

        if ($deleted > 0) {
            $target->unsetRelation('badges');
            Audit::log('badges.revoked', $target, sprintf('badge=%s ; pseudo=%s', $badge->value, $target->pseudo), $by);
        }
    }

    /** Le compte peut-il recevoir ce badge ? (compte actif ; pair·e pour « Pair animateur·rice », non pair·e sinon) */
    public static function eligible(User $user, VerifiedBadge $badge): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        $isPeer = $user->hasRole('pair');

        return $badge === VerifiedBadge::PeerFacilitator ? $isPeer : ! $isPeer;
    }

    /**
     * Badges affichés pour un compte (clé, libellé, icône), relation `badges` chargée d'avance quand c'est possible
     * (listes du fil et des forums) ; sinon une seule requête, gardée sur le modèle.
     *
     * @return list<array{key: string, label: string, icon: string}>
     */
    public static function for(User $user): array
    {
        if (! $user->relationLoaded('badges')) {
            $user->setRelation('badges', UserBadge::query()->where('user_id', $user->getKey())->get());
        }

        if ($user->isBlocked()) {
            return [];
        }

        $out = [];

        foreach ($user->getRelation('badges') as $row) {
            $badge = $row instanceof UserBadge ? $row->kind() : null;

            if ($badge === null || ($badge === VerifiedBadge::PeerFacilitator && ! $user->hasRole('pair'))) {
                continue;
            }

            $out[] = ['key' => $badge->value, 'label' => $badge->label(), 'icon' => $badge->icon()];
        }

        return $out;
    }
}
