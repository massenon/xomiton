<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Member\Peer;

use App\Application\Alerts\OnCallRoster;
use App\Application\Member\MemberDashboard;
use App\Http\Controllers\Controller;
use App\Http\Resources\Alerts\RoutedAlertResource;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tableau de bord `/espace-pair` (P9-03 ; prototype espace-pair.php) : page Inertia `PeerSpace/Home` — 4 KPI de **ses**
 * dossiers (collectés, ce mois-ci, en cours, clos : MemberDashboard::peer, comme `GET dashboard`), tâches du jour,
 * raccourcis, alertes routées vers ses fiches d'annuaire (résumé RoutedAlertResource, jamais le récit ni le contact :
 * le détail se charge à l'ouverture par `GET pair/alerts/{ulid}`). Interrogation toutes les
 * `xomiton.peer.alerts_poll_seconds` secondes (10) quand le compte est de garde (plage `focal` en cours,
 * OnCallRoster::isFocalOnDuty), sinon seulement à l'ouverture, au retour sur l'onglet et au geste « Actualiser ».
 * Réservé au compte actif détenant `peer_space.access` (middleware EnsurePeerSpaceAccess : RG-03, un·e pair·e en
 * attente de validation reste membre, 403 comme l'API).
 */
final class PeerHomePageController extends Controller
{
    public function __invoke(Request $request, MemberDashboard $dashboard, OnCallRoster $roster): Response
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $memo = null;
        $peer = static function () use (&$memo, $dashboard, $viewer): array {
            return $memo ??= $dashboard->peer($viewer);
        };
        $title = (string) __('peer.home.title');
        // Même taille de page que l'API (`GET pair/alerts?per_page=`) : l'écran charge la première page, « Voir plus » la suite.
        $perPage = max(1, (int) config('xomiton.api.dashboard_routed_alerts', 5));

        // Fermetures : une visite partielle (`only: ['on_duty', 'poll_seconds']`, au retour sur l'onglet) ne recalcule ni
        // les KPI ni les alertes.
        return Inertia::render('PeerSpace/Home', [
            'title' => $title,
            'kpi' => fn (): array => $peer()['kpi'],
            'tasks' => fn (): array => $peer()['tasks'],
            'alerts' => fn (): array => [
                'open' => $peer()['routed_alerts']['open'],
                'new' => $peer()['routed_alerts']['new'],
                'items' => RoutedAlertResource::collection($peer()['routed_alerts']['items'])->resolve($request),
            ],
            'per_page' => $perPage,
            'max_per_page' => max($perPage, (int) config('xomiton.api.max_per_page', 50)),
            'on_duty' => fn (): bool => $roster->isFocalOnDuty($viewer),
            'poll_seconds' => fn (): int => $roster->isFocalOnDuty($viewer) ? max(5, (int) config('xomiton.peer.alerts_poll_seconds', 10)) : 0,
        ])->withViewData(['title' => $title]);
    }
}
