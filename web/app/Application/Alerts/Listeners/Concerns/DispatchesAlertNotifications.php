<?php

declare(strict_types=1);

namespace App\Application\Alerts\Listeners\Concerns;

use App\Models\Alert;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Écouteurs d'alerte (P5-03) : synchrones (jamais `ShouldQueue`, D7), ils rechargent l'alerte par son identifiant
 * (événements de P3-08 : identifiants seulement) et ne font jamais échouer la requête ni le cron qui les émet : une
 * erreur est journalisée en `critical` (sans message brut, qui pourrait contenir un numéro) et les lignes déjà
 * planifiées restent en attente pour `notifications:retry`.
 */
trait DispatchesAlertNotifications
{
    /**
     * @param  list<string>  $with
     */
    protected function loadAlert(int $alertId, array $with = ['zone', 'commune', 'user.profile', 'routedContact.user']): ?Alert
    {
        $alert = Alert::query()->with($with)->find($alertId);

        if ($alert === null) {
            Log::warning('alerts.dispatch_missing_alert', ['alert' => $alertId, 'listener' => class_basename(static::class)]);
        }

        return $alert;
    }

    protected function guarded(Alert $alert, Closure $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            Log::critical('alerts.dispatch_failed', [
                'ulid' => $alert->ulid,
                'listener' => class_basename(static::class),
                'exception' => $e::class,
            ]);

            report($e);
        }
    }
}
