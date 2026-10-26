{{--
    Newsletter (P14-02) : composant App\Livewire\Admin\Newsletter\Index, gabarit admin.
    Ordre : KPI permanents · onglets (?onglet=) Statistiques / Abonné·es / Campagnes · modales (abonné·e, import, campagne, statistiques).
    Aucune bibliothèque de graphiques : les séries sont des tableaux accessibles avec barres de proportion (aucun JavaScript).
--}}
@php
    $totals = $this->kpis['totals'];
    $statusCounts = $this->kpis['status'];
    $statsClass = \App\Application\Admin\Newsletter\NewsletterStatistics::class;
    $rateItem = function (string $metric, string $label, string $icon, string $variant) use ($totals, $targets, $format, $statsClass): array {
        $rate = (float) $totals[$metric.'_rate'];
        $below = $statsClass::belowTarget($metric, $rate, (int) ($metric === 'bounce' ? $totals['sent'] : $totals['delivered']));
        $target = $format->percent($targets[$metric]['value'], $targets[$metric]['value'] < 1 ? 1 : 0);
        $sign = $targets[$metric]['kind'] === 'min' ? '≥ ' : '< ';

        return ['label' => $label, 'value' => $format->percent($rate, 1), 'icon' => $icon, 'variant' => $below ? 'danger' : $variant,
            'sub' => $below ? __('newsletter.admin.kpi.below_target', ['target' => $sign.$target]) : __('newsletter.admin.kpi.target', ['value' => $sign.$target])];
    };
@endphp
<div class="xm-newsletter-admin">
    <p class="text-secondary xm-prose mb-3">{{ __('newsletter.admin.campaigns.batch_info', ['batch' => $batchSize]) }}</p>

    <x-admin.kpi id="xmNlKpis" cols="col-6 col-md-4 col-xl" :items="[
        ['label' => __('newsletter.admin.kpi.active'), 'value' => $format->number($statusCounts['actif']), 'icon' => 'people', 'sub' => __('admin_newsletter.kpi.pending_sub', ['count' => $format->number($statusCounts['en_attente'])])],
        $rateItem('open', __('newsletter.admin.kpi.open_rate'), 'envelope-open', 'success'),
        $rateItem('click', __('newsletter.admin.kpi.click_rate'), 'cursor', 'violet'),
        $rateItem('unsubscribe', __('newsletter.admin.kpi.unsubscribe_rate'), 'person-dash', 'warning'),
        $rateItem('bounce', __('newsletter.admin.kpi.bounce_rate'), 'envelope-exclamation', 'danger'),
    ]" />

    <x-admin.tab-nav :tabs="$tabs" :current="$tab" :label="__('newsletter.admin.title')" />

    {{-- ============================== Statistiques ============================== --}}
    @if ($tab === 'stats')
        <div class="row g-4 mb-4">
            <div class="col-xl-8">
                <section class="card xm-admin-card h-100" aria-labelledby="xmNlGrowthTitle">
                    <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmNlGrowthTitle">{{ __('newsletter.admin.stats.subscribers_12m') }}</h2></div>
                    @php($maxMonth = max(1, collect($months)->max(fn ($m) => max($m['signups'], $m['unsubscribes']))))
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <caption class="visually-hidden">{{ __('admin_newsletter.stats.months_caption') }}</caption>
                            <thead><tr><th scope="col">{{ __('admin_newsletter.stats.month') }}</th><th scope="col" style="width:40%">{{ __('admin_newsletter.stats.signups') }}</th><th scope="col" style="width:40%">{{ __('admin_newsletter.stats.leaves') }}</th></tr></thead>
                            <tbody>
                                @foreach ($months as $month)
                                    <tr wire:key="month-{{ $month['month'] }}">
                                        <th scope="row" class="fw-normal">{{ $month['label'] }}</th>
                                        <td><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:6px" aria-hidden="true"><div class="progress-bar" style="width: {{ round($month['signups'] * 100 / $maxMonth) }}%"></div></div><span class="small text-nowrap">{{ $month['signups'] }}</span></div></td>
                                        <td><div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height:6px" aria-hidden="true"><div class="progress-bar bg-warning" style="width: {{ round($month['unsubscribes'] * 100 / $maxMonth) }}%"></div></div><span class="small text-nowrap">{{ $month['unsubscribes'] }}</span></div></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
            <div class="col-xl-4">
                <section class="card xm-admin-card h-100" aria-labelledby="xmNlStatusTitle">
                    <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmNlStatusTitle">{{ __('newsletter.admin.stats.by_status') }}</h2></div>
                    <ul class="list-group list-group-flush">
                        @foreach (\App\Enums\SubscriberStatus::cases() as $case)
                            <li class="list-group-item d-flex justify-content-between align-items-center" wire:key="st-{{ $case->value }}"><x-badge :color="$case->badge()" soft>{{ $case->label() }}</x-badge><span class="fw-semibold">{{ $format->number($statusCounts[$case->value]) }}</span></li>
                        @endforeach
                    </ul>
                </section>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <section class="card xm-admin-card h-100" aria-labelledby="xmNlPerfTitle">
                    <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmNlPerfTitle">{{ __('newsletter.admin.stats.title') }}</h2></div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <caption class="visually-hidden">{{ __('newsletter.admin.stats.by_campaign') }}</caption>
                            <thead><tr>
                                <th scope="col">{{ __('newsletter.admin.campaigns.columns.campaign') }}</th>
                                <th scope="col" class="text-end">{{ __('newsletter.admin.stats.sent') }}</th>
                                <th scope="col" class="text-end">{{ __('newsletter.admin.kpi.open_rate') }}</th>
                                <th scope="col" class="text-end">{{ __('newsletter.admin.kpi.click_rate') }}</th>
                                <th scope="col" class="text-end">{{ __('newsletter.admin.campaigns.columns.unsubscribes') }}</th>
                                <th scope="col" class="text-end">{{ __('newsletter.admin.kpi.bounce_rate') }}</th>
                            </tr></thead>
                            <tbody>
                                @forelse ($byCampaign as $row)
                                    <tr wire:key="perf-{{ $row['campaign']->id }}">
                                        <th scope="row"><span class="d-block fw-semibold text-break">{{ $row['campaign']->subject }}</span><span class="small text-secondary fw-normal">{{ $format->shortDate($row['campaign']->sent_at) }}</span></th>
                                        <td class="text-end">{{ $format->number($row['sent']) }}</td>
                                        @foreach (['open' => 'open_rate', 'click' => 'click_rate', 'unsubscribe' => 'unsubscribe_rate', 'bounce' => 'bounce_rate'] as $metric => $key)
                                            @php($low = $statsClass::belowTarget($metric, $row[$key], $metric === 'bounce' ? $row['sent'] : $row['delivered']))
                                            <td @class(['text-end', 'text-danger fw-semibold' => $low])>{{ $format->percent($row[$key], 1) }}@if ($low)<i class="bi bi-exclamation-triangle-fill ms-1" aria-hidden="true"></i><span class="visually-hidden"> ({{ __('admin_newsletter.stats.off_target') }})</span>@endif</td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-secondary py-4">{{ __('newsletter.admin.campaigns.empty_sent') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
            <div class="col-xl-4">
                <section class="card xm-admin-card mb-4" aria-labelledby="xmNlLinksTitle">
                    <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmNlLinksTitle">{{ __('newsletter.admin.stats.links') }}</h2></div>
                    <ul class="list-group list-group-flush small">
                        @forelse ($links as $link)
                            <li class="list-group-item d-flex justify-content-between gap-2" wire:key="link-{{ $loop->index }}"><span class="text-truncate" title="{{ $link->url }}">{{ preg_replace('#^https?://#', '', $link->url) }}</span><span class="badge text-bg-primary">{{ $link->total }}</span></li>
                        @empty
                            <li class="list-group-item text-secondary">{{ __('newsletter.admin.stats.no_clicks') }}</li>
                        @endforelse
                    </ul>
                </section>
                <section class="card xm-admin-card" aria-labelledby="xmNlInterestsTitle">
                    <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmNlInterestsTitle">{{ __('newsletter.admin.stats.interests') }}</h2></div>
                    <div class="card-body">
                        @php($maxInterest = max(1, ...array_values($interestCounts) ?: [1]))
                        @foreach ($interestCounts as $label => $count)
                            <div class="mb-2" wire:key="int-{{ $loop->index }}"><div class="d-flex justify-content-between small"><span>{{ $label }}</span><span>{{ $count }}</span></div><div class="progress" style="height:6px" aria-hidden="true"><div class="progress-bar" style="width: {{ round($count * 100 / $maxInterest) }}%"></div></div></div>
                        @endforeach
                    </div>
                </section>
            </div>
        </div>
    @endif

    {{-- ============================== Abonné·es ============================== --}}
    @if ($tab === 'abonnes')
        <section class="card xm-admin-card" aria-labelledby="xmNlSubsTitle">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h2 class="h6 fw-bold mb-0" id="xmNlSubsTitle">{{ __('newsletter.admin.subscribers.title') }}</h2>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="export" wire:loading.attr="disabled" wire:target="export"><i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>{{ __('admin.export.csv_filtered', ['count' => $format->number($rows->total())]) }}</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" wire:click="openImport"><i class="bi bi-upload me-1" aria-hidden="true"></i>{{ __('admin_newsletter.subscribers.import') }}</button>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="newSubscriber"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('newsletter.admin.subscribers.add') }}</button>
                </div>
            </div>
            <div class="card-body border-bottom">
                <x-admin.filters id="xmNlSubFilters" :active="$this->activeFilterCount()" :placeholder="__('admin_newsletter.subscribers.search_placeholder')">
                    <div class="col-6 col-lg-2"><label class="visually-hidden" for="xmNlStatus">{{ __('newsletter.admin.subscribers.columns.status') }}</label>
                        <select class="form-select form-select-sm" id="xmNlStatus" wire:model.live="status"><option value="">{{ __('admin_newsletter.subscribers.all_statuses') }}</option>@foreach (\App\Enums\SubscriberStatus::cases() as $case)<option value="{{ $case->value }}">{{ $case->label() }}</option>@endforeach</select></div>
                    <div class="col-6 col-lg-2"><label class="visually-hidden" for="xmNlTag">{{ __('newsletter.admin.subscribers.columns.tags') }}</label>
                        <select class="form-select form-select-sm" id="xmNlTag" wire:model.live="tagFilter"><option value="">{{ __('admin_newsletter.subscribers.all_tags') }}</option>@foreach ($this->tags as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-6 col-lg-2"><label class="visually-hidden" for="xmNlInterest">{{ __('newsletter.admin.subscribers.columns.interests') }}</label>
                        <select class="form-select form-select-sm" id="xmNlInterest" wire:model.live="interestFilter"><option value="">{{ __('admin_newsletter.subscribers.all_interests') }}</option>@foreach ($this->interests as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-6 col-lg-2"><label class="visually-hidden" for="xmNlFrom">{{ __('admin.audit.filters.from') }}</label><input class="form-control form-control-sm" type="date" id="xmNlFrom" wire:model.live="from" title="{{ __('admin.audit.filters.from') }}"></div>
                    <div class="col-6 col-lg-2"><label class="visually-hidden" for="xmNlUntil">{{ __('admin.audit.filters.to') }}</label><input class="form-control form-control-sm" type="date" id="xmNlUntil" wire:model.live="until" title="{{ __('admin.audit.filters.to') }}"></div>
                </x-admin.filters>
                <x-admin.bulk-bar class="mt-3" id="xmNlBulk" :count="count($selected)" :actions="$this->bulkActions()" :selected="$bulkAction" />
            </div>

            <x-admin.table id="xmNlSubTable" :caption="__('admin_newsletter.subscribers.table_caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
                ['key' => 'select', 'label' => '', 'sr' => __('admin_newsletter.subscribers.select_column'), 'width' => '2.5rem'],
                ['key' => 'email', 'label' => __('newsletter.admin.subscribers.columns.subscriber'), 'sortable' => true],
                ['key' => 'statut', 'label' => __('newsletter.admin.subscribers.columns.status'), 'sortable' => true],
                ['key' => 'tags', 'label' => __('newsletter.admin.subscribers.columns.tags')],
                ['key' => 'inscription', 'label' => __('newsletter.admin.subscribers.columns.subscribed_at'), 'sortable' => true],
                ['key' => 'actions', 'label' => '', 'sr' => __('admin_newsletter.actions'), 'class' => 'text-end'],
            ]">
                @foreach ($rows as $subscriber)
                    <tr wire:key="sub-{{ $subscriber->id }}">
                        <td data-label=""><input class="form-check-input" type="checkbox" value="{{ $subscriber->id }}" wire:model.live="selected" aria-label="{{ __('admin_newsletter.subscribers.select', ['name' => $subscriber->name ?: $subscriber->email]) }}"></td>
                        <td data-label="{{ __('newsletter.admin.subscribers.columns.subscriber') }}"><div class="min-w-0"><span class="d-block fw-semibold text-break">{{ $subscriber->email }}</span>@if ($subscriber->name)<span class="d-block small text-secondary">{{ $subscriber->name }}</span>@endif</div></td>
                        <td data-label="{{ __('newsletter.admin.subscribers.columns.status') }}"><x-badge :color="$subscriber->status->badge()" soft>{{ $subscriber->status->label() }}</x-badge></td>
                        <td data-label="{{ __('newsletter.admin.subscribers.columns.tags') }}"><span class="small text-break">{{ implode(', ', array_map(fn ($s) => $this->tags[$s] ?? $s, (array) $subscriber->tags)) ?: '—' }}</span></td>
                        <td data-label="{{ __('newsletter.admin.subscribers.columns.subscribed_at') }}"><time class="small" datetime="{{ $subscriber->created_at?->toIso8601String() }}">{{ $format->tableDateTime($subscriber->created_at) }}</time><span class="d-block small text-secondary">{{ $subscriber->source->label() }}</span></td>
                        <td class="xm-table__actions text-end text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="editSubscriber({{ $subscriber->id }})"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('admin_newsletter.edit') }}<span class="visually-hidden"> {{ $subscriber->email }}</span></button>
                            @if ($subscriber->isPending())
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="resendConfirmation({{ $subscriber->id }})"><i class="bi bi-envelope-arrow-up me-1" aria-hidden="true"></i>{{ __('newsletter.admin.subscribers.resend_confirmation') }}<span class="visually-hidden"> {{ $subscriber->email }}</span></button>
                            @endif
                            @if ($subscriber->isActive() || $subscriber->isPending())
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="unsubscribeSubscriber({{ $subscriber->id }})" wire:confirm="{{ __('admin_newsletter.subscribers.confirm_unsubscribe') }}"><i class="bi bi-person-dash me-1" aria-hidden="true"></i>{{ __('newsletter.admin.subscribers.unsubscribe_action') }}<span class="visually-hidden"> {{ $subscriber->email }}</span></button>
                            @endif
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteSubscriber({{ $subscriber->id }})" wire:confirm="{{ __('admin_newsletter.subscribers.confirm_delete') }}"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('newsletter.admin.subscribers.delete_rgpd') }}<span class="visually-hidden"> {{ $subscriber->email }}</span></button>
                        </td>
                    </tr>
                @endforeach
                <x-slot:emptyState><x-admin.empty icon="people" :filtered="$this->activeFilterCount() > 0" :title="__('newsletter.admin.subscribers.empty')" /></x-slot:emptyState>
                <x-slot:footer><x-admin.pagination id="xmNlSubPager" :paginator="$rows" /></x-slot:footer>
            </x-admin.table>
        </section>
    @endif

    {{-- ============================== Campagnes ============================== --}}
    @if ($tab === 'campagnes')
        <section class="card xm-admin-card" aria-labelledby="xmNlCampTitle">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h2 class="h6 fw-bold mb-0" id="xmNlCampTitle">{{ __('newsletter.admin.campaigns.title') }}</h2>
                <div class="d-flex flex-wrap gap-2">
                    @if ($canSend)<button type="button" class="btn btn-outline-secondary btn-sm" wire:click="processQueue" wire:loading.attr="disabled" wire:target="processQueue"><i class="bi bi-play-circle me-1" aria-hidden="true"></i>{{ __('newsletter.admin.campaigns.process_queue') }}</button>@endif
                    @can('create', \App\Models\NewsletterCampaign::class)<button type="button" class="btn btn-primary btn-sm" wire:click="newCampaign"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('newsletter.admin.campaigns.new') }}</button>@endcan
                </div>
            </div>
            <div class="card-body border-bottom">
                <x-admin.filters id="xmNlCampFilters" :active="$this->activeFilterCount()" :placeholder="__('admin_newsletter.campaigns.search_placeholder')">
                    <div class="col-6 col-lg-3"><label class="visually-hidden" for="xmNlCampStatus">{{ __('newsletter.admin.campaigns.columns.status') }}</label>
                        <select class="form-select form-select-sm" id="xmNlCampStatus" wire:model.live="campaignStatus"><option value="">{{ __('admin_newsletter.campaigns.all_statuses') }}</option>@foreach (\App\Enums\CampaignStatus::cases() as $case)<option value="{{ $case->value }}">{{ $case->label() }}</option>@endforeach</select></div>
                </x-admin.filters>
            </div>
            <x-admin.table id="xmNlCampTable" :caption="__('admin_newsletter.campaigns.table_caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
                ['key' => 'objet', 'label' => __('newsletter.admin.campaigns.columns.campaign'), 'sortable' => true],
                ['key' => 'statut', 'label' => __('newsletter.admin.campaigns.columns.status'), 'sortable' => true],
                ['key' => 'destinataires', 'label' => __('newsletter.admin.campaigns.columns.recipients')],
                ['key' => 'date', 'label' => __('newsletter.admin.campaigns.columns.scheduled_at'), 'sortable' => true],
                ['key' => 'actions', 'label' => '', 'sr' => __('admin_newsletter.actions'), 'class' => 'text-end'],
            ]">
                @foreach ($rows as $campaign)
                    <tr wire:key="camp-{{ $campaign->id }}">
                        <td data-label="{{ __('newsletter.admin.campaigns.columns.campaign') }}"><div class="min-w-0"><span class="d-block fw-semibold text-break">{{ $campaign->subject }}</span><span class="d-block small text-secondary">{{ $campaign->segmentLabel() }}</span></div></td>
                        <td data-label="{{ __('newsletter.admin.campaigns.columns.status') }}"><x-badge :color="$campaign->status->badge()" soft>{{ $campaign->status->label() }}</x-badge></td>
                        <td data-label="{{ __('newsletter.admin.campaigns.columns.recipients') }}">
                            @if ($campaign->recipients_count > 0)
                                {{ $format->number($campaign->recipients_count) }}
                                @if ($campaign->status === \App\Enums\CampaignStatus::Sending)<span class="d-block small text-secondary">{{ __('admin_newsletter.campaigns.progress', ['sent' => $format->number($campaign->recipients()->whereNotNull('sent_at')->count()), 'total' => $format->number($campaign->recipients_count)]) }}</span>@endif
                            @else — @endif
                        </td>
                        <td data-label="{{ __('newsletter.admin.campaigns.columns.scheduled_at') }}">
                            @php($when = $campaign->sent_at ?? $campaign->scheduled_at)
                            @if ($when)<time class="small" datetime="{{ $when->toIso8601String() }}">{{ $format->tableDateTime($when) }}</time>@else — @endif
                        </td>
                        <td class="xm-table__actions text-end text-nowrap">
                            @if ($campaign->recipients_count > 0)<button type="button" class="btn btn-sm btn-outline-secondary" wire:click="showStats({{ $campaign->id }})"><i class="bi bi-graph-up me-1" aria-hidden="true"></i>{{ __('admin_newsletter.campaigns.stats') }}<span class="visually-hidden"> {{ $campaign->subject }}</span></button>@endif
                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="editCampaign({{ $campaign->id }})"><i class="bi bi-{{ $campaign->isEditable() ? 'pencil' : 'eye' }} me-1" aria-hidden="true"></i>{{ $campaign->isEditable() ? __('admin_newsletter.edit') : __('admin_newsletter.view') }}<span class="visually-hidden"> {{ $campaign->subject }}</span></button>
                            @if ($campaign->status === \App\Enums\CampaignStatus::Scheduled)
                                @can('cancel', $campaign)<button type="button" class="btn btn-sm btn-outline-secondary" wire:click="cancelSchedule({{ $campaign->id }})" wire:confirm="{{ __('admin_newsletter.campaigns.confirm_cancel') }}"><i class="bi bi-calendar-x me-1" aria-hidden="true"></i>{{ __('admin_newsletter.campaigns.cancel_schedule') }}<span class="visually-hidden"> {{ $campaign->subject }}</span></button>@endcan
                            @endif
                            @can('create', \App\Models\NewsletterCampaign::class)<button type="button" class="btn btn-sm btn-outline-secondary" wire:click="duplicateCampaign({{ $campaign->id }})"><i class="bi bi-copy me-1" aria-hidden="true"></i>{{ __('admin_newsletter.duplicate') }}<span class="visually-hidden"> {{ $campaign->subject }}</span></button>@endcan
                            @if ($campaign->status !== \App\Enums\CampaignStatus::Sending)
                                @can('delete', $campaign)<button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteCampaign({{ $campaign->id }})" wire:confirm="{{ __('admin_newsletter.campaigns.confirm_delete') }}"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('admin_newsletter.delete') }}<span class="visually-hidden"> {{ $campaign->subject }}</span></button>@endcan
                            @endif
                        </td>
                    </tr>
                @endforeach
                <x-slot:emptyState><x-admin.empty icon="envelope-paper" :filtered="$this->activeFilterCount() > 0" :title="__('newsletter.admin.campaigns.empty')" /></x-slot:emptyState>
                <x-slot:footer><x-admin.pagination id="xmNlCampPager" :paginator="$rows" /></x-slot:footer>
            </x-admin.table>
        </section>
    @endif

    {{-- ============================== Modales ============================== --}}
    @if (isset($tabs['abonnes']))
        <x-admin.modal id="xmNlSubscriber" size="lg" dirty-guard :title="$subscriberId === null ? __('newsletter.admin.subscribers.add') : __('newsletter.admin.subscribers.edit')">
            <form id="xmNlSubscriberForm" wire:submit="saveSubscriber" novalidate>
                <div class="row g-3">
                    <div class="col-md-6"><x-field name="subForm.email" type="email" :label="__('newsletter.admin.subscribers.fields.email')" wire:model.blur="subForm.email" :readonly="$subscriberId !== null" autocomplete="off" /></div>
                    <div class="col-md-6"><x-field name="subForm.name" :label="__('newsletter.admin.subscribers.fields.name')" wire:model.blur="subForm.name" maxlength="80" optional /></div>
                    <div class="col-md-6"><x-field name="subForm.status" type="select" :label="__('newsletter.admin.subscribers.fields.status')" wire:model="subForm.status" :options="collect(\App\Enums\SubscriberStatus::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()" /></div>
                    <fieldset class="col-md-6"><legend class="form-label fw-semibold fs-6">{{ __('newsletter.admin.subscribers.fields.tags') }}</legend>
                        <div class="d-flex flex-wrap gap-3">@foreach ($this->tags as $slug => $label)<div class="form-check"><input class="form-check-input" type="checkbox" id="xmNlSubTag{{ $slug }}" value="{{ $slug }}" wire:model="subForm.tags"><label class="form-check-label" for="xmNlSubTag{{ $slug }}">{{ $label }}</label></div>@endforeach</div></fieldset>
                    <fieldset class="col-12"><legend class="form-label fw-semibold fs-6">{{ __('newsletter.admin.subscribers.fields.interests') }}</legend>
                        <div class="d-flex flex-wrap gap-3">@foreach ($this->interests as $slug => $label)<div class="form-check"><input class="form-check-input" type="checkbox" id="xmNlSubInt{{ $slug }}" value="{{ $slug }}" wire:model="subForm.interests"><label class="form-check-label" for="xmNlSubInt{{ $slug }}">{{ $label }}</label></div>@endforeach</div></fieldset>
                    @if ($subscriberId === null)
                        <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="xmNlSendConfirmation" wire:model="subForm.send_confirmation"><label class="form-check-label" for="xmNlSendConfirmation">{{ __('newsletter.admin.subscribers.fields.send_confirmation') }}</label></div></div>
                    @endif
                </div>
            </form>
            <x-slot:footer><button type="submit" form="xmNlSubscriberForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveSubscriber">{{ __('admin_newsletter.save') }}</button></x-slot:footer>
        </x-admin.modal>

        <x-admin.modal id="xmNlImport" size="lg" :title="__('newsletter.admin.subscribers.import_title')">
            <form id="xmNlImportForm" wire:submit="import" novalidate>
                <p class="small text-secondary">{{ __('newsletter.admin.subscribers.import_columns') }} <button type="button" class="btn btn-link btn-sm p-0 align-baseline" wire:click="downloadTemplate">{{ __('admin_newsletter.subscribers.template') }}</button></p>
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="xmNlCsv">{{ __('admin_newsletter.subscribers.import_file') }}</label>
                    <input class="form-control @error('csv') is-invalid @enderror" type="file" id="xmNlCsv" wire:model="csv" accept=".csv,text/csv,text/plain" required>
                    <div wire:loading wire:target="csv" class="form-text">{{ __('admin_newsletter.subscribers.uploading') }}</div>
                    @error('csv')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="form-check mb-2"><input class="form-check-input @error('importAttested') is-invalid @enderror" type="checkbox" id="xmNlAttest" wire:model="importAttested"><label class="form-check-label" for="xmNlAttest">{{ __('admin_newsletter.subscribers.attestation') }}</label>@error('importAttested')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="xmNlOptin" wire:model="importOptin"><label class="form-check-label" for="xmNlOptin">{{ __('admin_newsletter.subscribers.optin') }}</label></div>
                <div class="mb-0"><label class="form-label fw-semibold" for="xmNlImportTag">{{ __('admin_newsletter.subscribers.import_tag') }}</label>
                    <select class="form-select" id="xmNlImportTag" wire:model="importTag"><option value="">{{ __('admin_newsletter.subscribers.no_tag') }}</option>@foreach ($this->tags as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach</select></div>
            </form>
            @if ($importReport)
                <div class="alert alert-info mt-3 mb-0" role="status">{{ __('admin_newsletter.subscribers.import_report', ['imported' => $importReport['imported'], 'duplicates' => $importReport['duplicates'], 'invalid' => $importReport['invalid']]) }}</div>
            @endif
            <x-slot:footer><button type="submit" form="xmNlImportForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="import,csv"><i class="bi bi-upload me-1" aria-hidden="true"></i>{{ __('admin_newsletter.subscribers.import_submit') }}</button></x-slot:footer>
        </x-admin.modal>
    @endif

    @if (isset($tabs['campagnes']))
        @php($readonly = $editingCampaign !== null && ! $editingCampaign->isEditable())
        <x-admin.modal id="xmNlCampaign" size="xl" dirty-guard :title="$campaignId === null ? __('newsletter.admin.campaigns.new') : ($readonly ? __('admin_newsletter.campaigns.view') : __('newsletter.admin.campaigns.edit'))">
            <div x-data="{ insert(name) { const el = $refs.body; const text = '{' + '{' + name + '}' + '}'; const at = el.selectionStart ?? el.value.length; el.setRangeText(text, at, el.selectionEnd ?? at, 'end'); el.dispatchEvent(new Event('input', { bubbles: true })); el.focus(); } }">
                @if ($readonly)<div class="alert alert-secondary small" role="note">{{ __('newsletter.admin.campaigns.readonly') }}</div>@endif
                <div class="btn-group mb-3" role="group" aria-label="{{ __('admin_newsletter.emails.panes') }}">
                    <button type="button" @class(['btn btn-sm', 'btn-primary' => $campPane === 'edit', 'btn-outline-primary' => $campPane !== 'edit']) wire:click="setCampPane('edit')" aria-pressed="{{ $campPane === 'edit' ? 'true' : 'false' }}">{{ __('admin_newsletter.emails.pane_edit') }}</button>
                    <button type="button" @class(['btn btn-sm', 'btn-primary' => $campPane === 'preview', 'btn-outline-primary' => $campPane !== 'preview']) wire:click="setCampPane('preview')" aria-pressed="{{ $campPane === 'preview' ? 'true' : 'false' }}">{{ __('admin.emails.preview.title') }}</button>
                </div>

                <form id="xmNlCampaignForm" wire:submit="saveCampaign" novalidate @class(['d-none' => $campPane !== 'edit'])>
                    <fieldset class="row g-3" @disabled($readonly)>
                        <div class="col-md-8"><x-field name="campForm.subject" :label="__('newsletter.admin.campaigns.fields.subject')" wire:model.blur="campForm.subject" maxlength="150" :hint="__('newsletter.admin.campaigns.fields.subject_help', ['max' => 150])" /></div>
                        <div class="col-md-4"><x-field name="campForm.preheader" :label="__('newsletter.admin.campaigns.fields.preheader')" wire:model.blur="campForm.preheader" maxlength="150" optional /></div>
                        <div class="col-md-6"><x-field name="campForm.from_name" :label="__('newsletter.admin.campaigns.fields.from_name')" wire:model.blur="campForm.from_name" maxlength="60" /></div>
                        <div class="col-md-6"><x-field name="campForm.reply_to" type="email" :label="__('newsletter.admin.campaigns.fields.reply_to')" wire:model.blur="campForm.reply_to" /></div>

                        <div class="col-12">
                            <div class="fw-semibold mb-1" id="xmNlSectionsLabel">{{ __('admin_newsletter.campaigns.sections_title') }}</div>
                            <div class="d-flex flex-wrap gap-2 align-items-center" role="group" aria-labelledby="xmNlSectionsLabel">
                                @foreach (['featured', 'resource', 'event', 'emergency'] as $section)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="insertSection('{{ $section }}')" wire:key="sec-{{ $section }}">{{ __('admin_newsletter.campaigns.sections.'.$section.'.label') }}</button>
                                @endforeach
                                <span class="d-inline-flex align-items-center gap-2 ms-2">
                                    <span class="form-check mb-0"><input class="form-check-input" type="checkbox" id="xmNlTestimonialConsent" wire:model.live="testimonialConsent"><label class="form-check-label small" for="xmNlTestimonialConsent">{{ __('admin_newsletter.campaigns.testimonial_consent') }}</label></span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="insertSection('testimonial')" @disabled(! $testimonialConsent)>{{ __('admin_newsletter.campaigns.sections.testimonial.label') }}</button>
                                </span>
                            </div>
                        </div>

                        <div class="col-12"><x-field name="campForm.body" type="textarea" :label="__('newsletter.admin.campaigns.fields.body')" wire:model.blur="campForm.body" rows="12" x-ref="body" class="font-monospace" :hint="__('admin_newsletter.emails.body_help')" /></div>
                        <div class="col-12">
                            <div class="fw-semibold mb-1" id="xmNlVarsLabel">{{ __('newsletter.admin.campaigns.fields.variables') }}</div>
                            <div class="d-flex flex-wrap gap-1" role="group" aria-labelledby="xmNlVarsLabel">
                                @foreach ($variables as $name)
                                    <button type="button" class="btn btn-sm btn-outline-secondary font-monospace" x-on:click="insert(@js($name))" wire:key="nvar-{{ $name }}" title="{{ __('admin_newsletter.campaigns.variables.'.$name) }}">{{ '{'.'{'.$name.'}'.'}' }}</button>
                                @endforeach
                            </div>
                        </div>

                        <fieldset class="col-12 border rounded p-3">
                            <legend class="float-none w-auto px-2 fs-6 fw-semibold mb-0">{{ __('newsletter.admin.campaigns.fields.segment') }}</legend>
                            <div class="row g-3">
                                <div class="col-md-6"><div class="small fw-semibold mb-1">{{ __('newsletter.admin.campaigns.fields.segment_tags') }}</div>
                                    <div class="d-flex flex-wrap gap-3">@foreach ($this->tags as $slug => $label)<div class="form-check"><input class="form-check-input" type="checkbox" id="xmNlSegTag{{ $slug }}" value="{{ $slug }}" wire:model.live="campForm.seg_tags"><label class="form-check-label" for="xmNlSegTag{{ $slug }}">{{ $label }}</label></div>@endforeach</div></div>
                                <div class="col-md-6"><div class="small fw-semibold mb-1">{{ __('newsletter.admin.campaigns.fields.segment_interests') }}</div>
                                    <div class="d-flex flex-wrap gap-3">@foreach ($this->interests as $slug => $label)<div class="form-check"><input class="form-check-input" type="checkbox" id="xmNlSegInt{{ $slug }}" value="{{ $slug }}" wire:model.live="campForm.seg_interests"><label class="form-check-label" for="xmNlSegInt{{ $slug }}">{{ $label }}</label></div>@endforeach</div></div>
                                <div class="col-sm-6 col-md-3"><x-field name="campForm.seg_since" type="date" :label="__('admin_newsletter.campaigns.fields.seg_since')" wire:model.live="campForm.seg_since" optional /></div>
                                <div class="col-sm-6 col-md-3"><x-field name="campForm.seg_until" type="date" :label="__('admin_newsletter.campaigns.fields.seg_until')" wire:model.live="campForm.seg_until" optional /></div>
                                <div class="col-md-6 d-flex align-items-end"><p class="mb-2 fw-semibold" role="status" aria-live="polite">{{ trans_choice('newsletter.admin.campaigns.fields.segment_count', $this->segmentCount, ['count' => $format->number($this->segmentCount)]) }}</p></div>
                            </div>
                        </fieldset>

                        <fieldset class="col-12"><legend class="form-label fw-semibold fs-6">{{ __('newsletter.admin.campaigns.fields.mode') }}</legend>
                            <div class="d-flex flex-column gap-2">
                                <div class="form-check"><input class="form-check-input" type="radio" name="mode" id="xmNlModeDraft" value="draft" wire:model.live="campForm.mode"><label class="form-check-label" for="xmNlModeDraft">{{ __('newsletter.admin.campaigns.fields.mode_draft') }}</label></div>
                                <div class="form-check"><input class="form-check-input" type="radio" name="mode" id="xmNlModeSchedule" value="schedule" wire:model.live="campForm.mode" @disabled(! $canSend)><label class="form-check-label" for="xmNlModeSchedule">{{ __('newsletter.admin.campaigns.fields.mode_schedule') }}</label></div>
                                <div class="form-check"><input class="form-check-input" type="radio" name="mode" id="xmNlModeNow" value="now" wire:model.live="campForm.mode" @disabled(! $canSend)><label class="form-check-label" for="xmNlModeNow">{{ __('newsletter.admin.campaigns.fields.mode_now') }}</label></div>
                            </div>
                            @unless ($canSend)<p class="form-text">{{ __('admin_newsletter.campaigns.send_locked') }}</p>@endunless
                            @if (($campForm['mode'] ?? '') === 'schedule')
                                <div class="mt-2"><x-field name="campForm.scheduled_at" type="datetime-local" :label="__('newsletter.admin.campaigns.fields.scheduled_at')" wire:model.blur="campForm.scheduled_at" /></div>
                            @endif
                        </fieldset>
                    </fieldset>
                </form>

                @if ($campPane === 'preview')
                    <section aria-labelledby="xmNlPreviewTitle">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <h3 class="h6 mb-0" id="xmNlPreviewTitle">{{ __('admin.emails.preview.subject', ['subject' => $previewSubject]) }}</h3>
                            <div class="btn-group" role="group" aria-label="{{ __('admin.emails.preview.title') }}">
                                <button type="button" @class(['btn btn-sm', 'btn-primary' => $previewMode === 'desktop', 'btn-outline-primary' => $previewMode !== 'desktop']) wire:click="setPreviewMode('desktop')" aria-pressed="{{ $previewMode === 'desktop' ? 'true' : 'false' }}">{{ __('admin.emails.preview.desktop') }}</button>
                                <button type="button" @class(['btn btn-sm', 'btn-primary' => $previewMode === 'mobile', 'btn-outline-primary' => $previewMode !== 'mobile']) wire:click="setPreviewMode('mobile')" aria-pressed="{{ $previewMode === 'mobile' ? 'true' : 'false' }}">{{ __('admin.emails.preview.mobile') }}</button>
                            </div>
                        </div>
                        <div class="border rounded bg-body-tertiary p-2 text-center"><iframe class="bg-white border-0 rounded" title="{{ __('admin.emails.preview.title') }}" sandbox srcdoc="{{ $previewHtml }}" style="width: {{ $previewMode === 'mobile' ? '375px' : '100%' }}; max-width: 100%; height: 520px"></iframe></div>
                    </section>
                @endif

                <hr>
                <div class="row g-2 align-items-end">
                    <div class="col-md-7"><label class="form-label fw-semibold" for="xmNlTestTo">{{ __('newsletter.admin.campaigns.fields.test_address') }}</label>
                        <input class="form-control @error('testTo') is-invalid @enderror" type="email" id="xmNlTestTo" wire:model="testTo" autocomplete="off">
                        @error('testTo')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-auto"><button type="button" class="btn btn-outline-primary" wire:click="sendCampaignTest" wire:loading.attr="disabled" wire:target="sendCampaignTest"><i class="bi bi-send me-1" aria-hidden="true"></i>{{ __('admin_newsletter.campaigns.send_test') }}</button></div>
                </div>
            </div>
            @unless ($readonly)
                <x-slot:footer><button type="submit" form="xmNlCampaignForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveCampaign">{{ __('admin_newsletter.campaigns.submit.'.($campForm['mode'] ?? 'draft')) }}</button></x-slot:footer>
            @endunless
        </x-admin.modal>

        <x-admin.modal id="xmNlCampaignStats" size="md" :title="__('newsletter.admin.campaigns.stats', ['name' => $statsCampaign?->subject])">
            @if ($campaignStats)
                <dl class="row mb-0">
                    <dt class="col-7">{{ __('newsletter.admin.stats.sent') }}</dt><dd class="col-5 text-end">{{ $format->number($campaignStats['sent']) }}</dd>
                    <dt class="col-7">{{ __('admin_newsletter.stats.delivered') }}</dt><dd class="col-5 text-end">{{ $format->number($campaignStats['delivered']) }}</dd>
                    @foreach (['open' => ['open_rate', 'opens', 'newsletter.admin.kpi.open_rate'], 'click' => ['click_rate', 'clickers', 'newsletter.admin.kpi.click_rate'], 'unsubscribe' => ['unsubscribe_rate', 'unsubscribes', 'newsletter.admin.kpi.unsubscribe_rate'], 'bounce' => ['bounce_rate', 'bounces', 'newsletter.admin.kpi.bounce_rate']] as $metric => [$rate, $count, $label])
                        @php($low = $statsClass::belowTarget($metric, $campaignStats[$rate], $metric === 'bounce' ? $campaignStats['sent'] : $campaignStats['delivered']))
                        <dt class="col-7">{{ __($label) }}</dt><dd @class(['col-5 text-end', 'text-danger fw-semibold' => $low])>{{ $format->percent($campaignStats[$rate], 1) }} <span class="small text-secondary">({{ $format->number($campaignStats[$count]) }})</span>@if ($low)<span class="visually-hidden"> ({{ __('admin_newsletter.stats.off_target') }})</span>@endif</dd>
                    @endforeach
                </dl>
            @endif
        </x-admin.modal>
    @endif
</div>
