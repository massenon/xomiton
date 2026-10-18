<?php

declare(strict_types=1);

namespace App\Application\Content\Results;

use App\Models\Thematique;
use Illuminate\Support\Collection;

/**
 * Résultat de ListThematiques : filtres (catégories actives et leur nombre de fiches publiées), fiches affichées et
 * totaux pour « Charger plus » (P4-02).
 *
 * @phpstan-type CategoryRow array{id: int, kind: string, slug: string, label: string, icon: string|null, color: string|null, position: int, count: int}
 */
final readonly class ThematiqueListing
{
    /**
     * @param  list<CategoryRow>  $categories
     * @param  CategoryRow|null  $category  catégorie filtrée
     * @param  Collection<int, Thematique>  $thematiques
     */
    public function __construct(
        public array $categories,
        public ?array $category,
        public string $search,
        public Collection $thematiques,
        public int $total,
        public int $totalAll,
        public int $limit,
    ) {}

    /** Fiches affichées (min(limite, total)). */
    public function shown(): int
    {
        return min($this->limit, $this->total);
    }

    public function hasMore(): bool
    {
        return $this->total > $this->limit;
    }
}
