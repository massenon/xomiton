<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Steering\ContentEvents;
use App\Enums\ContentEventKind;
use App\Http\Controllers\Controller;
use App\Support\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Compteur d'événement de contenu (P10-06, CONTENT_STRATEGY.md §1) : `POST /evenements-contenu/{type}` (balise
 * `navigator.sendBeacon` des pages publiques). Sans session ni cookie, sans corps lu, sans identifiant : le serveur
 * ajoute 1 au compteur du jour du type demandé (liste fermée) ; les robots ne comptent pas, ni une requête qu'un
 * navigateur déclare venir d'un autre site (`Sec-Fetch-Site`, `Origin` : les compteurs ne se gonflent pas depuis une
 * page tierce). Réponse vide 204 dans tous les cas.
 */
final class ContentEventController extends Controller
{
    public function __invoke(Request $request, string $kind, ContentEvents $events): Response
    {
        $type = ContentEventKind::tryFrom($kind);
        abort_if($type === null, 404);

        if (! UserAgent::isBot($request->userAgent()) && self::sameSite($request)) {
            $events->record($type);
        }

        return response()->noContent();
    }

    /** Une requête d'un autre site (indication du navigateur) ne compte pas ; sans indication, elle compte. */
    private static function sameSite(Request $request): bool
    {
        $site = $request->headers->get('Sec-Fetch-Site');

        if ($site !== null && ! in_array($site, ['same-origin', 'none'], true)) {
            return false;
        }

        $origin = $request->headers->get('Origin');

        return $origin === null || parse_url($origin, PHP_URL_HOST) === $request->getHost();
    }
}
