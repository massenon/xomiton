<?php

declare(strict_types=1);

namespace App\Application\Account\Profile;

use App\Application\Community\Events\EventAccess;
use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\User;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;

/**
 * Onglet « Connexions » du profil (P8-07, prototype `my-profile-connections.php`, `x_connections()`) : comptes reliés au
 * profil par une invitation **acceptée**, dans un sens ou dans l'autre. Jamais : un compte suspendu, en attente,
 * anonymisé ou supprimé, un blocage avec la personne du profil ou avec celle qui lit (FR-SOC-04). Sur le profil d'une
 * autre personne, seules les connexions dont le profil est ouvert à la communauté (`public`, `community`) sont
 * listées : une connexion au profil réservé ou privé ne se révèle pas par le profil d'une autre. Recherche `q` sur le
 * pseudo (jamais le nom réel, RG-02).
 */
final class ProfileConnections
{
    /**
     * @return Builder<User>
     */
    public function query(User $viewer, User $owner, ?string $search = null): Builder
    {
        $ownerId = (int) $owner->getKey();
        $viewerId = (int) $viewer->getKey();

        $sent = Invitation::query()->select('to_user_id')->where('from_user_id', $ownerId)->where('status', InvitationStatus::Accepted);
        $received = Invitation::query()->select('from_user_id')->where('to_user_id', $ownerId)->where('status', InvitationStatus::Accepted);

        $query = User::query()
            ->where(static fn (Builder $q) => $q->whereIn('users.id', $sent)->orWhereIn('users.id', $received))
            ->whereKeyNot($ownerId)
            ->active()
            ->notAnonymized();

        EventAccess::withoutBlocksWith($query, 'users.id', $ownerId);

        if ($viewerId !== $ownerId) {
            EventAccess::withoutBlocksWith($query, 'users.id', $viewerId);
            $query->where(static fn (Builder $q) => $q->whereKey($viewerId)->orWhereHas('profile', static fn (Builder $p) => $p->visibleToCommunity()));
        }

        if ($search !== null && $search !== '') {
            $query->whereRaw(LikePattern::clause('users.pseudo'), [LikePattern::contains($search)]);
        }

        return $query->with(['profile', 'zone', 'roles'])->orderBy('users.pseudo')->orderBy('users.id');
    }
}
