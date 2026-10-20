{{--
    Indicateurs (P10-06) : composant App\Livewire\Admin\Indicators\Index, gabarit admin ; reprend admin/indicateurs.php.
    Ordre : introduction + actions · synthèse (3 KPI) · filtres (période, département, fréquence) · tableau des
    indicateurs (valeur, cible, avancement, tendance, graphique à la demande) · tableau « Contenu » (sans traceur tiers) ·
    modales (nouvel indicateur, nouvelle valeur, graphique), toujours montées. Effectifs de 1 à 4 : « données masquées ».
--}}
@php
    $masked = (string) __('admin.kpi.masked');
    $display = function (\App\Application\Steering\IndicatorRow $row) use ($format): string {
        if ($row->value === null) {
            return '—';
        }

        return $row->indicator->isPercentage() ? $format->percent($row->value, 1) : $format->number($row->value, (float) (int) $row->value === $row->value ? 0 : 2);
    };
    $targetText = function (\App\Models\Indicator $i) use ($format): string {
        $text = $i->isPercentage() ? $format->percent((float) $i->target, 0) : $format->number((float) $i->target, ((float) $i->target) == (int) $i->target ? 0 : 2);

        return $i->better === \App\Models\Indicator::BETTER_DOWN ? __('steering.indicators.target_down', ['value' => $text]) : $text;
    };
    $spark = function (array $trend): ?string {
        $values = array_values(array_filter(array_column($trend, 'value'), static fn ($v) => $v !== null));
        if (count($values) < 2) {
            return null;
        }
        $w = 90; $h = 26; $min = min($values); $max = max($values); $range = ($max - $min) ?: 1; $points = []; $n = count($trend);
        foreach ($trend as $i => $point) {
            if ($point['value'] === null) { continue; }
            $points[] = round($i * $w / max(1, $n - 1), 1).','.round($h - 2 - ($point['value'] - $min) * ($h - 4) / $range, 1);
        }
        return implode(' ', $points);
    };
    $statusColor = ['reached' => 'success', 'progress' => 'warning', 'low' => 'danger'];
    $kpis = [
        ['label' => __('steering.indicators.summary.reached'), 'value' => $format->number($summary['reached']), 'icon' => 'check2-circle', 'variant' => 'success'],
        ['label' => __('steering.indicators.summary.progress'), 'value' => $format->number($summary['progress']), 'icon' => 'graph-up-arrow', 'variant' => 'warning', 'sub' => __('steering.indicators.summary.progress_help', ['percent' => \App\Application\Steering\IndicatorBoard::PROGRESS_FLOOR])],
        ['label' => __('steering.indicators.summary.low'), 'value' => $format->number($summary['low']), 'icon' => 'exclamation-triangle', 'variant' => 'danger'],
        ['label' => __('steering.indicators.summary.unrated'), 'value' => $format->number($summary['unrated']), 'icon' => 'eye-slash', 'sub' => __('steering.indicators.summary.unrated_help')],
    ];
    $presetLabels = ['prec' => __('steering.indicators.filters.presets.prec'), 'mois' => __('steering.indicators.filters.presets.mois'), 'trim' => __('steering.indicators.filters.presets.trim'), 'annee' => __('steering.indicators.filters.presets.annee'), 'perso' => __('steering.indicators.filters.presets.perso')];
@endphp
<div class="xm-indicators" data-xm-screen="indicators" data-xm-needs="chart">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
        <p class="text-secondary mb-0 xm-prose">{{ __('steering.indicators.intro') }}</p>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="export" wire:loading.attr="disabled" wire:target="export">
                <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>{{ __('steering.indicators.export', ['count' => $format->number(count($items))]) }}
            </button>
            @if ($canManage)
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#xmIndicatorModal"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('steering.indicators.new') }}</button>
            @endif
        </div>
    </div>

    <x-admin.kpi id="xmIndKpis" cols="col-6 col-xl-3" :title="__('steering.indicators.summary.title')" :items="$kpis" />

    <section class="card xm-admin-card mb-4" aria-labelledby="xmIndFiltersTitle">
        <div class="card-body">
            <h2 class="visually-hidden" id="xmIndFiltersTitle">{{ __('steering.indicators.filters.title') }}</h2>
            <form class="row g-2 align-items-end" role="search" aria-label="{{ __('steering.indicators.filters.title') }}" wire:submit.prevent novalidate>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small mb-1" for="xmIndPreset">{{ __('steering.indicators.filters.period') }}</label>
                    <select class="form-select form-select-sm" id="xmIndPreset" wire:model.live="preset">
                        @foreach ($presetLabels as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                @if ($preset === 'perso')
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small mb-1" for="xmIndFrom">{{ __('steering.indicators.filters.from') }}</label>
                        <input class="form-control form-control-sm @if ($this->periodError) is-invalid @endif" type="date" id="xmIndFrom" wire:model.live="from" @if ($this->periodError) aria-invalid="true" aria-describedby="xmIndPeriodError" @endif>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small mb-1" for="xmIndUntil">{{ __('steering.indicators.filters.to') }}</label>
                        <input class="form-control form-control-sm @if ($this->periodError) is-invalid @endif" type="date" id="xmIndUntil" wire:model.live="until" @if ($this->periodError) aria-invalid="true" aria-describedby="xmIndPeriodError" @endif>
                    </div>
                @endif
                <div class="col-6 col-md-3 col-xl-3">
                    <label class="form-label small mb-1" for="xmIndZone">{{ __('steering.indicators.filters.zone') }}</label>
                    <select class="form-select form-select-sm" id="xmIndZone" wire:model.live="zone">
                        <option value="">{{ __('steering.indicators.filters.all_zones') }}</option>
                        @foreach ($zones as $z)<option value="{{ $z['id'] }}">{{ $z['name'] }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-xl-2">
                    <label class="form-label small mb-1" for="xmIndFreq">{{ __('steering.indicators.filters.frequency') }}</label>
                    <select class="form-select form-select-sm" id="xmIndFreq" wire:model.live="frequency">
                        <option value="">{{ __('steering.indicators.filters.all_frequencies') }}</option>
                        @foreach ($frequencies as $f)<option value="{{ $f->value }}">{{ $f->label() }}</option>@endforeach
                    </select>
                </div>
                @if ($filtered)
                    <div class="col-auto"><button type="button" class="btn btn-sm btn-light border" wire:click="resetFilters"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>{{ __('admin.filters.reset') }}</button></div>
                @endif
            </form>
            @if ($this->periodError)<p class="small text-danger mt-2 mb-0" id="xmIndPeriodError" role="alert"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $this->periodError }}</p>@endif
            <p class="small text-secondary mt-2 mb-0" role="status">
                {{ __('steering.indicators.filters.shown', ['period' => $this->period->label()]) }}
                @if ($zone !== '' && ($selected = collect($zones)->firstWhere('id', (int) $zone)))
                    {{ __('steering.indicators.filters.zone_note', ['zone' => $selected['name']]) }}
                @endif
            </p>
        </div>
    </section>

    <section class="card xm-admin-card mb-4" aria-labelledby="xmIndTableTitle">
        <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmIndTableTitle">{{ __('steering.indicators.table_title') }}</h2></div>
        <x-admin.table id="xmIndTable" :caption="__('steering.indicators.table_caption')" :empty="count($items) === 0" :columns="[
            ['key' => 'code', 'label' => __('steering.indicators.columns.code'), 'width' => '6rem'],
            ['key' => 'label', 'label' => __('steering.indicators.columns.label')],
            ['key' => 'source', 'label' => __('steering.indicators.columns.source_frequency')],
            ['key' => 'value', 'label' => __('steering.indicators.columns.value'), 'class' => 'text-end'],
            ['key' => 'target', 'label' => __('steering.indicators.columns.target'), 'width' => '12rem'],
            ['key' => 'trend', 'label' => __('steering.indicators.columns.trend')],
            ['key' => 'actions', 'label' => '', 'sr' => __('admin.alerts.columns.actions'), 'class' => 'text-end'],
        ]">
            @foreach ($items as $row)
                @php($indicator = $row->indicator)
                @php($points = $spark($row->trend))
                <tr wire:key="ind-{{ $indicator->id }}">
                    <td data-label="{{ __('steering.indicators.columns.code') }}" class="fw-semibold small text-nowrap">{{ $indicator->code }}</td>
                    <td data-label="{{ __('steering.indicators.columns.label') }}">
                        <div class="min-w-0">
                            <span class="fw-semibold d-block">{{ $indicator->label }}</span>
                            @if ($indicator->definition !== '' || $indicator->formula !== '')
                                <details class="small text-secondary">
                                    <summary>{{ __('steering.indicators.definition') }}</summary>
                                    @if ($indicator->definition !== '')<p class="mb-1">{{ $indicator->definition }}</p>@endif
                                    @if ($indicator->formula !== '')<p class="mb-0"><span class="fw-semibold">{{ __('steering.indicators.formula') }}</span> {{ $indicator->formula }}</p>@endif
                                </details>
                            @endif
                            @if (! $indicator->is_builtin)<x-badge color="violet" soft>{{ __('steering.indicators.custom') }}</x-badge>@endif
                        </div>
                    </td>
                    <td data-label="{{ __('steering.indicators.columns.source_frequency') }}" class="small">
                        <div class="min-w-0">{{ $indicator->source }}<span class="d-block text-secondary">{{ $indicator->frequency->label() }}</span></div>
                    </td>
                    <td data-label="{{ __('steering.indicators.columns.value') }}" class="text-end fw-bold text-nowrap">
                        @if ($row->masked)
                            <span class="text-secondary fst-italic fw-normal">{{ $masked }}</span>
                        @elseif ($row->value === null)
                            <span class="text-secondary fw-normal" title="{{ $row->reason === 'manual_missing' ? __('steering.indicators.manual_missing') : $row->reason }}">—<span class="visually-hidden"> {{ $row->reason === 'manual_missing' ? __('steering.indicators.manual_missing') : __('steering.indicators.no_data') }}</span></span>
                        @else
                            {{ $display($row) }}
                        @endif
                    </td>
                    <td data-label="{{ __('steering.indicators.columns.target') }}">
                        <div class="min-w-0">
                            @if ($row->progress !== null)
                                @php($color = $statusColor[$row->status] ?? 'secondary')
                                <div class="progress" style="height:8px" role="progressbar" aria-label="{{ __('steering.indicators.progress_of', ['code' => $indicator->code]) }}" aria-valuenow="{{ round($row->progress) }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bg-{{ $color }}" style="width:{{ round($row->progress) }}%"></div>
                                </div>
                            @endif
                            <span class="small text-secondary">{{ $targetText($indicator) }}@if ($row->progress !== null) · {{ $format->number($row->progress, 0) }} %<span class="visually-hidden"> — {{ __('steering.indicators.status.'.$row->status) }}</span>@endif</span>
                        </div>
                    </td>
                    <td data-label="{{ __('steering.indicators.columns.trend') }}">
                        @if ($points)
                            <div class="d-flex align-items-center gap-2">
                                <svg width="90" height="26" viewBox="0 0 90 26" aria-hidden="true" focusable="false"><polyline fill="none" stroke="var(--xm-primary, #0433C3)" stroke-width="2" points="{{ $points }}"/></svg>
                                @if ($row->trendDirection)
                                    @php($good = $row->trendDirection === 'flat' ? null : (($row->trendDirection === 'up') === ($indicator->better === \App\Models\Indicator::BETTER_UP)))
                                    <i @class(['bi', 'bi-arrow-up-right' => $row->trendDirection === 'up', 'bi-arrow-down-right' => $row->trendDirection === 'down', 'bi-arrow-right' => $row->trendDirection === 'flat', 'text-success' => $good === true, 'text-danger' => $good === false, 'text-secondary' => $good === null]) aria-hidden="true"></i>
                                    <span class="visually-hidden">{{ __('steering.indicators.trend.'.$row->trendDirection) }}</span>
                                @endif
                            </div>
                        @elseif ($indicator->isManual())
                            <span class="small text-secondary">{{ __('steering.indicators.manual') }}</span>
                        @else
                            <span class="small text-secondary">{{ $row->masked ? $masked : __('steering.indicators.no_trend') }}</span>
                        @endif
                    </td>
                    <td data-label="{{ __('admin.alerts.columns.actions') }}" class="text-end text-nowrap xm-table__actions">
                        @if ($row->trend !== [])
                            <button type="button" class="btn btn-sm btn-light border" data-xm-ind-chart="{{ json_encode(['code' => $indicator->code, 'label' => $indicator->label, 'unit' => $indicator->unit, 'target' => (float) $indicator->target, 'labels' => array_column($row->trend, 'label'), 'values' => array_column($row->trend, 'value'), 'maskedLabel' => $masked, 'valueLabel' => __('steering.indicators.columns.value'), 'targetLabel' => __('steering.indicators.columns.target')], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) }}" wire:key="chart-{{ $indicator->id }}">
                                <i class="bi bi-graph-up me-1" aria-hidden="true"></i>{{ __('steering.indicators.chart') }}<span class="visually-hidden"> {{ $indicator->code }}</span>
                            </button>
                        @endif
                        @if ($canManage && ! $indicator->is_builtin)
                            @if ($indicator->isManual())
                                <button type="button" class="btn btn-sm btn-light border" wire:click="editValue({{ $indicator->id }})"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('steering.indicators.update_value') }}<span class="visually-hidden"> {{ $indicator->code }}</span></button>
                            @endif
                            <button type="button" class="btn btn-sm btn-light text-danger border" wire:click="delete({{ $indicator->id }})"
                                    wire:confirm="{{ __('steering.indicators.confirm_delete', ['label' => $indicator->label]) }}"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ __('steering.indicators.delete') }} {{ $indicator->code }}</span></button>
                        @endif
                    </td>
                </tr>
            @endforeach
            <x-slot:emptyState>
                <x-admin.empty icon="bar-chart-line" :filtered="$filtered" :title="__('steering.indicators.empty')">{{ __('steering.indicators.empty_text') }}</x-admin.empty>
            </x-slot:emptyState>
        </x-admin.table>
        <div class="card-footer small text-secondary">{{ __('steering.indicators.footnote', ['min' => \App\Support\SmallCellMask::MIN]) }}</div>
    </section>

    <section class="card xm-admin-card mb-4" aria-labelledby="xmIndContentTitle">
        <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmIndContentTitle">{{ __('steering.content.title') }}</h2></div>
        <div class="card-body pb-0"><p class="small text-secondary mb-2">{{ __('steering.content.intro', ['days' => \App\Application\Steering\ContentEvents::retentionDays()]) }}</p></div>
        <x-admin.table id="xmIndContent" :caption="__('steering.content.title')" :stack="true" :empty="false" :columns="[
            ['key' => 'metric', 'label' => __('steering.content.columns.metric')],
            ['key' => 'source', 'label' => __('steering.content.columns.source')],
            ['key' => 'value', 'label' => __('steering.content.columns.value'), 'class' => 'text-end'],
        ]">
            @foreach ($content as $metric)
                <tr wire:key="content-{{ $metric['key'] }}">
                    <td data-label="{{ __('steering.content.columns.metric') }}">{{ __('steering.content.metrics.'.$metric['key']) }}</td>
                    <td data-label="{{ __('steering.content.columns.source') }}" class="small text-secondary">{{ __('steering.content.sources.'.$metric['source']) }}</td>
                    <td data-label="{{ __('steering.content.columns.value') }}" class="text-end fw-semibold text-nowrap">
                        @if ($metric['masked'] || $metric['value'] === null)
                            <span class="text-secondary fst-italic fw-normal">{{ $metric['masked'] ? $masked : '—' }}</span>
                        @else
                            {{ $format->number($metric['value']) }}@if ($metric['unit'] === 'min') <span class="fw-normal text-secondary">{{ __('steering.content.minutes') }}</span>@endif
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-admin.table>
    </section>

    {{-- Graphique à la demande : Chart.js chargé au premier clic (resources/js/admin/screens/indicators.js). --}}
    <x-admin.modal id="xmIndChartModal" size="lg" type="form" heading="h2" :title="__('steering.indicators.chart')">
        <div wire:ignore>
            <p class="small text-secondary" data-xm-ind-chart-title></p>
            <div class="xm-chart" style="height: 280px"><canvas id="xmIndChartCanvas" role="img" aria-label="{{ __('steering.indicators.chart') }}" aria-describedby="xmIndChartData"></canvas></div>
            <div class="table-responsive mt-3">
                <table class="table table-sm align-middle mb-0" id="xmIndChartData">
                    <caption class="visually-hidden">{{ __('steering.indicators.chart_table') }}</caption>
                    <thead><tr><th scope="col">{{ __('steering.indicators.chart_month') }}</th><th scope="col" class="text-end" data-xm-ind-chart-value></th></tr></thead>
                    <tbody data-xm-ind-chart-body></tbody>
                </table>
            </div>
        </div>
    </x-admin.modal>

    @if ($canManage)
        <x-admin.modal id="xmIndicatorModal" size="lg" type="form" heading="h2" :title="__('steering.indicators.new_title')" :dirty-guard="true">
            <form id="xmIndicatorForm" wire:submit="create" novalidate>
                <div class="row g-3">
                    <div class="col-md-3"><x-field name="code" :label="__('steering.indicators.fields.code')" wire:model.blur="code" maxlength="10" :placeholder="__('steering.indicators.fields.code_auto')" optional /></div>
                    <div class="col-md-9"><x-field name="label" :label="__('steering.indicators.fields.label')" wire:model.blur="label" maxlength="160" /></div>
                    <div class="col-12"><x-field name="definition" type="textarea" :label="__('steering.indicators.fields.definition')" wire:model.blur="definition" :rows="2" maxlength="500" optional /></div>
                    <div class="col-md-6"><x-field name="formula" :label="__('steering.indicators.fields.formula')" wire:model.blur="formula" maxlength="255" optional /></div>
                    <div class="col-md-6"><x-field name="source" :label="__('steering.indicators.fields.source')" wire:model.blur="source" maxlength="80" optional /></div>
                    <div class="col-md-4"><x-field name="newFrequency" type="select" :label="__('steering.indicators.fields.frequency')" wire:model.blur="newFrequency" :value="$newFrequency" :options="collect($frequencies)->mapWithKeys(fn ($f) => [$f->value => $f->label()])->all()" /></div>
                    <div class="col-md-4"><x-field name="unit" type="select" :label="__('steering.indicators.fields.unit')" wire:model.live="unit" :value="$unit" :options="['nb' => __('steering.indicators.unit_number'), '%' => __('steering.indicators.unit_percent')]" /></div>
                    <div class="col-md-4"><x-field name="better" type="select" :label="__('steering.indicators.fields.better')" wire:model.blur="better" :value="$better" :options="['up' => __('steering.indicators.better_up'), 'down' => __('steering.indicators.better_down')]" /></div>
                    <div class="col-md-6"><x-field name="target" :label="__('steering.indicators.fields.target')" wire:model.blur="target" inputmode="decimal" /></div>
                    <div class="col-md-6"><x-field name="value" :label="__('steering.indicators.fields.value')" wire:model.blur="value" inputmode="decimal" :hint="__('steering.indicators.fields.value_hint')" /></div>
                </div>
            </form>
            <x-slot:footer>
                <button type="submit" form="xmIndicatorForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="create"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('steering.indicators.add') }}</button>
            </x-slot:footer>
        </x-admin.modal>

        <x-admin.modal id="xmIndicatorValueModal" size="md" type="form" heading="h2" :title="__('steering.indicators.value_title')">
            <form id="xmIndicatorValueForm" wire:submit="saveValue" novalidate>
                @if ($editing)<p class="small text-secondary">{{ $editing->code }} — {{ $editing->label }}</p>@endif
                <x-field name="editValue" :label="__('steering.indicators.fields.value')" wire:model.blur="editValue" inputmode="decimal" :hint="__('steering.indicators.fields.value_month_hint')" />
            </form>
            <x-slot:footer>
                <button type="submit" form="xmIndicatorValueForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveValue">{{ __('steering.indicators.save_value') }}</button>
            </x-slot:footer>
        </x-admin.modal>
    @endif
</div>
