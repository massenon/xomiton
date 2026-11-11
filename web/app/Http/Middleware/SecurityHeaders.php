<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité HTTP (P2-08, P2-13 ; DEVELOPMENT_STANDARDS.md §7 « En-têtes »).
 *
 * - CSP avec nonce par requête : le nonce est généré ici via Vite::useCspNonce() et repris par @vite,
 *
 *   @livewireScripts (Livewire lit Vite::cspNonce()), @routes et les scripts en ligne des gabarits.
 *   'unsafe-eval' est accordé à script-src parce qu'Alpine (Bootstrap + Alpine dans public.js, Alpine embarqué
 *   par Livewire en admin) évalue ses expressions x-data / x-on avec new Function : décision consignée (P2-13).
 *   Aucun 'unsafe-inline' sur script-src. style-src accepte 'unsafe-inline' (SweetAlert2, Quill, Livewire).
 * - HSTS un an avec sous-domaines, en production et seulement sur une requête déjà sécurisée.
 * - Permissions-Policy : la géolocalisation n'est accordée qu'aux pages SOS et signalement.
 *
 * Les en-têtes déjà posés par une réponse (par exemple un contrôleur qui a besoin d'une CSP plus souple) ne sont pas écrasés.
 */
class SecurityHeaders
{
    /** @var list<string> Noms de routes (web) autorisées à demander la géolocalisation. */
    private const GEOLOCATION_ROUTES = ['sos', 'sos.*', 'signaler', 'signaler.*'];

    /** @var list<string> Premier segment d'URL des mêmes pages, si la route n'est pas nommée (repli). */
    private const GEOLOCATION_SEGMENTS = ['sos', 'signaler'];

    public function handle(Request $request, Closure $next): Response
    {
        // Un nonce neuf par requête, partagé par toutes les balises générées pendant le rendu.
        $nonce = Vite::useCspNonce();
        $this->shareNonceWithDebugbar($nonce);

        $response = $next($request);

        $headers = $response->headers;

        // 304 (CacheableGuestPage) : pas de CSP. Un 304 remplace les en-têtes de la copie gardée par le navigateur ; un nonce
        // neuf n'y correspondrait plus aux balises du corps déjà en cache. La copie garde son CSP d'origine.
        if ($response->getStatusCode() !== Response::HTTP_NOT_MODIFIED) {
            $this->setIfAbsent($response, 'Content-Security-Policy', $this->contentSecurityPolicy($nonce));
        }
        $this->setIfAbsent($response, 'X-Content-Type-Options', 'nosniff');
        $this->setIfAbsent($response, 'X-Frame-Options', 'DENY');
        $this->setIfAbsent($response, 'Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->setIfAbsent($response, 'Permissions-Policy', $this->permissionsPolicy($request));

        if (app()->isProduction() && $request->isSecure()) {
            $maxAge = (int) config('xomiton.security.hsts_max_age', 31536000);
            $this->setIfAbsent($response, 'Strict-Transport-Security', "max-age={$maxAge}; includeSubDomains");
        }

        // Ne pas annoncer la pile serveur.
        $headers->remove('X-Powered-By');

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        $scriptSources = ["'self'", "'nonce-{$nonce}'"];

        if ((bool) config('xomiton.security.csp_unsafe_eval', true)) {
            $scriptSources[] = "'unsafe-eval'";
        }

        $styleSources = ["'self'", "'unsafe-inline'"];
        $connectSources = ["'self'"];

        $fontSources = $this->debugbarEnabled() ? ["'self'", 'data:'] : ["'self'"];
        $imgSources = ["'self'", 'data:', 'blob:'];

        // Serveur de développement Vite (fichier public/hot) : jamais en production, seulement en local.
        foreach ($this->viteDevOrigins() as $origin) {
            $scriptSources[] = $origin;
            $styleSources[] = $origin;
            $fontSources[] = $origin;
            $imgSources[] = $origin;
            $connectSources[] = $origin;
            $connectSources[] = preg_replace('#^http#', 'ws', $origin) ?? $origin;
        }

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => $scriptSources,
            'style-src' => $styleSources,
            'img-src' => $imgSources,
            'font-src' => $fontSources,
            'connect-src' => $connectSources,
            'object-src' => ["'none'"],
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
        ];

        return collect($directives)
            ->map(static fn (array $sources, string $name): string => $name.' '.implode(' ', $sources))
            ->implode('; ');
    }

    /**
     * Laravel Debugbar (environnement local uniquement) injecte des scripts en ligne : il reçoit le nonce
     * de la requête pour ne jamais provoquer de violation CSP dans la console (critère P2-13).
     */
    private function shareNonceWithDebugbar(string $nonce): void
    {
        if (! $this->debugbarEnabled()) {
            return;
        }

        app('debugbar')->getJavascriptRenderer()->setCspNonce($nonce);
    }

    private function debugbarEnabled(): bool
    {
        if (! app()->bound('debugbar')) {
            return false;
        }

        $debugbar = app('debugbar');

        return method_exists($debugbar, 'isEnabled') && $debugbar->isEnabled();
    }

    private function permissionsPolicy(Request $request): string
    {
        $geolocation = $this->allowsGeolocation($request) ? '(self)' : '()';

        return "geolocation={$geolocation}, camera=(), microphone=()";
    }

    private function allowsGeolocation(Request $request): bool
    {
        if ($request->route() !== null && $request->routeIs(...self::GEOLOCATION_ROUTES)) {
            return true;
        }

        return in_array($request->segment(1), self::GEOLOCATION_SEGMENTS, true);
    }

    /**
     * Origines du serveur Vite en développement : celle de public/hot plus localhost et [::1] sur le même port.
     * Vide hors environnement local ou sans public/hot.
     *
     * @return list<string>
     */
    private function viteDevOrigins(): array
    {
        if (! app()->isLocal() || ! Vite::isRunningHot()) {
            return [];
        }

        $parts = parse_url(trim((string) file_get_contents(Vite::hotFile())));

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return [];
        }

        $port = (int) ($parts['port'] ?? 5173);

        return array_values(array_unique([
            $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : ''),
            "http://localhost:{$port}",
            "http://[::1]:{$port}",
        ]));
    }

    private function setIfAbsent(Response $response, string $name, string $value): void
    {
        if (! $response->headers->has($name)) {
            $response->headers->set($name, $value);
        }
    }
}
