{{--
    Mises à jour & optimisation (P13-02) : composant App\Livewire\Admin\Updates\Index, gabarit admin. Ordre (prototype
    admin/mises-a-jour.php) : introduction · KPI · colonne gauche (historique des versions, publier ou planifier) · colonne
    droite (application mobile, maintenance et sauvegardes en lien) · optimisation · contenus les plus consultés et adoption.
--}}
<div class="xm-updates">
    <p class="text-secondary mb-4 xm-prose">{{ __('admin_system.updates.intro') }}@unless ($canManage) {{ __('admin_system.updates.read_only') }}@endunless</p>

    <x-admin.kpi id="xmUpdatesKpis" :items="[
        ['label' => __('admin_system.updates.kpi.current'), 'value' => 'v'.$this->stats['current'], 'icon' => 'tag', 'sub' => $this->stats['updated'] ? __('admin_system.updates.kpi.updated', ['date' => $format->longDate($this->stats['updated'])]) : null],
        ['label' => __('admin_system.updates.kpi.planned'), 'value' => $format->number($this->stats['planned']), 'icon' => 'calendar-check', 'variant' => 'success'],
        ['label' => __('admin_system.updates.kpi.backups'), 'value' => $format->number($this->stats['backups']), 'icon' => 'database-check', 'variant' => 'violet', 'href' => auth()->user()?->can('backups.create') ? route('admin.sauvegardes') : null],
        ['label' => __('admin_system.updates.kpi.maintenance'), 'value' => $this->stats['maintenance'] ? __('admin_system.updates.kpi.maintenance_on') : __('admin_system.updates.kpi.maintenance_off'), 'icon' => 'cone-striped', 'variant' => $this->stats['maintenance'] ? 'danger' : 'warning', 'href' => auth()->user()?->can('platform.maintenance') ? route('admin.maintenance') : null],
    ]" />

    <div class="row g-4 mb-4">
        <div class="col-xl-7">
            {{-- Historique des versions --}}
            <section class="card xm-admin-card mb-4" aria-labelledby="xmVersionsTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmVersionsTitle">{{ __('admin_system.updates.history.title') }}</h2></div>
                <div class="card-body border-bottom">
                    <x-admin.filters id="xmVersionsFilters" :active="$this->activeFilterCount()" :placeholder="__('admin_system.updates.history.search_placeholder')" :search-help="__('admin_system.updates.history.search_help')">
                        <div class="col-12 col-sm-4 col-lg-3">
                            <label class="visually-hidden" for="xmVersionsStatus">{{ __('admin_system.updates.history.status') }}</label>
                            <select class="form-select form-select-sm" id="xmVersionsStatus" wire:model.live="status">
                                <option value="">{{ __('admin_system.updates.history.all_statuses') }}</option>
                                @foreach (\App\Models\AppVersion::STATUSES as $key)<option value="{{ $key }}">{{ __('admin_system.updates.statuses.'.$key) }}</option>@endforeach
                            </select>
                        </div>
                    </x-admin.filters>
                </div>
                <x-admin.table id="xmVersionsTable" :caption="__('admin_system.updates.history.caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
                    ['key' => 'version', 'label' => __('admin_system.updates.history.version'), 'sortable' => true],
                    ['key' => 'date', 'label' => __('admin_system.updates.history.date'), 'sortable' => true],
                    ['key' => 'type', 'label' => __('admin_system.updates.history.kind')],
                    ['key' => 'notes', 'label' => __('admin_system.updates.history.notes')],
                    ['key' => 'statut', 'label' => __('admin_system.updates.history.status')],
                    ['key' => 'actions', 'label' => '', 'sr' => __('admin_system.updates.history.actions'), 'class' => 'text-end'],
                ]">
                    @foreach ($rows as $row)
                        <tr wire:key="version-{{ $row->id }}">
                            <td data-label="{{ __('admin_system.updates.history.version') }}" class="fw-semibold text-nowrap">v{{ $row->version }}@if ($row->isDeployed() && $row->version === $this->stats['current']) <x-badge color="primary" soft>{{ __('admin_system.updates.history.current') }}</x-badge>@endif</td>
                            <td data-label="{{ __('admin_system.updates.history.date') }}" class="text-nowrap">{{ $format->shortDate($row->deployed_at ?? $row->scheduled_for ?? $row->created_at) }}</td>
                            <td data-label="{{ __('admin_system.updates.history.kind') }}"><x-badge :color="$row->kindBadge()" soft>{{ __('admin_system.updates.kinds.'.$row->kind) }}</x-badge></td>
                            <td data-label="{{ __('admin_system.updates.history.notes') }}" class="small">{{ $row->notes }}</td>
                            <td data-label="{{ __('admin_system.updates.history.status') }}"><x-badge :color="match ($row->status) { 'deployee' => 'success', 'planifiee' => 'warning', default => 'neutral' }" soft>{{ __('admin_system.updates.statuses.'.$row->status) }}</x-badge></td>
                            <td data-label="{{ __('admin_system.updates.history.actions') }}" class="text-end text-nowrap xm-table__actions">
                                @if ($canManage && $row->isPlanned())
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-success" wire:click="deploy({{ $row->id }})" wire:confirm="{{ __('admin_system.updates.history.confirm_deploy', ['version' => $row->version]) }}">{{ __('admin_system.updates.history.deploy') }}</button>
                                        <button type="button" class="btn btn-sm btn-light text-danger" wire:click="cancel({{ $row->id }})" wire:confirm="{{ __('admin_system.updates.history.confirm_cancel', ['version' => $row->version]) }}" aria-label="{{ __('admin_system.updates.history.cancel_of', ['version' => $row->version]) }}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    <x-slot:emptyState>
                        <x-admin.empty icon="tag" :filtered="$this->activeFilterCount() > 0" :title="__('admin_system.updates.history.empty')">{{ __('admin_system.updates.history.empty_text') }}</x-admin.empty>
                    </x-slot:emptyState>
                    <x-slot:footer>
                        <x-admin.pagination id="xmVersionsPager" :paginator="$rows" />
                    </x-slot:footer>
                </x-admin.table>
            </section>

            {{-- Publier ou planifier --}}
            <form class="card xm-admin-card" wire:submit="release" novalidate aria-labelledby="xmReleaseTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmReleaseTitle">{{ __('admin_system.updates.form.title') }}</h2></div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="xmRelVersion">{{ __('admin_system.updates.form.version') }} *</label>
                        <input class="form-control @error('version') is-invalid @enderror" id="xmRelVersion" wire:model="version" placeholder="1.4.3" inputmode="numeric" autocomplete="off" required @disabled(! $canManage) @error('version') aria-invalid="true" aria-describedby="xmRelVersionError" @enderror>
                        @error('version')<div class="invalid-feedback" id="xmRelVersionError">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="xmRelKind">{{ __('admin_system.updates.form.kind') }}</label>
                        <select class="form-select" id="xmRelKind" wire:model="kind" @disabled(! $canManage)>
                            @foreach (\App\Models\AppVersion::KINDS as $key)<option value="{{ $key }}">{{ __('admin_system.updates.kinds.'.$key) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="xmRelDate">{{ __('admin_system.updates.form.date') }}</label>
                        <input class="form-control @error('scheduledFor') is-invalid @enderror" type="date" id="xmRelDate" wire:model="scheduledFor" @disabled(! $canManage || $mode !== 'plan') @error('scheduledFor') aria-invalid="true" aria-describedby="xmRelDateError" @enderror>
                        @error('scheduledFor')<div class="invalid-feedback" id="xmRelDateError">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="xmRelNotes">{{ __('admin_system.updates.form.notes') }} *</label>
                        <textarea class="form-control @error('notes') is-invalid @enderror" id="xmRelNotes" rows="3" wire:model="notes" maxlength="2000" required @disabled(! $canManage) @error('notes') aria-invalid="true" aria-describedby="xmRelNotesError" @enderror></textarea>
                        @error('notes')<div class="invalid-feedback" id="xmRelNotesError">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <fieldset>
                            <legend class="visually-hidden">{{ __('admin_system.updates.form.mode') }}</legend>
                            <div class="form-check form-check-inline"><input class="form-check-input" type="radio" id="xmRelNow" value="now" wire:model.live="mode" @disabled(! $canManage)><label class="form-check-label" for="xmRelNow">{{ __('admin_system.updates.form.now') }}</label></div>
                            <div class="form-check form-check-inline"><input class="form-check-input" type="radio" id="xmRelPlan" value="plan" wire:model.live="mode" @disabled(! $canManage)><label class="form-check-label" for="xmRelPlan">{{ __('admin_system.updates.form.plan') }}</label></div>
                        </fieldset>
                    </div>
                </div>
                @if ($canManage)
                    <div class="card-footer text-end"><button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="release">{{ $mode === 'plan' ? __('admin_system.updates.form.submit_plan') : __('admin_system.updates.form.submit_now') }}</button></div>
                @endif
            </form>
        </div>

        <div class="col-xl-5">
            {{-- Application mobile --}}
            <form class="card xm-admin-card mb-4" wire:submit="saveMinimum" novalidate aria-labelledby="xmMinTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmMinTitle">{{ __('admin_system.updates.mobile.title') }}</h2></div>
                <div class="card-body">
                    <p class="small text-secondary">{{ __('admin_system.updates.mobile.text', ['endpoint' => 'GET /api/v1/app/version']) }}</p>
                    <label class="form-label" for="xmMinVersion">{{ __('admin_system.updates.mobile.minimum') }}</label>
                    <input class="form-control @error('minVersion') is-invalid @enderror" id="xmMinVersion" wire:model="minVersion" placeholder="1.0.0" inputmode="numeric" autocomplete="off" @disabled(! $canManage) @error('minVersion') aria-invalid="true" aria-describedby="xmMinVersionError" @enderror>
                    @error('minVersion')<div class="invalid-feedback" id="xmMinVersionError">{{ $message }}</div>@enderror
                    <div class="form-text">{{ __('admin_system.updates.mobile.hint', ['current' => $this->stats['current'], 'minimum' => $minimum]) }}</div>
                </div>
                @if ($canManage)
                    <div class="card-footer text-end"><button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="saveMinimum">{{ __('admin_system.common.save') }}</button></div>
                @endif
            </form>

            {{-- Fenêtres de maintenance : lien seulement (P13-06) --}}
            <section class="card xm-admin-card mb-4" aria-labelledby="xmUpdMaintTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmUpdMaintTitle"><i class="bi bi-cone-striped me-2" aria-hidden="true"></i>{{ __('admin_system.updates.maintenance.title') }}</h2></div>
                <div class="card-body">
                    <p class="small mb-2">{{ $this->stats['maintenance'] ? __('admin_system.updates.maintenance.on') : __('admin_system.updates.maintenance.off') }}</p>
                    @can('platform.maintenance')<a wire:navigate wire:navigate.hover class="btn btn-sm btn-outline-primary" href="{{ route('admin.maintenance') }}">{{ __('admin_system.updates.maintenance.link') }}</a>@endcan
                </div>
            </section>

            {{-- Sauvegardes : résumé et lien (P13-03) --}}
            <section class="card xm-admin-card" aria-labelledby="xmUpdBackupsTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmUpdBackupsTitle"><i class="bi bi-database me-2" aria-hidden="true"></i>{{ __('admin_system.updates.backups.title') }}</h2></div>
                <div class="card-body">
                    <p class="small mb-2">
                        @if ($this->stats['lastBackup'])
                            {{ __('admin_system.updates.backups.last', ['name' => $this->stats['lastBackup']->name, 'ago' => $format->ago($this->stats['lastBackup']->created_at)]) }}
                        @else
                            {{ __('admin_system.updates.backups.none') }}
                        @endif
                    </p>
                    @can('backups.create')<a wire:navigate wire:navigate.hover class="btn btn-sm btn-outline-primary" href="{{ route('admin.sauvegardes') }}">{{ __('admin_system.updates.backups.link') }}</a>@endcan
                </div>
            </section>
        </div>
    </div>

    {{-- Optimisation --}}
    <section class="card xm-admin-card mb-4" aria-labelledby="xmOptimTitle">
        <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmOptimTitle">{{ __('admin_system.updates.optimization.title') }}</h2></div>
        <ul class="list-group list-group-flush">
            @foreach ($tasks as $task)
                @php($last = $this->optimization[$task] ?? null)
                <li class="list-group-item d-flex flex-wrap align-items-center gap-3" wire:key="task-{{ $task }}">
                    <i class="bi bi-{{ __('admin_system.updates.optimization.tasks.'.$task.'.icon') }} fs-4 text-primary" aria-hidden="true"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ __('admin_system.updates.optimization.tasks.'.$task.'.label') }}</div>
                        <div class="small text-secondary">
                            @if ($last)
                                {{ __('admin_system.updates.optimization.last', ['date' => $format->tableDateTime(\Illuminate\Support\Carbon::parse($last['at'])), 'result' => $last['result']]) }}
                            @else
                                {{ __('admin_system.updates.optimization.never') }}
                            @endif
                        </div>
                    </div>
                    @if ($canManage)
                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="run('{{ $task }}')" wire:loading.attr="disabled" wire:target="run">
                            <span wire:loading.remove wire:target="run('{{ $task }}')"><i class="bi bi-play-fill me-1" aria-hidden="true"></i></span><span class="spinner-border spinner-border-sm me-1" wire:loading wire:target="run('{{ $task }}')" aria-hidden="true"></span>{{ __('admin_system.updates.optimization.run') }}
                            <span class="visually-hidden"> {{ __('admin_system.updates.optimization.tasks.'.$task.'.label') }}</span>
                        </button>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    <div class="row g-4">
        <div class="col-lg-6">
            <section class="card xm-admin-card h-100" aria-labelledby="xmTopTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmTopTitle">{{ __('admin_system.updates.top.title') }}</h2></div>
                <div class="card-body">
                    @if ($top === [])
                        <x-admin.empty icon="bar-chart" :title="__('admin_system.updates.top.empty')">{{ __('admin_system.updates.top.empty_text') }}</x-admin.empty>
                    @else
                        @php($max = max(array_column($top, 'views')))
                        <ol class="list-unstyled mb-0">
                            @foreach ($top as $item)
                                <li class="mb-2">
                                    <div class="d-flex justify-content-between small"><span class="text-break">{{ $item['label'] }} <span class="text-secondary">· {{ __('admin_system.updates.top.kinds.'.$item['kind']) }}</span></span><span class="text-nowrap">{{ trans_choice('admin_system.updates.top.views', $item['views'], ['count' => $format->number($item['views'])]) }}</span></div>
                                    <div class="progress" style="height:6px" role="presentation"><div class="progress-bar" style="width: {{ round($item['views'] / max(1, $max) * 100) }}%"></div></div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="card xm-admin-card h-100" aria-labelledby="xmAdoptTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmAdoptTitle">{{ __('admin_system.updates.adoption.title') }}</h2></div>
                <div class="card-body">
                    <p class="small text-secondary">{{ __('admin_system.updates.adoption.intro', ['days' => \App\Application\Platform\Updates\UsageInsights::WINDOW_DAYS, 'members' => $format->number($adoption['members'])]) }}</p>
                    @foreach ($adoption['items'] as $item)
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small"><span>{{ __('admin_system.updates.adoption.features.'.$item['key']) }}</span><span>{{ $item['percent'] === null ? __('admin_system.updates.adoption.masked') : $item['percent'].' %' }}</span></div>
                            <div class="progress" style="height:6px" role="progressbar" aria-label="{{ __('admin_system.updates.adoption.features.'.$item['key']) }}" @if ($item['percent'] !== null) aria-valuenow="{{ $item['percent'] }}" aria-valuemin="0" aria-valuemax="100" @endif><div class="progress-bar" style="width: {{ $item['percent'] ?? 0 }}%"></div></div>
                        </div>
                    @endforeach
                    @php($offline = collect($adoption['items'])->firstWhere('key', 'offline'))
                    @if (($offline['percent'] !== null && $offline['percent'] < 30) || $unsynced > 0)
                        <hr>
                        <h3 class="h6">{{ __('admin_system.updates.adoption.advice') }}</h3>
                        <ul class="small mb-0">
                            @if ($offline['percent'] !== null && $offline['percent'] < 30)<li>{{ __('admin_system.updates.adoption.advice_training', ['percent' => $offline['percent']]) }}</li>@endif
                            @if ($unsynced > 0)<li>{{ trans_choice('admin_system.updates.adoption.advice_reminder', $unsynced, ['count' => $unsynced]) }}</li>@endif
                        </ul>
                    @endif
                </div>
            </section>
        </div>
    </div>
</div>
