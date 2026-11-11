<?php

declare(strict_types=1);

namespace App\Application\Import;

/**
 * Résumé de l'import par collection : lignes créées, mises à jour (déjà présentes et réalignées),
 * rejetées, et collections ignorées avec leur raison. Rendu en table par la commande.
 */
final class ImportReport
{
    /** @var array<string, array{created: int, updated: int, rejected: int, skipped: string|null}> */
    private array $rows = [];

    public function touch(string $collection): void
    {
        $this->rows[$collection] ??= ['created' => 0, 'updated' => 0, 'rejected' => 0, 'skipped' => null];
    }

    public function created(string $collection, int $count = 1): void
    {
        $this->touch($collection);
        $this->rows[$collection]['created'] += $count;
    }

    public function updated(string $collection, int $count = 1): void
    {
        $this->touch($collection);
        $this->rows[$collection]['updated'] += $count;
    }

    public function rejected(string $collection, int $count = 1): void
    {
        $this->touch($collection);
        $this->rows[$collection]['rejected'] += $count;
    }

    public function skip(string $collection, string $reason): void
    {
        $this->touch($collection);
        $this->rows[$collection]['skipped'] = $reason;
    }

    /** @return array{created: int, updated: int, rejected: int, skipped: string|null} */
    public function of(string $collection): array
    {
        $this->touch($collection);

        return $this->rows[$collection];
    }

    /** @return array<string, array{created: int, updated: int, rejected: int, skipped: string|null}> */
    public function rows(): array
    {
        return $this->rows;
    }

    /** @return array{created: int, updated: int, rejected: int, skipped: int} */
    public function totals(): array
    {
        $totals = ['created' => 0, 'updated' => 0, 'rejected' => 0, 'skipped' => 0];

        foreach ($this->rows as $row) {
            $totals['created'] += $row['created'];
            $totals['updated'] += $row['updated'];
            $totals['rejected'] += $row['rejected'];
            $totals['skipped'] += $row['skipped'] === null ? 0 : 1;
        }

        return $totals;
    }
}
