<?php

declare(strict_types=1);

namespace App\Application\Content\Results;

use Illuminate\Support\Collection;

/**
 * Résultat de ListNews (P4-10) : puces de catégorie (catégories actives ayant au moins une actualité publiée,
 * regroupées par slug), catégorie filtrée, actualités affichées et totaux pour « Charger plus ».
 *
 * @phpstan-type NewsChip array{ids: list<int>, slug: string, label: string, icon: string|null, color: string|null, position: int, count: int}
 */
final readonly class NewsListing
{
    /**
     * @param  list<NewsChip>  $categories  puces affichées (compte > 0)
     * @param  NewsChip|null  $category  catégorie filtrée
     * @param  Collection<int, \App\Models\Resource>  $articles
     */
    public function __construct(
        public array $categories,
        public ?array $category,
        public Collection $articles,
        public int $total,
        public int $totalAll,
        public int $limit,
    ) {}

    public function shown(): int
    {
        return min($this->limit, $this->total);
    }

    public function hasMore(): bool
    {
        return $this->total > $this->limit;
    }
}
