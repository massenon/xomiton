<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages publiques en lecture seule servies à une personne SANS session (alias `guest.page`, performance : docs/PERFORMANCE.md §11).
 *
 * Avant ce middleware, chaque visite d'une page publique ouvrait une session en base (une lecture, une insertion, une
 * écriture), posait deux cookies (`xomiton-session`, `XSRF-TOKEN`) et répondait `Cache-Control: no-cache, private` :
 * rien n'était réutilisable par le navigateur, par le service worker ni par un cache partagé, et chaque robot
 * ajoutait une ligne à la table des sessions.
 *
 * Pour une requête GET / HEAD qui ne porte ni cookie de session, ni cookie « se souvenir de moi », ni `Authorization` :
 *  - la session de la requête est en mémoire (pilote `array`, posé avant StartSession : aucune requête SQL de session) ;
 *  - aucun cookie de session ni `XSRF-TOKEN` n'est posé ;
 *  - une réponse 200 reçoit `ETag` (empreinte du corps SANS le nonce CSP, tolérante au suffixe `-gzip` d'Apache),
 *    `Cache-Control: public, max-age, stale-while-revalidate` (xomiton.http_cache) et `Vary: Cookie` ; un `If-None-Match`
 *    égal donne 304 sans corps. Le nonce CSP reste aléatoire à chaque réponse ; le 304 ne porte PAS d'en-tête
 *    Content-Security-Policy (SecurityHeaders), pour que la copie gardée par le navigateur conserve celui qui correspond
 *    aux nonces de son corps (un 304 remplace les en-têtes qu'il porte).
 *
 * Toute autre requête (session ouverte, connectée, `Authorization`) suit le parcours normal, intact : session réelle,
 * `no-cache, private`, jamais partagée. Une personne connectée dont le cookie arrive ne voit donc jamais la variante
 * « invité », et `Vary: Cookie` empêche un cache de mélanger les deux.
 * Les pages à formulaire protégé par jeton CSRF (aide/feedback, signalement, SOS, connexion…) ne portent PAS cet alias.
 */
final class CacheableGuestPage
{
    /** Attribut de requête posé sur une page servie sans session (le pied de page n'y affiche pas de formulaire POST). */
    public const ATTRIBUTE = 'xm.stateless';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isAnonymous($request)) {
            return $next($request);
        }

        // Avant StartSession (priorité de bootstrap/app.php) : le gestionnaire est choisi à la première lecture.
        config(['session.driver' => 'array']);
        $request->attributes->set(self::ATTRIBUTE, true);

        /** @var Response $response */
        $response = $next($request);

        $this->forgetCookies($response);

        if ($response->getStatusCode() !== 200 || ! $request->isMethodCacheable()) {
            return $response;
        }

        $response->setPublic();
        $response->setMaxAge(max(0, (int) config('xomiton.http_cache.guest_max_age', 60)));
        $response->headers->addCacheControlDirective('stale-while-revalidate', (string) max(0, (int) config('xomiton.http_cache.guest_swr', 300)));
        $response->setVary(['Cookie'], false);

        // HEAD : Symfony a déjà vidé le corps (Response::prepare), donc pas d'ETag à calculer.
        $content = $response->getContent();

        if (is_string($content) && $content !== '') {
            // L'empreinte ignore le nonce CSP (différent à chaque requête, sinon aucun 304 ne serait jamais possible).
            $response->setEtag(HandleConditionalGet::etagFor(str_replace((string) Vite::cspNonce(), '', $content)));

            if (HandleConditionalGet::matches($request->headers->get('If-None-Match'), (string) $response->getEtag())) {
                $response->setNotModified();
            }
        }

        return $response;
    }

    /** Requête sans aucun signe de compte ni de session précédente. */
    private function isAnonymous(Request $request): bool
    {
        if (! $request->isMethodCacheable() || $request->headers->has('Authorization')) {
            return false;
        }

        return ! $request->cookies->has((string) config('session.cookie'))
            && ! $request->cookies->has(Auth::guard('web')->getRecallerName());
    }

    private function forgetCookies(Response $response): void
    {
        $names = [(string) config('session.cookie'), 'XSRF-TOKEN'];

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie instanceof Cookie && in_array($cookie->getName(), $names, true)) {
                $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
            }
        }
    }
}
