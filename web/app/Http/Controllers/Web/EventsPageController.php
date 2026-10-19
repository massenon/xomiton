<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Community\Events\EventAccess;
use App\Application\Community\Events\EventCreateOptions;
use App\Application\Community\Events\EventDirectory;
use App\Application\Community\Events\EventLinks;
use App\Application\Community\Events\EventStructuredData;
use App\Application\Seo\MetaText;
use App\Application\Seo\StructuredData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\EventsPageRequest;
use App\Http\Resources\Events\EventResource;
use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use App\Support\ListingQuery;
use App\Support\LocaleFormatter;
use App\Support\MediaUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `/evenements` et `/evenements/{ulid}` (P8-04 ; prototype events.php, event-details.php) : **une adresse, deux publics**
 * (middleware InertiaForMembers, Q35 a).
 *
 *  - Membre connecté·e : pages Inertia `Events/Index` (en-tête + « Créer un événement » (modale chargée à la demande),
 *    onglets À venir · Mes participations · Passés, puces de catégorie, recherche, cartes, « Charger plus » par l'API) et
 *    `Events/Show` (visuel, catégorie, date, heure, ville, adresse exacte et lien **seulement après « Je participe »**,
 *    description, organisation, charte, Je participe / annuler, rappel de la veille, Partager ; colonne : nombre de
 *    participant·es — jamais leur liste —, autres événements). Premières pages sous la forme de l'API (docs/API.md §15).
 *    Événement invisible (groupe privé, brouillon, masqué) : 404.
 *  - Visiteur·se — et compte connecté sans accès au réseau (`social.access`) : page Blade publique indexable des seuls
 *    événements **publics** (ville, jamais l'adresse ni le lien ; organisation : page, groupe ou « Équipe Xomiton »,
 *    jamais un pseudo ; JSON-LD CollectionPage et Event, plan du site) ; un événement réservé aux membres, ou inconnu,
 *    renvoie vers la connexion avec retour sur la fiche (`next`, même réponse dans les deux cas : on ne révèle pas
 *    qu'il existe).
 */
final class EventsPageController extends Controller
{
    public function __construct(
        private readonly EventDirectory $events,
        private readonly EventAccess $access,
    ) {}

    public function index(EventsPageRequest $request, EventCreateOptions $options): Response|View|SymfonyResponse
    {
        $viewer = $request->viewer();

        if ($viewer === null || ! $viewer->can('viewAny', Event::class)) {
            return $this->publicFallback($request) ?? $this->publicIndex($request);
        }

        $categories = $this->events->categories($viewer);
        $filter = $request->choice('filter', EventDirectory::FILTERS, EventDirectory::FILTER_UPCOMING);
        $category = $request->category(array_column($categories, 'slug'));
        $search = $request->term();
        $title = (string) __('community.events.title');

        return Inertia::render('Events/Index', [
            'title' => $title,
            'filter' => $filter,
            'category' => $category,
            'q' => $search,
            'categories' => $categories,
            'events' => fn (): array => $this->list($request, $viewer, $filter, $category, $search),
            'create' => fn (): array => $options->for($viewer),
        ])->withViewData(['title' => $title]);
    }

    public function show(EventsPageRequest $request, string $event): Response|View|RedirectResponse|SymfonyResponse
    {
        $viewer = $request->viewer();
        $model = Event::query()->whereUlid($event)->with(['category', 'group', 'page'])->first();

        if ($viewer === null || ! $viewer->can('viewAny', Event::class)) {
            $fallback = $this->publicFallback($request);

            if ($fallback !== null) {
                return $fallback;
            }

            if ($model === null || ! $this->access->isOpenToPublic($model)) {
                if ($viewer !== null) {
                    throw new NotFoundHttpException;
                }

                return redirect()->guest(self::loginUrl(route('evenements.show', $event, false)));
            }

            return $this->publicShow($model);
        }

        if ($model === null || ! $viewer->can('view', $model)) {
            throw new NotFoundHttpException;
        }

        $fmt = LocaleFormatter::make();
        $separator = ' '.__('common.separator').' ';

        return Inertia::render('Events/Show', [
            'title' => (string) $model->title,
            'event' => fn (): array => EventResource::make($model)->withRsvp($this->events->rsvp($viewer, $model))->resolve($request),
            'others' => fn (): array => $this->events->others($model, $viewer, (int) config('xomiton.events.sidebar_others', 3))
                ->map(static fn (Event $other): array => [
                    'id' => (string) $other->ulid,
                    'title' => (string) $other->title,
                    'href' => EventLinks::show($other),
                    'image_url' => MediaUrl::resolve($other->image_path, Event::DEFAULT_COVER),
                    'meta' => $fmt->dayMonth($other->starts_at).$separator.EventResource::locationLabel($other),
                ])->values()->all(),
        ])->withViewData(['title' => (string) $model->title]);
    }

    /**
     * Connexion avec retour sur `$path` après coup (LoginRequest::safeNext : la connexion lit `next`, pas
     * `url.intended`).
     */
    public static function loginUrl(string $path): string
    {
        return route('connexion', ['next' => $path]);
    }

    /**
     * Compte connecté sans accès au réseau arrivé par une visite Inertia (lien du gabarit membre) : rechargement complet
     * (409 + `X-Inertia-Location`), pour que la page Blade publique ne s'affiche pas dans la fenêtre Inertia.
     */
    private function publicFallback(EventsPageRequest $request): ?SymfonyResponse
    {
        return $request->viewer() !== null && $request->header('X-Inertia') !== null ? Inertia::location($request->fullUrl()) : null;
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function list(EventsPageRequest $request, User $viewer, string $filter, ?string $category, ?string $search): array
    {
        $paginator = $this->events->paginate($viewer, $filter, $category, $search, (int) config('xomiton.events.per_page', 6));

        return [
            'data' => EventResource::list($paginator->items(), $this->events->rsvps($viewer, $paginator->items()), $request),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ];
    }

    private function publicIndex(EventsPageRequest $request): View
    {
        $slug = $request->publicCategory();
        abort_if($slug === false, 404);

        $category = $slug !== null
            ? Category::query()->ofKind(Category::KIND_EVENT)->active()->where('slug', $slug)->first(['id', 'slug', 'label'])
            : null;
        abort_if($slug !== null && $category === null, 404);

        $step = max(1, (int) config('xomiton.events.per_page', 6));
        $max = max($step, (int) config('xomiton.content.max_per_load', 300));
        $limit = ListingQuery::limit($request->query('n'), $step, $max);
        ['events' => $events, 'total' => $total] = $this->events->publicListing($slug, $limit);
        $nextUrl = $total > $events->count() && $limit < $max
            ? route('evenements.index', array_filter(['cat' => $slug, 'n' => $limit + $step])).'#liste'
            : null;

        return view('public.evenements.index', [
            'categories' => $this->events->categories(null),
            'category' => $category !== null ? ['slug' => (string) $category->slug, 'label' => (string) $category->label] : null,
            'events' => $events,
            'total' => $total,
            'nextUrl' => $nextUrl,
            'loginUrl' => self::loginUrl(route('evenements.index', [], false)),
            'fmt' => LocaleFormatter::make(),
            'seo' => self::indexSeo($events, $total),
        ]);
    }

    private function publicShow(Event $event): View
    {
        $fmt = LocaleFormatter::make();
        $canonical = EventLinks::show($event);
        $description = MetaText::description((string) ($event->description ?? ''), null, (string) __('seo.pages.evenements.description'));
        $crumbs = [
            [(string) __('pages/evenements.title'), route('evenements.index')],
            [(string) $event->title, $canonical],
        ];

        return view('public.evenements.show', [
            'event' => $event,
            'others' => $this->events->others($event, null, (int) config('xomiton.events.sidebar_others', 3)),
            'organizer' => $event->publicOrganizerLabel(),
            'loginUrl' => self::loginUrl(route('evenements.show', $event->ulid, false)),
            'breadcrumb' => [
                ['label' => __('seo.breadcrumb_home'), 'url' => route('home')],
                ...array_map(static fn (array $crumb): array => ['label' => $crumb[0], 'url' => $crumb[1]], $crumbs),
            ],
            'fmt' => $fmt,
            'seo' => [
                'title' => MetaText::title((string) $event->title),
                'description' => $description,
                'image' => MediaUrl::resolve($event->image_path, Event::DEFAULT_COVER),
                'imageAlt' => __('pages/evenements.image_alt', ['title' => $event->title]),
                'breadcrumb' => $crumbs,
                'modified' => $event->updated_at !== null ? $fmt->isoDate($event->updated_at) : null,
                'schema' => [EventStructuredData::for($event, $canonical, $description)],
            ],
        ]);
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return array<string, mixed> props de `<x-seo>`
     */
    private static function indexSeo(Collection $events, int $total): array
    {
        $canonical = route('evenements.index');

        return [
            'title' => __('seo.pages.evenements.title'),
            'description' => __('seo.pages.evenements.description'),
            'image' => 'covers/og-default.jpg',
            'imageAlt' => __('pages/evenements.og_alt'),
            'breadcrumb' => [[__('pages/evenements.title'), $canonical]],
            'schema' => [StructuredData::collectionPage(
                $canonical,
                (string) __('pages/evenements.title'),
                $total,
                $events->map(static fn (Event $event): array => ['url' => EventLinks::show($event), 'name' => (string) $event->title])->values()->all(),
            )],
        ];
    }
}
