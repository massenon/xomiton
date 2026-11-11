<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Une même adresse, deux publics (P8-04, Q35 a : `/evenements`, `/evenements/{ulid}`) : connecté·e, la requête passe par
 * HandleInertiaRequests (gabarit membre Vue, props partagées, `noindex`, `no-store`) ; visiteur·se, elle reste une page
 * Blade publique indexable, sans aucune prop de session. Une visite Inertia arrivée sans session (session expirée en
 * naviguant dans l'espace membre) recharge la page entière (409 + `X-Inertia-Location`) : le navigateur reçoit la page
 * publique au lieu d'un HTML glissé dans la fenêtre Inertia.
 */
final class InertiaForMembers
{
    public function __construct(private readonly HandleInertiaRequests $inertia) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            return $this->inertia->handle($request, $next);
        }

        if ($request->header('X-Inertia') !== null) {
            return Inertia::location($request->fullUrl());
        }

        return $next($request);
    }
}
