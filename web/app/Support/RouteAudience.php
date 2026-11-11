<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

/**
 * Groupe de la route courante (ADR-31) : authentifié (`auth`, `auth:<garde>`) ou public. Lu par le middleware `idempotent`
 * (jamais de lien au compte sur une route publique de crise, RG-01 / RG-02) et par App\Exceptions\ApiExceptionRenderer
 * (419 au vous sur le groupe public, au tu sur l'espace connecté : VOICE_AND_TONE.md §3).
 */
final class RouteAudience
{
    private function __construct() {}

    public static function requiresAuthentication(Request $request): bool
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            if ($middleware === 'auth' || str_starts_with($middleware, 'auth:') || str_starts_with($middleware, Authenticate::class)) {
                return true;
            }
        }

        return false;
    }
}
