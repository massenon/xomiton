<?php

declare(strict_types=1);

namespace App\Application\Content\Results;

/**
 * Résultat de ListLaws (P4-04) : textes publiés prêts pour la vue `/lois` et catégories du filtre.
 *
 * @phpstan-type LawRow array{slug: string, title: string, subtitle: string|null, summary: string, simple: string, category: string, official_url: string|null}
 */
final readonly class LawListing
{
    /**
     * @param  list<LawRow>  $laws
     * @param  list<string>  $categories  catégories distinctes, ordre d'apparition
     * @param  string|null  $updatedAt  dernière mise à jour d'un texte publié (Y-m-d)
     */
    public function __construct(
        public array $laws,
        public array $categories,
        public ?string $updatedAt,
    ) {}
}
