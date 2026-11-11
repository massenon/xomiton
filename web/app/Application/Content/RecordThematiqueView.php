<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Models\Thematique;
use App\Support\UserAgent;
use Illuminate\Http\Request;

/**
 * Compteur de consultations d'une fiche (`thematiques.views_count`, P4-02). Décision par défaut (plan §7, Q58) : +1 par
 * affichage `GET` d'un navigateur — ni `HEAD`, ni préchargement (`Sec-Purpose` / `Purpose: prefetch`), ni robot ou
 * aperçu de lien (App\Support\UserAgent). Requête directe pour ne pas toucher `updated_at`, dont dépendent
 * `dateModified` (JSON-LD) et le `lastmod` du sitemap — seule une vraie modification éditoriale doit les changer.
 */
final class RecordThematiqueView
{
    public function __invoke(Thematique $thematique, Request $request): void
    {
        if (! self::counts($request)) {
            return;
        }

        Thematique::query()->whereKey($thematique->getKey())->toBase()->increment('views_count');
    }

    private static function counts(Request $request): bool
    {
        $purpose = strtolower((string) ($request->header('Sec-Purpose') ?? $request->header('Purpose') ?? ''));

        return $request->getMethod() === 'GET'
            && ! str_contains($purpose, 'prefetch')
            && ! UserAgent::isBot($request->userAgent());
    }
}
