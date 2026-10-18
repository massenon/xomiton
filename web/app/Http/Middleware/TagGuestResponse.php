<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hors connexion web (P5-10, ADR-26) : `X-Xm-Audience: guest` sur une page web rendue sans compte connecté ni message
 * de la requête précédente (flash, erreurs, saisie renvoyée). C'est la seule que le service worker garde à la
 * consultation (resources/js/sw/rules.js, isGuestResponse) : une page d'une session (zone et commune du profil sur /sos,
 * en-tête membre, /annuaire) n'est jamais servie sans réseau à la personne suivante sur l'appareil.
 */
final class TagGuestResponse
{
    public const HEADER = 'X-Xm-Audience';

    public const GUEST = 'guest';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $request->user() === null && ! $this->hasFlash($request)) {
            $response->headers->set(self::HEADER, self::GUEST);
        }

        return $response;
    }

    private function hasFlash(Request $request): bool
    {
        return $request->hasSession() && (array) $request->session()->get('_flash.old', []) !== [];
    }
}
