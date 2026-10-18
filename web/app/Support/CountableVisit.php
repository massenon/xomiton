<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Affichage ou téléchargement compté dans un compteur de contenu (`views_count`, `downloads_count`) : même règle que les
 * thématiques (plan §7, Q58) — `GET` d'un navigateur seulement, ni `HEAD`, ni préchargement, ni robot ou aperçu de lien
 * (App\Support\UserAgent). Préchargement : `Sec-Purpose` (navigateur) ou `Purpose: prefetch`, seul des deux qu'un
 * script peut poser (`Sec-*` est interdit à fetch()) : le service worker (P5-10) l'envoie quand il met en cache.
 * Utilisé par les ressources (P4-03) ; RecordThematiqueView (P4-02) garde sa copie privée jusqu'à sa migration.
 */
final class CountableVisit
{
    private function __construct() {}

    public static function counts(Request $request): bool
    {
        $purpose = strtolower((string) ($request->header('Sec-Purpose') ?? $request->header('Purpose') ?? ''));

        return $request->getMethod() === 'GET'
            && ! str_contains($purpose, 'prefetch')
            && ! UserAgent::isBot($request->userAgent());
    }

    /**
     * Première requête d'une lecture : sans `Range`, ou plage unique qui part de l'octet 0 (`bytes=0-`, `bytes=0-N`).
     * La sonde `bytes=0-1` de Safari et les sauts dans le fichier (`bytes=N-`) ne comptent pas : une lecture = un compte.
     */
    public static function isFirstRange(Request $request): bool
    {
        $range = trim((string) $request->header('Range'));

        if ($range === '') {
            return true;
        }

        if (preg_match('/^bytes=0-(\d*)$/', $range, $matches) !== 1) {
            return false;
        }

        return $matches[1] === '' || (int) $matches[1] > 1;
    }
}
