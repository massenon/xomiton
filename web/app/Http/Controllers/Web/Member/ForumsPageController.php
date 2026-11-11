<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Member;

use App\Application\Community\Forums\ForumDirectory;
use App\Application\Community\Forums\RecordTopicView;
use App\Application\Feed\FeedComposerOptions;
use App\Application\Feed\Queries\FeedHighlights;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\CommunityPageRequest;
use App\Http\Resources\Forums\ForumReplyResource;
use App\Http\Resources\Forums\ForumResource;
use App\Http\Resources\Forums\ForumTopicResource;
use App\Models\Forum;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Écrans Vue des forums (P8-05, prototype forums.php, forum-topic.php) : pages Inertia `Forums/Index` (`/forums` :
 * en-tête + « Nouveau sujet » (modale chargée à la demande), cartes des 5 forums (« N sujets · M messages », filtre
 * `?forum=f1`), « Sujets récents » avec recherche et « Charger plus ») et `Forums/Show` (`/forums/{ulid}` : fil
 * d'Ariane, sujet, vues, réponses, formulaire « Ta réponse » avec interrupteur anonyme). Premières pages servies dans
 * les props sous la forme de l'API (docs/API.md §16) ; la suite et les gestes passent par `/api/v1/forums`. Lecture
 * tolérante comme le prototype : forum inconnu ou recherche trop longue → « Tous les forums » (l'API répond 422). Sans
 * `social.access`, page sans forums ni sujets (l'API répond 403). Sujet
 * invisible (masqué, supprimé, forum fermé, blocage) → 404 ; vue comptée une fois par session (RecordTopicView).
 */
final class ForumsPageController extends Controller
{
    public function __construct(
        private readonly ForumDirectory $forums,
        private readonly FeedHighlights $highlights,
        private readonly FeedComposerOptions $composer,
    ) {}

    public function index(CommunityPageRequest $request): Response
    {
        $viewer = $request->viewer();
        // Sans accès au réseau (`social.access`), ni forums ni sujets : comme `GET /api/v1/forums` (403).
        $forums = $viewer->can('viewAny', ForumTopic::class) ? $this->forums->forums() : new Collection;
        $forum = $this->forums->forum(is_string($request->query('forum')) ? $request->query('forum') : null);
        $search = $request->term();
        $title = (string) __('community.forums.title');

        return Inertia::render('Forums/Index', [
            'title' => $title,
            'forum' => $forum?->key,
            'q' => $search,
            'search_max' => (int) config('xomiton.forums.search_max', 60),
            'forums' => fn (): array => ForumResource::collection($forums)->resolve($request),
            'topics' => fn (): array => $this->topics($request, $viewer, $forum, $search),
            'create' => fn (): array => [
                'can' => $viewer->can('create', ForumTopic::class),
                'forums' => $forums->map(static fn (Forum $item): array => ['value' => (string) $item->key, 'label' => (string) $item->name])->values()->all(),
                'limits' => self::topicLimits(),
            ],
            'events' => fn (): array => $this->highlights->events(),
        ])->withViewData(['title' => $title]);
    }

    public function show(CommunityPageRequest $request, ForumTopic $topic, RecordTopicView $views): Response
    {
        $viewer = $request->viewer();

        if (! $viewer->can('view', $topic)) {
            throw new NotFoundHttpException;
        }

        $topic->loadMissing(['forum', 'user.profile', 'user.roles']);
        $views($topic, $request, $viewer);

        return Inertia::render('Forums/Show', [
            'title' => (string) $topic->title,
            'topic' => fn (): array => ForumTopicResource::make($topic)->detailed()->resolve($request),
            'replies' => function () use ($request, $topic, $viewer): array {
                $paginator = $this->forums->replies($topic, $viewer, (int) config('xomiton.forums.replies_per_page', 30));

                return [
                    'data' => ForumReplyResource::collection(collect($paginator->items()))->resolve($request),
                    'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
                ];
            },
            'reply' => fn (): array => [
                'limits' => ['min' => (int) config('xomiton.forums.reply_min', 2), 'max' => (int) config('xomiton.forums.reply_max', 2000)],
            ],
            'report_reasons' => fn (): array => $this->composer->reportReasons(),
            'events' => fn (): array => $this->highlights->events(),
        ])->withViewData(['title' => (string) $topic->title]);
    }

    /**
     * Première page de « Sujets récents » ; vide sans accès au réseau (`social.access`), comme le fil.
     *
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function topics(CommunityPageRequest $request, User $viewer, ?Forum $forum, ?string $search): array
    {
        if (! $viewer->can('viewAny', ForumTopic::class)) {
            return ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0]];
        }

        $paginator = $this->forums->paginate($viewer, $forum, $search, (int) config('xomiton.forums.topics_per_page', 6));

        return [
            'data' => ForumTopicResource::collection(collect($paginator->items()))->resolve($request),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ];
    }

    /**
     * @return array{title_min: int, title_max: int, body_min: int, body_max: int}
     */
    private static function topicLimits(): array
    {
        return [
            'title_min' => (int) config('xomiton.forums.title_min', 5),
            'title_max' => (int) config('xomiton.forums.title_max', 140),
            'body_min' => (int) config('xomiton.forums.body_min', 10),
            'body_max' => (int) config('xomiton.forums.body_max', 5000),
        ];
    }
}
