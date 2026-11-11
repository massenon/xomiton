<?php

declare(strict_types=1);

namespace App\Application\Steering\Reports;

/**
 * Contenu agrégé d'un rapport, prêt à écrire dans n'importe quel format : chiffres clés et tableaux. Une cellule `null`
 * est un effectif masqué (1 à 4, SmallCellMask) : chaque format l'écrit « données masquées ».
 *
 * @phpstan-type Table array{title: string, head: list<string>, rows: list<list<int|string|null>>, note: string|null}
 */
final readonly class ReportDataset
{
    /**
     * @param  list<array{label: string, value: int|string|null}>  $kpis
     * @param  list<array{title: string, head: list<string>, rows: list<list<int|string|null>>, note: string|null}>  $tables
     */
    public function __construct(
        public string $title,
        public string $periodLabel,
        public string $generatedAt,
        public array $kpis,
        public array $tables,
        public bool $hasMasked,
    ) {}
}
