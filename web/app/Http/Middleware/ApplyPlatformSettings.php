<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Paramètres de la plateforme qui pilotent la configuration d'exécution (P13-04) : `session_minutes` → durée de session
 * (`session.lifetime`, lue par StartSession et par EnforceIdleTimeout, Q30) et `timezone` → fuseau d'affichage des
 * heures (`xomiton.display_timezone`, P13-06). Posé **avant** StartSession (middleware global) ; une base indisponible
 * laisse les valeurs de l'environnement. La commande Artisan garde les valeurs de l'environnement.
 */
final class ApplyPlatformSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (Settings::has('session_minutes')) {
                $minutes = Settings::int('session_minutes');

                if ($minutes >= 5 && $minutes <= 1440) {
                    config(['session.lifetime' => $minutes]);
                }
            }

            if (Settings::has('timezone')) {
                $timezone = Settings::string('timezone');

                if ($timezone !== '' && in_array($timezone, timezone_identifiers_list(), true)) {
                    config(['xomiton.display_timezone' => $timezone]);
                }
            }
        } catch (Throwable) {
            // Base ou cache indisponible : valeurs de l'environnement.
        }

        return $next($request);
    }
}
