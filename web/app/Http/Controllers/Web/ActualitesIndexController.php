<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Content\Queries\ListNews;
use App\Application\Content\Queries\UpcomingEvents;
use App\Application\Content\Results\NewsListing;
use App\Application\Seo\StructuredData;
use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Support\ListingQuery;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Liste publique `/actualites` (P4-10, prototype blog.php) : publique et indexable, gabarit public pour tout le monde
 * (Q18 a). Filtres GET `cat` (slug) et `n` (« Charger plus ») assainis par ListingQuery ; `cat` mal formé ou inconnu
 * → 404. SEO : titre et description de lang/fr/seo.php, canonical sans paramètres (`<x-seo>`), fil d'Ariane avec la
 * catégorie filtrée, CollectionPage + ItemList des actualités affichées. « Prochains événements » : membres seulement (Q35).
 */
final class ActualitesIndexController extends Controller
{
    public function __invoke(Request $request, ListNews $list, UpcomingEvents $events): View
    {
        $step = max(1, (int) config('xomiton.content.actualites_per_load', 6));
        $max = max($step, (int) config('xomiton.content.max_per_load', 300));
        $limit = ListingQuery::limit($request->query('n'), $step, $max);
        $categorySlug = ListingQuery::slug($request->query('cat'));
        abort_if($categorySlug === false, 404);

        $listing = $list($categorySlug, $limit);
        abort_if($listing === null, 404);

        $nextUrl = $listing->hasMore() && $limit < $max
            ? route('actualites.index', array_filter(['cat' => $listing->category['slug'] ?? null, 'n' => $limit + $step])).'#liste'
            : null;

        return view('public.actualites.index', [
            'categories' => $listing->categories,
            'category' => $listing->category,
            'articles' => $listing->articles,
            'total' => $listing->total,
            'totalAll' => $listing->totalAll,
            'shown' => $listing->shown(),
            'hasMore' => $listing->hasMore(),
            'step' => $step,
            'nextUrl' => $nextUrl,
            'events' => $request->user() !== null ? $events() : null,
            'fmt' => LocaleFormatter::make(),
            'seo' => self::seo($listing),
        ]);
    }

    /** @return array<string, mixed> props de `<x-seo>` */
    private static function seo(NewsListing $listing): array
    {
        $canonical = route('actualites.index');
        $breadcrumb = [[__('pages/actualites.title'), $canonical]];

        if ($listing->category !== null) {
            $breadcrumb[] = [$listing->category['label'], route('actualites.index', ['cat' => $listing->category['slug']])];
        }

        $first = $listing->articles->first();

        return [
            'title' => __('seo.pages.actualites.title'),
            'description' => __('seo.pages.actualites.description'),
            // Prototype l.9 : couverture de la première actualité (alt qui la décrit, comme sur la fiche), sinon l'image
            // par défaut du site et son alt générique.
            'image' => $first?->cover_url ?? 'covers/og-default.jpg',
            'imageAlt' => $first !== null
                ? __('pages/actualites.cover_alt', ['title' => $first->title])
                : __('pages/actualites.og_alt_index'),
            'breadcrumb' => $breadcrumb,
            'schema' => [StructuredData::collectionPage(
                $canonical,
                (string) __('seo.pages.actualites.title'),
                $listing->total,
                $listing->articles->map(static fn (Resource $article): array => [
                    'url' => route('actualites.show', $article->slug),
                    'name' => (string) $article->title,
                ])->values()->all(),
            )],
        ];
    }
}
