<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Member;

use App\Application\Community\Groups\GroupDirectory;
use App\Application\Community\Groups\GroupMembersQuery;
use App\Application\Community\Groups\GroupPostsQuery;
use App\Application\Feed\FeedComposerOptions;
use App\Application\Shared\Referentials;
use App\Enums\GroupPrivacy;
use App\Http\Controllers\Api\V1\Community\GroupPostController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\CommunityPageRequest;
use App\Http\Resources\Community\GroupMemberResource;
use App\Http\Resources\Community\GroupResource;
use App\Http\Resources\Feed\PostResource;
use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use App\Support\LocaleFormatter;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Écrans Vue des groupes (P8-02, prototype groups.php, group-details.php) : pages Inertia `Community/Groups`
 * (`/groupes` : Découvrir · Mes groupes, recherche, « Charger plus », modale « Créer un groupe ») et
 * `Community/GroupShow` (`/groupes/{ulid}` : couverture, Rejoindre / Demander à rejoindre / Membre – Quitter,
 * « Discussion du groupe », onglets À propos · Publications · Membres, colonne Membres et Événements du groupe).
 * Premières pages servies dans les props sous la forme de l'API (docs/API.md §13) ; la suite et les gestes passent par
 * `/api/v1`. Groupe privé dont la personne n'est pas membre : fiche réduite, sans publications ni membres (FR-SOC-05,
 * Q75) ; groupe inactif ou supprimé : 404.
 */
final class GroupsPageController extends Controller
{
    private const SIDEBAR_MEMBERS = 8;

    private const SIDEBAR_EVENTS = 2;

    public function __construct(
        private readonly GroupDirectory $groups,
        private readonly FeedComposerOptions $composer,
    ) {}

    public function index(CommunityPageRequest $request, Referentials $referentials): Response
    {
        $viewer = $request->viewer();
        $tab = $request->choice('tab', GroupDirectory::TABS, GroupDirectory::TAB_DISCOVER);
        $search = $request->term();
        $title = (string) __('community.groups.title');

        return Inertia::render('Community/Groups', [
            'title' => $title,
            'tab' => $tab,
            'q' => $search,
            'groups' => fn (): array => $this->list($request, $viewer, $tab, $search),
            'create' => fn (): array => [
                'can' => $viewer->can('create', Group::class),
                'zones' => array_map(static fn (array $zone): array => ['id' => $zone['id'], 'name' => $zone['name']], $referentials->zones()),
                'privacies' => array_map(static fn (GroupPrivacy $privacy): array => [
                    'value' => $privacy->value,
                    'label' => $privacy->label(),
                ], [GroupPrivacy::Private, GroupPrivacy::Public]),
                'limits' => [
                    'name_min' => (int) config('xomiton.community.group_name_min', 3),
                    'name_max' => (int) config('xomiton.community.group_name_max', 80),
                    'description_max' => (int) config('xomiton.community.group_description_max', 300),
                ],
            ],
        ])->withViewData(['title' => $title]);
    }

    public function show(CommunityPageRequest $request, GroupPostsQuery $posts, GroupMembersQuery $members, Group $group): Response
    {
        $viewer = $request->viewer();

        if (! $viewer->can('discover', $group)) {
            throw new NotFoundHttpException;
        }

        $group->loadMissing('zone');
        $readable = $viewer->can('view', $group);
        $team = $viewer->can('moderate', $group);
        $membership = $this->groups->membership($viewer, $group);
        $tab = $request->choice('tab', ['about', 'posts', 'members'], 'about');

        return Inertia::render('Community/GroupShow', [
            'title' => (string) $group->name,
            'tab' => $readable ? $tab : 'about',
            'group' => fn (): array => GroupResource::make($group)
                ->withMembership($membership)
                ->withPendingRequests($team ? $this->groups->pendingRequests($group) : null)
                ->resolve($request),
            'posts' => fn (): ?array => $readable ? $this->posts($request, $posts, $group, $viewer) : null,
            'members' => fn (): ?array => $readable ? [
                'data' => GroupMemberResource::list(
                    $members->for($group, $viewer, $team)->limit(self::SIDEBAR_MEMBERS)->get(),
                    $group,
                    $viewer,
                    $membership,
                ),
                'hidden_count' => $team ? 0 : $members->hiddenCount($group, $viewer),
            ] : null,
            'events' => fn (): array => $readable ? $this->events($group) : [],
            'composer' => fn (): ?array => $viewer->can('post', $group) && $group->is_active ? $this->composer($viewer, $group) : null,
            'config' => [
                'members_per_page' => (int) config('xomiton.community.members_per_page', 24),
            ],
        ])->withViewData(['title' => (string) $group->name]);
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function list(CommunityPageRequest $request, User $viewer, string $tab, ?string $search): array
    {
        // Sans accès au réseau (`social.access`, comme ListGroupsRequest) : liste vide, comme le fil.
        if (! $viewer->can('viewAny', Group::class)) {
            return ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0]];
        }

        $paginator = $this->groups->paginate($viewer, $tab, $search, (int) config('xomiton.community.groups_per_page', 6));
        $memberships = $this->groups->memberships($viewer, $paginator->items());

        return [
            'data' => array_map(
                static fn (Group $group): array => GroupResource::make($group)->withMembership($memberships[(int) $group->getKey()] ?? null)->resolve($request),
                $paginator->items(),
            ),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function posts(CommunityPageRequest $request, GroupPostsQuery $posts, Group $group, User $viewer): array
    {
        $paginator = $posts->for($group, $viewer)->cursorPaginate((int) config('xomiton.feed.per_page', 20));

        return [
            'data' => GroupPostController::forViewer(PostResource::collection(collect($paginator->items()))->resolve($request), $viewer, $group),
            'meta' => ['next_cursor' => $paginator->nextCursor()?->encode(), 'per_page' => $paginator->perPage()],
        ];
    }

    /**
     * Prochains événements publiés du groupe (titre, date, « En ligne » ; jamais le lieu, réservé aux participant·es
     * confirmé·es, P8-04).
     *
     * @return list<array{id: string, title: string, href: string, meta: string}>
     */
    private function events(Group $group): array
    {
        $fmt = LocaleFormatter::make();
        $separator = ' '.__('common.separator').' ';

        return Event::query()->published()->upcoming()->where('group_id', $group->getKey())
            ->limit(self::SIDEBAR_EVENTS)
            ->get(['id', 'ulid', 'title', 'starts_at', 'is_online'])
            ->map(static fn (Event $event): array => [
                'id' => (string) $event->ulid,
                'title' => (string) $event->title,
                'href' => route('evenements.show', ['event' => $event->ulid]),
                'meta' => implode($separator, array_filter([
                    $event->starts_at ? $fmt->dayMonthShort($event->starts_at) : null,
                    $event->is_online ? (string) __('feed.sidebar.event_online') : null,
                ])),
            ])
            ->values()->all();
    }

    /**
     * Réglages de la modale « Créer une publication » pour ce groupe : ceux du fil (FeedComposerOptions), audience
     * « Membres du groupe » seulement (visibilité `public`), ULID du groupe envoyé en `group` (docs/API.md §8.3).
     *
     * @return array<string, mixed>
     */
    private function composer(User $viewer, Group $group): array
    {
        $options = $this->composer->for($viewer);
        $options['visibilities'] = [['value' => 'public', 'label' => (string) __('community.groups.detail.visibility_members')]];
        $options['group'] = $group->ulid;

        return $options;
    }
}
