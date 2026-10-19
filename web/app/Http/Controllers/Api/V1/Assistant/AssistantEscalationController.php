<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assistant;

use App\Application\Assistant\EscalateToHuman;
use App\Http\Controllers\Api\V1\Alerts\ResolvesAlertSource;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Assistant\EscalateToHumanRequest;
use App\Http\Resources\Alerts\AlertResource;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/assistant/escalate (P8-11, « Parler à un humain ») : alerte « besoin » routée (EscalateToHuman), 201 avec
 * l'alerte telle que la personne la suit (code de suivi, contact routé, sans contact ni récit), `idempotent:required`.
 */
final class AssistantEscalationController extends ApiController
{
    use ResolvesAlertSource;

    public function __invoke(EscalateToHumanRequest $request, EscalateToHuman $escalate): JsonResponse
    {
        $anonymous = $request->boolean('anonymous', true);
        $result = $escalate($request->toData($this->alertSource($request), $anonymous ? null : $this->alertDevice($request)));
        $alert = $result->alert->loadMissing('routedContact');

        return $this->created(AlertResource::make($alert))->withHeaders(['Cache-Control' => 'no-store, private']);
    }
}
