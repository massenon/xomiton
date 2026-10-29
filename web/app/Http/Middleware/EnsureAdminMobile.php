<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Audit;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde du groupe `admin/*` de l'API (P15-16, application mobile unique) : compte **actif** détenant
 * `admin_mobile.access` (admin et super-admin ; retirable), sinon 403 `forbidden` neutre — un membre, un·e pair·e ou un·e
 * modérateur·rice n'a jamais accès, quel que soit son jeton (le jeton ne porte pas de capacité figée : les rôles sont
 * cumulables et changent sans reconnexion, la permission lue à chaque requête est la seule vérité). Les actions
 * d'audit de la requête portent `source=mobile` ; les réponses ne sont jamais gardées (`no-store, private`). À poser après `auth:sanctum`.
 */
final class EnsureAdminMobile
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isActive() || ! $user->can('admin_mobile.access')) {
            throw new AuthorizationException;
        }

        app()->instance(Audit::SOURCE_BINDING, 'mobile');

        try {
            $response = $next($request);
        } finally {
            app()->forgetInstance(Audit::SOURCE_BINDING);
        }

        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
