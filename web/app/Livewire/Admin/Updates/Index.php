<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Updates;

use App\Application\Platform\MaintenanceStatus;
use App\Application\Platform\Updates\ManageVersions;
use App\Application\Platform\Updates\RunOptimization;
use App\Application\Platform\Updates\UsageInsights;
use App\Domain\Shared\Exceptions\DomainException;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Models\AppVersion;
use App\Models\Backup;
use App\Models\Setting;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Mises à jour & optimisation `/admin/mises-a-jour` (P13-02, prototype admin/mises-a-jour.php ; lecture `updates.view`
 * admin et superadmin, écriture `updates.manage` superadmin) : KPI (version actuelle, versions planifiées, sauvegardes,
 * maintenance), historique des versions (`app_versions`), publier ou planifier une version, déployer ou annuler une
 * planification, version minimale de l'application mobile (`GET /api/v1/app/version`), tâches d'optimisation dont
 * « Mise à jour du cache hors connexion » (révision du service worker), contenus les plus consultés et adoption des
 * fonctionnalités. Les fenêtres de maintenance vivent dans /admin/maintenance (P13-06) : un lien seulement.
 */
final class Index extends Component
{
    use InteractsWithUi;
    use WithDataTable;

    #[SafeUrl(as: 'st', except: '')]
    public string $status = '';

    // --- Publier ou planifier ---
    public string $version = '';

    public string $kind = AppVersion::KIND_FIX;

    public string $notes = '';

    public string $mode = 'now';

    public string $scheduledFor = '';

    // --- Application mobile ---
    public string $minVersion = '';

    public function mount(): void
    {
        $this->authorize('viewAny', AppVersion::class);
        $this->scheduledFor = now((string) config('xomiton.display_timezone', 'UTC'))->addDays(7)->toDateString();
        $this->minVersion = Setting::get('app_min_version', '1.0.0') ?: '1.0.0';
    }

    protected function sortableColumns(): array
    {
        return ['date' => 'created_at', 'version' => 'id'];
    }

    protected function searchableColumns(): array
    {
        return ['version'];
    }

    protected function defaultSort(): array
    {
        return ['date', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['status'];
    }

    public function release(ManageVersions $versions): void
    {
        $this->authorize('create', AppVersion::class);
        $this->resetErrorBag();

        $this->validate([
            'version' => ['required', 'regex:'.AppVersion::VERSION_PATTERN, 'max:20'],
            'kind' => ['required', Rule::in(AppVersion::KINDS)],
            'notes' => ['required', 'string', 'min:10', 'max:2000'],
            'mode' => ['required', Rule::in(['now', 'plan'])],
            'scheduledFor' => [Rule::requiredIf($this->mode === 'plan'), 'nullable', 'date_format:Y-m-d'],
        ], [
            'version.regex' => (string) __('admin_system.updates.errors.version_format'),
        ], [
            'version' => (string) __('admin_system.updates.form.version'),
            'kind' => (string) __('admin_system.updates.form.kind'),
            'notes' => (string) __('admin_system.updates.form.notes'),
            'scheduledFor' => (string) __('admin_system.updates.form.date'),
        ]);

        try {
            if ($this->mode === 'plan') {
                $row = $versions->plan(auth()->user(), $this->version, $this->kind, $this->notes, Carbon::createFromFormat('Y-m-d', $this->scheduledFor, (string) config('xomiton.display_timezone', 'UTC'))->startOfDay());
                $message = __('admin_system.updates.toasts.planned', ['version' => $row->version, 'date' => $this->formatter()->longDate($row->scheduled_for)]);
            } else {
                $row = $versions->publish(auth()->user(), $this->version, $this->kind, $this->notes);
                $message = __('admin_system.updates.toasts.published', ['version' => $row->version]);
            }
        } catch (DomainException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, (string) $messages[0]);
            }

            return;
        }

        $this->reset('version', 'notes');
        $this->resetPage();
        unset($this->stats);
        $this->toast((string) $message);
    }

    public function deploy(int $id, ManageVersions $versions): void
    {
        $row = AppVersion::query()->findOrFail($id);
        $this->authorize('update', $row);

        try {
            $versions->deploy(auth()->user(), $row);
        } catch (DomainException $exception) {
            $this->toast($exception->userMessage(), 'error');

            return;
        }

        unset($this->stats);
        $this->toast((string) __('admin_system.updates.toasts.deployed', ['version' => $row->version]));
    }

    public function cancel(int $id, ManageVersions $versions): void
    {
        $row = AppVersion::query()->findOrFail($id);
        $this->authorize('update', $row);

        try {
            $versions->cancel(auth()->user(), $row);
        } catch (DomainException $exception) {
            $this->toast($exception->userMessage(), 'error');

            return;
        }

        unset($this->stats);
        $this->toast((string) __('admin_system.updates.toasts.cancelled', ['version' => $row->version]), 'warning');
    }

    public function saveMinimum(ManageVersions $versions): void
    {
        $this->authorize('create', AppVersion::class);
        $this->resetErrorBag('minVersion');

        try {
            $versions->setMinimum(auth()->user(), trim($this->minVersion));
        } catch (ValidationException $exception) {
            $this->addError('minVersion', (string) ($exception->errors()['app_min_version'][0] ?? __('admin_system.updates.errors.version_format')));

            return;
        } catch (DomainException $exception) {
            $this->addError('minVersion', $exception->userMessage());

            return;
        }

        $this->toast((string) __('admin_system.updates.toasts.minimum_saved', ['version' => trim($this->minVersion)]));
    }

    public function run(string $task, RunOptimization $optimize): void
    {
        $this->authorize('create', AppVersion::class);

        try {
            $result = $optimize(auth()->user(), $task);
        } catch (DomainException $exception) {
            $this->toast($exception->userMessage(), 'error');

            return;
        }

        unset($this->optimization);
        $this->toast($result);
    }

    /**
     * @return array{current: string, updated: string|null, planned: int, backups: int, lastBackup: Backup|null, maintenance: bool}
     */
    #[Computed]
    public function stats(): array
    {
        $updated = Setting::get('last_update');

        return [
            'current' => ManageVersions::currentVersion(),
            'updated' => is_string($updated) && $updated !== '' ? $updated : null,
            'planned' => AppVersion::query()->planned()->count(),
            'backups' => Backup::query()->completed()->count(),
            'lastBackup' => Backup::query()->completed()->latestFirst()->first(),
            'maintenance' => MaintenanceStatus::active(),
        ];
    }

    /**
     * @return array<string, array{at: string, result: string, by: string}>
     */
    #[Computed]
    public function optimization(): array
    {
        return RunOptimization::state();
    }

    public function render(): View
    {
        $query = AppVersion::query()->with('creator:id,pseudo');

        if (in_array($this->status, AppVersion::STATUSES, true)) {
            $query->where('status', $this->status);
        }

        [$sortKey, $sortDirection] = $this->currentSort();
        $insights = new UsageInsights;
        $user = auth()->user();

        return view('livewire.admin.updates.index', [
            'rows' => $this->paginateQuery($query),
            'sortKey' => $sortKey,
            'sortDirection' => $sortDirection,
            'format' => $this->formatter(),
            'canManage' => (bool) $user?->can('updates.manage'),
            'top' => $insights->topContent(),
            'adoption' => $insights->adoption(),
            'unsynced' => $insights->peersWithoutSync(),
            'tasks' => RunOptimization::TASKS,
            'minimum' => Setting::get('app_min_version', '1.0.0'),
        ])->layout('components.layouts.admin', [
            'title' => __('admin_system.updates.title'),
            'adminPage' => 'mises-a-jour',
        ]);
    }

    private function formatter(): LocaleFormatter
    {
        return LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC'));
    }
}
