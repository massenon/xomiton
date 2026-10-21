<?php

declare(strict_types=1);

namespace App\Application\Admin\Content;

use App\Application\Seo\SitemapBuilder;
use App\Application\Shared\Referentials;

/**
 * Invalidation des caches du site public après une écriture de l'administration (P12-03, P12-05) : plan du site
 * (SitemapBuilder, `lastmod` et nouvelles pages) et référentiels (catégories, puces des listes : Referentials, déjà
 * invalidé par ReferentialObserver à chaque écriture d'une catégorie, rejoué ici pour les autres contenus). Les pages
 * publiques elles-mêmes ne sont pas mises en cache (pas de cache de réponse HTML).
 */
final class PublicContentCache
{
    public function __construct(private readonly Referentials $referentials) {}

    public function flush(): void
    {
        SitemapBuilder::flush();
        $this->referentials->invalidate();
    }
}
