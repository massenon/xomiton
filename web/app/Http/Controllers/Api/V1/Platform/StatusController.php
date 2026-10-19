<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Application\Platform\MaintenanceStatus;
use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;

/**
 * `GET /api/v1/status` (P13-08, docs/API.md §5) : état public de la plateforme pour la page de maintenance (vérification
 * de la réouverture toutes les 60 s) et l'application mobile (P15). Public, sans compte, servi pendant la maintenance
 * (liste blanche EnsureNotInMaintenance) ; état mis en cache 15 s côté serveur, `Cache-Control: public, max-age=15`.
 * Route servie sans session (routes/api.php : sans EnsureFrontendRequestsAreStateful) : aucun Set-Cookie, même quand la
 * page de maintenance l'appelle avec un Referer de même origine — un cache partagé ne garde jamais une session.
 */
final class StatusController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        $status = MaintenanceStatus::publicStatus();
        $headers = ['Cache-Control' => 'public, max-age='.MaintenanceStatus::STATUS_CACHE_SECONDS];

        if ($status['maintenance']['active'] === true && is_int($status['maintenance']['retry_after'])) {
            $headers['Retry-After'] = (string) $status['maintenance']['retry_after'];
        }

        return $this->data($status)->withHeaders($headers);
    }
}
