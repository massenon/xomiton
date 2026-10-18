<?php

declare(strict_types=1);

namespace App\Application\Content\Queries;

use App\Application\Content\Results\ResourceListing;
use App\Application\Shared\Referentials;
use App\Enums\ResourceKind;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Catalogue public `/ressources` (P4-03, prototype ressources.php) : ressources publiées hors actualités (Q1), filtres
 * catégorie (slug), type (`ResourceKind` hors `article`) et recherche sur le titre et le résumé (jokers neutralisés),
 * tri `published_at` décroissant puis `id`, « Charger plus » par la limite. Lecture seule (ADR-29).
 *
 * Catégories (décision par défaut, plan §7) : catégories actives des types `thematique` et `resource` (toutes admissibles
 * comme `resources.category_id` ; le prototype filtrait par catégories de thématiques), lues dans le référentiel en cache
 * (Referentials, invalidé par ReferentialObserver) et regroupées par slug (un même slug dans les deux types = une puce) ;
 * une puce sans ressource publiée est masquée. Les compteurs viennent d'une requête groupée non cachée, comme P4-02 :
 * une ressource programmée apparaît à son heure sans événement de modèle à intercepter.
 */
final class ListResources
{
    /** @var list<string> colonnes lues pour une carte */
    private const CARD_COLUMNS = ['id', 'category_id', 'thematique_id', 'slug', 'title', 'kind', 'summary', 'file_path', 'file_size', 'external_url', 'image_path', 'duration_minutes', 'is_offline_available', 'published_at'];

    public function __construct(private readonly Referentials $referentials) {}

    /** null : catégorie inconnue ou inactive (404). */
    public function __invoke(?string $categorySlug, ?ResourceKind $kind, string $search, int $limit): ?ResourceListing
    {
        /** @var array<int|string, int> $counts */
        $counts = Resource::query()->catalog()
            ->selectRaw('category_id, count(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        $chips = $this->chips($counts);
        $category = null;

        if ($categorySlug !== null) {
            $category = collect($chips)->firstWhere('slug', $categorySlug);

            if ($category === null) {
                return null;
            }
        }

        $visible = array_values(array_filter($chips, static fn (array $chip): bool => $chip['count'] > 0));
        $totalAll = array_sum($counts);
        $query = Resource::query()->catalog()
            ->with(['category:id,kind,slug,label,icon,color', 'thematique:id,slug,cover_path,image_path'])
            ->when($category !== null, static fn (Builder $query) => $query->whereIn('category_id', $category['ids'] ?? []))
            ->when($kind !== null, static fn (Builder $query) => $query->where('kind', $kind))
            ->when($search !== '', static fn (Builder $query) => $query->search($search));

        $total = match (true) {
            $search !== '' || $kind !== null => (clone $query)->count(),
            $category !== null => $category['count'],
            default => $totalAll,
        };

        $resources = $total === 0 ? new Collection : $query
            ->orderByDesc('published_at')
            ->orderBy('id')
            ->limit($limit)
            ->get(self::CARD_COLUMNS);

        $hasFilters = $category !== null || $kind !== null || $search !== '';
        $listedIds = array_merge([], ...array_column($chips, 'ids'));

        return new ResourceListing(
            $visible,
            $category,
            $kind,
            $search,
            self::withListedCategory($resources, $listedIds),
            $total,
            $totalAll,
            $limit,
            $hasFilters ? new Collection : self::withListedCategory($this->files(), $listedIds),
            $category === null ? new Collection : $this->faqs($category['ids']),
        );
    }

    /**
     * Catégorie affichée (badge de carte, méta des fiches) seulement si elle est une puce de la liste : une catégorie
     * inactive ou d'un autre type n'apparaît jamais, la ressource reste listée.
     *
     * @param  Collection<int, \App\Models\Resource>  $resources
     * @param  list<int>  $listedIds
     * @return Collection<int, \App\Models\Resource>
     */
    private static function withListedCategory(Collection $resources, array $listedIds): Collection
    {
        return $resources->each(static function (Resource $resource) use ($listedIds): void {
            if ($resource->category_id !== null && ! in_array((int) $resource->category_id, $listedIds, true)) {
                $resource->setRelation('category', null);
            }
        });
    }

    /**
     * Puces : catégories actives `thematique` et `resource`, triées par position puis libellé, regroupées par slug.
     *
     * @param  array<int|string, int>  $counts
     * @return list<array{ids: list<int>, slug: string, label: string, icon: string|null, color: string|null, position: int, count: int}>
     */
    private function chips(array $counts): array
    {
        $categories = array_values(array_filter(
            $this->referentials->categories(),
            static fn (array $category): bool => in_array($category['kind'], [Category::KIND_THEMATIQUE, Category::KIND_RESOURCE], true),
        ));
        usort($categories, static fn (array $a, array $b): int => [$a['position'], $a['label'], $a['kind']] <=> [$b['position'], $b['label'], $b['kind']]);

        $chips = [];
        foreach ($categories as $category) {
            $count = $counts[$category['id']] ?? 0;

            if (isset($chips[$category['slug']])) {
                $chips[$category['slug']]['ids'][] = $category['id'];
                $chips[$category['slug']]['count'] += $count;

                continue;
            }

            $chips[$category['slug']] = [
                'ids' => [$category['id']],
                'slug' => $category['slug'],
                'label' => $category['label'],
                'icon' => $category['icon'],
                'color' => $category['color'],
                'position' => $category['position'],
                'count' => $count,
            ];
        }

        return array_values($chips);
    }

    /**
     * « Fiches à télécharger » (prototype l.82-93) : documents PDF publiés avec fichier, les plus récents.
     *
     * @return Collection<int, \App\Models\Resource>
     */
    private function files(): Collection
    {
        return Resource::query()->catalog()
            ->where('kind', ResourceKind::Pdf)
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->with('category:id,slug,label')
            ->orderByDesc('published_at')
            ->orderBy('id')
            ->limit(max(0, (int) config('xomiton.content.ressources_files_count', 6)))
            ->get(['id', 'category_id', 'slug', 'title', 'kind', 'file_path', 'file_size', 'published_at']);
    }

    /**
     * FAQ de la catégorie filtrée (même règle que P4-02 : `faqs.category_id`, 3 à 5 questions publiées, sinon rien).
     *
     * @param  list<int>  $categoryIds
     * @return Collection<int, Faq>
     */
    private function faqs(array $categoryIds): Collection
    {
        $faqs = Faq::query()
            ->published()
            ->whereIn('category_id', $categoryIds)
            ->ordered()
            ->limit(max(0, (int) config('xomiton.content.faq_max', 5)))
            ->get(['id', 'question', 'answer']);

        return $faqs->count() < (int) config('xomiton.content.faq_min', 3) ? new Collection : $faqs;
    }
}
