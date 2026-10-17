<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\JobStatus;
use App\Enums\ReportFormat;
use App\Enums\ReportKind;
use App\Models\GeneratedReport;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * rapports_generes.json → generated_reports (at → created_at / completed_at, by nom → generated_by,
 * rtype → kind, period « jj/mm/aaaa – jj/mm/aaaa » → period_start / period_end, format, cases → rows_count,
 * dims « a,b » → dimensions json). Clé naturelle : titre, nature, période et format.
 */
final class ImportGeneratedReports extends AbstractImporter
{
    public static function collection(): string
    {
        return 'rapports_generes';
    }

    public static function isDemo(): bool
    {
        return true;
    }

    protected function importRow(array $row, int $index): void
    {
        $title = $this->required($row['title'] ?? null, 'title', 160);
        $kind = ReportKind::tryFrom((string) ($row['rtype'] ?? $row['kind'] ?? ''))
            ?? $this->reject(sprintf('nature de rapport inconnue « %s »', (string) ($row['rtype'] ?? '')));
        $format = ReportFormat::tryFrom((string) ($row['format'] ?? ''))
            ?? $this->reject(sprintf('format inconnu « %s »', (string) ($row['format'] ?? '')));
        [$start, $end] = $this->period((string) ($row['period'] ?? ''));
        $at = $this->ctx->dateTime($row['at'] ?? null) ?? now();

        $dimensions = array_values(array_filter(array_map('trim', explode(',', (string) ($row['dims'] ?? '')))));

        // Les dates sont passées en Carbon : la clause where les formate comme la colonne `date` est stockée.
        $report = GeneratedReport::query()->firstOrNew([
            'title' => $title,
            'kind' => $kind->value,
            'period_start' => $start,
            'period_end' => $end,
            'format' => $format->value,
        ]);

        $report->fill([
            'dimensions' => $dimensions,
            'rows_count' => is_numeric($row['cases'] ?? null) ? (int) $row['cases'] : null,
            'status' => JobStatus::Done,
            'generated_by' => $this->ctx->userIdByName($this->text($row['by'] ?? null)),
            'completed_at' => $at,
        ]);
        $report->created_at = $at;
        $report->save();

        $this->track($report);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(string $period): array
    {
        $parts = preg_split('/\s*[–—-]\s*/u', trim($period)) ?: [];

        if (count($parts) !== 2) {
            $this->reject(sprintf('période illisible « %s »', $period));
        }

        try {
            $start = Carbon::createFromFormat('d/m/Y', trim($parts[0]), $this->ctx->timezone());
            $end = Carbon::createFromFormat('d/m/Y', trim($parts[1]), $this->ctx->timezone());
        } catch (Throwable) {
            $this->reject(sprintf('période illisible « %s »', $period));
        }

        if ($start === null || $end === null || $start->gt($end)) {
            $this->reject(sprintf('période incohérente « %s »', $period));
        }

        return [$start->startOfDay(), $end->startOfDay()];
    }
}
