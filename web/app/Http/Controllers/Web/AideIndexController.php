<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Content\Queries\ListHelpArticles;
use App\Application\Content\Results\HelpCenterListing;
use App\Application\Seo\StructuredData;
use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Support\ListingQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Centre d'aide `/aide` (P4-05, prototype help.php) : public et indexable, gabarit public pour tout le monde (Q18 a),
 * vous. Filtres GET `cat` (slug de catégorie `faq`) et `q` assainis par ListingQuery ; `cat` mal formé, inconnu ou sans
 * question → 404 ; `q` → noindex ; canonical commune sans paramètres (`<x-seo>`).
 * JSON-LD : FAQPage sur la page sans filtre seulement, sans les questions réellement balisées par l'accueil (même requête
 * que HomeController : `onHome()->ordered()->limit(FAQ_LIMIT)`, une question cochée au-delà reste balisée ici) — une
 * seule instance balisée par question sur le site (consigne Google) ; BreadcrumbList et fil visible construits ici, une
 * seule source.
 */
final class AideIndexController extends Controller
{
    public function __invoke(Request $request, ListHelpArticles $list): View
    {
        $slug = ListingQuery::slug($request->query('cat'));
        abort_if($slug === false, 404);

        $listing = $list($slug, ListingQuery::search($request->query('q')));
        abort_if($listing === null, 404);

        $crumbs = [[__('pages/aide.title'), route('aide.index')]];

        if ($listing->category !== null) {
            $crumbs[] = [$listing->category['label'], route('aide.index', ['cat' => $listing->category['slug']])];
        }

        return view('public.aide.index', [
            'listing' => $listing,
            'categories' => $listing->categories,
            'category' => $listing->category,
            'query' => $listing->search,
            'faqs' => $listing->faqs,
            'breadcrumb' => [
                ['label' => __('seo.breadcrumb_home'), 'url' => route('home')],
                ...array_map(static fn (array $crumb): array => ['label' => $crumb[0], 'url' => $crumb[1]], $crumbs),
            ],
            'seo' => $this->seo($listing, $crumbs),
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $crumbs
     * @return array<string, mixed> props de `<x-seo>`
     */
    private function seo(HelpCenterListing $listing, array $crumbs): array
    {
        $canonical = route('aide.index');
        $schema = null;

        if (! $listing->isFiltered()) {
            $onHome = Faq::query()->onHome()->ordered()->limit(HomeController::FAQ_LIMIT)->pluck('id')->map(intval(...))->all();
            $schema = StructuredData::faqPage($canonical, $listing->faqs
                ->reject(static fn (Faq $faq): bool => in_array((int) $faq->getKey(), $onHome, true))
                ->map(static fn (Faq $faq): array => ['question' => (string) $faq->question, 'answer' => (string) $faq->answer]));
        }

        return [
            'title' => __('seo.pages.aide.title'),
            'description' => __('seo.pages.aide.description'),
            'imageAlt' => __('pages/aide.og_alt'),
            // Résultats de recherche interne : jamais indexés (P4-11) ; sinon règle visiteur / membre du composant.
            'index' => $listing->search === '' ? null : false,
            'modified' => $listing->updatedAt,
            'breadcrumb' => $crumbs,
            'schema' => array_values(array_filter([$schema])),
        ];
    }
}
