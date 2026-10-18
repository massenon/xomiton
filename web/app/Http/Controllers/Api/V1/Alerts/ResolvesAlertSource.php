<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Alerts;

use App\Enums\AlertSource;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/**
 * Canal d'origine d'une alerte reçue par l'API (`alerts.source`) : `web` pour les pages du site (boîte d'envoi
 * `outbox.js`, Referer ou Origin d'un domaine SANCTUM_STATEFUL_DOMAINS), `app` sinon (Flutter). `sync-offline` est
 * réservé à `sync/push` (P9-06).
 */
trait ResolvesAlertSource
{
    protected function alertSource(Request $request): AlertSource
    {
        return EnsureFrontendRequestsAreStateful::fromFrontend($request) ? AlertSource::Web : AlertSource::App;
    }
}
