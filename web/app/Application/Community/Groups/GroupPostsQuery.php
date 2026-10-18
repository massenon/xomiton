<?php

declare(strict_types=1);

namespace App\Application\Community\Groups;

use App\Application\Feed\Queries\FeedQuery;
use App\Models\Group;
use App\Models\Post;
use App\Models\PostHide;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Onglet « Publications » d'un groupe (P8-02, group-details.php l.70-80 ; `GET /api/v1/groups/{ulid}/posts`) : les
 * règles du fil (App\Application\Feed\Queries\FeedQuery, ADR-29) restreintes au groupe — publiées, visibilité
 * (`Post::scopeVisibleTo` : le groupe est l'audience, les publications de groupe sont `public`), hors publications
 * masquées par la personne et comptes bloqués (deux sens ; contenu anonyme : seul le blocage de l'auteur·rice, Q72),
 * plus récentes d'abord, état propre à la personne (j'aime, enregistrée, ses votes). L'appelant vérifie d'abord que le
 * groupe est lisible (GroupPolicy::view : public et actif, ou membre active).
 */
final class GroupPostsQuery
{
    public function __construct(private readonly FeedQuery $feed) {}

    /**
     * @return Builder<Post>
     */
    public function for(Group $group, User $viewer): Builder
    {
        $query = Post::query()
            ->visibleTo($viewer)
            ->where('posts.group_id', $group->getKey())
            ->whereNotIn('posts.id', PostHide::query()->select('post_id')->where('user_id', $viewer->getKey()));

        return $this->feed->withViewerState($this->feed->withoutBlocked($query, $viewer)->latestFirst(), $viewer);
    }
}
