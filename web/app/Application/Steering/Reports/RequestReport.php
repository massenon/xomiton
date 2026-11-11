<?php

declare(strict_types=1);

namespace App\Application\Steering\Reports;

use App\Enums\JobStatus;
use App\Enums\ReportFormat;
use App\Jobs\GenerateReport;
use App\Models\AuditLog;
use App\Models\GeneratedReport;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Demande de rapport (P10-07, ADR-23) : contrôle des paramètres puis création de la ligne `generated_reports` et de la
 * chaîne de jobs GenerateReport. Refus (ValidationException, 422) :
 *  - période inversée ou de plus de 5 ans ;
 *  - registre détaillé : plus de `xomiton.reports.max_rows` lignes (50 000), et, en PDF ou Word, plus de
 *    `xomiton.exports.pdf_max_rows` lignes (200, mesuré sous 128 Mo avec dompdf) : « choisir Excel » ;
 *  - trois rapports déjà en cours pour la même personne ;
 *  - adresse de destinataire invalide, ou plus de 10 destinataires ;
 *  - registre détaillé et destinataires à la fois : le registre n'est jamais joint à un e-mail (lien de téléchargement
 *    seulement, compte connecté).
 * Le registre détaillé exige la permission `cases.export` (lignes pseudonymisées, ADR-33) et n'est jamais joint à un e-mail
 * (rapport planifié ou non : l'envoi par e-mail ne porte que des agrégats).
 */
final class RequestReport
{
    public const MAX_RECIPIENTS = 10;

    public const MAX_PENDING_PER_USER = 3;

    public function __construct(private readonly ReportDetail $detail) {}

    /**
     * @throws AuthorizationException|ValidationException
     */
    public function handle(User $by, ReportSpec $spec): GeneratedReport
    {
        if (! $by->can('reports.generate')) {
            throw new AuthorizationException;
        }

        if ($spec->detail && ! $by->can('cases.export')) {
            throw new AuthorizationException;
        }

        $this->validate($spec, $by);
        $report = $this->create($spec, $by->getKey());
        Audit::log('report.request', $report, $spec->format->value.' · '.$report->periodLabel().($spec->detail ? ' · '.trans_choice('steering.reports.audit.rows', (int) $report->rows_count, ['count' => (int) $report->rows_count]) : ''), $by);

        return $report;
    }

    /**
     * Rapport d'une planification échue : agrégats seulement, formats de la planification, un rapport par format.
     */
    public function forSchedule(ReportSchedule $schedule, ReportSpec $spec): GeneratedReport
    {
        $report = $this->create($spec, $schedule->created_by);
        Audit::logWithoutRequest('report.scheduled', $report, $spec->format->value.' · '.$report->periodLabel(), AuditLog::ACTOR_SYSTEM);

        return $report;
    }

    /**
     * @throws ValidationException
     */
    public function validate(ReportSpec $spec, ?User $by = null): void
    {
        $errors = [];

        if ($spec->end->lessThan($spec->start)) {
            $errors['until'] = (string) __('steering.reports.errors.period_inverted');
        } elseif ($spec->start->diffInYears($spec->end) >= ReportCatalog::MAX_YEARS) {
            $errors['until'] = (string) __('steering.reports.errors.period_too_long', ['years' => ReportCatalog::MAX_YEARS]);
        }

        if (! $spec->format->producesFile()) {
            $errors['format'] = (string) __('steering.reports.errors.format');
        }

        if ($spec->detail && $errors === []) {
            $rows = $this->detail->count($spec->period());
            $max = max(1, (int) config('xomiton.reports.max_rows', 50000));
            $pdfMax = max(1, (int) config('xomiton.exports.pdf_max_rows', 200));

            if ($rows > $max) {
                $errors['detail'] = (string) __('steering.reports.errors.too_many_rows', ['count' => number_format($rows, 0, ',', ' '), 'max' => number_format($max, 0, ',', ' ')]);
            } elseif ($rows > $pdfMax && $spec->format !== ReportFormat::Xlsx) {
                $errors['format'] = (string) __('steering.reports.errors.choose_excel', ['count' => number_format($rows, 0, ',', ' '), 'max' => number_format($pdfMax, 0, ',', ' ')]);
            }
        }

        if ($spec->detail && $spec->recipients !== []) {
            $errors['recipients'] = (string) __('steering.reports.errors.detail_no_mail');
        }

        foreach ($spec->recipients as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                $errors['recipients'] = (string) __('steering.reports.errors.recipient', ['address' => $address]);
                break;
            }
        }

        if (count($spec->recipients) > self::MAX_RECIPIENTS) {
            $errors['recipients'] = (string) __('steering.reports.errors.recipients_max', ['max' => self::MAX_RECIPIENTS]);
        }

        if ($by !== null && GeneratedReport::query()->unfinished()->where('generated_by', $by->getKey())->count() >= self::MAX_PENDING_PER_USER) {
            $errors['format'] = (string) __('steering.reports.errors.too_many_pending', ['max' => self::MAX_PENDING_PER_USER]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function create(ReportSpec $spec, ?int $userId): GeneratedReport
    {
        $period = $spec->period();
        $rows = $spec->detail ? $this->detail->count($period) : null;
        $bounds = $spec->detail ? $this->detail->boundaries($period, max(1, (int) config('xomiton.reports.rows_per_job', 5000))) : [[0, null]];

        /** @var GeneratedReport $report */
        $report = DB::transaction(fn (): GeneratedReport => GeneratedReport::query()->create([
            'title' => mb_substr($spec->title, 0, 160),
            'kind' => $spec->kind,
            'period_start' => $spec->start->toDateString(),
            'period_end' => $spec->end->toDateString(),
            'dimensions' => $spec->dimensions,
            'format' => $spec->format,
            'filters' => ['sections' => $spec->sections, 'detail' => $spec->detail, 'parts' => count($bounds)],
            'rows_count' => $rows,
            'status' => JobStatus::Pending,
            'recipients' => $spec->recipients === [] ? null : $spec->recipients,
            'generated_by' => $userId,
            'expires_at' => now()->addDays(max(1, (int) config('xomiton.reports.retention_days', 30))),
        ]));

        $parts = count($bounds);
        Bus::chain(array_map(
            static fn (array $range, int $index): GenerateReport => new GenerateReport($report->getKey(), $index, $parts, $range[0], $range[1]),
            $bounds,
            array_keys($bounds),
        ))->dispatch();

        return $report->refresh();
    }
}
