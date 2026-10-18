<?php

declare(strict_types=1);

namespace App\Application\Feed\Queries;

use App\Domain\Account\ProfileAudience;
use App\Enums\PostTag;
use App\Models\Follow;
use App\Models\Post;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Database\Eloquent\Builder;

/**
 * Publications d'un compte, onglet « Publications » du profil (P6-08, `GET /api/v1/users/{id}/posts`, simulateur
 * `profil`). Lecture seule (ADR-29) qui réutilise les règles du fil (FeedQuery) :
 *
 *  - profil : visible selon App\Domain\Account\ProfileAudience (soi-même toujours ; compte suspendu, anonymisé ou
 *    supprimé, blocage dans un sens ou dans l'autre, profil privé ou réservé aux abonné·es non suivi·e → null, 404 au
 *    contrôleur : on ne révèle pas qu'un compte existe) ;
 *  - publications : celles que la personne qui lit verrait dans le fil (FeedQuery::feed : visibilité, hors groupes,
 *    hors publications qu'elle a masquées, étiquette), restreintes au compte, plus récentes d'abord ;
 *  - jamais une publication anonyme, même pour son autrice ou son auteur (RG-02 : une publication anonyme n'est
 *    rattachée à aucun profil ; « Membre anonyme » dans le fil seulement).
 */
final class UserPostsQuery
{
    public function __construct(private readonly FeedQuery $feed) {}

    /** Compte dont le profil est visible par `$viewer`, sinon null. */
    public function owner(User $viewer, int $userId): ?User
    {
        $owner = User::query()->with('profile')->find($userId);

        if ($owner === null) {
            return null;
        }

        $isSelf = $owner->is($viewer);
        $allowed = ProfileAudience::allows(
            $owner->profile?->visibility,
            $isSelf,
            isReachable: ! $owner->isSuspended() && ! $owner->isAnonymized(),
            followsOwner: ! $isSelf && Follow::query()->where('follower_id', $viewer->getKey())->where('followed_id', $owner->getKey())->exists(),
            blocked: ! $isSelf && $this->blocked($viewer, $owner),
        );

        return $allowed ? $owner : null;
    }

    /**
     * Publications de `$owner` visibles par `$viewer` (étiquette facultative), avec l'état propre à la personne qui lit
     * (FeedQuery::withViewerState).
     *
     * @return Builder<Post>
     */
    public function posts(User $viewer, User $owner, ?PostTag $tag = null): Builder
    {
        $query = $this->feed->feed($viewer, $tag)
            ->where('posts.user_id', $owner->getKey())
            ->where('posts.is_anonymous', false);

        return $this->feed->withViewerState($query, $viewer);
    }

    private function blocked(User $viewer, User $owner): bool
    {
        return UserBlock::query()->between((int) $viewer->getKey(), (int) $owner->getKey())->exists();
    }
}
