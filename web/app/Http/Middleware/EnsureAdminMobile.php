<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Member\AccountSpaces;
use App\Application\Member\MobileSpaces;
use App\Domain\Account\Exceptions\SpaceDisabled;
use App\Models\User;
use App\Support\Audit;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde du groupe `admin/*` de l'API (P15-16, application mobile unique) : compte **actif** détenant
 * `admin_mobile.access` (admin et super-admin ; retirable), sinon 403 `forbidden` neutre (403 `space_disabled` si l'interrupteur serveur de l'espace est coupé, MobileSpaces) — un membre, un·e pair·e ou un·e
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

        // Interrupteur serveur de l'espace Admin (P15-22) : coupé, l'API `admin/*` répond 403 `space_disabled`.
        if (! MobileSpaces::enabled(AccountSpaces::ADMIN)) {
            throw new SpaceDisabled;
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
