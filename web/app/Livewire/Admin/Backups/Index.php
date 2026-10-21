<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Backups;

use App\Application\Platform\Backup\BackupService;
use App\Application\Platform\Backup\RestoreState;
use App\Application\Platform\Backup\StartRestore;
use App\Domain\Platform\Exceptions\BackupException;
use App\Enums\BackupKind;
use App\Enums\JobStatus;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Backup;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Sauvegardes `/admin/sauvegardes` (P13-03, ADR-22 ; prototype admin/mises-a-jour.php #sauvegardes) : KPI, « Sauvegarder
 * maintenant » (`backups.create`, admin et superadmin), liste triable (type, taille, lignes, état), aperçu en modale
 * (collections, écart avec l'état actuel, extrait des cinq premiers enregistrements **masqués**), téléchargement
 * (`backups.download`), suppression et restauration (superadmin : `backups.delete`, `backups.restore`). La restauration
 * exige la saisie de « RESTAURER » (revérifiée côté serveur) puis suit la chaîne de jobs RestoreBackup : état visible par
 * polling (RestoreState), reprise possible après un échec. Heures dans le fuseau d'affichage.
 */
final class Index extends Component
{
    use InteractsWithUi;
    use WithDataTable;

    #[SafeUrl(as: 'ty', except: '')]
    public string $kind = '';

    #[Locked]
    public ?int $previewId = null;

    #[Locked]
    public ?int $restoreId = null;

    public string $sampleTable = '';

    public string $restoreWord = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Backup::class);
    }

    protected function sortableColumns(): array
    {
        return ['date' => 'created_at', 'nom' => 'name', 'taille' => 'size_bytes'];
    }

    protected function searchableColumns(): array
    {
        return ['name'];
    }

    protected function defaultSort(): array
    {
        return ['date', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['kind'];
    }

    public function create(BackupService $service): void
    {
        $this->authorize('create', Backup::class);

        try {
            $backup = $service->create(BackupKind::Manual, auth()->user());
        } catch (BackupException $exception) {
            $this->toast($exception->userMessage(), 'error');

            return;
        }

        $this->resetPage();
        $this->toast((string) __('admin_system.backups.toasts.created', ['name' => $backup->name, 'size' => $backup->humanSize()]));
    }

    public function preview(int $id): void
    {
        $backup = Backup::query()->findOrFail($id);
        $this->authorize('view', $backup);
        $this->previewId = $backup->id;
        $this->sampleTable = '';
        unset($this->previewData, $this->sampleRows);
        $this->openModal('xmBackupPreview');
    }

    public function sample(string $table): void
    {
        $this->sampleTable = $table;
        unset($this->sampleRows);
    }

    public function delete(int $id, BackupService $service): void
    {
        $backup = Backup::query()->findOrFail($id);
        $this->authorize('delete', $backup);

        try {
            $service->delete($backup, auth()->user());
        } catch (BackupException $exception) {
            $this->toast($exception->userMessage(), 'error');

            return;
        }

        $this->toast((string) __('admin_system.backups.toasts.deleted', ['name' => $backup->name]), 'warning');
    }

    public function confirmRestore(int $id): void
    {
        $backup = Backup::query()->findOrFail($id);
        $this->authorize('restore', $backup);
        $this->restoreId = $backup->id;
        $this->restoreWord = '';
        $this->resetErrorBag();
        $this->closeModal('xmBackupPreview');
        $this->openModal('xmBackupRestore');
    }

    public function startRestore(StartRestore $start): void
    {
        $backup = Backup::query()->findOrFail((int) $this->restoreId);
        $this->authorize('restore', $backup);
        $this->resetErrorBag();

        try {
            $start(auth()->user(), $backup, $this->restoreWord);
        } catch (BackupException $exception) {
            $this->addError('restoreWord', $exception->userMessage());

            return;
        }

        $this->reset('restoreWord');
        $this->closeModal('xmBackupRestore');
        $this->toast((string) __('admin_system.backups.toasts.restore_started', ['name' => $backup->name]), 'warning');
    }

    public function resume(StartRestore $start): void
    {
        $start->resume(auth()->user());
        $this->toast((string) __('admin_system.backups.toasts.restore_resumed'), 'warning');
    }

    /** Ferme le suivi d'une restauration terminée ou en échec. */
    public function dismissRestore(): void
    {
        $this->authorize('create', Backup::class);
        $state = RestoreState::read();

        if ($state !== null && ! RestoreState::active($state)) {
            RestoreState::clear();
        }
    }

    /** Appelée par le polling : le rendu relit l'état. */
    public function refreshRestore(): void {}

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function previewData(): ?array
    {
        if ($this->previewId === null) {
            return null;
        }

        $backup = Backup::query()->find($this->previewId);

        if ($backup === null) {
            return null;
        }

        try {
            return app(BackupService::class)->preview($backup);
        } catch (BackupException $exception) {
            return ['error' => $exception->userMessage()];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function sampleRows(): array
    {
        $backup = $this->previewId === null || $this->sampleTable === '' ? null : Backup::query()->find($this->previewId);

        if ($backup === null) {
            return [];
        }

        try {
            return app(BackupService::class)->sample($backup, $this->sampleTable);
        } catch (BackupException) {
            return [];
        }
    }

    /**
     * @return array{count: int, last: ?Backup, size: int, restored: ?Backup}
     */
    #[Computed]
    public function stats(): array
    {
        $done = Backup::query()->completed();

        return [
            'count' => (clone $done)->count(),
            'last' => (clone $done)->latestFirst()->first(),
            'size' => (int) (clone $done)->sum('size_bytes'),
            'restored' => Backup::query()->restored()->orderByDesc('restored_at')->first(),
        ];
    }

    public function render(): View
    {
        $query = Backup::query()->with('creator:id,pseudo');

        if (BackupKind::tryFrom($this->kind) !== null) {
            $query->ofKind(BackupKind::from($this->kind));
        }

        [$sortKey, $sortDirection] = $this->currentSort();
        $user = auth()->user();
        $state = RestoreState::read();

        return view('livewire.admin.backups.index', [
            'rows' => $this->paginateQuery($query),
            'sortKey' => $sortKey,
            'sortDirection' => $sortDirection,
            'format' => LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC')),
            'canCreate' => (bool) $user?->can('create', Backup::class),
            'canRestore' => (bool) $user?->can('backups.restore'),
            'canDelete' => (bool) $user?->can('backups.delete'),
            'canDownload' => (bool) $user?->can('backups.download'),
            'zip' => BackupService::zipAvailable(),
            'passwordFromKey' => app(BackupService::class)->passwordSource() === 'app_key',
            'disk' => (string) config('xomiton.backup.disk', 'local'),
            'restore' => $state,
            'restoreActive' => RestoreState::active($state),
            'restorePercent' => $state === null ? 0 : RestoreState::percent($state),
            'restoreBackup' => $this->restoreId === null ? null : Backup::query()->find($this->restoreId),
            'statuses' => JobStatus::cases(),
        ])->layout('components.layouts.admin', [
            'title' => __('admin_system.backups.title'),
            'adminPage' => 'sauvegardes',
        ]);
    }
}
