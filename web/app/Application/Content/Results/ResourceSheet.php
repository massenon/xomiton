<?php

declare(strict_types=1);

namespace App\Application\Content\Results;

use App\Models\Category;
use App\Models\Resource;
use App\Models\Thematique;
use Illuminate\Support\Collection;

/**
 * Résultat de ShowResource : la ressource publiée et ses blocs liés (P4-03).
 */
final readonly class ResourceSheet
{
    /**
     * @param  Category|null  $category  catégorie du fil d'Ariane : celle de la ressource si elle est une puce de la liste (active, `thematique` ou `resource`), sinon null
     * @param  Thematique|null  $thematique  thématique liée, si elle est publiée
     * @param  Collection<int, \App\Models\Resource>  $related  0 à `related_count` ressources du catalogue
     */
    public function __construct(
        public Resource $resource,
        public ?Category $category,
        public ?Thematique $thematique,
        public Collection $related,
    ) {}
}
