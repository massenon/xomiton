<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Member;

use App\Application\Account\Profile\MemberProfiles;
use App\Application\Account\Profile\ProgressiveProfileState;
use App\Application\Feed\FeedComposerOptions;
use App\Application\Feed\Queries\UserPostsQuery;
use App\Application\Shared\Referentials;
use App\Enums\AgeRange;
use App\Enums\MemberType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\CommunityPageRequest;
use App\Http\Resources\Feed\PostResource;
use App\Http\Resources\Member\ProfilePresenter;
use App\Http\Resources\UserResource;
use App\Models\NewsletterInterest;
use App\Models\Post;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Profil (P8-07, prototype my-profile*.php, partials/profile-head.php, x_profile_side()) : page Inertia `Member/Profile`
 * pour `/profil/{onglet?}` (son profil) et `/membres/{pseudo}/{onglet?}` (celui d'une autre personne, noindex comme
 * tout l'espace membre ; invisible → 404 : privé, abonné·es seulement non suivi·e, blocage, compte suspendu ou
 * anonymisé ; son propre pseudo → /profil). Six onglets du prototype (Publications avec compteur · À propos ·
 * Connexions · Médias · Événements · Activité, Q24 a) ; Activité seulement sur son profil (journal personnel). Seules
 * les données de l'onglet ouvert sont calculées ; la suite (« Charger plus », recherche) passe par l'API (§11, §18).
 * Profil progressif P8-08 : bandeau sur son profil tant qu'un groupe reste à traiter.
 */
final class ProfilePageController extends Controller
{
    /** Onglet (segment d'adresse français) → clé. Publications : sans segment. */
    public const TABS = [
        'a-propos' => 'about',
        'connexions' => 'connections',
        'medias' => 'media',
        'evenements' => 'events',
        'activite' => 'activity',
    ];

    public function __construct(
        private readonly MemberProfiles $profiles,
        private readonly ProfilePresenter $presenter,
        private readonly UserPostsQuery $posts,
        private readonly FeedComposerOptions $composer,
    ) {}

    public function me(CommunityPageRequest $request, ?string $onglet = null): Response
    {
        $viewer = $request->viewer();
        $viewer->loadMissing(['profile', 'zone', 'commune']);

        return $this->render($request, $viewer, $viewer, self::TABS[$onglet] ?? 'posts');
    }

    public function show(CommunityPageRequest $request, string $pseudo, ?string $onglet = null): Response|RedirectResponse
    {
        $viewer = $request->viewer();

        if ($pseudo === $viewer->pseudo) {
            return redirect()->route('profil', array_filter(['onglet' => $onglet]));
        }

        $owner = $this->profiles->byPseudo($viewer, $pseudo) ?? throw new NotFoundHttpException;

        return $this->render($request, $viewer, $owner, self::TABS[$onglet] ?? 'posts');
    }

    private function render(CommunityPageRequest $request, User $viewer, User $owner, string $tab): Response
    {
        $self = $viewer->is($owner);
        $header = $this->presenter->header($request, $viewer, $owner);
        $title = $self ? (string) __('profile.title') : (string) __('profile.page_title_of', ['name' => $owner->pseudo]);

        return Inertia::render('Member/Profile', [
            'title' => $title,
            'tab' => $tab,
            'tabs' => $this->tabs($owner, $self, (int) $header['stats']['posts']),
            'profile' => $header,
            'panel' => fn (): ?array => $this->panel($request, $viewer, $owner, $tab),
            'side' => fn (): array => $this->presenter->side($request, $viewer, $owner),
            'composer' => fn (): ?array => $self && $tab === 'posts' && $viewer->can('create', Post::class) ? $this->composer->for($viewer) : null,
            'report_reasons' => fn (): array => $this->composer->reportReasons(),
            'progressive' => fn (): ?array => $self ? $this->progressive($request, $viewer) : null,
            'links' => $self ? [
                'settings' => route('parametres'),
                'privacy' => route('parametres').'#confidentialite',
                'data' => route('parametres').'#donnees',
                'media_library' => route('medias'),
                'who_to_follow' => route('qui-suivre'),
                'events' => route('evenements.index'),
            ] : [
                'media_library' => null,
                'who_to_follow' => null,
                'events' => route('evenements.index'),
            ],
            'config' => [
                'connections_per_page' => (int) config('xomiton.profile.connections_per_page', 24),
                'bio_max' => 500,
            ],
        ])->withViewData(['title' => $title]);
    }

    /**
     * @return list<array{key: string, href: string, count: int|null}>
     */
    private function tabs(User $owner, bool $self, int $posts): array
    {
        $href = static fn (?string $slug): string => $self
            ? route('profil', array_filter(['onglet' => $slug]))
            : route('membres.show', array_filter(['pseudo' => $owner->pseudo, 'onglet' => $slug]));

        $tabs = [['key' => 'posts', 'href' => $href(null), 'count' => $posts]];

        foreach (self::TABS as $slug => $key) {
            if ($key === 'activity' && ! $self) {
                continue;
            }

            $tabs[] = ['key' => $key, 'href' => $href($slug), 'count' => null];
        }

        return $tabs;
    }

    /**
     * Données de l'onglet ouvert (« À propos » : tout est dans `profile`).
     *
     * @return array<string, mixed>|null
     */
    private function panel(CommunityPageRequest $request, User $viewer, User $owner, string $tab): ?array
    {
        return match ($tab) {
            'posts' => $this->postsPage($request, $viewer, $owner),
            'connections' => $this->presenter->connections($request, $viewer, $owner, $request->term(), (int) config('xomiton.profile.connections_per_page', 24)),
            'media' => ['data' => $this->presenter->media($viewer, $owner, (int) config('xomiton.profile.media_limit', 24))],
            'events' => $this->presenter->events($request, $viewer, $owner, (int) config('xomiton.profile.events_limit', 12)),
            'activity' => $this->activity($viewer, $owner),
            default => null,
        };
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function postsPage(CommunityPageRequest $request, User $viewer, User $owner): array
    {
        $paginator = $this->posts->posts($viewer, $owner)->cursorPaginate((int) config('xomiton.feed.per_page', 20));

        /** @var list<array<string, mixed>> $items */
        $items = PostResource::collection(collect($paginator->items()))->resolve($request);

        return ['data' => $items, 'meta' => ['next_cursor' => $paginator->nextCursor()?->encode(), 'per_page' => $paginator->perPage(), 'user_id' => (int) $owner->getKey()]];
    }

    /**
     * @return array{data: list<array<string, mixed>>}|null
     */
    private function activity(User $viewer, User $owner): ?array
    {
        $profile = $owner->profile ?? new Profile(['user_id' => $owner->getKey()]);

        if (! $viewer->is($owner) || ! Gate::forUser($viewer)->allows('viewActivity', $profile)) {
            return null;
        }

        return ['data' => $this->presenter->activity($viewer, (int) config('xomiton.profile.activity_limit', 40))];
    }

    /**
     * Bandeau « Complète ton profil » (P8-08) : état, valeurs actuelles (vue « moi », comme `GET me/profile`) et listes
     * fermées des groupes ; null une fois tous les groupes traités.
     *
     * @return array<string, mixed>|null
     */
    private function progressive(CommunityPageRequest $request, User $viewer): ?array
    {
        $state = app(ProgressiveProfileState::class)->for($viewer);

        if ($state['done']) {
            return null;
        }

        $profile = (array) (UserResource::make($viewer)->asSelf()->withSensitiveProfile()->resolve($request)['profile'] ?? []);

        return [
            'state' => $state,
            'values' => [
                'pronouns' => $profile['pronouns'] ?? null,
                'member_type' => $profile['member_type'] ?? null,
                'ally_status' => $profile['ally_status'] ?? null,
                'gender_identity' => $profile['gender_identity'] ?? null,
                'orientation' => $profile['orientation'] ?? null,
                'age_range' => $profile['age_range'] ?? null,
                'zone_id' => $viewer->zone_id,
                'commune_id' => $viewer->commune_id,
                'interests' => $profile['interests'] ?? [],
                'shared_fields' => $profile['shared_fields'] ?? [],
                'show_zone' => (bool) ($profile['show_zone'] ?? true),
            ],
            'options' => [
                'member_types' => array_map(static fn (MemberType $type): array => ['value' => $type->value, 'label' => $type->label()], [MemberType::Member, MemberType::Peer]),
                'age_ranges' => array_map(static fn (AgeRange $range): array => ['value' => $range->value, 'label' => $range->label()], AgeRange::cases()),
                'zones' => array_map(static fn (array $zone): array => [
                    'id' => $zone['id'],
                    'name' => $zone['name'],
                    'communes' => array_map(static fn (array $commune): array => ['id' => $commune['id'], 'name' => $commune['name']], $zone['communes']),
                ], app(Referentials::class)->zones()),
                'interests' => NewsletterInterest::query()->ordered()->get(['slug', 'label'])
                    ->map(static fn (NewsletterInterest $interest): array => ['slug' => (string) $interest->slug, 'label' => (string) $interest->label])->values()->all(),
            ],
        ];
    }
}
