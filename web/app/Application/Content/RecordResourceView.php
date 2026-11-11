<?php

declare(strict_types=1);

namespace App\Application\Content;

use App\Models\Resource;
use App\Support\CountableVisit;
use Illuminate\Http\Request;

/**
 * Compteur de consultations d'une ressource (`resources.views_count`, P4-03) : même règle que les thématiques
 * (CountableVisit, plan §7 Q58) — +1 par affichage `GET` d'un navigateur, ni `HEAD`, ni préchargement, ni robot ou
 * aperçu de lien. Requête directe : `updated_at` (dateModified du JSON-LD, lastmod du sitemap) reste éditorial.
 */
final class RecordResourceView
{
    public function __invoke(Resource $resource, Request $request): void
    {
        if (CountableVisit::counts($request)) {
            Resource::query()->whereKey($resource->getKey())->toBase()->increment('views_count');
        }
    }
}
