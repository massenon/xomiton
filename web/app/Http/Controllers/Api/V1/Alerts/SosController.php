<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Alerts;

use App\Application\Alerts\CancelSos;
use App\Application\Alerts\Dispatch\NotifiedPeople;
use App\Application\Alerts\MarkSafe;
use App\Application\Alerts\TriggerSos;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Alerts\CancelSosRequest;
use App\Http\Requests\Alerts\MarkSafeRequest;
use App\Http\Requests\Alerts\ShowSosRequest;
use App\Http\Requests\Alerts\StoreSosRequest;
use App\Http\Resources\Alerts\SosStatusResource;
use App\Models\Alert;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;

/**
 * SOS (P5-06, docs/API.md §6.4-§6.6) : `POST sos` (jamais de 429 : `idempotent:required,nonblocking,limit=sos` sans
 * `throttle:api`), `GET sos/{id}` (statut + personnes prévenues NotifiedPeople du lot P5-03, polling 10 s), `POST sos/{id}/cancel` (repentir 60 s,
 * Q39) et `POST sos/{id}/safe` (« Je suis en sécurité »). Lecture et actions réservées au compte propriétaire ou au
 * porteur du jeton `X-Sos-Token` rendu à la création d'un SOS anonyme (Q52 b ; ActsAsSosOwner → 403 sinon).
 */
final class SosController extends ApiController
{
    use ResolvesAlertSource;

    private const PRIVATE_HEADERS = ['Cache-Control' => 'no-store, private'];

    public function store(StoreSosRequest $request, TriggerSos $trigger): JsonResponse
    {
        $result = $trigger($request->toData($this->alertSource($request), $this->alertDevice($request)));
        $alert = $this->loaded($result->alert);

        return $this->created(new SosStatusResource($alert, NotifiedPeople::for($alert), $result->sosToken), route('api.sos.show', ['alert' => $alert->ulid]))
            ->withHeaders(self::PRIVATE_HEADERS);
    }

    public function show(ShowSosRequest $request, Alert $alert): JsonResponse
    {
        abort_unless($alert->isSos(), 404);

        return $this->status($this->loaded($alert));
    }

    public function cancel(CancelSosRequest $request, Alert $alert, CancelSos $cancel): JsonResponse
    {
        $alert = $cancel($alert, $request->actor(), $request->sosToken());

        return $this->status($this->loaded($alert));
    }

    public function safe(MarkSafeRequest $request, Alert $alert, MarkSafe $markSafe): JsonResponse
    {
        $alert = $markSafe($alert, $request->actor(), $request->sosToken());

        return $this->status($this->loaded($alert));
    }

    private function status(Alert $alert): JsonResponse
    {
        return $this->respond(new SosStatusResource($alert, NotifiedPeople::for($alert)), headers: self::PRIVATE_HEADERS);
    }

    private function loaded(Alert $alert): Alert
    {
        return $alert->load([
            'routedContact',
            'statusChanges' => fn (HasMany $changes) => $changes->orderBy('created_at')->orderBy('id'),
        ]);
    }
}
