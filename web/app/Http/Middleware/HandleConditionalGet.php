<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lectures conditionnelles génériques de /api/v1 (P6-01, docs/API.md §1.6) — alias `conditional`, posé sur le groupe
 * `auth:sanctum` de routes/api.php. Sur un GET / HEAD 200 dont le corps est connu : `ETag` fort dérivé du corps
 * (sauf si la réponse en porte déjà un), `Cache-Control: private, no-cache` (le client garde la réponse mais la
 * revalide à chaque fois ; aucun cache partagé ne la garde), `Vary: Authorization, Cookie` ; `If-None-Match` égal → 304
 * sans corps. Le polling (cloche toutes les 30 s, Flutter) ne retélécharge ainsi rien quand rien n'a changé.
 *
 * Comparaison tolérante : Apache `mod_deflate` (cPanel) suffixe l'ETag fort en `"…-gzip"` (`-br` pour Brotli) et
 * le client renvoie cette forme ; suffixe et préfixe faible `W/` sont ignorés, sinon le 304 ne partirait jamais.
 *
 * Ignorés : autres méthodes et statuts, fichiers et flux, réponses `no-store` (SOS, suivi, `me`, `me/profile`,
 * `me/security` : jamais gardées, même dans le cache privé d'un appareil partagé).
 * Les référentiels publics gardent leur cache public (`cache.headers:public;max_age=…;etag`).
 */
final class HandleConditionalGet
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $request->isMethodCacheable() || $response->getStatusCode() !== 200) {
            return $response;
        }

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        if ($response->headers->hasCacheControlDirective('no-store')) {
            return $response;
        }

        $content = $response->getContent();

        if ($response->getEtag() === null) {
            if (! is_string($content) || $content === '') {
                return $response;
            }

            $response->setEtag(self::etagFor($content));
        }

        if (! $response->headers->hasCacheControlDirective('public')) {
            $response->setPrivate();
            $response->headers->addCacheControlDirective('no-cache');
        }

        $response->setVary(['Authorization', 'Cookie'], false);

        if (self::matches($request->headers->get('If-None-Match'), (string) $response->getEtag())) {
            $response->setNotModified();
        }

        return $response;
    }

    /**
     * `If-None-Match` (liste ou `*`) désigne-t-il cet ETag, au suffixe de compression d'Apache et au `W/` près ?
     */
    public static function matches(?string $ifNoneMatch, string $etag): bool
    {
        if ($ifNoneMatch === null || trim($ifNoneMatch) === '' || $etag === '') {
            return false;
        }

        $current = self::normalize($etag);

        foreach (explode(',', $ifNoneMatch) as $candidate) {
            $candidate = trim($candidate);

            if ($candidate === '*' || ($candidate !== '' && self::normalize($candidate) === $current)) {
                return true;
            }
        }

        return false;
    }

    private static function normalize(string $etag): string
    {
        $value = trim($etag);
        $value = str_starts_with($value, 'W/') ? substr($value, 2) : $value;
        $value = trim($value, '"');

        return (string) preg_replace('/-(gzip|br|deflate)$/', '', $value);
    }

    /** Empreinte du corps (non cryptographique : seule l'égalité compte). */
    public static function etagFor(string $content): string
    {
        return hash('xxh128', $content);
    }
}
