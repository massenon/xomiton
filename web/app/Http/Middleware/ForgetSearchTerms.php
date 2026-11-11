<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Recherche membre (P8-13) : le terme cherché ne laisse **aucune trace côté serveur**. La session (base `sessions` sur
 * l'hébergement mutualisé, D7) retient sinon l'adresse complète, `?q=` compris : StartSession enregistre celle de chaque
 * page GET chargée hors XHR (`_previous.url`, après la réponse) et `auth` celle d'une visite sans compte
 * (`url.intended`, avant la redirection vers la connexion). Ce middleware retire donc la chaîne de requête de la
 * requête courante :
 *  - **après** la réponse quand la personne est connectée (la page et l'API ont lu le terme ; StartSession ne retient
 *    plus que le chemin) ;
 *  - **avant** `auth` sinon (la visite est refusée : la connexion ramènera vers le chemin seul).
 * À placer avant `auth` / `auth:sanctum` dans la liste de la route (priorité : avant EnsureNotInMaintenance, donc
 * aussi pour un 503 de maintenance ou un 429) ; paramètre : la garde à consulter (`web` par défaut, `sanctum` pour
 * l'API). Toute réponse porte `Referrer-Policy: strict-origin` (SecurityHeaders ne l'écrase pas) : les requêtes de même
 * origine émises depuis `/recherche?q=` (images, API, liens) n'envoient que l'origine dans l'en-tête Referer, que les
 * journaux d'accès du serveur web recopient (Q84 ; visites Inertia : resources/js/search/referrer.ts). La ligne de
 * requête de ces journaux reste hors de l'application.
 */
final class ForgetSearchTerms
{
    /** Origine seule dans l'en-tête Referer (et non `no-referrer` : Sanctum reconnaît l'espace Vue à son Referer). */
    public const REFERRER_POLICY = 'strict-origin';

    public function handle(Request $request, Closure $next, string $guard = 'web'): Response
    {
        if (Auth::guard($guard)->guest()) {
            self::forget($request);

            return self::withReferrerPolicy($next($request));
        }

        $response = $next($request);
        self::forget($request);

        return self::withReferrerPolicy($response);
    }

    /** Requête sans chaîne de requête : `fullUrl()`, `url()->full()` et `input()` ne portent plus le terme. */
    public static function forget(Request $request): void
    {
        $request->server->remove('QUERY_STRING');
        $request->query->replace([]);
        $request->request->remove('q');
    }

    private static function withReferrerPolicy(Response $response): Response
    {
        $response->headers->set('Referrer-Policy', self::REFERRER_POLICY);

        return $response;
    }
}
