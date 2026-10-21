<?php

declare(strict_types=1);

namespace App\Application\Directory\Data;

/**
 * Rapport d'un import CSV de l'annuaire (P11-04) : lignes créées, mises à jour, rejetées (numéro de ligne du fichier, nom
 * et motifs). Aucune coordonnée dans le rapport.
 */
final readonly class DirectoryImportReport
{
    /**
     * @param  list<array{line: int, name: string, reasons: list<string>}>  $rejected
     */
    public function __construct(
        public int $created,
        public int $updated,
        public array $rejected,
    ) {}

    public function accepted(): int
    {
        return $this->created + $this->updated;
    }

    public function total(): int
    {
        return $this->accepted() + count($this->rejected);
    }
}
