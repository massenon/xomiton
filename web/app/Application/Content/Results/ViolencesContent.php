<?php

declare(strict_types=1);

namespace App\Application\Content\Results;

/**
 * Résultat de ViolencesPage (P4-04) : contenu prêt pour la vue `/violences`.
 *
 * @phpstan-type ViolenceForm array{slug: string, label: string, description: string|null, icon: string, signs: list<string>}
 */
final readonly class ViolencesContent
{
    /**
     * @param  list<ViolenceForm>  $forms  formes actives du référentiel, dans l'ordre
     * @param  list<mixed>  $cycle  étapes du cycle de la violence (lang)
     * @param  list<string>  $questions  questions de l'auto-évaluation (lang)
     * @param  int  $lowMax  nombre de « oui » au plus pour le niveau faible
     * @param  int  $mediumMax  nombre de « oui » au plus pour le niveau moyen
     */
    public function __construct(
        public array $forms,
        public array $cycle,
        public array $questions,
        public int $lowMax,
        public int $mediumMax,
    ) {}
}
