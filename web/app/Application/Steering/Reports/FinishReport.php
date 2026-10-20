<?php

declare(strict_types=1);

namespace App\Application\Steering\Reports;

use App\Enums\JobStatus;
use App\Enums\ReportFormat;
use App\Models\AuditLog;
use App\Models\GeneratedReport;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Support\Facades\Log;

/**
 * Dernier morceau d'un rapport : construit les agrégats, écrit le fichier dans son format (flux pour Excel, mémoire pour
 * PDF et Word), le range sur le disque des rapports, supprime les morceaux temporaires puis envoie le fichier aux
 * destinataires. Le registre détaillé est relu depuis les fichiers des morceaux, un par un.
 */
final class FinishReport
{
    public function __construct(private readonly ReportBuilder $builder, private readonly ReportDelivery $delivery) {}

    public function handle(GeneratedReport $report, int $parts): void
    {
        $spec = $this->spec($report);
        $dataset = $this->builder->build($spec);
        $detail = (bool) ($report->filters['detail'] ?? false);
        $head = $detail ? ReportDetail::head($spec->dimensions) : null;
        $writer = $this->writer($report->format);
        $temp = tempnam(sys_get_temp_dir(), 'xmr');

        if ($temp === false) {
            throw new \RuntimeException('Fichier temporaire impossible à créer.');
        }

        try {
            $count = 0;
            $rows = $detail ? $this->rows($report, $parts, $count) : [];
            $writer->write($temp, $dataset, $head, $rows);

            $path = ReportStorage::finalPath($report);
            $stream = fopen($temp, 'rb');
            ReportStorage::disk()->put($path, $stream === false ? '' : $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $report->forceFill([
                'status' => JobStatus::Done,
                'file_path' => $path,
                'file_size' => (int) filesize($temp),
                'rows_count' => $detail ? $count : null,
                'error' => null,
                'completed_at' => now(),
            ])->save();
        } finally {
            @unlink($temp);
            ReportStorage::forgetParts($report);
        }

        Audit::logWithoutRequest('report.generated', $report, $report->format->value.' · '.$report->periodLabel(), AuditLog::ACTOR_SYSTEM);
        Log::info('Rapport généré.', ['report' => $report->getKey(), 'format' => $report->format->value, 'size' => $report->file_size]);

        $this->delivery->send($report);
    }

    public function spec(GeneratedReport $report): ReportSpec
    {
        /** @var list<string> $sections */
        $sections = array_values(array_filter((array) ($report->filters['sections'] ?? []), is_string(...)));

        return new ReportSpec(
            kind: $report->kind,
            title: $report->title,
            start: CarbonImmutable::parse($report->period_start),
            end: CarbonImmutable::parse($report->period_end),
            dimensions: array_values(array_filter((array) $report->dimensions, is_string(...))),
            sections: $sections,
            detail: (bool) ($report->filters['detail'] ?? false),
            format: $report->format,
            recipients: array_values(array_filter((array) $report->recipients, is_string(...))),
        );
    }

    private function writer(ReportFormat $format): ReportWriter
    {
        return match ($format) {
            ReportFormat::Xlsx => new XlsxReportWriter,
            ReportFormat::Docx => new DocxReportWriter,
            default => new PdfReportWriter,
        };
    }

    /**
     * Lignes du registre, relues morceau par morceau (`reports/tmp/<id>/part-<n>.ndjson`).
     *
     * @return Generator<int, list<int|string|null>>
     */
    private function rows(GeneratedReport $report, int $parts, int &$count): Generator
    {
        $disk = ReportStorage::disk();

        for ($part = 0; $part < $parts; $part++) {
            $path = ReportStorage::partPath($report, $part);

            if (! $disk->exists($path)) {
                continue;
            }

            $stream = $disk->readStream($path);

            if (! is_resource($stream)) {
                continue;
            }

            while (($line = fgets($stream)) !== false) {
                $row = json_decode($line, true);

                if (is_array($row)) {
                    $count++;

                    yield array_values($row);
                }
            }

            fclose($stream);
        }
    }
}
