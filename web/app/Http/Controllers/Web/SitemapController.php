<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Seo\SitemapBuilder;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * `/sitemap.xml` (P4-11) : XML produit par SitemapBuilder (mis en cache 1 h), servi en `application/xml`.
 */
final class SitemapController extends Controller
{
    public function __invoke(SitemapBuilder $builder): Response
    {
        return response($builder->xml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
