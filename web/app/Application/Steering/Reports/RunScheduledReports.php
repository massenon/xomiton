<?php

declare(strict_types=1);

namespace App\Application\Steering\Reports;

use App\Models\ReportSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Planifications échues (`reports:generate`, quotidien) : pour chaque planification active dont `next_run_at` est
 * atteint, un rapport par format sur la dernière période entière (semaine, mois, trimestre, semestre ou année civils
 * écoulés), enfilé comme une demande de l'assistant ; l'envoi par e-mail suit la fin de chaque job. La prochaine
 * échéance est posée **avant** l'enfilage : un second passage le même jour ne regénère rien.
 */
final class RunScheduledReports
{
    public function __construct(private readonly RequestReport $request) {}

    /**
     * @return array{schedules: int, reports: int, failed: int}
     */
    public function run(?CarbonInterface $now = null): array
    {
        $now = CarbonImmutable::instance($now ?? now());
        $result = ['schedules' => 0, 'reports' => 0, 'failed' => 0];

        ReportSchedule::query()->due($now)->orderBy('id')->each(function (ReportSchedule $schedule) use ($now, &$result): void {
            $claimed = DB::transaction(function () use ($schedule, $now): bool {
                $fresh = ReportSchedule::query()->lockForUpdate()->find($schedule->getKey());

                if ($fresh === null || ! $fresh->is_active || $fresh->next_run_at === null || $fresh->next_run_at->greaterThan($now)) {
                    return false;
                }

                $fresh->forceFill(['last_run_at' => $now, 'next_run_at' => $fresh->computeNextRunAt($now)])->save();

                return true;
            });

            if (! $claimed) {
                return;
            }

            $result['schedules']++;
            $period = ReportCatalog::previousPeriod((string) $schedule->frequency, $now);

            foreach ($schedule->formatList() as $format) {
                try {
                    $this->request->forSchedule($schedule, new ReportSpec(
                        kind: $schedule->kind,
                        title: $schedule->kind->label().' — '.$period->label(),
                        start: $period->start,
                        end: $period->end,
                        dimensions: array_values(array_filter((array) $schedule->dimensions, is_string(...))),
                        sections: ReportCatalog::DEFAULT_SECTIONS,
                        detail: false,
                        format: $format,
                        recipients: array_values(array_filter((array) $schedule->recipients, is_string(...))),
                    ));
                    $result['reports']++;
                } catch (Throwable $exception) {
                    $result['failed']++;
                    Log::error('Rapport planifié non enfilé.', ['schedule' => $schedule->getKey(), 'format' => $format->value, 'exception' => $exception::class]);
                }
            }
        });

        return $result;
    }
}
