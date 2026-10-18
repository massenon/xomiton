<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Content\Queries\ListLaws;
use App\Application\Seo\StructuredData;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Page `/lois` (P4-04, prototype lois-benin.php) : publique et indexable (Q19 a), gabarit public pour tout le monde
 * (Q18 a), vous. Textes publiés de la table `laws` (ListLaws) ; recherche, catégorie et « Lire en version simple »
 * dans le navigateur (resources/js/public/lois.js, préférence locale `xm_law_simple`) : sans JavaScript, tous les
 * textes restent lisibles dans leur version juridique. Une seule adresse, canonical sans paramètres.
 * SEO : CollectionPage + ItemList des textes (ancre de chaque texte), `modified` = dernière mise à jour d'un texte.
 */
final class LoisController extends Controller
{
    public function __invoke(ListLaws $list): View
    {
        $listing = $list();
        $canonical = route('lois');

        return view('public.lois.index', [
            'laws' => $listing->laws,
            'categories' => $listing->categories,
            'seo' => [
                'title' => __('seo.pages.lois.title'),
                'description' => __('seo.pages.lois.description'),
                'image' => 'covers/ccc-lois.jpg',
                'imageAlt' => __('pages/lois.og_alt'),
                'modified' => $listing->updatedAt,
                'breadcrumb' => [[__('pages/lois.title'), $canonical]],
                'schema' => [StructuredData::collectionPage(
                    $canonical,
                    (string) __('seo.pages.lois.title'),
                    count($listing->laws),
                    array_map(static fn (array $law): array => [
                        'url' => $canonical.'#texte-'.$law['slug'],
                        'name' => $law['title'],
                    ], $listing->laws),
                )],
            ],
        ]);
    }
}
