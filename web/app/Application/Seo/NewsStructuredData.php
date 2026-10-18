<?php

declare(strict_types=1);

namespace App\Application\Seo;

use App\Models\Resource;

/**
 * Nœud JSON-LD `NewsArticle` de la fiche `/actualites/{slug}` (P4-10 ; prototype blog-details.php l.17-19 : Article).
 * Mêmes propriétés que StructuredData::article() (rattaché au nœud de page `#page`, auteur et éditeur = l'organisation
 * `#org` : la signature affichée est « Équipe Xomiton », aucun nom de personne n'est publié), type précisé en
 * `NewsArticle` pour une actualité datée. Le BreadcrumbList vient de `<x-seo>` (fil d'Ariane visible de même source).
 */
final class NewsStructuredData
{
    private function __construct() {}

    /**
     * @param  array{description: string, image: string, published: string|null, modified: string|null, minutes: int}  $data
     * @return array<string, mixed>
     */
    public static function for(Resource $article, string $canonical, array $data): array
    {
        $node = StructuredData::article($canonical, [
            'headline' => (string) $article->title,
            'description' => $data['description'],
            'section' => $article->category?->label,
            'minutes' => $data['minutes'],
            'published' => $data['published'],
            'modified' => $data['modified'],
            'image' => $data['image'],
        ]);

        return ['@type' => 'NewsArticle'] + $node;
    }
}
