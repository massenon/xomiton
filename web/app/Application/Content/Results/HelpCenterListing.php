<?php

declare(strict_types=1);

namespace App\Application\Content\Results;

use App\Models\Faq;
use Illuminate\Support\Collection;

/**
 * Résultat de ListHelpArticles (P4-05) : catégories qui ont au moins une question (avec leur nombre), catégorie
 * filtrée, recherche (`searched` : au moins un mot significatif retenu, seul cas où un nombre de résultats est annoncé),
 * questions affichées et date de dernière mise à jour du centre d'aide.
 *
 * @phpstan-type CategoryRow array{id: int, kind: string, slug: string, label: string, icon: string|null, color: string|null, position: int, count: int}
 */
final readonly class HelpCenterListing
{
    /**
     * @param  list<CategoryRow>  $categories
     * @param  CategoryRow|null  $category
     * @param  Collection<int, Faq>  $faqs
     */
    public function __construct(
        public array $categories,
        public ?array $category,
        public string $search,
        public bool $searched,
        public Collection $faqs,
        public int $totalAll,
        public ?string $updatedAt,
    ) {}

    public function isFiltered(): bool
    {
        return $this->category !== null || $this->search !== '';
    }
}
