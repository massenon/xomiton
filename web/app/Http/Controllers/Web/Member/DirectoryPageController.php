<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Member;

use App\Application\Directory\BrowseDirectory;
use App\Application\Shared\Referentials;
use App\Enums\DirectoryRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Directory\BrowseDirectoryRequest;
use App\Http\Resources\Directory\DirectoryContactResource;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Écran Vue « Annuaire dynamique » `/annuaire` (P8-12 ; prototype annuaire.php) : page Inertia `Member/Directory` dans le
 * gabarit membre (col-lg-9 à côté du sidenav) — en-tête + « Urgences », filtres recherche · rôle · département · commune
 * (adresse `?q=&role=&zone=&commune=` gardée, visite partielle), carte schématique du Bénin par département (SVG, sans
 * Leaflet : Q15 b côté membre), cartes de contact (Appeler `tel:`, WhatsApp, E-mail) et modale « Demander à être
 * rappelé·e » (`POST /api/v1/directory/{id}/callback` → RequestCallback, P5-02). Fiches selon la portée du compte
 * (DirectoryReach) ; première page dans les props sous la forme de l'API (docs/API.md §19), la suite par l'API.
 * Page d'une session : jamais marquée `X-Xm-Audience: guest`, donc jamais gardée par le service worker (P5-10) ; sans
 * compte, `auth` redirige vers la connexion (préchargement refusé, repli hors ligne `/urgences`) ; sans
 * `directory.view`, 403 comme l'API (`can:directory.view` sur la route).
 */
final class DirectoryPageController extends Controller
{
    public function __invoke(BrowseDirectoryRequest $request, BrowseDirectory $directory, Referentials $referentials): Response
    {
        $viewer = $request->viewer();
        $reach = BrowseDirectory::reachOf($viewer);

        // Route `can:directory.view` : même refus que l'API (403), jamais un écran vide.
        abort_unless($reach->canBrowse(), 403);

        $filters = $request->filters();
        $title = (string) __('directory.title');

        return Inertia::render('Member/Directory', [
            'title' => $title,
            'reach' => $reach->value,
            'filters' => $filters->toArray(),
            'roles' => array_map(
                static fn (DirectoryRole $role): array => ['value' => $role->value, 'label' => $role->label()],
                DirectoryRole::cases(),
            ),
            'zones' => fn (): array => array_map(static fn (array $zone): array => [
                'id' => $zone['id'],
                'name' => $zone['name'],
                'communes' => array_map(static fn (array $commune): array => ['id' => $commune['id'], 'name' => $commune['name']], $zone['communes']),
            ], $referentials->zones()),
            'contacts' => function () use ($request, $directory, $viewer, $filters): array {
                $page = $directory->page($viewer, $filters, (int) config('xomiton.directory.per_page', 24));

                return [
                    'data' => DirectoryContactResource::collection(collect($page->items()))->resolve($request),
                    'meta' => [
                        'current_page' => $page->currentPage(),
                        'last_page' => $page->lastPage(),
                        'per_page' => $page->perPage(),
                        'total' => $page->total(),
                    ],
                ];
            },
            'map' => fn (): array => $directory->map($viewer, $filters),
            'limits' => [
                'search_max' => (int) config('xomiton.directory.search_max', 80),
                'message_max' => (int) config('xomiton.alerts.callback_message_max', 1000),
                'contact_max' => (int) config('xomiton.alerts.callback_contact_max', 190),
            ],
        ])->withViewData(['title' => $title]);
    }
}
