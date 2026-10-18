<?php

declare(strict_types=1);

namespace App\Application\Feed\Queries;

use App\Enums\GroupPrivacy;
use App\Enums\MembershipStatus;
use App\Enums\PostStatus;
use App\Enums\PostTag;
use App\Models\Comment;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Post;
use App\Models\PostHide;
use App\Models\PostSave;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Lectures du fil (P7-01, P7-02 ; ADR-29 : lectures Eloquent directes) : la règle App\Domain\Feed\PostAudience
 * traduite en requête, plus ce qui ne regarde que la personne qui lit :
 *  - publiées, hors groupes (les publications d'un groupe vivent dans le groupe, P8) ;
 *  - `public`, les siennes, ou `followers` d'un·e auteur·rice suivi·e (Post::scopeVisibleTo) ;
 *  - ni celles qu'elle a masquées (`post_hides`), ni celles des comptes qu'elle bloque ou qui la bloquent — pour un
 *    contenu anonyme, seul le blocage posé par l'auteur·rice compte (Q72, RG-02 : bloquer n'identifie personne) ;
 *  - « Enregistrées » : même règle de groupe que PostPolicy (FR-SOC-05) ;
 *  - plus récentes d'abord (`published_at`, `id`), pagination par curseur.
 * `withViewerState()` charge en une requête par relation l'auteur·rice, les médias, le sondage et, pour la seule
 * personne qui lit, « j'aime », « enregistrée » et ses propres votes (jamais ceux des autres : RG-09).
 */
final class FeedQuery
{
    /**
     * @return Builder<Post>
     */
    public function feed(User $viewer, ?PostTag $tag = null): Builder
    {
        $query = Post::query()
            ->visibleTo($viewer)
            ->whereNull('group_id')
            ->whereNotIn('posts.id', PostHide::query()->select('post_id')->where('user_id', $viewer->getKey()))
            ->when($tag !== null, static fn (Builder $q) => $q->where('tag', $tag));

        return $this->withoutBlocked($query, $viewer)->latestFirst();
    }

    /**
     * Publications enregistrées encore visibles (« Enregistrées », P7-06) : une publication de groupe n'y reste que si
     * le groupe est lisible (public et actif, ou dont la personne est propriétaire ou membre active), comme
     * PostPolicy::view → GroupPolicy::view.
     *
     * @return Builder<Post>
     */
    public function saved(User $viewer): Builder
    {
        $query = Post::query()
            ->visibleTo($viewer)
            ->whereIn('posts.id', PostSave::query()->select('post_id')->where('user_id', $viewer->getKey()))
            ->where(static function (Builder $q) use ($viewer): void {
                $q->whereNull('posts.group_id')->orWhereIn('posts.group_id', self::readableGroups($viewer));
            });

        return $this->withoutBlocked($query, $viewer)->latestFirst();
    }

    /**
     * Commentaires publiés d'une publication, du plus ancien au plus récent (fil de discussion), réponses comprises
     * (`parent_id`), hors comptes bloqués. Le parent n'est chargé que s'il est encore publié : la réponse à un
     * commentaire supprimé ou masqué remonte au premier niveau (CommentResource : `parent_id` nul).
     *
     * @return Builder<Comment>
     */
    public function comments(Post $post, User $viewer): Builder
    {
        $query = Comment::query()
            ->where('post_id', $post->getKey())
            ->where('status', PostStatus::Published)
            ->with([
                'user.profile',
                'user.roles',
                'parent' => static fn (BelongsTo $parent) => $parent->select(['id', 'status'])->where('status', PostStatus::Published),
            ]);

        return $this->withoutBlocked($query, $viewer)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @template TModel of Post|Comment
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function withoutBlocked(Builder $query, User $viewer): Builder
    {
        $table = $query->getModel()->getTable();
        $id = $viewer->getKey();

        return $query->where(static function (Builder $q) use ($table, $id): void {
            $q->whereNull($table.'.user_id')
                ->orWhere(static function (Builder $r) use ($table, $id): void {
                    $r->whereNotIn($table.'.user_id', UserBlock::query()->select('user_id')->where('blocked_user_id', $id))
                        ->where(static function (Builder $s) use ($table, $id): void {
                            $s->where($table.'.is_anonymous', true)
                                ->orWhereNotIn($table.'.user_id', UserBlock::query()->select('blocked_user_id')->where('user_id', $id));
                        });
                });
        });
    }

    /**
     * Groupes dont les publications sont lisibles par la personne (GroupPolicy::view, hors droit de modération).
     */
    private static function readableGroups(User $viewer): QueryBuilder
    {
        $id = $viewer->getKey();

        return Group::query()->toBase()->select('id')
            ->where(static fn (QueryBuilder $q) => $q->where('privacy', GroupPrivacy::Public->value)->where('is_active', true))
            ->orWhere('owner_id', $id)
            ->orWhereIn('id', GroupMember::query()->toBase()->select('group_id')->where('user_id', $id)->where('status', MembershipStatus::Active->value));
    }

    /**
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function withViewerState(Builder $query, User $viewer): Builder
    {
        $id = $viewer->getKey();

        return $query
            ->with([
                'user.profile',
                'user.roles',
                'media',
                'poll.options',
                'poll.votes' => static fn (HasMany $votes) => $votes->where('user_id', $id),
            ])
            ->withExists([
                'likes as liked_by_viewer' => static fn (Builder $likes) => $likes->where('user_id', $id),
                'saves as saved_by_viewer' => static fn (Builder $saves) => $saves->where('user_id', $id),
            ]);
    }

    /**
     * Même état pour une publication déjà chargée (détail, réponse d'une écriture).
     */
    public function loadViewerState(Post $post, User $viewer): Post
    {
        /** @var Post $fresh */
        $fresh = $this->withViewerState(Post::query()->withTrashed()->whereKey($post->getKey()), $viewer)->firstOrFail();

        return $fresh;
    }
}
