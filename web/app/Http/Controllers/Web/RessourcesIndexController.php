<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Content\OfflineLibrary;
use App\Application\Content\Queries\ListResources;
use App\Application\Seo\ResourceListingSeo;
use App\Enums\ResourceKind;
use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Support\ListingQuery;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

/**
 * Liste publique `/ressources` (P4-03, prototype ressources.php) : publique et indexable (Q19 a), gabarit public pour
 * tout le monde (Q18). Filtres GET `q`, `type`, `cat`, `n` assainis ici (ListingQuery) ; `type` inconnu ou `article`,
 * `cat` mal formé ou inconnu → 404 ; SEO (ResourceListingSeo) : `q` → noindex, canonical commune sans paramètres.
 */
final class RessourcesIndexController extends Controller
{
    public function __invoke(Request $request, ListResources $list): View
    {
        $step = max(1, (int) config('xomiton.content.ressources_per_load', 6));
        $max = max($step, (int) config('xomiton.content.max_per_load', 300));
        $search = ListingQuery::search($request->query('q'));
        $limit = ListingQuery::limit($request->query('n'), $step, $max);
        $categorySlug = ListingQuery::slug($request->query('cat'));
        abort_if($categorySlug === false, 404);

        $listing = $list($categorySlug, self::kind($request->query('type')), $search, $limit);
        abort_if($listing === null, 404);

        $nextUrl = $listing->hasMore() && $limit < $max
            ? route('ressources.index', [...$listing->filters(), 'n' => $limit + $step]).'#liste'
            : null;
        $offlineEnabled = OfflineLibrary::enabled();

        return view('public.ressources.index', [
            'categories' => array_map(static fn (array $chip): array => $chip + ['chip' => self::chipLabel($chip)], $listing->categories),
            'category' => $listing->category,
            'kind' => $listing->kind,
            'kinds' => self::kinds(),
            'query' => $search,
            'resources' => $listing->resources,
            'total' => $listing->total,
            'totalAll' => $listing->totalAll,
            'shown' => $listing->shown(),
            'hasMore' => $listing->hasMore(),
            'hasFilters' => $listing->hasFilters(),
            'step' => $step,
            'nextUrl' => $nextUrl,
            'files' => $listing->files,
            'faqItems' => $listing->faqs->map(static fn (Faq $faq): array => [
                'q' => (string) $faq->question,
                // Réponse en texte brut (DATABASE_DICTIONARY.md), échappée ici : <x-accordion> l'insère telle quelle.
                'a' => '<p class="mb-0">'.e((string) $faq->answer).'</p>',
            ])->values()->all(),
            'offlineEnabled' => $offlineEnabled,
            'offlineOptions' => $offlineEnabled ? OfflineLibrary::options(OfflineLibrary::marked()) : null,
            'fmt' => LocaleFormatter::make(),
            'seo' => ResourceListingSeo::props($listing),
        ]);
    }

    /**
     * Types proposés par le filtre : tous sauf les actualités, dans l'ordre de l'enum.
     *
     * @return list<ResourceKind>
     */
    private static function kinds(): array
    {
        return array_values(array_filter(ResourceKind::cases(), static fn (ResourceKind $kind): bool => $kind !== ResourceKind::Article));
    }

    /** `type` : vide → aucun filtre ; inconnu, `article` ou non textuel → 404. */
    private static function kind(mixed $value): ?ResourceKind
    {
        if ($value === null || $value === '') {
            return null;
        }

        $kind = is_string($value) ? ResourceKind::tryFrom($value) : null;
        abort_if($kind === null || $kind === ResourceKind::Article, 404);

        return $kind;
    }

    /**
     * Libellé de puce : forme courte de lang (prototype : « VBG »), sinon le libellé de la catégorie.
     *
     * @param  array{slug: string, label: string}  $chip
     */
    private static function chipLabel(array $chip): string
    {
        $key = 'pages/ressources.category_short.'.$chip['slug'];

        return Lang::has($key) ? (string) __($key) : $chip['label'];
    }
}
