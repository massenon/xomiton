<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Application\Platform\Updates\AppVersionInfo;
use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/v1/app/version` (P13-02, docs/API.md §5) : version courante de la plateforme et version minimale de
 * l'application mobile ; `?version=1.4.2` (ou l'en-tête `X-App-Version`) ajoute `update_available` et `update_required`
 * (mise à jour obligatoire sous le minimum). Public, sans compte ni session (routes/api.php : comme `status`, aucun
 * Set-Cookie), `Cache-Control: public, max-age=300` ; une version mal formée est ignorée, jamais une erreur.
 */
final class AppVersionController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $client = $request->query('version', $request->header('X-App-Version'));

        return $this->data(AppVersionInfo::for(is_string($client) ? trim($client) : null))
            ->withHeaders(['Cache-Control' => 'public, max-age=300', 'Vary' => 'X-App-Version']);
    }
}
