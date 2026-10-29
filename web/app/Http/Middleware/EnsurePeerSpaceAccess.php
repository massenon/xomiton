<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Cases\PeerSpace;
use App\Application\Member\AccountSpaces;
use App\Application\Member\MobileSpaces;
use App\Domain\Account\Exceptions\SpaceDisabled;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde de l'API `pair/` (P9-02) : compte actif détenant `peer_space.access` (App\Application\Cases\PeerSpace, RG-03),
 * sinon 403 `forbidden` neutre (ApiExceptionRenderer) ; 403 `space_disabled` pour l'application mobile quand l'interrupteur serveur
 * de l'espace est coupé (MobileSpaces). À poser après `auth:sanctum`.
 */
final class EnsurePeerSpaceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! PeerSpace::allows($user)) {
            throw new AuthorizationException;
        }

        // Interrupteur serveur de l'espace Pair (P15-22) : coupé, l'application mobile (jeton Bearer) reçoit 403 `space_disabled` ;
        // le site web (session) n'est jamais touché.
        if ($request->bearerToken() !== null && ! MobileSpaces::enabled(AccountSpaces::PEER)) {
            throw new SpaceDisabled;
        }

        return $next($request);
    }
}
