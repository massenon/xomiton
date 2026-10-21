{{--
    Sauvegardes (P13-03, ADR-22) : composant App\Livewire\Admin\Backups\Index, gabarit admin. Ordre : introduction · alertes
    (ZIP absent, mot de passe dérivé d'APP_KEY) · suivi de restauration (polling) · KPI · liste (en-tête + « Sauvegarder
    maintenant », filtres, tableau) · modale d'aperçu · modale rouge de restauration (saisie « RESTAURER »). Modales
    toujours montées (x-admin.modal).
--}}
@php
    $restoreFinished = $restore !== null && ! $restoreActive;
@endphp
<div class="xm-backups">
    <p class="text-secondary mb-4 xm-prose">{{ __('admin_system.backups.intro') }}</p>

    @unless ($zip)
        <x-alert type="danger" icon="exclamation-octagon">{{ __('admin_system.backups.errors.zip_missing') }}</x-alert>
    @endunless
    @if ($passwordFromKey)
        <x-alert type="warning" icon="key">{{ __('admin_system.backups.password_from_key') }}</x-alert>
    @endif

    {{-- Suivi de la restauration : polling tant qu'elle tourne, reprise ou fermeture ensuite. --}}
    @if ($restore !== null)
        <section class="card xm-admin-card mb-4 border-warning" aria-labelledby="xmRestoreTitle" @if ($restoreActive) wire:poll.5s="refreshRestore" @endif>
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                    <h2 class="h6 fw-bold mb-0" id="xmRestoreTitle">
                        <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>{{ __('admin_system.backups.restore.title', ['name' => $restore['backup_name'] ?? '']) }}
                    </h2>
                    <x-badge :color="match ($restore['status']) { 'done' => 'success', 'failed' => 'danger', default => 'warning' }" soft>{{ __('admin_system.backups.restore.status.'.$restore['status']) }}</x-badge>
                </div>
                <div class="progress mb-2" role="progressbar" aria-label="{{ __('admin_system.backups.restore.progress') }}" aria-valuenow="{{ $restorePercent }}" aria-valuemin="0" aria-valuemax="100" style="height:.6rem">
                    <div @class(['progress-bar', 'bg-danger' => $restore['status'] === 'failed', 'bg-success' => $restore['status'] === 'done']) style="width: {{ $restorePercent }}%"></div>
                </div>
                <p class="small mb-1" role="status">
                    {{ __('admin_system.backups.restore.steps.'.$restore['step']) }}
                    · {{ $restorePercent }} %
                    @if ($restore['rows_done'] > 0) · {{ __('admin_system.backups.restore.rows', ['rows' => $format->number((int) $restore['rows_done'])]) }}@endif
                </p>
                @if ($restore['safety_backup_name'])
                    <p class="small text-secondary mb-1"><i class="bi bi-shield-check me-1" aria-hidden="true"></i>{{ __('admin_system.backups.restore.safety', ['name' => $restore['safety_backup_name']]) }}</p>
                @endif
                @if ($restore['status'] === 'failed')
                    <x-alert type="danger" class="mt-2 mb-2">
                        <strong>{{ __('admin_system.backups.restore.failed') }}</strong>
                        <span class="d-block small">{{ $restore['error'] }}</span>
                        @if ($restore['safety_backup_name'])<span class="d-block small">{{ __('admin_system.backups.restore.failed_hint', ['name' => $restore['safety_backup_name']]) }}</span>@endif
                    </x-alert>
                @elseif ($restore['status'] === 'done')
                    <p class="small text-success mb-0"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ __('admin_system.backups.restore.done', ['date' => $format->tableDateTime(\Illuminate\Support\Carbon::parse($restore['finished_at']))]) }}</p>
                @else
                    <p class="small text-secondary mb-0">{{ __('admin_system.backups.restore.running') }}</p>
                @endif
                @if ($restoreFinished)
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        @if ($restore['status'] === 'failed' && $canRestore)
                            <button type="button" class="btn btn-sm btn-warning" wire:click="resume"><i class="bi bi-play-fill me-1" aria-hidden="true"></i>{{ __('admin_system.backups.restore.resume') }}</button>
                        @endif
                        @if ($canCreate)
                            <button type="button" class="btn btn-sm btn-light border" wire:click="dismissRestore">{{ __('admin_system.backups.restore.dismiss') }}</button>
                        @endif
                    </div>
                @endif
            </div>
        </section>
    @endif

    <x-admin.kpi id="xmBackupKpis" :items="[
        ['label' => __('admin_system.backups.kpi.count'), 'value' => $format->number($this->stats['count']), 'icon' => 'database-check', 'variant' => 'violet'],
        ['label' => __('admin_system.backups.kpi.last'), 'value' => $this->stats['last'] ? $format->ago($this->stats['last']->created_at) : '—', 'icon' => 'clock-history', 'sub' => $this->stats['last']?->name],
        ['label' => __('admin_system.backups.kpi.size'), 'value' => $format->fileSize($this->stats['size']), 'icon' => 'hdd'],
        ['label' => __('admin_system.backups.kpi.restored'), 'value' => $this->stats['restored'] ? $format->shortDate($this->stats['restored']->restored_at) : __('admin_system.backups.kpi.never'), 'icon' => 'arrow-counterclockwise', 'variant' => 'warning'],
    ]" />

    <section class="card xm-admin-card" aria-labelledby="xmBackupListTitle">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <h2 class="h6 fw-bold mb-0" id="xmBackupListTitle">{{ __('admin_system.backups.title') }}</h2>
                <div class="small text-secondary">{{ __('admin_system.backups.storage', ['disk' => $disk]) }}</div>
            </div>
            @if ($canCreate)
                <button type="button" class="btn btn-primary btn-sm" wire:click="create" wire:loading.attr="disabled" wire:target="create" @disabled(! $zip || $restoreActive)>
                    <span wire:loading.remove wire:target="create"><i class="bi bi-database-down me-1" aria-hidden="true"></i></span><span class="spinner-border spinner-border-sm me-1" wire:loading wire:target="create" aria-hidden="true"></span>{{ __('admin_system.backups.create') }}
                </button>
            @endif
        </div>
        <div class="card-body border-bottom">
            <x-admin.filters id="xmBackupFilters" :active="$this->activeFilterCount()" :placeholder="__('admin_system.backups.filters.search_placeholder')" :search-help="__('admin_system.backups.filters.search_help')">
                <div class="col-12 col-sm-4 col-lg-3">
                    <label class="visually-hidden" for="xmBackupKind">{{ __('admin_system.backups.filters.kind') }}</label>
                    <select class="form-select form-select-sm" id="xmBackupKind" wire:model.live="kind">
                        <option value="">{{ __('admin_system.backups.filters.all_kinds') }}</option>
                        @foreach (\App\Enums\BackupKind::cases() as $case)
                            <option value="{{ $case->value }}">{{ $case->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </x-admin.filters>
        </div>

        <x-admin.table id="xmBackupTable" :caption="__('admin_system.backups.table_caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
            ['key' => 'date', 'label' => __('admin_system.backups.columns.date'), 'sortable' => true, 'width' => '11rem'],
            ['key' => 'type', 'label' => __('admin_system.backups.columns.kind')],
            ['key' => 'taille', 'label' => __('admin_system.backups.columns.size'), 'sortable' => true, 'class' => 'text-end'],
            ['key' => 'lignes', 'label' => __('admin_system.backups.columns.rows'), 'class' => 'text-end'],
            ['key' => 'etat', 'label' => __('admin_system.backups.columns.status')],
            ['key' => 'actions', 'label' => '', 'sr' => __('admin_system.backups.columns.actions'), 'class' => 'text-end'],
        ]">
            @foreach ($rows as $backup)
                <tr wire:key="backup-{{ $backup->id }}">
                    <td data-label="{{ __('admin_system.backups.columns.date') }}">
                        <div class="min-w-0">
                            <time class="d-block fw-semibold" datetime="{{ $backup->created_at?->toIso8601String() }}">{{ $format->tableDateTime($backup->created_at) }}</time>
                            <code class="small text-secondary">{{ $backup->name }}</code>
                        </div>
                    </td>
                    <td data-label="{{ __('admin_system.backups.columns.kind') }}">
                        <div class="min-w-0">
                            <x-badge :color="$backup->kind->badge()" soft>{{ $backup->kind->label() }}</x-badge>
                            @if ($backup->isRestored())<span class="d-block small text-secondary mt-1">{{ __('admin_system.backups.restored_on', ['date' => $format->shortDate($backup->restored_at)]) }}</span>@endif
                        </div>
                    </td>
                    <td data-label="{{ __('admin_system.backups.columns.size') }}" class="text-end text-nowrap">{{ $backup->status === \App\Enums\JobStatus::Done ? $format->fileSize($backup->size_bytes) : '—' }}</td>
                    <td data-label="{{ __('admin_system.backups.columns.rows') }}" class="text-end">{{ $backup->status === \App\Enums\JobStatus::Done ? $format->number($backup->rowsCount()) : '—' }}</td>
                    <td data-label="{{ __('admin_system.backups.columns.status') }}"><x-badge :color="$backup->status->badge() === 'secondary' ? 'neutral' : $backup->status->badge()" soft>{{ $backup->status->label() }}</x-badge></td>
                    <td data-label="{{ __('admin_system.backups.columns.actions') }}" class="text-end xm-table__actions">
                        @if ($backup->isRestorable())
                            <div class="d-inline-flex gap-1">
                                <button type="button" class="btn btn-sm btn-light" wire:click="preview({{ $backup->id }})" title="{{ __('admin_system.backups.actions.preview') }}" aria-label="{{ __('admin_system.backups.actions.preview_of', ['name' => $backup->name]) }}"><i class="bi bi-eye" aria-hidden="true"></i></button>
                                @if ($canDownload)
                                    <a class="btn btn-sm btn-light" href="{{ route('admin.sauvegardes.telecharger', $backup) }}" title="{{ __('admin_system.backups.actions.download') }}" aria-label="{{ __('admin_system.backups.actions.download_of', ['name' => $backup->name]) }}"><i class="bi bi-download" aria-hidden="true"></i></a>
                                @endif
                                @if ($canRestore)
                                    <button type="button" class="btn btn-sm btn-light text-warning-emphasis" wire:click="confirmRestore({{ $backup->id }})" @disabled($restoreActive) title="{{ __('admin_system.backups.actions.restore') }}" aria-label="{{ __('admin_system.backups.actions.restore_of', ['name' => $backup->name]) }}"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button>
                                @endif
                                @if ($canDelete && $backup->kind->isDeletable())
                                    <button type="button" class="btn btn-sm btn-light text-danger" wire:click="delete({{ $backup->id }})" wire:confirm="{{ __('admin_system.backups.confirm_delete', ['name' => $backup->name]) }}" title="{{ __('admin_system.backups.actions.delete') }}" aria-label="{{ __('admin_system.backups.actions.delete_of', ['name' => $backup->name]) }}"><i class="bi bi-trash" aria-hidden="true"></i></button>
                                @endif
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
            <x-slot:emptyState>
                <x-admin.empty icon="database-x" :filtered="$this->activeFilterCount() > 0" :title="__('admin_system.backups.empty')">{{ __('admin_system.backups.empty_text') }}</x-admin.empty>
            </x-slot:emptyState>
            <x-slot:footer>
                <x-admin.pagination id="xmBackupPager" :paginator="$rows" />
            </x-slot:footer>
        </x-admin.table>
    </section>

    {{-- Aperçu (KPI, collections avec écart, extrait masqué) --}}
    <x-admin.modal id="xmBackupPreview" type="form" size="xl" :title="__('admin_system.backups.preview.title')">
        @php($preview = $this->previewData)
        @if ($preview === null)
            <p class="text-secondary mb-0">{{ __('admin_system.backups.preview.empty') }}</p>
        @elseif (isset($preview['error']))
            <x-alert type="danger" class="mb-0">{{ $preview['error'] }}</x-alert>
        @else
            <p class="small text-secondary">{{ __('admin_system.backups.preview.meta', ['name' => $preview['name'], 'date' => $preview['created_at'] !== '' ? $format->tableDateTime(\Illuminate\Support\Carbon::parse($preview['created_at'])) : '—', 'tables' => $format->number(count($preview['tables'])), 'size' => $format->fileSize($preview['size'])]) }}</p>
            <div class="row g-2 mb-3">
                @foreach ([
                    [__('admin_system.backups.preview.collections'), count($preview['tables']), 'files'],
                    [__('admin_system.backups.preview.records'), $preview['rows'], 'list-ol'],
                    [__('admin_system.backups.preview.size'), $format->fileSize($preview['size']), 'hdd'],
                    [__('admin_system.backups.preview.changed'), $preview['changed'], 'arrow-left-right'],
                ] as [$label, $value, $icon])
                    <div class="col-6 col-md-3"><div class="border rounded p-2 h-100"><div class="small text-secondary"><i class="bi bi-{{ $icon }} me-1" aria-hidden="true"></i>{{ $label }}</div><div class="fw-bold fs-5">{{ is_int($value) ? $format->number($value) : $value }}</div></div></div>
                @endforeach
            </div>
            <div class="xm-table-wrap mb-3">
                <table class="table table-sm table-hover align-middle mb-0">
                    <caption class="visually-hidden">{{ __('admin_system.backups.preview.caption') }}</caption>
                    <thead><tr>
                        <th scope="col">{{ __('admin_system.backups.preview.collection') }}</th>
                        <th scope="col" class="text-end">{{ __('admin_system.backups.preview.records') }}</th>
                        <th scope="col" class="text-end">{{ __('admin_system.backups.preview.now') }}</th>
                        <th scope="col" class="text-end">{{ __('admin_system.backups.preview.diff') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('admin_system.backups.columns.actions') }}</span></th>
                    </tr></thead>
                    <tbody>
                        @foreach ($preview['tables'] as $table)
                            <tr wire:key="bk-table-{{ $table['name'] }}" @class(['table-active' => $sampleTable === $table['name']])>
                                <td><code>{{ $table['name'] }}</code></td>
                                <td class="text-end">{{ $format->number($table['rows']) }}</td>
                                <td class="text-end">{{ $table['current'] === null ? '—' : $format->number($table['current']) }}</td>
                                <td class="text-end">
                                    @if ($table['diff'] === null)<x-badge color="neutral" soft>{{ __('admin_system.backups.preview.absent') }}</x-badge>
                                    @elseif ($table['diff'] === 0)<span class="text-secondary">=</span>
                                    @else<x-badge :color="$table['diff'] > 0 ? 'primary' : 'warning'" soft>{{ ($table['diff'] > 0 ? '+' : '').$format->number($table['diff']) }}</x-badge>@endif
                                </td>
                                <td class="text-end">@if ($table['rows'] > 0)<button type="button" class="btn btn-sm btn-link py-0" wire:click="sample('{{ $table['name'] }}')">{{ __('admin_system.backups.preview.see') }}<span class="visually-hidden"> {{ $table['name'] }}</span></button>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($sampleTable !== '')
                <h3 class="h6 d-flex justify-content-between"><span>{{ __('admin_system.backups.preview.sample') }} <code>{{ $sampleTable }}</code></span><span class="small text-secondary">{{ __('admin_system.backups.preview.sample_note') }}</span></h3>
                <pre class="bg-body-tertiary border rounded p-3 small mb-0 xm-backup-sample" tabindex="0">{{ json_encode($this->sampleRows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
            @endif
        @endif
        <x-slot:footer>
            @if ($previewId !== null && $canDownload)
                <a class="btn btn-light border" href="{{ route('admin.sauvegardes.telecharger', $previewId) }}"><i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('admin_system.backups.actions.download') }}</a>
            @endif
            @if ($previewId !== null && $canRestore)
                <button type="button" class="btn btn-outline-danger" wire:click="confirmRestore({{ $previewId }})" @disabled($restoreActive)><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>{{ __('admin_system.backups.actions.restore') }}</button>
            @endif
        </x-slot:footer>
    </x-admin.modal>

    {{-- Restauration : saisie « RESTAURER » (revérifiée côté serveur) --}}
    @if ($canRestore)
        <x-admin.modal id="xmBackupRestore" type="delete" size="md" :title="__('admin_system.backups.restore.confirm_title')" static data-xm-confirm-word="{{ __('admin_system.backups.restore.word') }}">
            <p>{{ __('admin_system.backups.restore.confirm_text', ['name' => $restoreBackup?->name ?? '']) }}</p>
            <ul class="small">
                <li>{{ __('admin_system.backups.restore.consequence_safety') }}</li>
                <li>{{ __('admin_system.backups.restore.consequence_maintenance') }}</li>
                <li>{{ __('admin_system.backups.restore.consequence_session') }}</li>
            </ul>
            <div class="alert alert-danger small mb-0">
                <label class="form-label mb-1" for="xmBackupRestoreWord">{{ __('admin_system.backups.restore.word_label') }} <code>{{ __('admin_system.backups.restore.word') }}</code></label>
                <input class="form-control form-control-sm font-monospace @error('restoreWord') is-invalid @enderror" id="xmBackupRestoreWord" autocomplete="off" data-xm-confirm-input wire:model="restoreWord"
                       @error('restoreWord') aria-invalid="true" aria-describedby="xmBackupRestoreWordError" @enderror>
                @error('restoreWord')<div class="invalid-feedback" id="xmBackupRestoreWordError">{{ $message }}</div>@enderror
            </div>
            <x-slot:footer>
                <button type="button" class="btn btn-danger" wire:click="startRestore" wire:ignore data-xm-modal-action>
                    <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>{{ __('admin_system.backups.actions.restore') }}
                </button>
            </x-slot:footer>
        </x-admin.modal>
    @endif
</div>
