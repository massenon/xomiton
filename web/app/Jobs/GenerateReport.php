<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Steering\Reports\FinishReport;
use App\Application\Steering\Reports\ReportDetail;
use App\Application\Steering\Reports\ReportStorage;
use App\Enums\JobStatus;
use App\Models\GeneratedReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Génération d'un rapport par morceaux (P10-07, ADR-23, cPanel : `queue:work --stop-when-empty` lancé par cron, 30 s /
 * 128 Mo par passage). Une chaîne de N jobs : les morceaux 0 à N-2 lisent chacun leur tranche du registre détaillé
 * (`rows_per_job` dossiers, bornes d'identifiants fixées à la demande) et l'écrivent dans un fichier temporaire ; le
 * dernier morceau écrit les agrégats puis assemble le fichier final (FinishReport). Sans registre détaillé : un seul job.
 * Un échec marque le rapport « Échec » (message court, sans donnée) et supprime les morceaux ; la chaîne s'arrête.
 */
final class GenerateReport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public readonly int $reportId,
        public readonly int $part,
        public readonly int $parts,
        public readonly int $afterId = 0,
        public readonly ?int $untilId = null,
    ) {}

    public function handle(FinishReport $finish, ReportDetail $detail): void
    {
        $report = GeneratedReport::query()->find($this->reportId);

        if ($report === null || $report->status->isFinished()) {
            return;
        }

        if ($report->status === JobStatus::Pending) {
            $report->forceFill(['status' => JobStatus::Running, 'error' => null])->save();
        }

        $withDetail = (bool) ($report->filters['detail'] ?? false);

        if ($withDetail && $this->part < $this->parts - 1) {
            $this->collect($report, $detail);

            return;
        }

        if ($withDetail) {
            $this->collect($report, $detail);
        }

        $finish->handle($report, $this->parts);
    }

    public function failed(Throwable $exception): void
    {
        $report = GeneratedReport::query()->find($this->reportId);

        if ($report === null || $report->status === JobStatus::Failed) {
            return;
        }

        Log::error('Rapport en échec.', ['report' => $this->reportId, 'part' => $this->part, 'exception' => $exception::class, 'message' => $exception->getMessage()]);
        $report->forceFill(['status' => JobStatus::Failed, 'error' => (string) __('steering.reports.errors.failed'), 'completed_at' => now()])->save();
        ReportStorage::forgetParts($report);
    }

    /** Écrit la tranche du registre (une ligne JSON par dossier) dans le fichier du morceau. */
    private function collect(GeneratedReport $report, ReportDetail $detail): void
    {
        $spec = app(FinishReport::class)->spec($report);
        $lines = '';

        foreach ($detail->rows($spec->period(), $spec->dimensions, $this->afterId, $this->untilId) as $row) {
            $lines .= json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
        }

        ReportStorage::disk()->put(ReportStorage::partPath($report, $this->part), $lines);
    }
}
