<?php

declare(strict_types=1);

namespace App\Application\Steering\Reports;

/**
 * Écriture d'un rapport dans un fichier local (xlsx, pdf, docx). `$detailRows` est lu une seule fois, en flux.
 */
interface ReportWriter
{
    /**
     * @param  list<string>|null  $detailHead  en-têtes du registre détaillé (null : pas de registre)
     * @param  iterable<list<int|string|null>>  $detailRows
     */
    public function write(string $path, ReportDataset $dataset, ?array $detailHead, iterable $detailRows): void;
}
