<?php

declare(strict_types=1);

namespace App\Application\Content\Queries;

use App\Application\Content\Results\ThematiqueListing;
use App\Application\Shared\Referentials;
use App\Models\Category;
use App\Models\Thematique;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;

/**
 * Liste publique des thématiques (P4-02, prototype thematiques.php) : fiches publiées, filtre par catégorie (slug),
 * recherche sur le titre et le résumé (jokers `%` / `_` de la saisie neutralisés : LikePattern), tri `published_at` décroissant puis `id` (ordre du prototype avec le seed),
 * « Charger plus » par la limite. Lecture seule (ADR-29).
 *
 * Catégories : référentiel en cache (Referentials, invalidé par ReferentialObserver) ; leurs compteurs viennent d'une
 * requête groupée non mise en cache — une fiche programmée devient visible à son heure sans qu'aucun événement de modèle
 * ne puisse invalider un compteur caché.
 */
final class ListThematiques
{
    public function __construct(private readonly Referentials $referentials) {}

    public function __invoke(?string $categorySlug, string $search, int $limit): ?ThematiqueListing
    {
        /** @var array<int, int> $counts */
        $counts = Thematique::query()->published()
            ->selectRaw('category_id, count(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        $categories = array_map(static fn (array $category): array => $category + ['count' => $counts[$category['id']] ?? 0],
            $this->referentials->categories(Category::KIND_THEMATIQUE));

        $category = null;
        if ($categorySlug !== null) {
            $category = collect($categories)->firstWhere('slug', $categorySlug);

            if ($category === null) {
                return null;
            }
        }

        $totalAll = array_sum($counts);
        $pattern = LikePattern::contains($search);
        $query = Thematique::query()->published()
            ->with('category:id,slug,label,icon,color')
            ->when($category !== null, static fn (Builder $query) => $query->where('category_id', $category['id'] ?? null))
            ->when($search !== '', static fn (Builder $query) => $query->where(static fn (Builder $where) => $where
                ->whereRaw(LikePattern::clause('title'), [$pattern])
                ->orWhereRaw(LikePattern::clause('summary'), [$pattern])));

        $total = match (true) {
            $search !== '' => (clone $query)->count(),
            $category !== null => $category['count'],
            default => $totalAll,
        };

        $thematiques = $total === 0 ? collect() : $query
            ->orderByDesc('published_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'category_id', 'slug', 'title', 'summary', 'cover_path', 'image_path', 'reading_minutes', 'published_at']);

        return new ThematiqueListing($categories, $category, $search, $thematiques, $total, $totalAll, $limit);
    }
}
