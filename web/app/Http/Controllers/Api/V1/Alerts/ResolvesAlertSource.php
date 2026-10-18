<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Alerts;

use App\Application\Account\DeviceRegistry;
use App\Enums\AlertSource;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/**
 * Canal d'origine d'une alerte reçue par l'API (`alerts.source`) : `web` pour les pages du site (boîte d'envoi
 * `outbox.js`, Referer ou Origin d'un domaine SANCTUM_STATEFUL_DOMAINS), `app` sinon (Flutter). `sync-offline` est
 * réservé à `sync/push` (P9-06). Appareil d'envoi : en-tête `X-Device-Id` (P6-09).
 */
trait ResolvesAlertSource
{
    protected function alertSource(Request $request): AlertSource
    {
        return EnsureFrontendRequestsAreStateful::fromFrontend($request) ? AlertSource::Web : AlertSource::App;
    }

    /**
     * Appareil d'envoi (`alerts.device_id`, P6-09) : `X-Device-Id` d'un appareil actif du compte connecté, sinon null.
     * Une alerte anonyme n'en garde jamais (AlertRecorder::author, RG-01).
     */
    protected function alertDevice(Request $request): ?int
    {
        $user = $request->user() ?? $request->user('sanctum');

        return app(DeviceRegistry::class)->resolve($user instanceof User ? $user : null, $request);
    }
}
