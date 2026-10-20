{{--
    Alertes & signalements (P10-05) : composant App\Livewire\Admin\Alerts\Index, gabarit admin ; reprend admin/alertes.php.
    Ordre UI_UX_DESIGN_SYSTEM.md §4 : KPI · onglets par type · carte « liste » (en-tête + export, filtres, tableau, pied) ·
    modale « Détail » toujours montée. Actualisation (wire:poll) : 10 s pour les agent·es de garde, 30 s sinon (D7) ; suspendue
    tant que la modale est ouverte. Le récit, le contact et la position ne sont affichés que dans la modale (audit `alerts.viewed`).
--}}
@php
    $statusColor = static fn (\App\Enums\AlertStatus $status): string => $status === \App\Enums\AlertStatus::Closed ? 'neutral' : $status->badge();
    $detail = $this->detail;
@endphp
<div class="xm-alerts" @if ($detailId === null) wire:poll.{{ $pollSeconds }}s @endif>
    <x-admin.kpi id="xmAlertsKpis" cols="col-6 col-lg-3" :items="[
        ['label' => __('admin.alerts.kpi.new'), 'value' => $format->number($kpis['new']), 'icon' => 'bell', 'variant' => 'danger', 'href' => route('admin.alertes', ['st' => 'nouveau'])],
        ['label' => __('admin.alerts.kpi.sos_active'), 'value' => $format->number($kpis['sos_active']), 'icon' => 'geo-alt-fill', 'variant' => 'danger', 'href' => route('admin.alertes', ['onglet' => 'sos'])],
        ['label' => __('admin.alerts.kpi.critical_open'), 'value' => $format->number($kpis['critical_open']), 'icon' => 'exclamation-octagon', 'variant' => 'warning', 'href' => route('admin.alertes', ['pr' => 'critique'])],
        ['label' => __('admin.alerts.kpi.handled'), 'value' => $format->number($kpis['handled']), 'icon' => 'check2-circle', 'variant' => 'success', 'href' => route('admin.alertes', ['st' => 'traite'])],
    ]" />

    <section class="card xm-admin-card" aria-labelledby="xmAlertsListTitle">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h6 fw-bold mb-0" id="xmAlertsListTitle" role="status">{{ trans_choice('admin.alerts.count', $rows->total(), ['count' => $format->number($rows->total())]) }}</h2>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span class="small text-secondary"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>{{ __('admin.alerts.refresh', ['seconds' => $pollSeconds, 'time' => $format->pattern('HH:mm:ss', now())]) }}</span>
                @if ($canExport)
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="export" wire:loading.attr="disabled" wire:target="export">
                        <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>{{ __('admin.export.csv_filtered', ['count' => $format->number($rows->total())]) }}
                    </button>
                @endif
            </div>
        </div>

        <div class="card-body border-bottom pb-0">
            <nav aria-label="{{ __('admin.alerts.tabs_label') }}">
                <ul class="nav nav-pills flex-wrap gap-1 mb-3">
                    @foreach (\App\Application\Admin\Alerts\AlertDesk::TABS as $key => $tabType)
                        @php($selected = $tab === $key)
                        <li class="nav-item">
                            <button type="button" id="xmAlertsTab-{{ $key }}" @class(['nav-link', 'active' => $selected]) @if ($selected) aria-current="true" @endif wire:click="setTab('{{ $key }}')">
                                {{ __('admin.alerts.tabs.'.$key) }}
                                <span class="badge ms-1 bg-light text-body border">{{ $format->number($tabCounts[$key] ?? 0) }}<span class="visually-hidden"> {{ trans_choice('admin.alerts.tab_count', $tabCounts[$key] ?? 0) }}</span></span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <x-admin.filters id="xmAlertsFilters" :active="$this->activeFilterCount()" :placeholder="__('admin.alerts.search_placeholder')" :search-help="__('admin.alerts.search_help')">
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="visually-hidden" for="xmAlertsStatus">{{ __('admin.filters.status') }}</label>
                    <select class="form-select form-select-sm" id="xmAlertsStatus" wire:model.live="status">
                        <option value="">{{ __('admin.filters.all_statuses') }}</option>
                        @foreach (\App\Enums\AlertStatus::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="visually-hidden" for="xmAlertsPriority">{{ __('admin.alerts.columns.priority') }}</label>
                    <select class="form-select form-select-sm" id="xmAlertsPriority" wire:model.live="priority">
                        <option value="">{{ __('admin.filters.all_priorities') }}</option>
                        @foreach (\App\Enums\AlertPriority::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="visually-hidden" for="xmAlertsZone">{{ __('admin.filters.zone') }}</label>
                    <select class="form-select form-select-sm" id="xmAlertsZone" wire:model.live="zone">
                        <option value="">{{ __('admin.filters.all_zones') }}</option>
                        @foreach ($zones as $option)
                            <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="visually-hidden" for="xmAlertsCategory">{{ __('admin.alerts.columns.category') }}</label>
                    <select class="form-select form-select-sm" id="xmAlertsCategory" wire:model.live="category">
                        <option value="">{{ __('admin.filters.all_categories') }}</option>
                        @foreach ($categories as $option)
                            <option value="{{ $option['slug'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </x-admin.filters>
        </div>

        <div id="xmAlertsPanel">
            <x-admin.table id="xmAlertsTable" :caption="__('admin.alerts.table_caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets().',setTab'" :columns="[
                ['key' => 'code', 'label' => __('admin.alerts.columns.code')],
                ['key' => 'type', 'label' => __('admin.alerts.columns.type'), 'sortable' => true],
                ['key' => 'priorite', 'label' => __('admin.alerts.columns.priority'), 'sortable' => true],
                ['key' => 'zone', 'label' => __('admin.alerts.columns.zone').' · '.__('admin.alerts.columns.category')],
                ['key' => 'routed', 'label' => __('admin.alerts.columns.routed_to')],
                ['key' => 'status', 'label' => __('admin.alerts.columns.status')],
                ['key' => 'recue', 'label' => __('admin.alerts.columns.received'), 'sortable' => true],
                ['key' => 'actions', 'label' => '', 'sr' => __('admin.alerts.columns.actions'), 'class' => 'text-end'],
            ]">
                @foreach ($rows as $alert)
                    <tr wire:key="alert-{{ $alert->id }}" class="xm-alert-row xm-alert-row--{{ $alert->priority->value }}">
                        <td data-label="{{ __('admin.alerts.columns.code') }}">
                            <div class="min-w-0">
                                <span class="d-block fw-semibold">#{{ $alert->id }}</span>
                                <code class="d-block small text-secondary">{{ $alert->displayCode() }}</code>
                            </div>
                        </td>
                        <td data-label="{{ __('admin.alerts.columns.type') }}"><x-badge scope="alert_type" :status="$alert->type->value" /></td>
                        <td data-label="{{ __('admin.alerts.columns.priority') }}"><x-badge scope="priority" :status="$alert->priority->value" /></td>
                        <td data-label="{{ __('admin.alerts.columns.zone') }}">
                            <div class="min-w-0">
                                <span class="d-block">{{ $alert->zone?->name ?? '—' }}</span>
                                @if ($alert->commune)<span class="d-block small text-secondary">{{ $alert->commune->name }}</span>@endif
                                @if ($alert->has_gps)<span class="d-block small text-danger"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i> {{ __('admin.alerts.has_gps') }}</span>@endif
                                <span class="d-block small mt-1">{{ collect($categories)->firstWhere('slug', $alert->category)['label'] ?? ($alert->category ?: '—') }}</span>
                                @if ($alert->is_anonymous)
                                    <x-badge color="neutral" icon="incognito">{{ __('admin.alerts.anonymous') }}</x-badge>
                                @elseif ($alert->pseudo)
                                    <span class="d-block small text-secondary">{{ $alert->pseudo }}</span>
                                @endif
                            </div>
                        </td>
                        <td data-label="{{ __('admin.alerts.columns.routed_to') }}" class="small">{{ $alert->routedContact?->name ?? __('admin.alerts.not_routed') }}</td>
                        <td data-label="{{ __('admin.alerts.columns.status') }}"><x-badge :color="$statusColor($alert->status)" soft>{{ $alert->status->label() }}</x-badge></td>
                        <td data-label="{{ __('admin.alerts.columns.received') }}" class="text-nowrap"><time class="small" datetime="{{ $alert->created_at?->toIso8601String() }}">{{ $format->tableDateTime($alert->created_at) }}</time></td>
                        <td data-label="{{ __('admin.alerts.columns.actions') }}" class="text-end xm-table__actions">
                            <div class="d-flex flex-column align-items-end gap-1">
                            @if ($alert->status === \App\Enums\AlertStatus::New)
                                @if (in_array($alert->id, $takeable, true))
                                    <button type="button" class="btn btn-sm btn-primary" wire:click="take({{ $alert->id }})" wire:loading.attr="disabled" wire:target="take({{ $alert->id }})">
                                        <i class="bi bi-hand-index-thumb me-1" aria-hidden="true"></i>{{ __('admin.alerts.take') }}<span class="visually-hidden"> {{ __('admin.alerts.of', ['code' => '#'.$alert->id]) }}</span>
                                    </button>
                                @endif
                            @endif
                            <button type="button" class="btn btn-sm btn-primary-soft" wire:click="openDetail({{ $alert->id }})" wire:loading.attr="disabled" wire:target="openDetail({{ $alert->id }})">
                                <i class="bi bi-eye me-1" aria-hidden="true"></i>{{ __('admin.alerts.detail_button') }}<span class="visually-hidden"> {{ __('admin.alerts.of', ['code' => '#'.$alert->id]) }}</span>
                            </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
                <x-slot:emptyState>
                    <x-admin.empty icon="bell" :filtered="$this->activeFilterCount() > 0" :title="__('admin.alerts.empty')">{{ __('admin.alerts.empty_text') }}</x-admin.empty>
                </x-slot:emptyState>
                <x-slot:footer>
                    <x-admin.pagination id="xmAlertsPager" :paginator="$rows" />
                </x-slot:footer>
            </x-admin.table>
        </div>
    </section>

    {{-- Détail : montée en permanence ; vidée à la fermeture (hidden.bs.modal → closeDetail) pour ne laisser aucune donnée déchiffrée dans la page. --}}
    <x-admin.modal id="xmAlertDetail" size="xl" heading="h2" :title="$detail ? __('admin.alerts.detail.title', ['code' => '#'.$detail['alert']->id]) : __('admin.alerts.detail_button')"
                   x-data x-on:hidden-bs-modal.dot="$wire.closeDetail()">
        @if ($detail)
            @php($alert = $detail['alert'])
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <x-badge scope="alert_type" :status="$alert->type->value" />
                        <x-badge scope="priority" :status="$alert->priority->value" />
                        <x-badge :color="$statusColor($alert->status)" soft>{{ $alert->status->label() }}</x-badge>
                        @if ($alert->isCancelled())<x-badge color="neutral" icon="x-circle">{{ __('admin.alerts.detail.cancelled') }}</x-badge>@endif
                        @if ($alert->safe_at)<x-badge color="success" soft icon="shield-check">{{ __('admin.alerts.detail.safe') }}</x-badge>@endif
                        @if ($alert->isEscalated())<x-badge color="warning" soft icon="arrow-up-circle">{{ __('admin.alerts.detail.escalated') }}</x-badge>@endif
                    </div>

                    <h3 class="h6">{{ __('admin.alerts.detail.description') }}</h3>
                    <p class="mb-3 text-break" style="white-space: pre-line">{{ $alert->description ?: '—' }}</p>

                    <dl class="row small mb-3">
                        <dt class="col-sm-4">{{ __('admin.alerts.detail.reference') }}</dt>
                        <dd class="col-sm-8"><code>{{ $alert->displayCode() }}</code></dd>
                        <dt class="col-sm-4">{{ __('admin.alerts.columns.received') }}</dt>
                        <dd class="col-sm-8">{{ $format->dateTime($alert->created_at) }}</dd>
                        @if ($alert->occurred_on)
                            <dt class="col-sm-4">{{ __('admin.alerts.detail.occurred_on') }}</dt>
                            <dd class="col-sm-8">{{ $format->longDate($alert->occurred_on) }}</dd>
                        @endif
                        <dt class="col-sm-4">{{ __('admin.alerts.columns.zone') }}</dt>
                        <dd class="col-sm-8">{{ $alert->zone?->name ?? '—' }}@if ($alert->commune) / {{ $alert->commune->name }}@endif</dd>
                        <dt class="col-sm-4">{{ __('admin.alerts.columns.category') }}</dt>
                        <dd class="col-sm-8">{{ $detail['category'] ?? '—' }}</dd>
                        <dt class="col-sm-4">{{ __('admin.alerts.columns.pseudo') }}</dt>
                        <dd class="col-sm-8">{{ $alert->is_anonymous ? __('admin.alerts.anonymous') : ($alert->pseudo ?: $alert->authorLabel()) }}</dd>
                        <dt class="col-sm-4">{{ __('admin.alerts.detail.needs') }}</dt>
                        <dd class="col-sm-8">{{ $detail['needs'] === [] ? '—' : implode(', ', $detail['needs']) }}</dd>
                        <dt class="col-sm-4">{{ __('admin.alerts.detail.contact') }}</dt>
                        <dd class="col-sm-8">
                            @if ($alert->contact)
                                {{ $alert->contact }}@if ($alert->preferred_channel) <span class="text-secondary">({{ $alert->preferred_channel->label() }})</span>@endif
                            @else
                                {{ __('admin.alerts.detail.no_contact') }}
                            @endif
                        </dd>
                        <dt class="col-sm-4">{{ __('admin.alerts.columns.source') }}</dt>
                        <dd class="col-sm-8">{{ $alert->source->label() }}@if ($alert->device) · {{ __('admin.alerts.detail.device', ['device' => $alert->device->label]) }}@endif</dd>
                        <dt class="col-sm-4">{{ __('admin.alerts.columns.routed_to') }}</dt>
                        <dd class="col-sm-8">
                            @if ($alert->routedContact)
                                {{ __('admin.alerts.detail.routed', ['name' => $alert->routedContact->name, 'role' => $alert->routedContact->role->label()]) }}
                                @if ($alert->routing_fallback)<span class="text-secondary">· {{ $alert->routing_fallback->label() }}</span>@endif
                            @else
                                {{ __('admin.alerts.detail.not_routed') }}
                            @endif
                        </dd>
                        @if ($alert->isAcknowledged())
                            <dt class="col-sm-4">{{ __('admin.alerts.detail.acknowledged_label') }}</dt>
                            <dd class="col-sm-8">{{ __('admin.alerts.detail.acknowledged_at', ['date' => $format->tableDateTime($alert->acknowledged_at), 'name' => $alert->acknowledgedBy?->pseudo ?? $alert->acknowledgedByContact?->name ?? __('admin.alerts.detail.someone')]) }}</dd>
                        @endif
                    </dl>

                    @if ($detail['escalation_at'])
                        <x-alert type="warning" icon="clock-history" class="small">{{ __('admin.alerts.detail.escalation', ['time' => $format->tableDateTime($detail['escalation_at'])]) }}</x-alert>
                    @endif

                    <h3 class="h6">{{ __('admin.alerts.detail.position') }}</h3>
                    @if ($coordinates = $alert->coordinates())
                        <p class="small mb-3">
                            <a class="btn btn-sm btn-outline-danger" target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps?q={{ $coordinates['lat'] }},{{ $coordinates['lng'] }}" aria-describedby="xmAlertGpsHelp">
                                <i class="bi bi-geo-alt-fill me-1" aria-hidden="true"></i>{{ __('admin.alerts.gps_link') }} ({{ $format->number($coordinates['lat'], 4) }}, {{ $format->number($coordinates['lng'], 4) }})<span class="visually-hidden"> {{ __('microcopy.a11y.opens_new_tab') }}</span>
                            </a>
                            <span class="d-block form-text" id="xmAlertGpsHelp">{{ __('admin.alerts.gps_link_help') }}</span>
                            @if ($alert->location_accuracy)<span class="text-secondary ms-2">{{ __('admin.alerts.detail.accuracy', ['meters' => $format->accuracy($alert->location_accuracy)]) }}</span>@endif
                        </p>
                    @else
                        <p class="small text-secondary mb-3">{{ __('admin.alerts.no_gps') }}</p>
                    @endif

                    @if ($detail['attachments'] !== [])
                        <h3 class="h6">{{ __('admin.alerts.detail.attachments') }}</h3>
                        <ul class="small list-unstyled mb-3">
                            @foreach ($detail['attachments'] as $attachment)
                                <li><a href="{{ $attachment['url'] }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-paperclip me-1" aria-hidden="true"></i>{{ __('admin.alerts.detail.attachment_'.$attachment['kind']) }} ({{ $format->fileSize($attachment['size']) }})<span class="visually-hidden"> {{ __('microcopy.a11y.opens_new_tab') }}</span></a></li>
                            @endforeach
                        </ul>
                    @endif

                    <h3 class="h6">{{ __('admin.alerts.detail.history') }}</h3>
                    <ul class="list-unstyled small mb-0 xm-alert-timeline">
                        @foreach ($detail['timeline'] as $entry)
                            <li @class(['xm-alert-timeline__item border-start border-3 ps-2 mb-2', 'border-warning' => $entry['internal'], 'border-primary' => ! $entry['internal']])>
                                <div class="text-secondary">{{ $format->tableDateTime($entry['at']) }}@if ($entry['by']) · {{ $entry['by'] }}@endif @if ($entry['internal'])· <span class="fw-semibold">{{ __('admin.alerts.detail.internal_note') }}</span>@endif</div>
                                <div class="text-break" style="white-space: pre-line">{{ $entry['text'] }}</div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="col-lg-5">
                    @if ($detail['can_take'])
                        <div class="border rounded p-3 mb-3 bg-body-tertiary">
                            <p class="small mb-2">{{ __('admin.alerts.detail.take_help') }}</p>
                            <button type="button" class="btn btn-primary w-100" wire:click="acknowledgeDetail" wire:loading.attr="disabled" wire:target="acknowledgeDetail">
                                <i class="bi bi-hand-index-thumb me-1" aria-hidden="true"></i>{{ __('admin.alerts.take') }}
                            </button>
                        </div>
                    @endif

                    @if ($detail['transitions'] !== [])
                        <form class="border rounded p-3 mb-3" wire:submit="changeStatus" novalidate>
                            <label class="form-label small mb-1" for="xmAlertStatus">{{ __('admin.alerts.detail.change_status') }}</label>
                            <div class="input-group input-group-sm">
                                <select id="xmAlertStatus" class="form-select @error('newStatus') is-invalid @enderror" wire:model="newStatus" @error('newStatus') aria-invalid="true" aria-describedby="xmAlertStatusError" @enderror>
                                    <option value="">{{ __('admin.alerts.detail.choose_status') }}</option>
                                    @foreach ($detail['transitions'] as $option)
                                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="changeStatus">{{ __('admin.alerts.detail.apply') }}</button>
                            </div>
                            @error('newStatus')<div class="small text-danger mt-1" id="xmAlertStatusError" role="alert">{{ $message }}</div>@enderror
                        </form>
                    @endif

                    @if ($detail['can_reassign'])
                        <form class="border rounded p-3 mb-3" wire:submit="reassign" novalidate>
                            <h3 class="h6 mb-1">{{ __('admin.alerts.detail.reassign') }}</h3>
                            <p class="small text-secondary mb-2">{{ __('admin.alerts.detail.reassign_help') }}</p>

                            @if ($detail['routing'])
                                @php($retained = $detail['routing']->retainedLevel())
                                <details class="small mb-2">
                                    <summary class="fw-semibold">{{ __('admin.alerts.routing.title') }}</summary>
                                    <ol class="ps-3 mb-2 mt-1">
                                        @foreach ($detail['routing']->levels as $level)
                                            <li>
                                                <span class="@if ($level['retained']) fw-semibold @endif">{{ $level['fallback']->label() }}</span>
                                                —
                                                @if ($level['skipped'])
                                                    {{ __('admin.alerts.routing.skipped') }}
                                                @elseif ($level['retained'])
                                                    {{ __('admin.alerts.routing.retained', ['name' => $detail['routing_contacts'][$level['matched']] ?? '#'.$level['matched']]) }}
                                                @elseif ($level['matched'] === null)
                                                    {{ trans_choice('admin.alerts.routing.none', count($level['examined']), ['count' => count($level['examined'])]) }}
                                                @else
                                                    {{ __('admin.alerts.routing.not_reached') }}
                                                @endif
                                            </li>
                                        @endforeach
                                    </ol>
                                    @if ($retained && $retained['matched'] !== null)
                                        <button type="button" class="btn btn-sm btn-light border" wire:click="useSuggested({{ $retained['matched'] }})">{{ __('admin.alerts.routing.use', ['name' => $detail['routing_contacts'][$retained['matched']] ?? '#'.$retained['matched']]) }}</button>
                                    @endif
                                </details>
                            @endif

                            <label class="form-label small mb-1" for="xmAlertRole">{{ __('admin.filters.role') }}</label>
                            <select id="xmAlertRole" class="form-select form-select-sm mb-1" wire:model.live="reassignRole">
                                <option value="">{{ __('admin.filters.all_roles') }}</option>
                                @foreach (\App\Enums\DirectoryRole::cases() as $role)
                                    <option value="{{ $role->value }}">{{ $role->label() }}</option>
                                @endforeach
                            </select>
                            <div class="form-check small mb-2">
                                <input class="form-check-input" type="checkbox" id="xmAlertSameZone" wire:model.live="reassignSameZone">
                                <label class="form-check-label" for="xmAlertSameZone">{{ __('admin.filters.same_zone_only') }}</label>
                            </div>
                            <label class="form-label small mb-1" for="xmAlertRouteTo">{{ __('admin.alerts.detail.contact_label') }}</label>
                            <div class="input-group input-group-sm">
                                <select id="xmAlertRouteTo" class="form-select @error('reassignTo') is-invalid @enderror" wire:model="reassignTo" @error('reassignTo') aria-invalid="true" aria-describedby="xmAlertRouteError" @enderror>
                                    <option value="">{{ __('admin.alerts.detail.choose_contact') }}</option>
                                    @foreach ($detail['contacts'] as $contact)
                                        <option value="{{ $contact->id }}">{{ $contact->name }} — {{ $contact->role->label() }} ({{ $contact->zone?->name ?? __('admin.alerts.detail.national') }})</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-outline-primary" wire:loading.attr="disabled" wire:target="reassign">{{ __('admin.alerts.detail.route') }}</button>
                            </div>
                            @error('reassignTo')<div class="small text-danger mt-1" id="xmAlertRouteError" role="alert">{{ $message }}</div>@enderror
                        </form>
                    @endif

                    @if ($detail['can_note'])
                        <form class="border rounded p-3" wire:submit="addNote" novalidate>
                            <label class="form-label small mb-1" for="xmAlertNote">{{ __('admin.alerts.detail.internal_note') }}</label>
                            <textarea id="xmAlertNote" rows="3" maxlength="{{ (int) config('xomiton.alerts.note_max', 1000) }}" wire:model="noteBody" class="form-control form-control-sm mb-1 @error('noteBody') is-invalid @enderror"
                                      placeholder="{{ __('admin.alerts.detail.note_placeholder') }}" @error('noteBody') aria-invalid="true" aria-describedby="xmAlertNoteError" @enderror></textarea>
                            @error('noteBody')<div class="small text-danger mb-1" id="xmAlertNoteError" role="alert">{{ $message }}</div>@enderror
                            <button type="submit" class="btn btn-sm btn-outline-secondary w-100" wire:loading.attr="disabled" wire:target="addNote"><i class="bi bi-journal-plus me-1" aria-hidden="true"></i>{{ __('admin.alerts.detail.add_note') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @else
            <p class="text-secondary mb-0" role="status">{{ __('admin.alerts.detail.loading') }}</p>
        @endif
    </x-admin.modal>
</div>
