<?php

declare(strict_types=1);

namespace App\Application\Content\Results;

use App\Models\Thematique;
use Illuminate\Support\Collection;

/**
 * Résultat de DoGlegbePage (P4-04) : fiches de la catégorie Do Glegbe et quiz prêts pour la vue `/do-glegbe`.
 */
final readonly class DoGlegbeContent
{
    /**
     * @param  Collection<int, Thematique>  $sheets  fiches publiées affichées (vide : section omise)
     * @param  int  $sheetsTotal  nombre total de fiches publiées de la catégorie
     * @param  string|null  $sheetsCategory  slug de la catégorie listée, null si elle n'existe pas ou n'est pas active
     * @param  list<array{statement: string, answer: bool, explanation: string}>  $quiz  affirmations complètes
     */
    public function __construct(
        public Collection $sheets,
        public int $sheetsTotal,
        public ?string $sheetsCategory,
        public array $quiz,
    ) {}
}
