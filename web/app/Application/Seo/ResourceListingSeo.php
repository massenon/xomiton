<?php

declare(strict_types=1);

namespace App\Application\Seo;

use App\Application\Content\Results\ResourceListing;
use App\Models\Faq;
use App\Models\Resource;

/**
 * Props `<x-seo>` de la liste `/ressources` (P4-03) : titre et description de lang/fr/seo.php, canonical commune sans
 * paramètres, `noindex` sur une recherche interne (P4-11), fil d'Ariane (catégorie filtrée comprise), CollectionPage +
 * ItemList des ressources affichées et FAQPage de la catégorie filtrée.
 */
final class ResourceListingSeo
{
    private function __construct() {}

    /** @return array<string, mixed> */
    public static function props(ResourceListing $listing): array
    {
        $canonical = route('ressources.index');
        $breadcrumb = [[__('pages/ressources.title'), $canonical]];

        if ($listing->category !== null) {
            $breadcrumb[] = [$listing->category['label'], route('ressources.index', ['cat' => $listing->category['slug']])];
        }

        return [
            'title' => __('seo.pages.ressources.title'),
            'description' => __('seo.pages.ressources.description'),
            'imageAlt' => __('pages/ressources.og_alt_index'),
            // Résultats de recherche interne : jamais indexés (P4-11) ; sinon règle visiteur / membre du composant.
            'index' => $listing->search === '' ? null : false,
            'breadcrumb' => $breadcrumb,
            'schema' => array_values(array_filter([
                StructuredData::collectionPage(
                    $canonical,
                    (string) __('seo.pages.ressources.title'),
                    $listing->total,
                    $listing->resources->map(static fn (Resource $resource): array => [
                        'url' => route('ressources.show', $resource->slug),
                        'name' => (string) $resource->title,
                    ])->values()->all(),
                ),
                StructuredData::faqPage($canonical, $listing->faqs->map(static fn (Faq $faq): array => [
                    'question' => (string) $faq->question,
                    'answer' => (string) $faq->answer,
                ])),
            ])),
        ];
    }
}
