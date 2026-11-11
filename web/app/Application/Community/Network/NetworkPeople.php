<?php

declare(strict_types=1);

namespace App\Application\Community\Network;

use App\Application\Community\Events\EventAccess;
use App\Domain\Account\ProfileAudience;
use App\Domain\Community\NetworkRules;
use App\Enums\ProfileVisibility;
use App\Models\Follow;
use App\Models\Invitation;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Database\Eloquent\Builder;

/**
 * Qui la personne peut trouver, suivre et inviter (P8-06, who-to-follow.php ; règles App\Domain\Community\NetworkRules) :
 *
 *  - un compte du réseau est **actif** (le prototype ne propose que `actif`), non anonymisé, avec `social.access`, autre
 *    que soi, et **sans blocage dans un sens ou dans l'autre** (FR-SOC-04, comme le fil, les événements et les forums :
 *    EventAccess::withoutBlocksWith) ;
 *  - suggestions : profil ouvert au réseau (`public`, `community`) et `searchable` ; jamais un rôle caché
 *    (NetworkRules::HIDDEN_ROLES) ;
 *  - suivre : profil ouvert au réseau ; inviter : profil visible selon App\Domain\Account\ProfileAudience (un profil
 *    « connexions » seulement pour qui le suit déjà, un profil privé pour personne), et compte découvrable ou déjà lié
 *    (`linked` : abonnement ou invitation en attente, dans un sens ou dans l'autre ; ManageInvitations). Un profil
 *    « connexions » ne se suit pas d'un geste et n'est jamais proposé : le lien naît de son initiative (il suit ou
 *    invite), ou d'une invitation de quelqu'un qui le suivait déjà (Q79).
 * Un compte sans ligne `profiles` suit les valeurs par défaut de la table (communauté, trouvable, zone affichée).
 */
final class NetworkPeople
{
    /**
     * Comptes du réseau visibles par la personne, profils joints (`profiles.*` lisibles dans la requête).
     *
     * @return Builder<User>
     */
    public function visibleTo(User $viewer): Builder
    {
        $id = (int) $viewer->getKey();

        $query = User::query()
            ->select('users.*')
            ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
            ->whereKeyNot($id)
            ->active()
            ->notAnonymized()
            ->permission('social.access');

        return EventAccess::withoutBlocksWith($query, 'users.id', $id);
    }

    /**
     * Comptes proposables en suggestion : profil ouvert au réseau et trouvable, rôle non caché.
     *
     * @return Builder<User>
     */
    public function discoverableBy(User $viewer): Builder
    {
        return $this->visibleTo($viewer)
            ->withoutRole(NetworkRules::HIDDEN_ROLES)
            ->where(static fn (Builder $q) => $q->whereNull('profiles.id')->orWhereNull('profiles.visibility')
                ->orWhereIn('profiles.visibility', array_map(static fn (ProfileVisibility $v): string => $v->value, NetworkRules::OPEN_VISIBILITIES)))
            ->where(static fn (Builder $q) => $q->whereNull('profiles.id')->orWhere('profiles.searchable', true));
    }

    /** La personne figurerait-elle dans les suggestions de `$viewer` (hors filtres et liens déjà faits) ? */
    public function discoverable(User $viewer, User $target): bool
    {
        return $this->discoverableBy($viewer)->whereKey($target->getKey())->exists();
    }

    /** Un lien existe déjà : abonnement ou invitation en attente, dans un sens ou dans l'autre. */
    public function linked(User $a, User $b): bool
    {
        $x = (int) $a->getKey();
        $y = (int) $b->getKey();

        return $this->follows($a, $b) || $this->follows($b, $a) || Invitation::query()->pending()
            ->where(static fn (Builder $q) => $q->where(static fn (Builder $ab) => $ab->where('from_user_id', $x)->where('to_user_id', $y))
                ->orWhere(static fn (Builder $ba) => $ba->where('from_user_id', $y)->where('to_user_id', $x)))
            ->exists();
    }

    public function reachable(User $user): bool
    {
        return $user->isActive() && ! $user->isAnonymized() && $user->can('social.access');
    }

    public function blockedBetween(User $a, User $b): bool
    {
        return UserBlock::query()->between((int) $a->getKey(), (int) $b->getKey())->exists();
    }

    public function follows(User $follower, User $followed): bool
    {
        return Follow::query()->between((int) $follower->getKey(), (int) $followed->getKey())->exists();
    }

    /** Connecté·es = abonnement dans les deux sens (invitation acceptée, who-to-follow.php l.42). */
    public function connected(User $a, User $b): bool
    {
        return $this->follows($a, $b) && $this->follows($b, $a);
    }

    public function canFollow(User $viewer, User $target): bool
    {
        if ($target->is($viewer) || ! $this->reachable($target) || $this->blockedBetween($viewer, $target)) {
            return false;
        }

        $target->loadMissing('profile');

        return NetworkRules::followable($target->profile?->visibility);
    }

    public function canInvite(User $viewer, User $target): bool
    {
        if ($target->is($viewer) || ! $this->reachable($target)) {
            return false;
        }

        $target->loadMissing('profile');

        return ProfileAudience::allows(
            $target->profile?->visibility,
            isSelf: false,
            isReachable: true,
            followsOwner: $this->follows($viewer, $target),
            blocked: $this->blockedBetween($viewer, $target),
        );
    }
}
