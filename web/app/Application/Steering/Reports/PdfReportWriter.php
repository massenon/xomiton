<?php

declare(strict_types=1);

namespace App\Application\Steering\Reports;

use Barryvdh\DomPDF\Facade\Pdf;

/**
 * PDF par `barryvdh/laravel-dompdf` (ADR-23) : HTML hors ligne (`isRemoteEnabled` désactivé, aucune ressource distante),
 * police DejaVu Sans fournie avec dompdf (accents français). Le registre détaillé est lu en mémoire : sa taille est
 * plafonnée par `xomiton.exports.pdf_max_rows` (200 lignes : environ 100 Mo de pic mesuré, 1,4 Go à 2 000 ; RequestReport refuse au-delà, 422 « choisir Excel »).
 */
final class PdfReportWriter implements ReportWriter
{
    public function write(string $path, ReportDataset $dataset, ?array $detailHead, iterable $detailRows): void
    {
        $rows = [];

        foreach ($detailRows as $row) {
            $rows[] = $row;
        }

        $pdf = Pdf::setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'dpi' => 96])
            ->setPaper('a4', 'portrait')
            ->loadView('reports.pdf', [
                'dataset' => $dataset,
                'detailHead' => $detailHead,
                'detailRows' => $rows,
                'siteName' => (string) config('app.name', 'Xomiton'),
            ]);

        file_put_contents($path, $pdf->output());
    }
}
