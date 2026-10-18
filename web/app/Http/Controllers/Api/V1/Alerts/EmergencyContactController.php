<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Alerts;

use App\Application\Alerts\Queries\ListEmergencyContacts;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Alerts\EmergencyContactsRequest;
use App\Http\Resources\Alerts\EmergencyContactsResource;
use Illuminate\Http\JsonResponse;

/**
 * `GET urgences?zone=` (P5-06, docs/API.md §6.7) : numéros nationaux et fiches publiques de l'annuaire, même source que la page
 * `/urgences` (ListEmergencyContacts) ; lecture publique en cache HTTP comme les référentiels (ETag, 304).
 */
final class EmergencyContactController extends ApiController
{
    public function __invoke(EmergencyContactsRequest $request, ListEmergencyContacts $contacts): JsonResponse
    {
        return $this->respond(EmergencyContactsResource::make($contacts($request->zone())));
    }
}
