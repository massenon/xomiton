<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Peer;

use App\Application\Alerts\AcknowledgeAlert;
use App\Application\Alerts\ChangeAlertStatus;
use App\Application\Cases\Queries\ListRoutedAlerts;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Alerts\ChangeAlertStatusRequest;
use App\Http\Requests\Peer\ListRoutedAlertsRequest;
use App\Http\Resources\Alerts\RoutedAlertResource;
use App\Http\Resources\Peer\PeerAlertResource;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Alertes routées vers moi (P9-02, docs/API.md §22 ; ROLE_ET_PERMISSIONS.md §4 l.72 « Pair : routées ») : liste
 * (ListRoutedAlerts, résumé RoutedAlertResource du tableau de bord), détail, accusé de réception (AcknowledgeAlert,
 * P5-02) et changement de statut — prendre en charge (`pris_en_charge`, `en_cours` : `alerts.take`), traiter ou
 * clore (`traite`, `clos` : `alerts.close`) — par ChangeAlertStatus (P5-02). **Ni réattribution, ni note interne, ni
 * export** (`alerts.manage`, administration). Alerte non routée vers le compte → 403, même pour un compte
 * `alerts.view` (middleware EnsureAlertRoutedToViewer sur les routes, avant AlertPolicy ; identifiant ULID opaque,
 * ADR-27). Réponses `no-store` (récit, contact et position déchiffrés).
 */
final class RoutedAlertController extends ApiController
{
    public function index(ListRoutedAlertsRequest $request, ListRoutedAlerts $alerts): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $page = $alerts->page($viewer, $request->state(), $this->perPage($request), $request->page());

        return $this->paginated($page, RoutedAlertResource::class, [
            'state' => $request->state(),
            'new' => $alerts->query($viewer)->unacknowledged()->count(),
        ])->withHeaders(self::NO_STORE);
    }

    public function show(Request $request, Alert $alert): JsonResponse
    {
        Gate::authorize('view', $alert);

        return $this->detail($alert);
    }

    public function acknowledge(Request $request, Alert $alert, AcknowledgeAlert $acknowledge): JsonResponse
    {
        /** @var User $by */
        $by = $request->user();
        $result = $acknowledge->byUser($alert, $by);

        return $this->detail($result->alert, [
            'acknowledged_now' => $result->acknowledgedNow(),
            'already_acknowledged' => $result->alreadyAcknowledged,
            'closed' => $result->closed,
        ]);
    }

    public function status(ChangeAlertStatusRequest $request, Alert $alert, ChangeAlertStatus $change): JsonResponse
    {
        /** @var User $by */
        $by = $request->user();

        return $this->detail($change($alert, $request->status(), $by));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function detail(Alert $alert, array $meta = []): JsonResponse
    {
        $alert->loadMissing(['zone:id,name', 'commune:id,name', 'statusChanges']);

        return $this->respond(PeerAlertResource::make($alert), meta: $meta, headers: self::NO_STORE);
    }
}
