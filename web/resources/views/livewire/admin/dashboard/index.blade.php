{{--
    Tableau de bord (P10-04) : composant App\Livewire\Admin\Dashboard\Index, gabarit admin ; reprend admin/index.php et
    design-system/xomiton/pages/dashboard-admin.md. Ordre : bandeau SOS actifs · KPI · graphiques (Chart.js à la demande,
    tableau de données sous chacun, effectifs de 1 à 4 masqués) · dernières alertes + SOS actifs (carte schématique, Q15 b) ·
    raccourcis + état de la plateforme. Chaque bloc n'apparaît qu'avec la permission de son écran d'origine.
--}}
@php
    // Effectif de dossiers : `null` = masqué (1 à 4, ou suppression complémentaire, DashboardStats::caseCounts()).
    $mask = static fn (?int $count): array => ['masked' => $count === null, 'value' => $format->number((int) $count)];
    $kpis = [];

    if ($alertCounters) {
        $kpis[] = ['label' => __('admin.kpi.new_alerts'), 'value' => $format->number($alertCounters['new']), 'icon' => 'bell', 'variant' => 'danger', 'href' => route('admin.alertes', ['st' => 'nouveau'])];
        $kpis[] = ['label' => __('admin.kpi.active_sos'), 'value' => $format->number($alertCounters['sos_active']), 'icon' => 'geo-alt-fill', 'variant' => 'danger', 'href' => route('admin.alertes', ['onglet' => 'sos'])];
    }

    if ($cases) {
        foreach (['open' => ['open_cases', 'folder2-open', ''], 'in_progress' => ['in_progress_cases', 'hourglass-split', 'warning'], 'closed' => ['closed_cases', 'check2-circle', 'success']] as $key => [$label, $icon, $variant]) {
            $cell = $mask($cases[$key]);
            $kpis[] = ['label' => __('admin.kpi.'.$label), 'value' => $cell['value'], 'masked' => $cell['masked'], 'icon' => $icon, 'variant' => $variant, 'href' => Route::has('admin.registres') ? route('admin.registres') : null];
        }
    }

    if ($userCounts) {
        $kpis[] = ['label' => __('admin.kpi.users'), 'value' => $format->number($userCounts['users']), 'icon' => 'people', 'variant' => 'violet', 'href' => route('admin.utilisateurs'),
                   'sub' => $userCounts['pending'] > 0 ? trans_choice('admin.dashboard.pending_sub', $userCounts['pending'], ['count' => $format->number($userCounts['pending'])]) : null];
    }

    if ($canSync) {
        $kpis[] = ['label' => __('admin.kpi.sync_rate'), 'value' => $syncRate ? $format->percent($syncRate['rate'], 0) : '—', 'icon' => 'cloud-check', 'variant' => 'success', 'href' => route('admin.synchronisation'),
                   'sub' => $syncRate ? __('admin.dashboard.sync_sub', ['synced' => $format->number($syncRate['synced']), 'active' => $format->number($syncRate['active']), 'hours' => (int) config('xomiton.sync.device_stale_hours', 24)]) : __('admin.dashboard.sync_none')];
    }

    $chartTitles = ['month' => 'cases_per_month', 'violence' => 'by_violence', 'zone' => 'by_zone', 'population' => 'by_population', 'sex_age' => 'by_sex_age'];
    $chartCols = ['month' => 'col-xl-8', 'violence' => 'col-xl-4', 'zone' => 'col-lg-6 col-xl-4', 'population' => 'col-lg-6 col-xl-4', 'sex_age' => 'col-xl-4'];
    // Vrai décompte (le KPI et le bandeau disent la même chose) : la liste $activeSos est plafonnée à 20.
    $activeSosCount = (int) ($alertCounters['sos_active'] ?? $activeSos->count());
@endphp
<div class="xm-dashboard" wire:poll.{{ $pollSeconds }}s="refresh" data-xm-screen="dashboard">
    {{-- Bandeau « Alertes SOS actives » : en tête, fond --xm-secondary-subtle, icône cloche, accusé de réception ; fixe en mouvement réduit. --}}
    @if ($canAlerts && $activeSosCount > 0)
        <section class="xm-sos-banner mb-4" aria-labelledby="xmSosBannerTitle" data-xm-sos-banner>
            <div class="d-flex flex-wrap align-items-start gap-3">
                <span class="xm-sos-banner__icon" aria-hidden="true"><i class="bi bi-bell-fill"></i></span>
                <div class="flex-grow-1">
                    {{-- Seule région « live » : titre et consigne au texte stable. Les durées relatives (« il y a X min ») changent
                         à chaque sondage de 30 s et restent hors de cette région, sinon les lecteurs d'écran ré-annonceraient
                         tout le bandeau, boutons compris. --}}
                    <div role="alert" aria-atomic="true">
                        <h2 class="h5 mb-1" id="xmSosBannerTitle">{{ trans_choice('admin.dashboard.sos_banner', $activeSosCount, ['count' => $format->number($activeSosCount)]) }}</h2>
                        <p class="small mb-2">{{ __('admin.dashboard.sos_banner_text', ['minutes' => $escalationMinutes ?? 5]) }}</p>
                    </div>
                    <ul class="list-unstyled mb-0 xm-sos-banner__list">
                        @foreach ($activeSos->take(5) as $sos)
                            <li class="d-flex flex-wrap align-items-center gap-2 py-1" wire:key="sos-banner-{{ $sos->id }}">
                                <span class="fw-semibold">#{{ $sos->id }}</span>
                                <span>{{ $sos->commune?->name ?? $sos->zone?->name ?? __('admin.alerts.zone_unknown') }}</span>
                                <span class="small text-secondary">{{ $format->ago($sos->created_at) }}</span>
                                @if ($sos->isAcknowledged())
                                    <x-badge color="success" soft icon="check2">{{ __('admin.dashboard.acknowledged') }}</x-badge>
                                @else
                                    @can('take', $sos)
                                        <button type="button" class="btn btn-sm btn-danger ms-auto" wire:click="acknowledge({{ $sos->id }})" wire:loading.attr="disabled" wire:target="acknowledge({{ $sos->id }})">
                                            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>{{ __('admin.dashboard.acknowledge') }}<span class="visually-hidden"> {{ __('admin.alerts.of', ['code' => '#'.$sos->id]) }}</span>
                                        </button>
                                    @endcan
                                @endif
                                <a class="btn btn-sm btn-light border @unless ($sos->isAcknowledged()) @else ms-auto @endunless" href="{{ route('admin.alertes', ['q' => '#'.$sos->id]) }}">{{ __('admin.dashboard.open_alert') }}<span class="visually-hidden"> {{ __('admin.alerts.of', ['code' => '#'.$sos->id]) }}</span></a>
                            </li>
                        @endforeach
                    </ul>
                    @if ($activeSosCount > 5)
                        <a wire:navigate wire:navigate.hover class="small" href="{{ route('admin.alertes', ['onglet' => 'sos']) }}">{{ __('admin.dashboard.all_sos', ['count' => $format->number($activeSosCount)]) }}</a>
                    @endif
                </div>
                <button type="button" class="btn btn-sm btn-light border" data-xm-sos-sound aria-pressed="false" wire:ignore>
                    <i class="bi bi-volume-mute" aria-hidden="true" data-xm-sos-sound-icon></i>
                    <span data-xm-sos-sound-label data-on="{{ __('admin.dashboard.sound_on') }}" data-off="{{ __('admin.dashboard.sound_off') }}">{{ __('admin.dashboard.sound_off') }}</span>
                </button>
            </div>
        </section>
    @endif

    @if ($kpis !== [])
        <x-admin.kpi id="xmDashKpis" cols="col-6 col-md-4 col-xl-3" :items="$kpis" />
    @endif

    @if ($charts !== [])
        <div wire:ignore data-xm-needs="chart" data-xm-dashboard-charts>
            <script type="application/json" id="xmDashboardCharts">@json(['charts' => $charts, 'maskedLabel' => __('admin.kpi.masked')])</script>
            <div class="row g-4 mb-2">
                @foreach ($charts as $chart)
                    @php($title = __('admin.dashboard.'.$chartTitles[$chart['key']]))
                    <div class="{{ $chartCols[$chart['key']] ?? 'col-xl-4' }}">
                        <section class="card h-100 xm-admin-card" aria-labelledby="xmChart-{{ $chart['key'] }}-title">
                            <div class="card-header"><h2 class="h6 mb-0" id="xmChart-{{ $chart['key'] }}-title">{{ $title }}</h2></div>
                            <div class="card-body">
                                <div class="xm-chart" style="height: 260px">
                                    <canvas id="xmChart-{{ $chart['key'] }}" data-xm-chart="{{ $chart['key'] }}" role="img" aria-label="{{ $title }}" aria-describedby="xmChart-{{ $chart['key'] }}-data">{{ $title }}</canvas>
                                </div>
                                <details class="mt-3 small" id="xmChart-{{ $chart['key'] }}-data">
                                    <summary>{{ __('admin.dashboard.chart_table', ['title' => $title]) }}</summary>
                                    <div class="table-responsive mt-2">
                                        <table class="table table-sm align-middle mb-0">
                                            <caption class="visually-hidden">{{ __('admin.dashboard.chart_table', ['title' => $title]) }}</caption>
                                            <thead>
                                                <tr>
                                                    <th scope="col">{{ __('admin.dashboard.chart_category') }}</th>
                                                    @foreach ($chart['datasets'] as $dataset)<th scope="col" class="text-end">{{ $dataset['label'] }}</th>@endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($chart['labels'] as $index => $label)
                                                    <tr>
                                                        <th scope="row" class="fw-normal">{{ $label }}</th>
                                                        @foreach ($chart['datasets'] as $dataset)
                                                            @php($cell = $dataset['data'][$index] ?? null)
                                                            <td @class(['text-end', 'text-secondary fst-italic' => $cell === null])>{{ $cell === null ? __('admin.kpi.masked') : $format->number($cell) }}</td>
                                                        @endforeach
                                                    </tr>
                                                @empty
                                                    <tr><td colspan="{{ 1 + count($chart['datasets']) }}" class="text-secondary">{{ __('admin.dashboard.chart_empty') }}</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                    @if ($chart['masked'])<p class="form-text mb-0 mt-2">{{ __('admin.kpi.masked_help', ['min' => \App\Support\SmallCellMask::MIN]) }}</p>@endif
                                </details>
                            </div>
                        </section>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($canAlerts)
        <div class="row g-4 mb-4 mt-0">
            <div class="col-xl-8">
                <section class="card h-100 xm-admin-card" aria-labelledby="xmLatestAlertsTitle">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="h6 mb-0" id="xmLatestAlertsTitle">{{ __('admin.dashboard.latest_alerts') }}</h2>
                        <a wire:navigate wire:navigate.hover href="{{ route('admin.alertes') }}" class="small">{{ __('admin.dashboard.see_all') }}</a>
                    </div>
                    <x-admin.table id="xmLatestAlerts" :caption="__('admin.dashboard.latest_alerts')" :empty="$latestAlerts->isEmpty()" :columns="[
                        ['key' => 'code', 'label' => __('admin.alerts.columns.code')],
                        ['key' => 'type', 'label' => __('admin.alerts.columns.type')],
                        ['key' => 'zone', 'label' => __('admin.alerts.columns.zone')],
                        ['key' => 'priority', 'label' => __('admin.alerts.columns.priority')],
                        ['key' => 'status', 'label' => __('admin.alerts.columns.status')],
                        ['key' => 'actions', 'label' => '', 'sr' => __('admin.alerts.columns.actions'), 'class' => 'text-end'],
                    ]">
                        @foreach ($latestAlerts as $alert)
                            <tr wire:key="latest-{{ $alert->id }}">
                                <td data-label="{{ __('admin.alerts.columns.code') }}">#{{ $alert->id }}</td>
                                <td data-label="{{ __('admin.alerts.columns.type') }}"><x-badge scope="alert_type" :status="$alert->type->value" /></td>
                                <td data-label="{{ __('admin.alerts.columns.zone') }}"><div class="min-w-0">{{ $alert->zone?->name ?? '—' }} @if ($alert->commune)<span class="small text-secondary">{{ $alert->commune->name }}</span>@endif</div></td>
                                <td data-label="{{ __('admin.alerts.columns.priority') }}"><x-badge scope="priority" :status="$alert->priority->value" /></td>
                                <td data-label="{{ __('admin.alerts.columns.status') }}"><div class="min-w-0"><x-badge :color="$alert->status === \App\Enums\AlertStatus::Closed ? 'neutral' : $alert->status->badge()" soft>{{ $alert->status->label() }}</x-badge><span class="d-block small text-secondary text-nowrap">{{ $format->ago($alert->created_at) }}</span></div></td>
                                <td data-label="{{ __('admin.alerts.columns.actions') }}" class="text-end text-nowrap xm-table__actions">
                                    @if ($alert->status === \App\Enums\AlertStatus::New)
                                        @can('take', $alert)
                                            <button type="button" class="btn btn-sm btn-primary" wire:click="acknowledge({{ $alert->id }})" wire:loading.attr="disabled" wire:target="acknowledge({{ $alert->id }})">{{ __('admin.alerts.take') }}<span class="visually-hidden"> {{ __('admin.alerts.of', ['code' => '#'.$alert->id]) }}</span></button>
                                        @endcan
                                    @else
                                        <a wire:navigate wire:navigate.hover class="btn btn-sm btn-light border" href="{{ route('admin.alertes', ['q' => '#'.$alert->id]) }}">{{ __('admin.dashboard.view') }}<span class="visually-hidden"> {{ __('admin.alerts.of', ['code' => '#'.$alert->id]) }}</span></a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        <x-slot:emptyState>
                            <x-admin.empty icon="bell" :title="__('admin.alerts.empty')">{{ __('admin.alerts.empty_text') }}</x-admin.empty>
                        </x-slot:emptyState>
                    </x-admin.table>
                </section>
            </div>
            <div class="col-xl-4">
                <section class="card h-100 xm-admin-card" aria-labelledby="xmSosMapTitle">
                    <div class="card-header"><h2 class="h6 mb-0" id="xmSosMapTitle"><i class="bi bi-geo-alt text-danger me-1" aria-hidden="true"></i>{{ __('admin.dashboard.active_sos_map') }}</h2></div>
                    <div class="card-body">
                        <div class="xm-map xm-sos-map mb-3" role="img" aria-label="{{ __('admin.dashboard.map_label') }}">
                            @foreach ($activeSos as $sos)
                                @if ($pin = \App\Livewire\Admin\Dashboard\Index::pin($sos))
                                    <span class="pin" style="left: {{ $pin['left'] }}%; top: {{ $pin['top'] }}%" title="SOS #{{ $sos->id }} — {{ $sos->commune?->name ?? $sos->zone?->name }}"></span>
                                @endif
                            @endforeach
                            <span class="position-absolute bottom-0 start-0 m-2 small text-secondary">{{ __('admin.dashboard.map_caption') }}</span>
                        </div>
                        <ul class="list-unstyled small mb-0">
                            @forelse ($activeSos as $sos)
                                <li class="d-flex justify-content-between gap-2 mb-1" wire:key="sos-map-{{ $sos->id }}">
                                    <span><i class="bi bi-geo-alt-fill text-danger" aria-hidden="true"></i> #{{ $sos->id }} {{ $sos->commune?->name ?? $sos->zone?->name }}</span>
                                    {{-- Aucune coordonnée exacte ici : seul le détail de l'alerte les lit, et l'ouverture est journalisée (`alerts.viewed`). --}}
                                    <a wire:navigate wire:navigate.hover href="{{ route('admin.alertes', ['q' => '#'.$sos->id]) }}">{{ __('admin.dashboard.open_alert') }}<span class="visually-hidden"> {{ __('admin.alerts.of', ['code' => '#'.$sos->id]) }}</span></a>
                                </li>
                            @empty
                                <li class="text-secondary">{{ __('admin.dashboard.no_active_sos') }}</li>
                            @endforelse
                        </ul>
                    </div>
                </section>
            </div>
        </div>
    @endif

    <div class="row g-4">
        @if ($shortcuts !== [])
            <div class="{{ $platform ? 'col-lg-6' : 'col-12' }}">
                <section class="card h-100 xm-admin-card" aria-labelledby="xmShortcutsTitle">
                    <div class="card-header"><h2 class="h6 mb-0" id="xmShortcutsTitle">{{ __('admin.dashboard.shortcuts') }}</h2></div>
                    <div class="card-body">
                        <div class="row g-2">
                            @foreach ($shortcuts as $shortcut)
                                <div class="col-6"><a wire:navigate wire:navigate.hover class="btn btn-light w-100 text-start" href="{{ $shortcut['href'] }}"><i class="bi bi-{{ $shortcut['icon'] }} me-2 text-primary" aria-hidden="true"></i>{{ $shortcut['label'] }}</a></div>
                            @endforeach
                        </div>
                    </div>
                </section>
            </div>
        @endif
        @if ($platform)
            <div class="{{ $shortcuts !== [] ? 'col-lg-6' : 'col-12' }}">
                <section class="card h-100 xm-admin-card" aria-labelledby="xmPlatformTitle">
                    <div class="card-header"><h2 class="h6 mb-0" id="xmPlatformTitle">{{ __('admin.dashboard.platform_status') }}</h2></div>
                    <div class="card-body">
                        <dl class="mb-0 xm-platform-status">
                            <div class="d-flex justify-content-between py-1 border-bottom"><dt class="fw-normal">{{ __('admin.dashboard.status.version') }}</dt><dd class="mb-0 fw-semibold">{{ $platform['version'] }}</dd></div>
                            @if ($platform['last_update'])
                                <div class="d-flex justify-content-between py-1 border-bottom"><dt class="fw-normal">{{ __('admin.dashboard.status.last_update') }}</dt><dd class="mb-0 fw-semibold">{{ $format->shortDate($platform['last_update']) }}</dd></div>
                            @endif
                            <div class="d-flex justify-content-between py-1 border-bottom"><dt class="fw-normal">{{ __('admin.dashboard.status.registrations') }}</dt><dd class="mb-0"><x-badge :color="$platform['registrations'] ? 'success' : 'neutral'">{{ $platform['registrations'] ? __('admin.dashboard.status.registrations_open') : __('admin.dashboard.status.registrations_closed') }}</x-badge></dd></div>
                            <div class="d-flex justify-content-between py-1 border-bottom"><dt class="fw-normal">{{ __('admin.dashboard.status.maintenance') }}</dt><dd class="mb-0"><x-badge :color="$platform['maintenance'] ? 'warning' : 'success'">{{ $platform['maintenance'] ? __('admin.dashboard.status.maintenance_on') : __('admin.dashboard.status.maintenance_off') }}</x-badge></dd></div>
                            <div class="d-flex justify-content-between py-1 border-bottom"><dt class="fw-normal">{{ __('admin.dashboard.status.offline_sync') }}</dt><dd class="mb-0"><x-badge :color="$platform['offline_sync'] ? 'success' : 'neutral'">{{ $platform['offline_sync'] ? __('admin.dashboard.status.offline_sync_on') : __('admin.dashboard.status.offline_sync_off') }}</x-badge></dd></div>
                            <div class="d-flex justify-content-between py-1 border-bottom"><dt class="fw-normal">{{ __('admin.dashboard.status.pending_accounts') }}</dt><dd class="mb-0 fw-semibold">{{ $format->number($platform['pending_accounts']) }}</dd></div>
                            <div class="d-flex justify-content-between py-1"><dt class="fw-normal">{{ __('admin.dashboard.status.queue_pending') }}</dt><dd class="mb-0 fw-semibold">{{ $format->number($platform['queue_pending']) }}</dd></div>
                        </dl>
                    </div>
                </section>
            </div>
        @endif
    </div>

    @if ($kpis === [] && ! $canAlerts && $shortcuts === [] && ! $platform)
        <x-empty-state icon="speedometer2" :title="__('admin.dashboard.nothing')">{{ __('admin.dashboard.nothing_text') }}</x-empty-state>
    @endif
</div>
