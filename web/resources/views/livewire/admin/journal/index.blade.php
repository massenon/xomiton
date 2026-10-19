{{--
    Journal d'audit (P13-05) : composant App\Livewire\Admin\Journal\Index, gabarit admin ; écran témoin des composants
    communs P10-02 (x-admin.kpi, x-admin.filters, x-admin.table, x-admin.pagination, x-admin.empty, x-admin.modal).
    Ordre UI_UX_DESIGN_SYSTEM.md §4 : introduction · KPI · carte « liste » (en-tête + export, filtres, tableau, pied) ·
    carte de purge (superadmin) · modale rouge de purge (toujours montée).
--}}
<div class="xm-journal">
    <p class="text-secondary mb-4 xm-prose">{{ __('admin.audit.intro') }} {{ __('admin.audit.retention', ['days' => $format->number($retentionDays)]) }}</p>

    <x-admin.kpi id="xmJournalKpis" :items="[
        ['label' => __('admin.audit.kpi.entries'), 'value' => $format->number($this->stats['entries']), 'icon' => 'clock-history'],
        ['label' => __('admin.audit.kpi.today'), 'value' => $format->number($this->stats['today']), 'icon' => 'calendar-day', 'variant' => 'success'],
        ['label' => __('admin.audit.kpi.actors'), 'value' => $format->number($this->stats['actors']), 'icon' => 'people', 'variant' => 'violet'],
        ['label' => __('admin.audit.kpi.filtered'), 'value' => $format->number($rows->total()), 'icon' => 'funnel', 'variant' => 'warning'],
    ]" />

    <section class="card xm-admin-card" aria-labelledby="xmJournalListTitle">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h6 fw-bold mb-0" id="xmJournalListTitle">{{ __('admin.audit.title') }}</h2>
            @can('export', \App\Models\AuditLog::class)
                <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="export" wire:loading.attr="disabled" wire:target="export">
                    <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>{{ __('admin.export.csv_filtered', ['count' => $format->number($rows->total())]) }}
                </button>
            @endcan
        </div>
        <div class="card-body border-bottom">
            <x-admin.filters id="xmJournalFilters" :active="$this->activeFilterCount()" :placeholder="__('admin.audit.filters.search_placeholder')" :search-help="__('admin.audit.filters.search_help')">
                <div class="col-12 col-sm-4 col-lg-3">
                    <label class="visually-hidden" for="xmJournalAction">{{ __('admin.audit.filters.action') }}</label>
                    <select class="form-select form-select-sm" id="xmJournalAction" wire:model.live="action">
                        <option value="">{{ __('admin.audit.filters.all_actions') }}</option>
                        @foreach ($this->actions as $code)
                            <option value="{{ $code }}">{{ $code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-sm-4 col-lg-2">
                    <label class="visually-hidden" for="xmJournalFrom">{{ __('admin.audit.filters.from') }}</label>
                    <input class="form-control form-control-sm @if ($periodError) is-invalid @endif" type="date" id="xmJournalFrom" wire:model.live="from" title="{{ __('admin.audit.filters.from') }}"
                           @if ($periodError) aria-invalid="true" aria-describedby="xmJournalPeriodError" @endif>
                </div>
                <div class="col-6 col-sm-4 col-lg-2">
                    <label class="visually-hidden" for="xmJournalUntil">{{ __('admin.audit.filters.to') }}</label>
                    <input class="form-control form-control-sm @if ($periodError) is-invalid @endif" type="date" id="xmJournalUntil" wire:model.live="until" title="{{ __('admin.audit.filters.to') }}"
                           @if ($periodError) aria-invalid="true" aria-describedby="xmJournalPeriodError" @endif>
                </div>
                @if ($periodError)
                    <div class="col-12 order-last"><p class="small text-danger mb-0" id="xmJournalPeriodError" role="alert"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $periodError }}</p></div>
                @endif
            </x-admin.filters>
        </div>

        <x-admin.table id="xmJournalTable" :caption="__('admin.audit.table_caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
            ['key' => 'date', 'label' => __('admin.audit.columns.at'), 'sortable' => true, 'width' => '11rem'],
            ['key' => 'compte', 'label' => __('admin.audit.columns.user'), 'sortable' => true],
            ['key' => 'action', 'label' => __('admin.audit.columns.action'), 'sortable' => true],
            ['key' => 'detail', 'label' => __('admin.audit.columns.detail')],
        ]">
            @foreach ($rows as $log)
                @php($family = \App\Livewire\Admin\Journal\Index::family($log->action))
                <tr wire:key="log-{{ $log->id }}">
                    <td data-label="{{ __('admin.audit.columns.at') }}" class="text-nowrap"><time class="small" datetime="{{ $log->created_at?->toIso8601String() }}">{{ $format->tableDateTime($log->created_at) }}</time></td>
                    {{-- Une seule boîte par cellule : en cartes < 768 px, la cellule est une ligne flex « libellé | valeur ». --}}
                    <td data-label="{{ __('admin.audit.columns.user') }}">
                        <div class="min-w-0">
                            <span class="d-block fw-semibold text-break">{{ $this->actorOf($log) }}</span>
                            @if ($log->user?->pseudo)<span class="d-block small text-secondary">{{ $log->user->pseudo }}</span>@endif
                        </div>
                    </td>
                    <td data-label="{{ __('admin.audit.columns.action') }}">
                        <div class="min-w-0">
                            <x-badge :color="\App\Livewire\Admin\Journal\Index::familyColor($family)" soft>{{ __('admin.audit.families.'.$family) }}</x-badge>
                            <code class="d-block small mt-1 xm-journal__code">{{ $log->action }}</code>
                        </div>
                    </td>
                    <td data-label="{{ __('admin.audit.columns.detail') }}" class="xm-journal__detail"><span class="small">{{ $log->detail ?? '—' }}</span></td>
                </tr>
            @endforeach
            <x-slot:emptyState>
                <x-admin.empty icon="clock-history" :filtered="$this->activeFilterCount() > 0" :title="__('admin.audit.empty')">{{ __('admin.audit.empty_text') }}</x-admin.empty>
            </x-slot:emptyState>
            <x-slot:footer>
                <x-admin.pagination id="xmJournalPager" :paginator="$rows" />
            </x-slot:footer>
        </x-admin.table>
    </section>

    {{-- Purge : superadmin seulement (audit_logs.purge) ; la rétention automatique suit le même réglage (P1-14). --}}
    <section class="card xm-admin-card mt-4 border-danger-subtle" aria-labelledby="xmJournalPurgeTitle">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <div class="flex-grow-1 xm-prose">
                <h2 class="h6 mb-1 text-danger" id="xmJournalPurgeTitle">{{ __('admin.audit.purge.title') }}</h2>
                <p class="small text-secondary mb-0">{{ __('admin.audit.purge.text') }}</p>
            </div>
            @if ($canPurge)
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div>
                        <label class="visually-hidden" for="xmJournalPurgeDays">{{ __('admin.audit.purge.days') }}</label>
                        <div class="input-group input-group-sm xm-journal__days">
                            <input class="form-control @error('purgeDays') is-invalid @enderror" type="number" inputmode="numeric" id="xmJournalPurgeDays" min="{{ \App\Application\Retention\PurgeAuditLogs::MIN_DAYS }}" max="{{ \App\Application\Retention\PurgeAuditLogs::MAX_DAYS }}"
                                   wire:model.live.debounce.500ms="purgeDays" aria-describedby="xmJournalPurgeDaysHint">
                            <span class="input-group-text">{{ __('admin.audit.purge.days_suffix') }}</span>
                        </div>
                        <div class="form-text small" id="xmJournalPurgeDaysHint">{{ __('admin.audit.purge.days_hint', ['min' => \App\Application\Retention\PurgeAuditLogs::MIN_DAYS, 'max' => $format->number(\App\Application\Retention\PurgeAuditLogs::MAX_DAYS)]) }}</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#xmJournalPurge"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('admin.audit.purge.action') }}</button>
                </div>
            @else
                <x-badge color="neutral" icon="lock">{{ __('admin.audit.purge.locked') }}</x-badge>
            @endif
        </div>
    </section>

    @if ($canPurge)
        <x-admin.modal id="xmJournalPurge" type="delete" size="md" :title="__('admin.audit.purge.confirm_title')" static data-xm-confirm-word="{{ __('admin.audit.purge.word') }}">
            @if ($purgeDays === null)
                <p>{{ __('admin.audit.purge.days_hint', ['min' => \App\Application\Retention\PurgeAuditLogs::MIN_DAYS, 'max' => $format->number(\App\Application\Retention\PurgeAuditLogs::MAX_DAYS)]) }}</p>
            @else
                <p>{{ trans_choice('admin.audit.purge.confirm_text', $purgeCount, ['days' => $format->number(max(\App\Application\Retention\PurgeAuditLogs::MIN_DAYS, min(\App\Application\Retention\PurgeAuditLogs::MAX_DAYS, $purgeDays))), 'count' => $format->number($purgeCount)]) }}</p>
            @endif
            @error('purgeDays')<x-alert type="danger">{{ $message }}</x-alert>@enderror
            <div class="alert alert-danger small mb-0">
                <label class="form-label mb-1" for="xmJournalPurgeWord">{{ __('admin.audit.purge.word_label') }} <code>{{ __('admin.audit.purge.word') }}</code></label>
                <input class="form-control form-control-sm font-monospace @error('purgeWord') is-invalid @enderror" id="xmJournalPurgeWord" autocomplete="off" data-xm-confirm-input wire:model="purgeWord"
                       @error('purgeWord') aria-invalid="true" aria-describedby="xmJournalPurgeWordError" @enderror>
                @error('purgeWord')<div class="invalid-feedback" id="xmJournalPurgeWordError">{{ $message }}</div>@enderror
            </div>
            <x-slot:footer>
                {{-- Inactif tant que « PURGER » n'est pas saisi (xomiton-ui.js, data-xm-modal-action) ; wire:ignore : Livewire ne
                     réécrit pas cet état entre deux rendus. Le mot est revérifié côté serveur. --}}
                <button type="button" class="btn btn-danger" wire:click="purge" wire:ignore data-xm-modal-action>
                    <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('admin.audit.purge.action') }}
                </button>
            </x-slot:footer>
        </x-admin.modal>
    @endif
</div>
