<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Offline\ServiceWorkerScript;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * `GET /sw.js` (P5-10, ADR-26) : service worker servi à la racine pour la portée « / » (le build Vite est sous /build/),
 * sans session ni cookie, pendant la maintenance comme le reste du parcours de crise. `Cache-Control: no-cache` : le
 * navigateur revalide le script à chaque vérification de mise à jour, avec l'ETag (empreinte du contenu) : 304 sans
 * corps tant que rien n'a changé ; `Service-Worker-Allowed: /` explicite la portée. 404 tant que le build n'a pas
 * produit public/build/sw.js (le module d'enregistrement ignore alors l'échec).
 */
final class ServiceWorkerController extends Controller
{
    public function __invoke(Request $request, ServiceWorkerScript $script): Response
    {
        $content = $script->render();

        abort_if($content === null, 404);

        $etag = '"'.hash('xxh128', $content).'"';
        $headers = [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache',
            'ETag' => $etag,
            'X-Robots-Tag' => 'noindex',
        ];

        // Une étiquette affaiblie par un proxy (W/"…") désigne le même contenu.
        $known = array_map(static fn (string $tag): string => (string) preg_replace('#^W/#', '', $tag), $request->getETags());

        if (in_array($etag, $known, true)) {
            return response('', 304, $headers);
        }

        return response($content, 200, $headers);
    }
}
