<?php

declare(strict_types=1);

namespace App\Application\Community\Forums;

use App\Application\Feed\Queries\FeedQuery;
use App\Enums\PostStatus;
use App\Models\Forum;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Lectures des forums (P8-05, prototype forums.php et forum-topic.php ; ADR-29 : lectures Eloquent directes) :
 *
 *  - les 5 forums fixes actifs, dans l'ordre du prototype, avec leurs compteurs ;
 *  - « Sujets récents » : sujets **publiés** des forums actifs, filtrés par forum (`?forum=f1`) et par recherche
 *    « contient » sur le titre et le message (jamais sur l'auteur·rice : RG-02), épinglés d'abord puis dernière activité
 *    (ForumTopic::scopeByActivity), pagination par page (6, « Charger plus ») ;
 *  - réponses publiées d'un sujet, de la plus ancienne à la plus récente ;
 *  - blocages comme le fil (FeedQuery::withoutBlocked, App\Domain\Community\ForumAudience, Q72) : le blocage posé par
 *    l'auteur·rice retire son contenu, celui posé par la personne qui lit ne retire qu'un contenu signé.
 */
final class ForumDirectory
{
    public function __construct(private readonly FeedQuery $feed) {}

    /**
     * @return Collection<int, Forum>
     */
    public function forums(): Collection
    {
        return Forum::query()->active()->ordered()->get();
    }

    public function forum(?string $key): ?Forum
    {
        if ($key === null || $key === '') {
            return null;
        }

        return Forum::query()->active()->forKey($key)->first();
    }

    /**
     * @return LengthAwarePaginator<int, ForumTopic>
     */
    public function paginate(User $viewer, ?Forum $forum, ?string $search, int $perPage): LengthAwarePaginator
    {
        return $this->topics($viewer, $forum, $search)->paginate($perPage);
    }

    /**
     * @return Builder<ForumTopic>
     */
    public function topics(User $viewer, ?Forum $forum = null, ?string $search = null): Builder
    {
        $search = trim((string) $search);

        $query = ForumTopic::query()
            ->published()
            ->whereIn('forum_topics.forum_id', Forum::query()->active()->select('id'))
            ->when($forum !== null, static fn (Builder $q) => $q->where('forum_topics.forum_id', $forum?->getKey()))
            ->with(['forum', 'user.profile', 'user.roles']);

        if ($search !== '') {
            $pattern = LikePattern::contains($search);
            $query->where(static fn (Builder $q) => $q->whereRaw(LikePattern::clause('forum_topics.title'), [$pattern])
                ->orWhereRaw(LikePattern::clause('forum_topics.body'), [$pattern]));
        }

        return $this->feed->withoutBlocked($query, $viewer)->byActivity();
    }

    /**
     * @return LengthAwarePaginator<int, ForumReply>
     */
    public function replies(ForumTopic $topic, User $viewer, int $perPage): LengthAwarePaginator
    {
        $query = ForumReply::query()
            ->where('topic_id', $topic->getKey())
            ->where('status', PostStatus::Published)
            ->with(['user.profile', 'user.roles']);

        return $this->feed->withoutBlocked($query, $viewer)->orderBy('created_at')->orderBy('id')->paginate($perPage);
    }
}
