<?php

declare(strict_types=1);

namespace App\Application\Account\Profile;

use App\Application\Community\Events\EventAccess;
use App\Application\Feed\Queries\UserPostsQuery;
use App\Enums\InvitationStatus;
use App\Models\Follow;
use App\Models\Invitation;
use App\Models\User;
use App\Models\UserBlock;

/**
 * Profils des membres (P8-07, `/profil`, `/membres/{pseudo}`, `GET members/{pseudo}`) : qui voit quel profil et ce que
 * l'en-tête affiche. Visibilité : App\Domain\Account\ProfileAudience par UserPostsQuery::owner (mêmes règles que
 * l'onglet Publications de l'API, P6-08) — soi-même toujours ; `public` et `community` : tout compte du réseau
 * (`social.access`) ; `followers` : ses abonné·es ; `private` : personne d'autre ; compte suspendu, anonymisé ou
 * supprimé, blocage dans un sens ou dans l'autre → null (404 : on ne révèle pas qu'un compte existe).
 * Connexion = invitation acceptée, dans un sens ou dans l'autre (prototype `x_connections()`).
 */
final class MemberProfiles
{
    public function __construct(private readonly UserPostsQuery $posts) {}

    /** Compte de pseudo `$pseudo` dont le profil est visible par `$viewer`, sinon null. */
    public function byPseudo(User $viewer, string $pseudo): ?User
    {
        $id = User::query()->where('pseudo', $pseudo)->value('id');

        return $id === null ? null : $this->visible($viewer, (int) $id);
    }

    /** Compte `$userId` dont le profil est visible par `$viewer`, sinon null. */
    public function visible(User $viewer, int $userId): ?User
    {
        $isSelf = (int) $viewer->getKey() === $userId;

        if (! $isSelf && ! $viewer->can('social.access')) {
            return null;
        }

        $owner = $this->posts->owner($viewer, $userId);
        $owner?->loadMissing(['profile', 'zone', 'commune', 'roles']);

        return $owner;
    }

    public function canView(User $viewer, User $owner): bool
    {
        return $this->visible($viewer, (int) $owner->getKey()) !== null;
    }

    /**
     * Compteurs de l'en-tête : publications que `$viewer` peut lire (onglet Publications), connexions qu'il ou elle peut
     * voir (ProfileConnections), abonné·es et abonnements — comme les connexions, sans compte suspendu, en attente,
     * anonymisé ou supprimé, ni compte bloqué avec la personne du profil ou avec celle qui lit.
     *
     * @return array{posts: int, connections: int, followers: int, following: int}
     */
    public function stats(User $viewer, User $owner, ProfileConnections $connections): array
    {
        return [
            'posts' => $this->posts->posts($viewer, $owner)->toBase()->getCountForPagination(),
            'connections' => $connections->query($viewer, $owner)->count(),
            'followers' => $this->countFollows($viewer, $owner, 'followed_id', 'follower_id'),
            'following' => $this->countFollows($viewer, $owner, 'follower_id', 'followed_id'),
        ];
    }

    private function countFollows(User $viewer, User $owner, string $ownerColumn, string $otherColumn): int
    {
        $others = User::query()->select('users.id')->active()->notAnonymized();
        EventAccess::withoutBlocksWith($others, 'users.id', (int) $owner->getKey());

        if (! $viewer->is($owner)) {
            EventAccess::withoutBlocksWith($others, 'users.id', (int) $viewer->getKey());
        }

        return Follow::query()->where($ownerColumn, $owner->getKey())->whereIn($otherColumn, $others)->count();
    }

    /**
     * Lien entre la personne qui lit et le profil (null pour son propre profil).
     *
     * @return array{following: bool, follows_you: bool, connected: bool}|null
     */
    public function relationship(User $viewer, User $owner): ?array
    {
        if ($viewer->is($owner)) {
            return null;
        }

        $me = (int) $viewer->getKey();
        $other = (int) $owner->getKey();

        return [
            'following' => Follow::query()->where(['follower_id' => $me, 'followed_id' => $other])->exists(),
            'follows_you' => Follow::query()->where(['follower_id' => $other, 'followed_id' => $me])->exists(),
            'connected' => Invitation::query()->where('status', InvitationStatus::Accepted)
                ->where(static fn ($q) => $q->where(['from_user_id' => $me, 'to_user_id' => $other])
                    ->orWhere(static fn ($r) => $r->where(['from_user_id' => $other, 'to_user_id' => $me])))
                ->exists(),
        ];
    }

    public static function blocked(User $viewer, User $owner): bool
    {
        return UserBlock::query()->between((int) $viewer->getKey(), (int) $owner->getKey())->exists();
    }
}
