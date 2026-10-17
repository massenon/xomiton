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

        $this->setIfAbsent($response, 'Content-Security-Policy', $this->contentSecurityPolicy($nonce));
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

        // Serveur de développement Vite (fichier public/hot) : jamais présent en production.
        if ($devServer = $this->viteDevServer()) {
            $scriptSources[] = $devServer;
            $styleSources[] = $devServer;
            $connectSources[] = $devServer;
            $connectSources[] = preg_replace('#^http#', 'ws', $devServer) ?? $devServer;
        }

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => $scriptSources,
            'style-src' => $styleSources,
            'img-src' => ["'self'", 'data:', 'blob:'],
            'font-src' => $this->debugbarEnabled() ? ["'self'", 'data:'] : ["'self'"],
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
     * Origine du serveur Vite en mode développement (contenu de public/hot), sinon null.
     */
    private function viteDevServer(): ?string
    {
        if (app()->isProduction() || ! Vite::isRunningHot()) {
            return null;
        }

        $url = trim((string) file_get_contents(Vite::hotFile()));
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    private function setIfAbsent(Response $response, string $name, string $value): void
    {
        if (! $response->headers->has($name)) {
            $response->headers->set($name, $value);
        }
    }
}
