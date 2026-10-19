<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Cases\PeerSpace;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde de l'API `pair/` (P9-02) : compte actif détenant `peer_space.access` (App\Application\Cases\PeerSpace, RG-03),
 * sinon 403 `forbidden` neutre (ApiExceptionRenderer). À poser après `auth:sanctum`.
 */
final class EnsurePeerSpaceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! PeerSpace::allows($user)) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
