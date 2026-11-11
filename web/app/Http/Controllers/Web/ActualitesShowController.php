<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Content\Queries\ShowNews;
use App\Application\Content\RecordResourceView;
use App\Application\Content\Results\NewsSheet;
use App\Application\Seo\MetaText;
use App\Application\Seo\NewsStructuredData;
use App\Http\Controllers\Controller;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Fiche publique `/actualites/{slug}` (P4-10, prototype blog-details.php) : publique et indexable, gabarit public pour
 * tout le monde (Q18 a). Actualité non publiée ou inconnue → 404 (ShowNews) ; ressource publiée d'un autre type → 301
 * vers `/ressources/{slug}` (symétrique de Q60). Consultation comptée comme les ressources (RecordResourceView). Fil
 * d'Ariane visible Accueil › Actualités › Catégorie › titre, de même source que le BreadcrumbList ; JSON-LD NewsArticle.
 */
final class ActualitesShowController extends Controller
{
    public function __invoke(Request $request, string $slug, ShowNews $show, RecordResourceView $recordView): View|RedirectResponse
    {
        $sheet = $show($slug);
        $article = $sheet->article;

        if (! $sheet->isNews()) {
            return redirect()->to(route('ressources.show', $article->slug), 301);
        }

        $recordView($article, $request);

        $fmt = LocaleFormatter::make();
        $crumbs = array_values(array_filter([
            [__('pages/actualites.title'), route('actualites.index')],
            $sheet->category ? [$sheet->category->label, route('actualites.index', ['cat' => $sheet->category->slug])] : null,
            [$article->title, route('actualites.show', $article->slug)],
        ]));

        return view('public.actualites.show', [
            'article' => $article,
            'category' => $sheet->category,
            'others' => $sheet->others,
            'minutes' => $sheet->minutes,
            'breadcrumb' => [
                ['label' => __('seo.breadcrumb_home'), 'url' => route('home')],
                ...array_map(static fn (array $crumb): array => ['label' => $crumb[0], 'url' => $crumb[1]], $crumbs),
            ],
            'fmt' => $fmt,
            'seo' => self::seo($sheet, $crumbs, $fmt),
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $crumbs
     * @return array<string, mixed> props de `<x-seo>`
     */
    private static function seo(NewsSheet $sheet, array $crumbs, LocaleFormatter $fmt): array
    {
        $article = $sheet->article;
        $canonical = route('actualites.show', $article->slug);
        $description = $article->meta_description
            ?: MetaText::description((string) $article->summary, $article->body, (string) __('pages/actualites.meta_description_filler'));
        $modified = $article->updated_at ? $fmt->isoDate($article->updated_at) : null;

        return [
            'title' => MetaText::title($article->meta_title ?: (string) $article->title),
            'description' => $description,
            'type' => 'article',
            'image' => $article->og_url,
            'imageAlt' => __('pages/actualites.cover_alt', ['title' => $article->title]),
            'breadcrumb' => $crumbs,
            'modified' => $modified,
            'schema' => [NewsStructuredData::for($article, $canonical, [
                'description' => (string) $description,
                'image' => $article->cover_url,
                'published' => $article->published_at ? $fmt->isoDate($article->published_at) : null,
                'modified' => $modified,
                'minutes' => $sheet->minutes,
            ])],
        ];
    }
}
