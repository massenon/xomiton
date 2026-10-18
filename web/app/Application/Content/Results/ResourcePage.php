<?php

declare(strict_types=1);

namespace App\Application\Content\Results;

use App\Enums\ResourceKind;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Résultat de ListResources::page() (P6-08, API mobile) : puces de catégorie affichées (compte > 0), filtres actifs et
 * page de ressources du catalogue (même ordre et mêmes filtres que la liste publique `/ressources`).
 *
 * @phpstan-import-type ChipRow from ResourceListing
 */
final readonly class ResourcePage
{
    /**
     * @param  list<ChipRow>  $categories
     * @param  ChipRow|null  $category  catégorie filtrée
     * @param  LengthAwarePaginator<int, \App\Models\Resource>  $resources
     */
    public function __construct(
        public array $categories,
        public ?array $category,
        public ?ResourceKind $kind,
        public string $search,
        public LengthAwarePaginator $resources,
    ) {}
}
