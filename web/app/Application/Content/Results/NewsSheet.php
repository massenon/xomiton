<?php

declare(strict_types=1);

namespace App\Application\Content\Results;

use App\Enums\ResourceKind;
use App\Models\Category;
use App\Models\Resource;
use Illuminate\Support\Collection;

/**
 * Résultat de ShowNews (P4-10) : l'actualité, sa catégorie si elle est listée (active, `thematique` ou `resource`),
 * les autres actualités « À lire aussi » et la durée de lecture. Une ressource publiée d'un autre type est rendue sans
 * compléments : le contrôleur la redirige vers `/ressources/{slug}` (Q1, symétrique de Q60).
 */
final readonly class NewsSheet
{
    /**
     * @param  Collection<int, \App\Models\Resource>  $others
     */
    public function __construct(
        public Resource $article,
        public ?Category $category,
        public Collection $others,
        public int $minutes = 0,
    ) {}

    public function isNews(): bool
    {
        return $this->article->kind === ResourceKind::Article;
    }
}
