<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Seo\SitemapBuilder;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * `/sitemap.xml` (P4-11) : XML produit par SitemapBuilder (mis en cache 1 h), servi en `application/xml`.
 * 404 quand le site n'accepte pas l'indexation (RobotsController::indexable() : hors production ou
 * `seo.allow_indexing` faux), comme robots.txt qui ne l'annonce alors pas.
 */
final class SitemapController extends Controller
{
    public function __invoke(SitemapBuilder $builder): Response
    {
        abort_unless(RobotsController::indexable(), 404);

        return response($builder->xml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
