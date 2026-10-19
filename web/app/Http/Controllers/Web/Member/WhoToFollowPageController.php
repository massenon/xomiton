<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Member;

use App\Application\Community\Network\InvitationDirectory;
use App\Application\Community\Network\ManageInvitations;
use App\Application\Community\Network\NetworkSuggestions;
use App\Application\Shared\Referentials;
use App\Http\Controllers\Controller;
use App\Http\Requests\Member\NetworkPageRequest;
use App\Http\Resources\Network\InvitationResource;
use App\Http\Resources\Network\NetworkPersonResource;
use App\Models\Invitation;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Écran Vue « Qui suivre et invitations » (P8-06, prototype who-to-follow.php) : page Inertia `Community/WhoToFollow`
 * dans le gabarit membre (col-lg-9 à côté du sidenav, comme le prototype) — en-tête et onglets Suggestions ·
 * Invitations reçues · Invitations envoyées avec leurs compteurs (`?tab=recues|envoyees`), filtres des suggestions
 * (pseudonyme, zone, profil), cartes « Envoyer une invitation » et « Suivre », listes « Accepter / Refuser » et
 * « Annuler ». Premières pages servies dans les props sous la forme de l'API (docs/API.md §17) ; la suite (« Charger
 * plus », par curseur `meta.next_cursor`) et les gestes passent par `/api/v1/follows` et `/api/v1/invitations`. `?invite=<id>` (carte « Qui suivre » du
 * fil) **prépare** l'invitation (`invite` : la personne et l'issue prévue) : elle n'est envoyée qu'après confirmation,
 * un lien ne devant jamais suffire à relier deux comptes (le prototype l'envoyait dès le GET). Sans `social.access` :
 * page sans suggestions ni invitations (l'API répond 403).
 */
final class WhoToFollowPageController extends Controller
{
    public function __construct(
        private readonly NetworkSuggestions $suggestions,
        private readonly InvitationDirectory $invitations,
        private readonly ManageInvitations $manage,
    ) {}

    public function __invoke(NetworkPageRequest $request, Referentials $referentials): Response
    {
        $viewer = $request->viewer();
        $access = $viewer->can('viewAny', Invitation::class);
        $tab = $request->tab();
        $filters = $request->filters();
        $title = (string) __('community.follow.title');

        return Inertia::render('Community/WhoToFollow', [
            'title' => $title,
            'tab' => $tab,
            'filters' => [
                'q' => $filters->q,
                'zone' => $filters->zoneId,
                'role' => NetworkPageRequest::roleParam($filters->role),
            ],
            'zones' => fn (): array => array_map(static fn (array $zone): array => ['id' => $zone['id'], 'name' => $zone['name']], $referentials->zones()),
            'roles' => array_keys(NetworkPageRequest::ROLES),
            'search_max' => (int) config('xomiton.network.search_max', 60),
            'counts' => fn (): array => $access ? $this->invitations->counts($viewer) : ['received' => 0, 'sent' => 0],
            'suggestions' => function () use ($request, $viewer, $access, $tab, $filters): array {
                if (! $access || $tab !== 'suggestions') {
                    return self::empty();
                }

                $page = $this->suggestions->page($viewer, $filters, (int) config('xomiton.network.suggestions_per_page', 12));

                return ['data' => NetworkPersonResource::collection($page->items)->resolve($request), 'meta' => $page->meta()];
            },
            'invitations' => function () use ($request, $viewer, $access, $tab): array {
                if (! $access || $tab === 'suggestions') {
                    return self::empty();
                }

                $page = $this->invitations->page($viewer, $tab, (int) config('xomiton.network.invitations_per_page', 20));

                return ['data' => InvitationResource::collection($page->items)->resolve($request), 'meta' => $page->meta()];
            },
            'invite' => fn (): ?array => $this->invite($request, $viewer, $access),
            'can' => [
                'access' => $access,
                'invite' => $access && $viewer->hasVerifiedEmail(),
                'follow' => $access,
            ],
        ])->withViewData(['title' => $title]);
    }

    /**
     * @return array{status: string, person: array<string, mixed>|null}|null
     */
    private function invite(NetworkPageRequest $request, User $viewer, bool $access): ?array
    {
        $id = $request->inviteId();

        if ($id === null) {
            return null;
        }

        if (! $access) {
            return ['status' => ManageInvitations::UNAVAILABLE, 'person' => null];
        }

        ['status' => $status, 'target' => $target, 'following' => $following] = $this->manage->preview($viewer, $id);

        if ($target === null) {
            return ['status' => $status, 'person' => null];
        }

        $person = NetworkPersonResource::make($target)->resolve($request);
        $person['following'] = $following;
        $person['can']['invite'] = $status === ManageInvitations::READY;

        return ['status' => $status, 'person' => $person];
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{next_cursor: null, per_page: int}}
     */
    private static function empty(): array
    {
        return ['data' => [], 'meta' => ['next_cursor' => null, 'per_page' => 0]];
    }
}
