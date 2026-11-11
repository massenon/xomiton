<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Sync;

use App\Application\Platform\Settings\SettingsCatalog;
use App\Application\Platform\Settings\UpdateSettings;
use App\Application\Sync\Admin\ConflictValue;
use App\Application\Sync\Admin\RequestDeviceWipe;
use App\Application\Sync\Admin\ResolveSyncConflict;
use App\Application\Sync\Admin\RevokeDevice;
use App\Domain\Shared\Exceptions\DomainException;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Models\AuditLog;
use App\Models\SyncConflict;
use App\Models\SyncDevice;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Hors connexion & synchro `/admin/synchronisation` (P13-01, prototype admin/synchronisation.php ; permission
 * `sync.manage`, admin et superadmin) : KPI (appareils suivis, éléments en attente, conflits à résoudre, sans synchro depuis
 * plus de 24 h), appareils et pair·es (filtre, tri, recherche sur le libellé et la plateforme, révocation, effacement à distance `device.wipe`), résolution des
 * conflits — valeur du serveur, de l'appareil ou fusion saisie à la main (ResolveSyncConflict, choix journalisé, valeurs
 * chiffrées en base et jamais écrites dans le journal, ADR-33) —, paramètres de synchronisation (catalogue typé),
 * journal de synchronisation et cache local de ce navigateur (script d'écran `sync`). Heures dans le fuseau d'affichage.
 */
final class Index extends Component
{
    use InteractsWithUi;
    use WithDataTable;

    public const VIEWS = ['stale', 'pending', 'conflicts', 'revoked'];

    #[SafeUrl(as: 'vue', except: '')]
    public string $view = '';

    // --- Fusion (modale) ---
    #[Locked]
    public ?int $mergeId = null;

    public string $merged = '';

    // --- Paramètres : volontairement sans type natif (un champ vidé reviendrait à la valeur par défaut, cf. Journal). ---
    public bool $offlineSync = true;

    /** @var int|string|null */
    public $interval = 15;

    /** @var int|string|null */
    public $maxMb = 5;

    public bool $wifiOnly = false;

    public function mount(): void
    {
        $this->authorize('viewAny', SyncDevice::class);
        $this->loadSettings();
    }

    protected function sortableColumns(): array
    {
        return ['appareil' => 'label', 'synchro' => 'last_sync_at', 'attente' => 'pending_count', 'conflits' => 'conflicts_count'];
    }

    protected function searchableColumns(): array
    {
        return ['label', 'platform'];
    }

    protected function defaultSort(): array
    {
        return ['synchro', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['view'];
    }

    public function resolve(int $id, string $choice, ResolveSyncConflict $resolve): void
    {
        $conflict = SyncConflict::query()->findOrFail($id);
        $this->authorize('resolve', $conflict);

        try {
            $resolve(auth()->user(), $conflict, $choice);
        } catch (DomainException $exception) {
            $this->toast($exception->userMessage(), 'error');
            unset($this->openConflicts, $this->stats);

            return;
        }

        unset($this->openConflicts, $this->resolvedConflicts, $this->stats);
        $this->toast((string) __('admin_system.sync.toasts.resolved_'.$choice));
    }

    public function openMerge(int $id): void
    {
        $conflict = SyncConflict::query()->findOrFail($id);
        $this->authorize('resolve', $conflict);
        $this->mergeId = $conflict->getKey();
        $this->merged = (string) ($conflict->device_value ?? '');
        $this->resetErrorBag();
        $this->openModal('xmSyncMerge');
    }

    public function saveMerge(ResolveSyncConflict $resolve): void
    {
        $conflict = SyncConflict::query()->findOrFail((int) $this->mergeId);
        $this->authorize('resolve', $conflict);
        $this->resetErrorBag();
        $this->validate(['merged' => ['required', 'string', 'max:4000']], [], ['merged' => (string) __('admin_system.sync.merge.field')]);

        try {
            $resolve(auth()->user(), $conflict, SyncConflict::RESOLUTION_MERGE, $this->merged);
        } catch (DomainException $exception) {
            $this->addError('merged', $exception->userMessage());

            return;
        }

        $this->reset('merged', 'mergeId');
        $this->closeModal('xmSyncMerge');
        unset($this->openConflicts, $this->resolvedConflicts, $this->stats);
        $this->toast((string) __('admin_system.sync.toasts.resolved_fusion'));
    }

    public function revoke(int $id, RevokeDevice $revoke): void
    {
        $device = SyncDevice::query()->findOrFail($id);
        $this->authorize('revoke', $device);
        $revoke(auth()->user(), $device);
        unset($this->stats);
        $this->toast((string) __('admin_system.sync.toasts.revoked', ['label' => $device->label]), 'warning');
    }

    /** Effacement à distance (P15-11, `device.wipe`) : l'appareil est révoqué et vide ses données à son prochain contact. */
    public function wipe(int $id, RequestDeviceWipe $wipe): void
    {
        $device = SyncDevice::query()->findOrFail($id);
        $this->authorize('wipe', $device);
        $wipe(auth()->user(), $device);
        unset($this->stats);
        $this->toast((string) __('admin_system.sync.toasts.wipe_requested', ['label' => $device->label]), 'warning');
    }

    public function saveSettings(UpdateSettings $update): void
    {
        $this->authorize('viewAny', SyncDevice::class);

        try {
            $changed = $update(auth()->user(), [
                'offline_sync' => $this->offlineSync,
                'sync_interval_minutes' => $this->interval,
                'sync_max_mb' => $this->maxMb,
                'sync_wifi_only' => $this->wifiOnly,
            ], 'sync.manage');
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                $this->addError(match ($key) {
                    'sync_interval_minutes' => 'interval',
                    'sync_max_mb' => 'maxMb',
                    default => 'settings',
                }, (string) $messages[0]);
            }

            return;
        }

        $this->resetErrorBag();
        $this->loadSettings();
        $this->toast((string) __($changed === [] ? 'admin_system.sync.toasts.settings_unchanged' : 'admin_system.sync.toasts.settings_saved'), $changed === [] ? 'info' : 'success');
    }

    /**
     * @return array{devices: int, pending: int, conflicts: int, stale: int, revoked: int}
     */
    #[Computed]
    public function stats(): array
    {
        $active = SyncDevice::query()->active();
        $threshold = now()->subHours((int) config('xomiton.sync.device_stale_hours', 24));

        return [
            'devices' => (clone $active)->count(),
            'pending' => (int) (clone $active)->sum('pending_count'),
            'conflicts' => SyncConflict::query()->open()->count(),
            'stale' => (clone $active)->where(fn (Builder $q) => $q->whereNull('last_sync_at')->orWhere('last_sync_at', '<', $threshold))->count(),
            'revoked' => SyncDevice::query()->revoked()->count(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, SyncConflict>
     */
    #[Computed]
    public function openConflicts(): \Illuminate\Support\Collection
    {
        return SyncConflict::query()->open()->with(['device.user:id,pseudo', 'subject'])->latestFirst()->limit(50)->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, SyncConflict>
     */
    #[Computed]
    public function resolvedConflicts(): \Illuminate\Support\Collection
    {
        return SyncConflict::query()->resolved()->with(['device.user:id,pseudo', 'resolver:id,pseudo', 'subject'])->orderByDesc('resolved_at')->limit(8)->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, AuditLog>
     */
    #[Computed]
    public function journal(): \Illuminate\Support\Collection
    {
        return AuditLog::query()
            ->where(fn (Builder $q) => $q->where('action', 'like', 'sync.%')->orWhere('action', 'like', 'device.%'))
            ->latestFirst()->limit(15)->get();
    }

    public function render(): View
    {
        $query = SyncDevice::query()->with('user:id,pseudo');
        $stale = now()->subHours((int) config('xomiton.sync.device_stale_hours', 24));

        match ($this->view) {
            'stale' => $query->active()->where(fn (Builder $q) => $q->whereNull('last_sync_at')->orWhere('last_sync_at', '<', $stale)),
            'pending' => $query->active()->where('pending_count', '>', 0),
            'conflicts' => $query->where('conflicts_count', '>', 0),
            'revoked' => $query->revoked(),
            default => null,
        };

        [$sortKey, $sortDirection] = $this->currentSort();

        return view('livewire.admin.sync.index', [
            'rows' => $this->paginateQuery($query),
            'sortKey' => $sortKey,
            'sortDirection' => $sortDirection,
            'format' => LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC')),
            'value' => new ConflictValue,
            'staleHours' => (int) config('xomiton.sync.device_stale_hours', 24),
            'staleAt' => $stale,
            'mergeConflict' => $this->mergeId === null ? null : SyncConflict::query()->find($this->mergeId),
            'mergeable' => ResolveSyncConflict::MERGEABLE,
            'canSeeCase' => static fn (mixed $subject): bool => $subject !== null && Gate::allows('view', $subject),
        ])->layout('components.layouts.admin', [
            'title' => __('admin_system.sync.title'),
            'adminPage' => 'synchronisation',
        ]);
    }

    private function loadSettings(): void
    {
        $this->offlineSync = (bool) SettingsCatalog::value('offline_sync');
        $this->interval = (int) SettingsCatalog::value('sync_interval_minutes');
        $this->maxMb = (int) SettingsCatalog::value('sync_max_mb');
        $this->wifiOnly = (bool) SettingsCatalog::value('sync_wifi_only');
    }
}
