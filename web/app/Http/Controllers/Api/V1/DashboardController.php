<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Member\MemberDashboard;
use App\Http\Resources\Member\DashboardResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/dashboard (P6-08, simulateur `dashboard`, docs/API.md §11) : tableau de bord de la personne connectée
 * selon son rôle (MemberDashboard) ; `conditional` (ETag, 304) comme toute lecture du groupe `auth:sanctum`, sauf avec
 * la section pair : elle résume des alertes d'autres personnes (type, priorité, commune), donc `no-store` comme les
 * autres lectures sensibles (jamais gardée dans le cache d'un appareil, Q73).
 */
final class DashboardController extends ApiController
{
    public function __invoke(Request $request, MemberDashboard $dashboard): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $result = $dashboard($user);

        return $this->respond(DashboardResource::make($result), headers: $result->peer === null ? [] : self::NO_STORE);
    }
}
