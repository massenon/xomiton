<?php

declare(strict_types=1);

namespace App\Application\Community\Events;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\User;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Listes d'événements (P8-04, prototype events.php ; ADR-29 : lectures Eloquent directes), toujours restreintes par
 * EventAccess :
 *
 *  - `upcoming` (défaut, `?from=today` du mobile) : à venir ou en cours, du plus proche au plus lointain ;
 *  - `going` : celles et ceux auxquels la personne a répondu « Je participe », à venir ;
 *  - `past` : terminés, du plus récent au plus ancien ;
 *  - filtre par catégorie (`categories.kind = event`, par slug) et recherche « contient » sur le titre, la ville et la
 *    description (prototype : titre, lieu, description) — jamais sur l'adresse, chiffrée et réservée.
 * `rsvps()` charge en une requête la réponse de la personne pour une page d'événements (bouton « Je participe »).
 */
final class EventDirectory
{
    public const FILTER_UPCOMING = 'upcoming';

    public const FILTER_GOING = 'going';

    public const FILTER_PAST = 'past';

    /** @var list<string> */
    public const FILTERS = [self::FILTER_UPCOMING, self::FILTER_GOING, self::FILTER_PAST];

    public function __construct(private readonly EventAccess $access) {}

    /**
     * @return LengthAwarePaginator<int, Event>
     */
    public function paginate(User $viewer, string $filter, ?string $category, ?string $search, int $perPage): LengthAwarePaginator
    {
        return $this->query($viewer, $filter, $category, $search)->paginate($perPage);
    }

    /**
     * @return Builder<Event>
     */
    public function query(User $viewer, string $filter = self::FILTER_UPCOMING, ?string $category = null, ?string $search = null): Builder
    {
        // Relations lues par EventResource (catégorie, groupe, organisateur·rice) : chargées d'avance, pas de N+1.
        $query = $this->access->forMember(Event::query(), $viewer)->with(['category', 'group', 'organizer:id,pseudo']);

        return $this->filtered($query, $filter, $category, $search, $viewer);
    }

    /**
     * Événements ouverts aux visiteur·ses, à venir (page publique `/evenements`, Q35 a) : les `$limit` premiers et le
     * total (« Charger plus » par `?n=`, comme `/actualites`).
     *
     * @return array{events: Collection<int, Event>, total: int}
     */
    public function publicListing(?string $category, int $limit): array
    {
        $query = $this->filtered($this->access->forPublic(Event::query())->with('category'), self::FILTER_UPCOMING, $category, null, null);

        return [
            'total' => (clone $query)->reorder()->count(),
            'events' => $query->limit(max(1, $limit))->get(),
        ];
    }

    /**
     * « Autres événements » de la fiche (event-details.php l.12) : prochains événements visibles, sauf celui-ci.
     *
     * @return Collection<int, Event>
     */
    public function others(Event $event, ?User $viewer, int $limit): Collection
    {
        $query = $viewer !== null ? $this->access->forMember(Event::query(), $viewer) : $this->access->forPublic(Event::query());

        return $query->upcoming()->whereKeyNot($event->getKey())->limit(max(0, $limit))->get();
    }

    /**
     * Catégories d'événement actives qui ont au moins un événement listé (puces « Tous · Atelier · … » du prototype).
     *
     * @return list<array{slug: string, label: string}>
     */
    public function categories(?User $viewer): array
    {
        $listed = $viewer !== null ? $this->access->forMember(Event::query(), $viewer) : $this->access->forPublic(Event::query());
        $ids = (clone $listed)->upcoming()->reorder()->whereNotNull('category_id')->distinct()->pluck('category_id');

        return Category::query()->ofKind(Category::KIND_EVENT)->active()->whereIn('id', $ids)->ordered()->get(['slug', 'label'])
            ->map(static fn (Category $category): array => ['slug' => (string) $category->slug, 'label' => (string) $category->label])
            ->values()->all();
    }

    public function rsvp(User $viewer, Event $event): ?EventRsvp
    {
        return EventRsvp::query()->where('event_id', $event->getKey())->where('user_id', $viewer->getKey())->first();
    }

    /**
     * Réponses de la personne pour ces événements, par identifiant d'événement.
     *
     * @param  iterable<Event>  $events
     * @return array<int, EventRsvp>
     */
    public function rsvps(User $viewer, iterable $events): array
    {
        $ids = [];

        foreach ($events as $event) {
            $ids[] = (int) $event->getKey();
        }

        if ($ids === []) {
            return [];
        }

        return EventRsvp::query()->where('user_id', $viewer->getKey())->whereIn('event_id', $ids)->get()
            ->keyBy(static fn (EventRsvp $rsvp): int => (int) $rsvp->event_id)
            ->all();
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    private function filtered(Builder $query, string $filter, ?string $category, ?string $search, ?User $viewer): Builder
    {
        if ($category !== null && $category !== '') {
            $query->whereHas('category', static fn (Builder $c) => $c->where('kind', Category::KIND_EVENT)->where('slug', $category));
        }

        $search = trim((string) $search);

        if ($search !== '') {
            $pattern = LikePattern::contains($search);
            $query->where(static fn (Builder $q) => $q->whereRaw(LikePattern::clause('events.title'), [$pattern])
                ->orWhereRaw(LikePattern::clause('events.place'), [$pattern])
                ->orWhereRaw(LikePattern::clause('events.description'), [$pattern]));
        }

        if ($filter === self::FILTER_PAST) {
            return $query->past()->orderByDesc('events.id');
        }

        if ($filter === self::FILTER_GOING && $viewer !== null) {
            $query->whereHas('rsvps', static fn (Builder $r) => $r->where('user_id', $viewer->getKey())->going());
        }

        return $query->upcoming();
    }
}
