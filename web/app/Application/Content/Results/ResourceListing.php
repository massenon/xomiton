<?php

declare(strict_types=1);

namespace App\Application\Content\Results;

use App\Enums\ResourceKind;
use App\Models\Faq;
use Illuminate\Support\Collection;

/**
 * Résultat de ListResources (P4-03) : puces de catégorie (catégories actives `thematique` et `resource` ayant au moins
 * une ressource publiée, regroupées par slug), filtres actifs, ressources affichées et totaux pour « Charger plus »,
 * « Fiches à télécharger » (sans filtre) et FAQ de la catégorie (avec `cat`).
 *
 * @phpstan-type ChipRow array{ids: list<int>, slug: string, label: string, icon: string|null, color: string|null, position: int, count: int}
 */
final readonly class ResourceListing
{
    /**
     * @param  list<ChipRow>  $categories  puces affichées (compte > 0)
     * @param  ChipRow|null  $category  catégorie filtrée
     * @param  Collection<int, \App\Models\Resource>  $resources
     * @param  Collection<int, \App\Models\Resource>  $files  documents PDF récents (vide dès qu'un filtre est actif)
     * @param  Collection<int, Faq>  $faqs  vide, ou entre `faq_min` et `faq_max` questions publiées de la catégorie
     */
    public function __construct(
        public array $categories,
        public ?array $category,
        public ?ResourceKind $kind,
        public string $search,
        public Collection $resources,
        public int $total,
        public int $totalAll,
        public int $limit,
        public Collection $files,
        public Collection $faqs,
    ) {}

    public function hasFilters(): bool
    {
        return $this->category !== null || $this->kind !== null || $this->search !== '';
    }

    /** Ressources affichées (min(limite, total)). */
    public function shown(): int
    {
        return min($this->limit, $this->total);
    }

    public function hasMore(): bool
    {
        return $this->total > $this->limit;
    }

    /** @return array<string, string> filtres actifs, pour les liens qui les conservent */
    public function filters(): array
    {
        return array_filter([
            'cat' => $this->category['slug'] ?? null,
            'q' => $this->search,
            'type' => $this->kind?->value,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }
}
