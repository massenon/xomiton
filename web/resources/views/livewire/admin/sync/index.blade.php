{{--
    Hors connexion & synchro (P13-01) : composant App\Livewire\Admin\Sync\Index, gabarit admin. Ordre (prototype
    admin/synchronisation.php) : explication · KPI · colonne gauche (appareils & pair·es, résolution des conflits) · colonne
    droite (paramètres, cache local de ce navigateur) · journal de synchronisation · modale de fusion (toujours montée).
--}}
@php
    $canManage = auth()->user()?->can('sync.manage') ?? false;
    $cacheTexts = collect(['active' => 'sw_active', 'waiting' => 'sw_waiting', 'unsupported' => 'sw_unsupported', 'none' => 'none', 'unavailable' => 'unavailable', 'failed' => 'read_failed',
        'resources' => 'resources', 'confirm' => 'confirm', 'cleared' => 'cleared'])->map(fn (string $key): string => (string) __('admin_system.sync.cache.'.$key))->all();
@endphp
<div class="xm-sync" data-xm-screen="sync">
    <section class="card xm-admin-card mb-4" aria-labelledby="xmSyncHowTitle">
        <div class="card-body d-flex gap-3 align-items-start">
            <i class="bi bi-cloud-arrow-up fs-1 text-primary" aria-hidden="true"></i>
            <div>
                <h2 class="h6" id="xmSyncHowTitle">{{ __('admin_system.sync.how_title') }}</h2>
                <p class="mb-0 small text-secondary xm-prose">{{ __('admin_system.sync.how_text') }}</p>
            </div>
        </div>
    </section>

    <x-admin.kpi id="xmSyncKpis" :items="[
        ['label' => __('admin_system.sync.kpi.devices'), 'value' => $format->number($this->stats['devices']), 'icon' => 'phone', 'sub' => $this->stats['revoked'] > 0 ? trans_choice('admin_system.sync.kpi.revoked', $this->stats['revoked'], ['count' => $this->stats['revoked']]) : null],
        ['label' => __('admin_system.sync.kpi.pending'), 'value' => $format->number($this->stats['pending']), 'icon' => 'hourglass-split', 'variant' => 'warning'],
        ['label' => __('admin_system.sync.kpi.conflicts'), 'value' => $format->number($this->stats['conflicts']), 'icon' => 'exclamation-diamond', 'variant' => 'danger'],
        ['label' => __('admin_system.sync.kpi.stale', ['hours' => $staleHours]), 'value' => $format->number($this->stats['stale']), 'icon' => 'wifi-off', 'variant' => 'violet'],
    ]" />

    <div class="row g-4 mb-4">
        <div class="col-xl-8">
            {{-- Appareils & pair·es --}}
            <section class="card xm-admin-card mb-4" aria-labelledby="xmSyncDevicesTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmSyncDevicesTitle">{{ __('admin_system.sync.devices.title') }}</h2></div>
                <div class="card-body border-bottom">
                    <x-admin.filters id="xmSyncFilters" :active="$this->activeFilterCount()" :placeholder="__('admin_system.sync.devices.search_placeholder')" :search-help="__('admin_system.sync.devices.search_help')">
                        <div class="col-12 col-sm-4 col-lg-3">
                            <label class="visually-hidden" for="xmSyncView">{{ __('admin_system.sync.devices.filter') }}</label>
                            <select class="form-select form-select-sm" id="xmSyncView" wire:model.live="view">
                                <option value="">{{ __('admin_system.sync.devices.views.all') }}</option>
                                @foreach (\App\Livewire\Admin\Sync\Index::VIEWS as $key)
                                    <option value="{{ $key }}">{{ __('admin_system.sync.devices.views.'.$key) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </x-admin.filters>
                </div>
                <x-admin.table id="xmSyncTable" :caption="__('admin_system.sync.devices.caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
                    ['key' => 'appareil', 'label' => __('admin_system.sync.devices.columns.device'), 'sortable' => true],
                    ['key' => 'synchro', 'label' => __('admin_system.sync.devices.columns.last_sync'), 'sortable' => true],
                    ['key' => 'attente', 'label' => __('admin_system.sync.devices.columns.pending'), 'sortable' => true, 'class' => 'text-end'],
                    ['key' => 'conflits', 'label' => __('admin_system.sync.devices.columns.conflicts'), 'sortable' => true, 'class' => 'text-end'],
                    ['key' => 'actions', 'label' => '', 'sr' => __('admin_system.sync.devices.columns.actions'), 'class' => 'text-end'],
                ]">
                    @foreach ($rows as $device)
                        @php($old = ! $device->is_revoked && ($device->last_sync_at === null || $device->last_sync_at->lt($staleAt)))
                        <tr wire:key="device-{{ $device->id }}">
                            <td data-label="{{ __('admin_system.sync.devices.columns.device') }}">
                                <div class="min-w-0">
                                    <span class="d-block fw-semibold text-break">{{ $device->label }}@if ($device->is_revoked) <x-badge color="neutral" soft icon="slash-circle">{{ __('admin_system.sync.devices.revoked') }}</x-badge>@endif @if ($device->wiped_at) <x-badge color="success" soft icon="eraser">{{ __('admin_system.sync.devices.wiped') }}</x-badge>@elseif ($device->wipe_requested_at) <x-badge color="warning" soft icon="hourglass-split">{{ __('admin_system.sync.devices.wipe_pending') }}</x-badge>@endif</span>
                                    <span class="d-block small text-secondary">{{ $device->user?->pseudo ?? '—' }} · {{ $device->platform ?? '—' }}@if ($device->app_version) · v{{ $device->app_version }}@endif</span>
                                </div>
                            </td>
                            <td data-label="{{ __('admin_system.sync.devices.columns.last_sync') }}" @class(['text-danger' => $old])>
                                @if ($device->last_sync_at)
                                    <time datetime="{{ $device->last_sync_at->toIso8601String() }}">{{ $format->tableDateTime($device->last_sync_at) }}</time>
                                    <span class="d-block small text-secondary">{{ $format->ago($device->last_sync_at) }}</span>
                                @else
                                    {{ __('admin_system.sync.devices.never') }}
                                @endif
                            </td>
                            <td data-label="{{ __('admin_system.sync.devices.columns.pending') }}" class="text-end">@if ($device->pending_count > 0)<x-badge color="warning">{{ $format->number($device->pending_count) }}</x-badge>@else 0 @endif</td>
                            <td data-label="{{ __('admin_system.sync.devices.columns.conflicts') }}" class="text-end">@if ($device->conflicts_count > 0)<x-badge color="danger">{{ $format->number($device->conflicts_count) }}</x-badge>@else 0 @endif</td>
                            <td data-label="{{ __('admin_system.sync.devices.columns.actions') }}" class="text-end xm-table__actions">
                                @if ($canManage && ! $device->is_revoked)
                                    <button type="button" class="btn btn-sm btn-light text-danger" wire:click="revoke({{ $device->id }})" wire:confirm="{{ __('admin_system.sync.devices.confirm_revoke', ['label' => $device->label]) }}"
                                            aria-label="{{ __('admin_system.sync.devices.revoke_of', ['label' => $device->label]) }}"><i class="bi bi-slash-circle me-1" aria-hidden="true"></i>{{ __('admin_system.sync.devices.revoke') }}</button>
                                @endif
                                @can('wipe', $device)
                                    @if ($device->wipe_requested_at === null)
                                        <button type="button" class="btn btn-sm btn-light text-danger" wire:click="wipe({{ $device->id }})" wire:confirm="{{ __('admin_system.sync.devices.confirm_wipe', ['label' => $device->label]) }}"
                                                aria-label="{{ __('admin_system.sync.devices.wipe_of', ['label' => $device->label]) }}"><i class="bi bi-eraser me-1" aria-hidden="true"></i>{{ __('admin_system.sync.devices.wipe') }}</button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    <x-slot:emptyState>
                        <x-admin.empty icon="phone" :filtered="$this->activeFilterCount() > 0" :title="__('admin_system.sync.devices.empty')">{{ __('admin_system.sync.devices.empty_text') }}</x-admin.empty>
                    </x-slot:emptyState>
                    <x-slot:footer>
                        <x-admin.pagination id="xmSyncPager" :paginator="$rows" />
                    </x-slot:footer>
                </x-admin.table>
            </section>

            {{-- Résolution des conflits --}}
            <section class="card xm-admin-card" aria-labelledby="xmSyncConflictsTitle">
                <div class="card-header d-flex flex-wrap justify-content-between gap-2">
                    <h2 class="h6 fw-bold mb-0" id="xmSyncConflictsTitle">{{ __('admin_system.sync.conflicts.title') }}</h2>
                    <span class="small text-secondary">{{ trans_choice('admin_system.sync.conflicts.open', $this->openConflicts->count(), ['count' => $this->openConflicts->count()]) }}</span>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($this->openConflicts as $conflict)
                        @php($subject = $conflict->subject)
                        @php($visible = $canSeeCase($subject))
                        <li class="list-group-item" wire:key="conflict-{{ $conflict->id }}">
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                <strong>{{ $visible ? $subject->code : __('admin_system.sync.conflicts.hidden_case') }}</strong>
                                <span class="text-secondary">· {{ __('admin_system.sync.fields.'.$conflict->field) }} · {{ $conflict->device?->label ?? __('admin_system.sync.conflicts.device_gone') }}@if ($conflict->device?->user) ({{ $conflict->device->user->pseudo }})@endif</span>
                                <x-badge class="ms-auto" :color="$conflict->status->badge()" soft>{{ $conflict->status->label() }}</x-badge>
                            </div>
                            <div class="row g-2 small">
                                <div class="col-md-6"><div class="border rounded p-2 h-100"><div class="text-secondary">{{ __('admin_system.sync.conflicts.server') }}</div><div class="text-break xm-sync-value">{{ $visible ? $value->present($conflict->field, $conflict->server_value) : '••••' }}</div></div></div>
                                <div class="col-md-6"><div class="border rounded p-2 h-100"><div class="text-secondary">{{ __('admin_system.sync.conflicts.device') }}</div><div class="text-break xm-sync-value">{{ $visible ? $value->present($conflict->field, $conflict->device_value) : '••••' }}</div></div></div>
                            </div>
                            @if ($canManage && $visible)
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="resolve({{ $conflict->id }}, 'serveur')" wire:loading.attr="disabled">{{ __('admin_system.sync.conflicts.keep_server') }}</button>
                                    <button type="button" class="btn btn-sm btn-primary" wire:click="resolve({{ $conflict->id }}, 'appareil')" wire:loading.attr="disabled">{{ __('admin_system.sync.conflicts.keep_device') }}</button>
                                    @if (in_array($conflict->field, $mergeable, true))
                                        <button type="button" class="btn btn-sm btn-light border" wire:click="openMerge({{ $conflict->id }})">{{ __('admin_system.sync.conflicts.merge') }}</button>
                                    @endif
                                </div>
                            @endif
                        </li>
                    @empty
                        <li class="list-group-item"><x-admin.empty icon="check2-circle" :title="__('admin_system.sync.conflicts.empty')">{{ __('admin_system.sync.conflicts.empty_text') }}</x-admin.empty></li>
                    @endforelse
                </ul>
                @if ($this->resolvedConflicts->isNotEmpty())
                    <div class="card-footer">
                        <h3 class="h6 small fw-bold">{{ __('admin_system.sync.conflicts.history') }}</h3>
                        <ul class="list-unstyled small mb-0">
                            @foreach ($this->resolvedConflicts as $conflict)
                                <li class="text-success" wire:key="resolved-{{ $conflict->id }}"><i class="bi bi-check2 me-1" aria-hidden="true"></i>{{ __('admin_system.sync.conflicts.resolved_line', [
                                    'case' => $canSeeCase($conflict->subject) ? $conflict->subject->code : __('admin_system.sync.conflicts.hidden_case'),
                                    'field' => __('admin_system.sync.fields.'.$conflict->field),
                                    'choice' => __('admin_system.sync.conflicts.resolutions.'.$conflict->resolution),
                                    'by' => $conflict->resolver?->pseudo ?? '—',
                                    'date' => $format->tableDateTime($conflict->resolved_at),
                                ]) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </section>
        </div>

        <div class="col-xl-4">
            {{-- Paramètres de synchronisation --}}
            <form class="card xm-admin-card mb-4" wire:submit="saveSettings" novalidate aria-labelledby="xmSyncSettingsTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmSyncSettingsTitle">{{ __('admin_system.sync.settings.title') }}</h2></div>
                <div class="card-body">
                    @error('settings')<x-alert type="danger">{{ $message }}</x-alert>@enderror
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="xmSyncOffline" wire:model="offlineSync">
                        <label class="form-check-label" for="xmSyncOffline">{{ __('admin_system.sync.settings.offline') }}</label>
                    </div>
                    <label class="form-label small" for="xmSyncInterval">{{ __('admin_system.sync.settings.interval') }}</label>
                    <select class="form-select form-select-sm mb-3 @error('interval') is-invalid @enderror" id="xmSyncInterval" wire:model="interval" @error('interval') aria-invalid="true" aria-describedby="xmSyncIntervalError" @enderror>
                        @foreach ([5, 15, 30, 60, 240] as $minutes)
                            <option value="{{ $minutes }}">{{ __('admin_system.sync.settings.intervals.'.$minutes) }}</option>
                        @endforeach
                    </select>
                    @error('interval')<div class="invalid-feedback d-block mb-2" id="xmSyncIntervalError">{{ $message }}</div>@enderror
                    <label class="form-label small" for="xmSyncMax">{{ __('admin_system.sync.settings.max_mb') }}</label>
                    <input class="form-control form-control-sm mb-3 @error('maxMb') is-invalid @enderror" type="number" inputmode="numeric" min="1" max="50" id="xmSyncMax" wire:model="maxMb" @error('maxMb') aria-invalid="true" aria-describedby="xmSyncMaxError" @enderror>
                    @error('maxMb')<div class="invalid-feedback d-block mb-2" id="xmSyncMaxError">{{ $message }}</div>@enderror
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="xmSyncWifi" wire:model="wifiOnly">
                        <label class="form-check-label" for="xmSyncWifi">{{ __('admin_system.sync.settings.wifi_only') }}</label>
                    </div>
                    <p class="small text-secondary mb-0"><i class="bi bi-lock me-1" aria-hidden="true"></i>{{ __('admin_system.sync.settings.encryption') }}</p>
                </div>
                @if ($canManage)
                    <div class="card-footer text-end"><button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="saveSettings">{{ __('admin_system.common.save') }}</button></div>
                @endif
            </form>

            {{-- Cache local de ce navigateur (script d'écran `sync`) --}}
            <section class="card xm-admin-card" aria-labelledby="xmSyncCacheTitle" wire:ignore>
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmSyncCacheTitle">{{ __('admin_system.sync.cache.title') }}</h2></div>
                <div class="card-body">
                    <p class="small mb-2">{{ __('admin_system.sync.cache.worker') }} <strong id="xmSyncSwState">{{ __('admin_system.sync.cache.checking') }}</strong></p>
                    <ul class="small list-unstyled mb-3" id="xmSyncCacheList" aria-live="polite"><li class="text-secondary">{{ __('admin_system.sync.cache.reading') }}</li></ul>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="xmSyncCacheClear"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('admin_system.sync.cache.clear') }}</button>
                    @can('updates.view')
                        <a wire:navigate wire:navigate.hover class="btn btn-link btn-sm" href="{{ route('admin.mises-a-jour') }}">{{ __('admin_system.sync.cache.update_link') }}</a>
                    @endcan
                    <script type="application/json" id="xmSyncI18n">@json($cacheTexts)</script>
                </div>
            </section>
        </div>
    </div>

    {{-- Journal de synchronisation --}}
    <section class="card xm-admin-card" aria-labelledby="xmSyncLogTitle">
        <div class="card-header d-flex flex-wrap justify-content-between gap-2">
            <h2 class="h6 fw-bold mb-0" id="xmSyncLogTitle">{{ __('admin_system.sync.log.title') }}</h2>
            @can('audit_logs.view')<a wire:navigate wire:navigate.hover class="small" href="{{ route('admin.journal', ['ac' => 'sync.conflict_resolved']) }}">{{ __('admin_system.sync.log.all') }}</a>@endcan
        </div>
        <div class="xm-table-wrap">
            <table class="table table-sm align-middle mb-0 xm-table--stack">
                <caption class="visually-hidden">{{ __('admin_system.sync.log.caption') }}</caption>
                <thead><tr>
                    <th scope="col">{{ __('admin_system.sync.log.date') }}</th><th scope="col">{{ __('admin_system.sync.log.user') }}</th>
                    <th scope="col">{{ __('admin_system.sync.log.event') }}</th><th scope="col">{{ __('admin_system.sync.log.detail') }}</th>
                </tr></thead>
                <tbody>
                    @forelse ($this->journal as $log)
                        <tr wire:key="log-{{ $log->id }}">
                            <td data-label="{{ __('admin_system.sync.log.date') }}" class="text-nowrap"><time datetime="{{ $log->created_at?->toIso8601String() }}">{{ $format->tableDateTime($log->created_at) }}</time></td>
                            <td data-label="{{ __('admin_system.sync.log.user') }}">{{ $log->user?->pseudo ?? $log->actorLabel() }}</td>
                            <td data-label="{{ __('admin_system.sync.log.event') }}"><code class="small">{{ $log->action }}</code></td>
                            <td data-label="{{ __('admin_system.sync.log.detail') }}" class="small">{{ $log->detail ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-secondary py-3">{{ __('admin_system.sync.log.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Fusion à la main (notes, orientation) --}}
    <x-admin.modal id="xmSyncMerge" type="form" size="lg" :title="__('admin_system.sync.merge.title')" dirty-guard>
        @if ($mergeConflict)
            <p class="small text-secondary">{{ __('admin_system.sync.merge.intro', ['field' => __('admin_system.sync.fields.'.$mergeConflict->field)]) }}</p>
            <div class="row g-2 small mb-3">
                <div class="col-md-6"><div class="border rounded p-2 h-100"><div class="text-secondary">{{ __('admin_system.sync.conflicts.server') }}</div><div class="text-break xm-sync-value">{{ $value->present($mergeConflict->field, $mergeConflict->server_value) }}</div></div></div>
                <div class="col-md-6"><div class="border rounded p-2 h-100"><div class="text-secondary">{{ __('admin_system.sync.conflicts.device') }}</div><div class="text-break xm-sync-value">{{ $value->present($mergeConflict->field, $mergeConflict->device_value) }}</div></div></div>
            </div>
        @endif
        <label class="form-label" for="xmSyncMergeValue">{{ __('admin_system.sync.merge.field') }}</label>
        <textarea class="form-control @error('merged') is-invalid @enderror" id="xmSyncMergeValue" rows="5" maxlength="4000" wire:model="merged" @error('merged') aria-invalid="true" aria-describedby="xmSyncMergeError" @enderror></textarea>
        @error('merged')<div class="invalid-feedback d-block" id="xmSyncMergeError">{{ $message }}</div>@enderror
        <x-slot:footer>
            <button type="button" class="btn btn-primary" wire:click="saveMerge" wire:loading.attr="disabled" wire:target="saveMerge">{{ __('admin_system.sync.merge.save') }}</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
