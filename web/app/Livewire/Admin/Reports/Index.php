<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Reports;

use App\Application\Retention\PurgeExpiredReports;
use App\Application\Steering\Reports\ReportBuilder;
use App\Application\Steering\Reports\ReportCatalog;
use App\Application\Steering\Reports\ReportDelivery;
use App\Application\Steering\Reports\ReportDimensions;
use App\Application\Steering\Reports\ReportSpec;
use App\Application\Steering\Reports\RequestReport;
use App\Enums\JobStatus;
use App\Enums\ReportFormat;
use App\Enums\ReportKind;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Models\GeneratedReport;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Support\Audit;
use App\Support\LocaleFormatter;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Rapports `/admin/rapports` (P10-07, prototype admin/rapports.php) : assistant (type, période, titre, désagrégations,
 * sections, registre détaillé pseudonymisé, format PDF / Excel / Word, destinataires e-mail), aperçu à l'écran, génération
 * asynchrone par morceaux (job GenerateReport), historique (suivi de l'état, téléchargement par lien signé, renvoi par
 * e-mail, suppression, « Vider l'historique »), planification récurrente (`report_schedules` : hebdomadaire à annuelle,
 * jour 1–28, formats, destinataires, activer / pause / supprimer). Permission `reports.generate` ; le registre détaillé
 * exige aussi `cases.export`. Les effectifs de 1 à 4 sont masqués dans tous les formats.
 */
final class Index extends Component
{
    use InteractsWithUi;
    use WithDataTable;

    /** Durée de l'aperçu gardé en cache (secondes). */
    private const PREVIEW_SECONDS = 600;

    // --- Assistant ---
    public string $kind = 'annuel';

    public string $from = '';

    public string $until = '';

    public string $title = '';

    /** @var list<string> */
    public array $dimensions = ReportCatalog::DEFAULT_DIMENSIONS;

    /** @var list<string> */
    public array $sections = ReportCatalog::DEFAULT_SECTIONS;

    public bool $detail = false;

    public string $fileFormat = 'pdf';

    public string $recipients = '';

    public bool $previewing = false;

    // --- Planification ---
    public string $sKind = 'mensuel';

    public string $sFrequency = 'mensuel';

    public int $sDay = 1;

    /** @var list<string> */
    public array $sFormats = ['pdf'];

    /** @var list<string> */
    public array $sDimensions = ReportCatalog::DEFAULT_DIMENSIONS;

    public string $sRecipients = '';

    // --- Historique ---
    #[SafeUrl(as: 'st', except: '')]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('reports.generate');
        $this->applyKind();
    }

    protected function sortableColumns(): array
    {
        return ['date' => 'created_at', 'titre' => 'title', 'periode' => 'period_start', 'statut' => 'status'];
    }

    protected function searchableColumns(): array
    {
        return ['title'];
    }

    protected function filterProperties(): array
    {
        return ['status'];
    }

    public function updatedKind(): void
    {
        $this->previewing = false;
        $this->applyKind();
    }

    /** Une date saisie à la main fait du rapport un rapport personnalisé (prototype). */
    public function updatedFrom(): void
    {
        $this->previewing = false;
        $this->kind = ReportKind::Custom->value;
    }

    public function updatedUntil(): void
    {
        $this->previewing = false;
        $this->kind = ReportKind::Custom->value;
    }

    public function updatedSections(): void
    {
        $this->previewing = false;
    }

    public function updatedDimensions(): void
    {
        $this->previewing = false;
    }

    /** Aperçu à l'écran : agrégats uniquement, aucun fichier, aucun registre. */
    public function preview(): void
    {
        $this->authorize('reports.generate');
        $spec = $this->spec(false);
        // Les agrégats sont construits ici seulement, puis relus du cache tant que l'aperçu reste affiché : aucun
        // rendu Livewire (filtre de l'historique, autre champ) ne les recalcule.
        Cache::put($this->previewKey(), app(ReportBuilder::class)->build($spec), self::PREVIEW_SECONDS);
        $this->previewing = true;
        $this->toast((string) __('steering.reports.toasts.previewed', ['period' => $spec->period()->label()]), 'info');
    }

    /** Demande de génération d'un fichier (job GenerateReport par morceaux). */
    public function generate(RequestReport $request): void
    {
        $spec = $this->spec(true);

        try {
            $request->handle($this->user(), $spec);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages($this->fieldKeys($exception->errors()));
        }

        $this->toast((string) __('steering.reports.toasts.queued'));
        $this->resetPage();
    }

    public function resend(int $id, ReportDelivery $delivery): void
    {
        $this->authorize('reports.generate');
        $report = GeneratedReport::query()->find($id);

        if ($report === null || ! $report->hasFile() || empty($report->recipients) || ($report->hasDetail() && ! $this->user()->can('cases.export'))) {
            $this->toast((string) __('steering.reports.errors.not_found'), 'error');

            return;
        }

        if ($report->hasDetail()) {
            $this->toast((string) __('steering.reports.errors.detail_no_mail'), 'error');

            return;
        }

        $sent = $delivery->send($report);
        $this->toast($sent > 0 ? trans_choice('steering.reports.toasts.resent', $sent, ['count' => $sent]) : (string) __('steering.reports.errors.mail_failed_toast'), $sent > 0 ? 'success' : 'error');
    }

    public function deleteReport(int $id, PurgeExpiredReports $files): void
    {
        $this->authorize('reports.generate');
        $report = GeneratedReport::query()->find($id);

        if ($report === null || ! $report->isFinished()) {
            $this->toast((string) __('steering.reports.errors.not_found'), 'error');

            return;
        }

        $files->deleteFile($report);
        $report->delete();
        Audit::log('report.deleted', null, $report->title, $this->user());
        $this->toast((string) __('steering.reports.toasts.deleted'));
    }

    /** « Vider l'historique » : rapports terminés (fichiers compris) ; les rapports en cours restent. */
    public function clearHistory(PurgeExpiredReports $files): void
    {
        $this->authorize('reports.generate');
        $count = 0;

        GeneratedReport::query()->whereIn('status', [JobStatus::Done->value, JobStatus::Failed->value])->orderBy('id')
            ->each(function (GeneratedReport $report) use ($files, &$count): void {
                $files->deleteFile($report);
                $report->delete();
                $count++;
            });

        Audit::log('report.history_cleared', null, trans_choice('steering.reports.audit.cleared', $count, ['count' => $count]), $this->user());
        $this->resetPage();
        $this->toast(trans_choice('steering.reports.toasts.cleared', $count, ['count' => $count]), $count > 0 ? 'success' : 'info');
    }

    public function saveSchedule(): void
    {
        $this->authorize('reports.generate');
        $weekly = $this->sFrequency === ReportSchedule::FREQUENCY_WEEKLY;
        $emails = $this->parseEmails($this->sRecipients);

        $this->validate([
            'sKind' => ['required', Rule::in($this->scheduleKinds())],
            'sFrequency' => ['required', Rule::in(ReportSchedule::FREQUENCIES)],
            'sDay' => ['required', 'integer', 'min:1', 'max:'.($weekly ? ReportSchedule::MAX_WEEKDAY : ReportSchedule::MAX_DAY_OF_PERIOD)],
            'sFormats' => ['required', 'array', 'min:1'],
            'sFormats.*' => ['string', Rule::in(array_map(static fn (ReportFormat $f): string => $f->value, ReportFormat::downloadable()))],
            'sDimensions' => ['array'],
            'sDimensions.*' => ['string', Rule::in(ReportDimensions::KEYS)],
        ], [
            'sFormats.required' => (string) __('steering.reports.errors.formats_required'),
            'sFormats.min' => (string) __('steering.reports.errors.formats_required'),
            'sDay.max' => (string) __($weekly ? 'steering.reports.errors.weekday_range' : 'steering.reports.errors.day_range'),
            'sDay.min' => (string) __($weekly ? 'steering.reports.errors.weekday_range' : 'steering.reports.errors.day_range'),
        ], $this->scheduleNames());

        $this->validateRecipients($emails, 'sRecipients', true);

        $schedule = new ReportSchedule([
            'kind' => ReportKind::from($this->sKind),
            'frequency' => $this->sFrequency,
            'day_of_period' => $this->sDay,
            'formats' => array_values(array_unique($this->sFormats)),
            'recipients' => $emails,
            'dimensions' => array_values(array_intersect(ReportDimensions::KEYS, $this->sDimensions)),
            'is_active' => true,
            'created_by' => $this->user()->getKey(),
        ]);
        $schedule->next_run_at = $schedule->computeNextRunAt();
        $schedule->save();

        Audit::log('report.schedule_created', $schedule, $schedule->kind->value.' · '.$schedule->frequency, $this->user());
        $this->reset(['sRecipients']);
        $this->toast((string) __('steering.reports.toasts.scheduled', ['frequency' => mb_strtolower((string) __('steering.reports.frequencies.'.$schedule->frequency))]));
    }

    public function toggleSchedule(int $id): void
    {
        $this->authorize('reports.generate');
        $schedule = ReportSchedule::query()->find($id);

        if ($schedule === null) {
            $this->toast((string) __('steering.reports.errors.not_found'), 'error');

            return;
        }

        $schedule->is_active = ! $schedule->is_active;
        // Reprise : l'échéance repart d'aujourd'hui (une pause longue ne déclenche pas des rapports en retard).
        $schedule->next_run_at = $schedule->is_active ? $schedule->computeNextRunAt() : $schedule->next_run_at;
        $schedule->save();
        Audit::log($schedule->is_active ? 'report.schedule_resumed' : 'report.schedule_paused', $schedule, null, $this->user());
        $this->toast((string) __($schedule->is_active ? 'steering.reports.toasts.resumed' : 'steering.reports.toasts.paused'));
    }

    public function deleteSchedule(int $id): void
    {
        $this->authorize('reports.generate');
        $schedule = ReportSchedule::query()->find($id);

        if ($schedule === null) {
            $this->toast((string) __('steering.reports.errors.not_found'), 'error');

            return;
        }

        $schedule->delete();
        Audit::log('report.schedule_deleted', null, $schedule->kind->value.' · '.$schedule->frequency, $this->user());
        $this->toast((string) __('steering.reports.toasts.schedule_deleted'));
    }

    public function render(): View
    {
        $user = $this->user();
        $rows = $this->paginateQuery($this->history());
        [$sortKey, $sortDirection] = $this->currentSort();
        $links = [];

        foreach ($rows as $report) {
            if ($report->status === JobStatus::Done && $report->hasFile() && (! $report->hasDetail() || $user->can('cases.export'))) {
                $links[$report->getKey()] = URL::temporarySignedRoute('admin.rapports.telecharger', now()->addMinutes(5), ['report' => $report->getKey(), 'u' => $user->getKey()]);
            }
        }

        return view('livewire.admin.reports.index', [
            'format' => LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC')),
            'rows' => $rows,
            'sortKey' => $sortKey,
            'sortDirection' => $sortDirection,
            'links' => $links,
            'schedules' => ReportSchedule::query()->orderByDesc('id')->get(),
            'preview' => $this->previewing ? Cache::get($this->previewKey()) : null,
            'canDetail' => (bool) $user->can('cases.export'),
            'hasUnfinished' => GeneratedReport::query()->unfinished()->exists(),
            'kinds' => ReportKind::cases(),
            'scheduleKinds' => array_map(static fn (string $kind): ReportKind => ReportKind::from($kind), $this->scheduleKinds()),
            'formats' => ReportFormat::downloadable(),
            'pdfMax' => (int) config('xomiton.exports.pdf_max_rows', 200),
            'excelMax' => (int) config('xomiton.reports.max_rows', 50000),
        ])->layout('components.layouts.admin', [
            'title' => __('steering.reports.title'),
            'adminPage' => 'rapports',
        ]);
    }

    /** @return Builder<GeneratedReport> */
    private function history(): Builder
    {
        $query = GeneratedReport::query()->with('generatedBy:id,pseudo');

        if ($this->status !== '' && JobStatus::tryFrom($this->status) !== null) {
            $query->where('status', $this->status);
        }

        return $query;
    }

    private function previewKey(): string
    {
        return 'admin-reports-preview:'.$this->user()->getKey();
    }

    /** Remplit « Du » et « Au » et le titre d'un type de rapport périodique (prototype setPeriod). */
    private function applyKind(): void
    {
        $kind = ReportKind::tryFrom($this->kind) ?? ReportKind::Annual;

        if ($kind === ReportKind::Custom) {
            if ($this->from === '' || $this->until === '') {
                $period = ReportCatalog::currentPeriod(ReportKind::Annual);
                $this->from = $period->start->toDateString();
                $this->until = $period->end->toDateString();
            }

            $this->title = $this->title === '' ? $kind->label() : $this->title;

            return;
        }

        $period = ReportCatalog::currentPeriod($kind);
        $this->from = $period->start->toDateString();
        $this->until = $period->end->toDateString();
        $this->title = $kind->label();
    }

    private function spec(bool $withRecipients): ReportSpec
    {
        $emails = $withRecipients ? $this->parseEmails($this->recipients) : [];

        $this->validate([
            'kind' => ['required', Rule::enum(ReportKind::class)],
            'from' => ['required', 'date_format:Y-m-d'],
            'until' => ['required', 'date_format:Y-m-d'],
            'title' => ['required', 'string', 'max:120'],
            'dimensions' => ['array'],
            'dimensions.*' => ['string', Rule::in(ReportDimensions::KEYS)],
            'sections' => ['array'],
            'sections.*' => ['string', Rule::in(ReportCatalog::SECTIONS)],
            'fileFormat' => ['required', Rule::in(array_map(static fn (ReportFormat $f): string => $f->value, ReportFormat::downloadable()))],
            'detail' => ['boolean'],
        ], [], $this->wizardNames());

        if ($withRecipients) {
            $this->validateRecipients($emails, 'recipients', false);
        }

        $start = $this->date($this->from);
        $end = $this->date($this->until);

        if ($start === null || $end === null) {
            throw ValidationException::withMessages(['until' => (string) __('steering.reports.errors.period_invalid')]);
        }

        $spec = new ReportSpec(
            kind: ReportKind::from($this->kind),
            title: trim($this->title),
            start: $start,
            end: $end,
            dimensions: array_values(array_intersect(ReportDimensions::KEYS, $this->dimensions)),
            sections: array_values(array_intersect(ReportCatalog::SECTIONS, $this->sections)),
            detail: $this->detail,
            format: ReportFormat::from($this->fileFormat),
            recipients: $emails,
        );

        if (! $withRecipients) {
            // Aperçu : mêmes bornes de période que la génération, sans registre ni fichier.
            app(RequestReport::class)->validate(new ReportSpec($spec->kind, $spec->title, $spec->start, $spec->end, $spec->dimensions, $spec->sections, false, ReportFormat::Pdf));
        }

        return $spec;
    }

    /**
     * Adresses séparées par des virgules, points-virgules, espaces ou retours à la ligne ; doublons retirés.
     *
     * @return list<string>
     */
    private function parseEmails(string $text): array
    {
        $parts = preg_split('/[\s,;]+/u', trim($text)) ?: [];

        return array_values(array_unique(array_map('mb_strtolower', array_filter($parts, static fn (string $part): bool => $part !== ''))));
    }

    /**
     * @param  list<string>  $emails
     */
    private function validateRecipients(array $emails, string $field, bool $required): void
    {
        if ($required && $emails === []) {
            throw ValidationException::withMessages([$field => (string) __('steering.reports.errors.recipients_required')]);
        }

        if (count($emails) > RequestReport::MAX_RECIPIENTS) {
            throw ValidationException::withMessages([$field => (string) __('steering.reports.errors.recipients_max', ['max' => RequestReport::MAX_RECIPIENTS])]);
        }

        foreach ($emails as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                throw ValidationException::withMessages([$field => (string) __('steering.reports.errors.recipient', ['address' => $address])]);
            }
        }
    }

    /**
     * Clés d'erreur du use case (format, until…) vers les propriétés de l'assistant.
     *
     * @param  array<string, list<string>>  $errors
     * @return array<string, string>
     */
    private function fieldKeys(array $errors): array
    {
        $map = ['format' => 'fileFormat'];
        $messages = [];

        foreach ($errors as $key => $list) {
            $messages[$map[$key] ?? $key] = (string) ($list[0] ?? '');
        }

        return $messages;
    }

    /** @return list<string> */
    private function scheduleKinds(): array
    {
        return [ReportKind::Monthly->value, ReportKind::Quarterly->value, ReportKind::Semiannual->value, ReportKind::Annual->value, ReportKind::Custom->value];
    }

    private function date(string $value): ?CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (InvalidFormatException) {
            return null;
        }

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    /** @return array<string, string> */
    private function wizardNames(): array
    {
        return ['kind' => (string) __('steering.reports.fields.kind'), 'from' => (string) __('steering.reports.fields.from'), 'until' => (string) __('steering.reports.fields.until'), 'title' => (string) __('steering.reports.fields.title'), 'fileFormat' => (string) __('steering.reports.fields.format')];
    }

    /** @return array<string, string> */
    private function scheduleNames(): array
    {
        return ['sKind' => (string) __('steering.reports.fields.kind'), 'sFrequency' => (string) __('steering.reports.fields.frequency'), 'sDay' => (string) __('steering.reports.fields.day'), 'sFormats' => (string) __('steering.reports.fields.formats')];
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
