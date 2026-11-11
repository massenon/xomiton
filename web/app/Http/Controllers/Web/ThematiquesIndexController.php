<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Content\Queries\ListThematiques;
use App\Application\Content\Results\ThematiqueListing;
use App\Application\Seo\StructuredData;
use App\Http\Controllers\Controller;
use App\Models\Thematique;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Liste publique `/thematiques` (P4-02, prototype thematiques.php) : publique et indexable (Q19 a), filtres GET `cat`,
 * `q`, `n` assainis ici (pas de Form Request : un GET invalide redirigerait sans message utile). `cat` mal formé ou
 * inconnu → 404 (pas d'espace d'URL infini) ; `q` → noindex ; canonical commune sans paramètres (`<x-seo>`).
 */
final class ThematiquesIndexController extends Controller
{
    /** Longueur maximale de la recherche. */
    public const SEARCH_MAX = 100;

    public function __invoke(Request $request, ListThematiques $list): View
    {
        $step = max(1, (int) config('xomiton.content.thematiques_per_load', 6));
        $max = max($step, (int) config('xomiton.content.max_per_load', 300));
        $search = self::search($request->query('q'));
        $limit = self::limit($request->query('n'), $step, $max);

        $listing = $list(self::categorySlug($request->query('cat')), $search, $limit);
        abort_if($listing === null, 404);

        $filters = array_filter(['cat' => $listing->category['slug'] ?? null, 'q' => $search], static fn (?string $value): bool => $value !== null && $value !== '');
        $nextUrl = $listing->hasMore() && $limit < $max
            ? route('thematiques.index', [...$filters, 'n' => $limit + $step]).'#liste'
            : null;

        return view('public.thematiques.index', [
            'categories' => $listing->categories,
            'category' => $listing->category,
            'query' => $search,
            'thematiques' => $listing->thematiques,
            'total' => $listing->total,
            'totalAll' => $listing->totalAll,
            'shown' => $listing->shown(),
            'hasMore' => $listing->hasMore(),
            'step' => $step,
            'nextUrl' => $nextUrl,
            'seo' => $this->seo($listing),
        ]);
    }

    /** Recherche : texte, sans caractères de contrôle, espaces normalisés, 100 caractères au plus. */
    private static function search(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $clean = (string) preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $value);

        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $clean)), 0, self::SEARCH_MAX);
    }

    /** Slug de catégorie, null si absent ; un format invalide répond 404 comme un slug inconnu. */
    private static function categorySlug(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        abort_unless(is_string($value) && preg_match('/^[a-z0-9-]{1,60}$/', $value) === 1, 404);

        return $value;
    }

    /** `n` : multiple du pas, borné ; non numérique → un pas. */
    private static function limit(mixed $value, int $step, int $max): int
    {
        $n = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;

        if ($n === false) {
            return $step;
        }

        return min($max, max($step, (int) ceil($n / $step) * $step));
    }

    /** @return array<string, mixed> props de `<x-seo>` */
    private function seo(ThematiqueListing $listing): array
    {
        $canonical = route('thematiques.index');
        $breadcrumb = [[__('pages/thematiques.title'), $canonical]];

        if ($listing->category !== null) {
            $breadcrumb[] = [$listing->category['label'], route('thematiques.index', ['cat' => $listing->category['slug']])];
        }

        return [
            'title' => __('seo.pages.thematiques.title'),
            'description' => __('seo.pages.thematiques.description'),
            'image' => 'covers/th-1.jpg',
            'imageAlt' => __('pages/thematiques.og_alt_index'),
            // Résultats de recherche interne : jamais indexés (P4-11) ; sinon règle visiteur / membre du composant.
            'index' => $listing->search === '' ? null : false,
            'breadcrumb' => $breadcrumb,
            'schema' => [StructuredData::collectionPage(
                $canonical,
                (string) __('seo.pages.thematiques.title'),
                $listing->total,
                $listing->thematiques->map(static fn (Thematique $thematique): array => [
                    'url' => route('thematiques.show', $thematique),
                    'name' => (string) $thematique->title,
                ])->values()->all(),
            )],
        ];
    }
}
