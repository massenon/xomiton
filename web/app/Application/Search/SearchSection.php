<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\Search\SearchType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Une famille de résultats de la recherche membre (P8-13) : ses premiers éléments (modèles déjà chargés pour leur
 * Resource) et son total, pour « Voir les n résultats ».
 */
final readonly class SearchSection
{
    /**
     * @param  Collection<int, Model>  $items
     */
    public function __construct(
        public SearchType $type,
        public Collection $items,
        public int $total,
    ) {}

    /**
     * @param  LengthAwarePaginator<int, Model>  $page
     */
    public static function fromPage(SearchType $type, LengthAwarePaginator $page): self
    {
        return new self($type, collect($page->items())->values(), $page->total());
    }

    public static function empty(SearchType $type): self
    {
        return new self($type, collect(), 0);
    }

    public function hasMore(): bool
    {
        return $this->total > $this->items->count();
    }
}
