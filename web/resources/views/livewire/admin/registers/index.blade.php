{{--
    Registres numériques (P11-01) : composant App\Livewire\Admin\Registers\Index, gabarit admin. Ordre UI_UX_DESIGN_SYSTEM.md §4 :
    introduction · KPI · carte « liste » (pastilles de statut, export, filtres, tableau, pied) · modale « Nouveau dossier /
    Modifier » · modale « Détail ». Les notes (récit chiffré) ne sont jamais dans la liste : seule la modale « Détail »
    les lit, après autorisation, et la lecture est journalisée.
--}}
<div class="xm-registers">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
        <p class="text-secondary mb-0 xm-prose">{{ __('admin_data.registers.intro') }}</p>
        @if ($canCreate)
            <button type="button" class="btn btn-primary btn-sm" wire:click="openCreate" wire:loading.attr="disabled" wire:target="openCreate">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_data.registers.new') }}
            </button>
        @endif
    </div>

    @php($counts = $this->counts)
    <x-admin.kpi id="xmRegistersKpis" :items="[
        ['label' => __('admin_data.registers.kpi.active'), 'value' => $format->number($counts['ouvert'] + $counts['en_cours'] + $counts['clos']), 'icon' => 'journal-bookmark'],
        ['label' => __('admin_data.registers.kpi.open'), 'value' => $format->number($counts['ouvert']), 'icon' => 'folder2-open', 'variant' => 'warning'],
        ['label' => __('admin_data.registers.kpi.in_progress'), 'value' => $format->number($counts['en_cours']), 'icon' => 'hourglass-split', 'variant' => 'violet'],
        ['label' => __('admin_data.registers.kpi.closed'), 'value' => $format->number($counts['clos']), 'icon' => 'check2-circle', 'variant' => 'success'],
    ]" />

    <section class="card xm-admin-card" aria-labelledby="xmRegistersListTitle">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h6 fw-bold mb-0" id="xmRegistersListTitle" role="status">{{ trans_choice('admin_data.registers.count', $rows->total(), ['count' => $format->number($rows->total())]) }}</h2>
            @if ($canExport)
                <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="export" wire:loading.attr="disabled" wire:target="export">
                    <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>{{ __('admin.export.csv_filtered', ['count' => $format->number($rows->total())]) }}
                </button>
            @endif
        </div>

        <div class="card-body border-bottom pb-0">
            <nav aria-label="{{ __('admin_data.registers.tabs_label') }}">
                <ul class="nav nav-pills flex-wrap gap-1 mb-3">
                    <li class="nav-item">
                        <button type="button" id="xmRegistersTab-active" @class(['nav-link', 'active' => $status === '']) @if ($status === '') aria-current="true" @endif wire:click="setStatus('')">
                            {{ __('admin_data.registers.tabs.active') }}
                            <span class="badge ms-1 bg-light text-body border">{{ $format->number($counts['ouvert'] + $counts['en_cours'] + $counts['clos']) }}</span>
                        </button>
                    </li>
                    @foreach (\App\Enums\CaseStatus::cases() as $option)
                        <li class="nav-item">
                            <button type="button" id="xmRegistersTab-{{ $option->value }}" @class(['nav-link', 'active' => $status === $option->value]) @if ($status === $option->value) aria-current="true" @endif wire:click="setStatus('{{ $option->value }}')">
                                {{ $option->label() }}
                                <span class="badge ms-1 bg-light text-body border">{{ $format->number($counts[$option->value]) }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <x-admin.filters id="xmRegistersFilters" :active="$this->activeFilterCount()" :placeholder="__('admin_data.registers.search_placeholder')" :search-help="__('admin_data.registers.search_help')">
                {{-- Saut de ligne : la recherche garde toute la largeur, les sept filtres tiennent sur la ligne suivante. --}}
                <div class="w-100 d-none d-lg-block" aria-hidden="true"></div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="visually-hidden" for="xmRegistersKind">{{ __('admin_data.registers.columns.kind') }}</label>
                    <select class="form-select form-select-sm" id="xmRegistersKind" wire:model.live="kind">
                        <option value="">{{ __('admin_data.registers.filters.all_kinds') }}</option>
                        @foreach (\App\Enums\CaseKind::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->value }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="visually-hidden" for="xmRegistersZone">{{ __('admin.filters.zone') }}</label>
                    <select class="form-select form-select-sm" id="xmRegistersZone" wire:model.live="zone">
                        <option value="">{{ __('admin.filters.all_zones') }}</option>
                        @foreach ($zones as $option)
                            <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="visually-hidden" for="xmRegistersViolence">{{ __('admin_data.registers.columns.violence') }}</label>
                    <select class="form-select form-select-sm" id="xmRegistersViolence" wire:model.live="violence">
                        <option value="">{{ __('admin_data.registers.filters.all_violences') }}</option>
                        @foreach ($violenceTypes as $option)
                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="visually-hidden" for="xmRegistersPopulation">{{ __('admin_data.registers.columns.population') }}</label>
                    <select class="form-select form-select-sm" id="xmRegistersPopulation" wire:model.live="population">
                        <option value="">{{ __('admin_data.registers.filters.all_populations') }}</option>
                        @foreach ($populations as $option)
                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="visually-hidden" for="xmRegistersFrom">{{ __('admin.audit.filters.from') }}</label>
                    <input class="form-control form-control-sm @if ($periodError) is-invalid @endif" type="date" id="xmRegistersFrom" wire:model.live="from" title="{{ __('admin.audit.filters.from') }}"
                           @if ($periodError) aria-invalid="true" aria-describedby="xmRegistersPeriodError" @endif>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="visually-hidden" for="xmRegistersUntil">{{ __('admin.audit.filters.to') }}</label>
                    <input class="form-control form-control-sm @if ($periodError) is-invalid @endif" type="date" id="xmRegistersUntil" wire:model.live="until" title="{{ __('admin.audit.filters.to') }}"
                           @if ($periodError) aria-invalid="true" aria-describedby="xmRegistersPeriodError" @endif>
                </div>
                @if ($periodError)
                    <div class="col-12 order-last"><p class="small text-danger mb-0" id="xmRegistersPeriodError" role="alert"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $periodError }}</p></div>
                @endif
            </x-admin.filters>
        </div>

        <x-admin.table id="xmRegistersTable" :caption="__('admin_data.registers.table_caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets().',setStatus'" :columns="[
            ['key' => 'code', 'label' => __('admin_data.registers.columns.code'), 'sortable' => true],
            ['key' => 'date', 'label' => __('admin_data.registers.columns.date'), 'sortable' => true],
            ['key' => 'zone', 'label' => __('admin_data.registers.columns.zone')],
            ['key' => 'population', 'label' => __('admin_data.registers.columns.population')],
            ['key' => 'sexage', 'label' => __('admin_data.registers.columns.sex_age')],
            ['key' => 'violence', 'label' => __('admin_data.registers.columns.violence')],
            ['key' => 'statut', 'label' => __('admin_data.registers.columns.status'), 'sortable' => true],
            ['key' => 'peer', 'label' => __('admin_data.registers.columns.peer')],
            ['key' => 'actions', 'label' => '', 'sr' => __('admin_data.registers.columns.actions'), 'class' => 'text-end'],
        ]">
            @foreach ($rows as $case)
                <tr wire:key="case-{{ $case->id }}" @class(['text-secondary' => $case->status === \App\Enums\CaseStatus::Archived])>
                    <td data-label="{{ __('admin_data.registers.columns.code') }}">
                        <div class="min-w-0">
                            <span class="d-block fw-semibold">{{ $case->code }}</span>
                            <x-badge :color="$case->kind->badge()" soft>{{ $case->kind->value }}</x-badge>
                        </div>
                    </td>
                    <td data-label="{{ __('admin_data.registers.columns.date') }}" class="text-nowrap"><time class="small" datetime="{{ $case->occurred_on?->format('Y-m-d') }}">{{ $format->shortDate($case->occurred_on) }}</time></td>
                    <td data-label="{{ __('admin_data.registers.columns.zone') }}">
                        <div class="min-w-0">
                            <span class="d-block">{{ $case->zone?->name ?? '—' }}</span>
                            @if ($case->commune)<span class="d-block small text-secondary">{{ $case->commune->name }}</span>@endif
                        </div>
                    </td>
                    <td data-label="{{ __('admin_data.registers.columns.population') }}" class="small">{{ $case->population?->label ?? '—' }}</td>
                    <td data-label="{{ __('admin_data.registers.columns.sex_age') }}" class="small">
                        <div class="min-w-0">
                            <span class="d-block">{{ $case->sex->label() }}</span>
                            <span class="d-block text-secondary">{{ $case->age_range->label() }}</span>
                        </div>
                    </td>
                    <td data-label="{{ __('admin_data.registers.columns.violence') }}" class="small">{{ $case->violenceType?->label ?? '—' }}</td>
                    <td data-label="{{ __('admin_data.registers.columns.status') }}"><x-badge :color="$case->status->badge()" soft>{{ $case->status->label() }}</x-badge></td>
                    <td data-label="{{ __('admin_data.registers.columns.peer') }}" class="small">{{ $case->collector?->pseudo ?? '—' }}</td>
                    <td data-label="{{ __('admin_data.registers.columns.actions') }}" class="text-end xm-table__actions">
                        <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                            <button type="button" class="btn btn-sm btn-light border" wire:click="openDetail({{ $case->id }})" wire:loading.attr="disabled" wire:target="openDetail({{ $case->id }})" title="{{ __('admin_data.registers.actions.view') }}">
                                <i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_data.registers.actions.view') }} {{ $case->code }}</span>
                            </button>
                            @if ($case->status !== \App\Enums\CaseStatus::Archived && ! $case->anonymized_at)
                                <button type="button" class="btn btn-sm btn-light border" wire:click="openEdit({{ $case->id }})" wire:loading.attr="disabled" wire:target="openEdit({{ $case->id }})" title="{{ __('admin_data.registers.actions.edit') }}">
                                    <i class="bi bi-pencil" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_data.registers.actions.edit') }} {{ $case->code }}</span>
                                </button>
                                <button type="button" class="btn btn-sm btn-light border text-danger" wire:click="archive({{ $case->id }})"
                                        wire:confirm="{{ __('admin_data.registers.confirm_archive', ['code' => $case->code]) }}" title="{{ __('admin_data.registers.actions.archive') }}">
                                    <i class="bi bi-archive" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_data.registers.actions.archive') }} {{ $case->code }}</span>
                                </button>
                            @elseif ($case->status === \App\Enums\CaseStatus::Archived && ! $case->anonymized_at)
                                <button type="button" class="btn btn-sm btn-light border" wire:click="restore({{ $case->id }})"
                                        wire:confirm="{{ __('admin_data.registers.confirm_restore', ['code' => $case->code]) }}" title="{{ __('admin_data.registers.actions.restore') }}">
                                    <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_data.registers.actions.restore') }} {{ $case->code }}</span>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            <x-slot:emptyState>
                <x-admin.empty icon="journal-bookmark" :filtered="$this->activeFilterCount() > 0 || $status !== ''" :title="__('admin_data.registers.empty')">{{ __('admin_data.registers.empty_text') }}</x-admin.empty>
            </x-slot:emptyState>
            <x-slot:footer>
                <x-admin.pagination id="xmRegistersPager" :paginator="$rows" />
            </x-slot:footer>
        </x-admin.table>
    </section>

    {{-- Nouveau dossier / Modifier : montée en permanence (wire:ignore.self) ; le formulaire vit dans l'état de la page, sans les notes existantes. --}}
    <x-admin.modal id="xmCaseForm" size="lg" type="form" heading="h2" :title="$editId === 0 ? __('admin_data.registers.form.title_new') : __('admin_data.registers.form.title_edit')" :dirty-guard="true">
        <form id="xmCaseFormEl" wire:submit="save" novalidate>
            <div class="alert alert-warning small d-flex gap-2" role="note">
                <i class="bi bi-shield-exclamation" aria-hidden="true"></i>
                <span>{{ __('admin_data.registers.form.warning') }}</span>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="xmCaseKind">{{ __('admin_data.registers.fields.kind') }}</label>
                    <select class="form-select @error('form.kind') is-invalid @enderror" id="xmCaseKind" wire:model="form.kind" @disabled($editId !== 0) @error('form.kind') aria-invalid="true" aria-describedby="xmCaseKindError" @enderror>
                        @foreach (\App\Enums\CaseKind::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->value }} — {{ $option->label() }}</option>
                        @endforeach
                    </select>
                    @error('form.kind')<div class="invalid-feedback d-block" id="xmCaseKindError">{{ $message }}</div>@enderror
                    <div class="form-text">{{ $editId === 0 ? __('admin_data.registers.form.code_hint') : __('admin_data.registers.form.kind_locked') }}</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="xmCaseDate">{{ __('admin_data.registers.fields.occurred_on') }}</label>
                    <input class="form-control @error('form.occurred_on') is-invalid @enderror" type="date" id="xmCaseDate" wire:model="form.occurred_on" max="{{ now((string) config('xomiton.display_timezone', 'UTC'))->format('Y-m-d') }}" required
                           @error('form.occurred_on') aria-invalid="true" aria-describedby="xmCaseDateError" @enderror>
                    @error('form.occurred_on')<div class="invalid-feedback d-block" id="xmCaseDateError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="xmCaseViolence">{{ __('admin_data.registers.fields.violence_type_id') }}</label>
                    <select class="form-select @error('form.violence_type_id') is-invalid @enderror" id="xmCaseViolence" wire:model="form.violence_type_id" required @error('form.violence_type_id') aria-invalid="true" aria-describedby="xmCaseViolenceError" @enderror>
                        <option value="">—</option>
                        @foreach ($violenceTypes as $option)
                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                    @error('form.violence_type_id')<div class="invalid-feedback d-block" id="xmCaseViolenceError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="xmCaseZone">{{ __('admin_data.registers.fields.zone_id') }}</label>
                    <select class="form-select @error('form.zone_id') is-invalid @enderror" id="xmCaseZone" wire:model.live="form.zone_id" required @error('form.zone_id') aria-invalid="true" aria-describedby="xmCaseZoneError" @enderror>
                        <option value="">—</option>
                        @foreach ($zones as $option)
                            <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                        @endforeach
                    </select>
                    @error('form.zone_id')<div class="invalid-feedback d-block" id="xmCaseZoneError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="xmCaseCommune">{{ __('admin_data.registers.fields.commune_id') }}</label>
                    <select class="form-select @error('form.commune_id') is-invalid @enderror" id="xmCaseCommune" wire:model="form.commune_id" @error('form.commune_id') aria-invalid="true" aria-describedby="xmCaseCommuneError" @enderror>
                        <option value="">—</option>
                        @foreach ($this->formCommunes as $option)
                            <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                        @endforeach
                    </select>
                    @error('form.commune_id')<div class="invalid-feedback d-block" id="xmCaseCommuneError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="xmCasePopulation">{{ __('admin_data.registers.fields.population_id') }}</label>
                    <select class="form-select @error('form.population_id') is-invalid @enderror" id="xmCasePopulation" wire:model="form.population_id" required @error('form.population_id') aria-invalid="true" aria-describedby="xmCasePopulationError" @enderror>
                        <option value="">—</option>
                        @foreach ($populations as $option)
                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                    @error('form.population_id')<div class="invalid-feedback d-block" id="xmCasePopulationError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="xmCaseSex">{{ __('admin_data.registers.fields.sex') }}</label>
                    <select class="form-select @error('form.sex') is-invalid @enderror" id="xmCaseSex" wire:model="form.sex" required @error('form.sex') aria-invalid="true" aria-describedby="xmCaseSexError" @enderror>
                        <option value="">—</option>
                        @foreach (\App\Enums\Sex::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('form.sex')<div class="invalid-feedback d-block" id="xmCaseSexError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="xmCaseAge">{{ __('admin_data.registers.fields.age_range') }}</label>
                    <select class="form-select @error('form.age_range') is-invalid @enderror" id="xmCaseAge" wire:model="form.age_range" required @error('form.age_range') aria-invalid="true" aria-describedby="xmCaseAgeError" @enderror>
                        <option value="">—</option>
                        @foreach ($options['age_ranges'] as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                    @error('form.age_range')<div class="invalid-feedback d-block" id="xmCaseAgeError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="xmCaseReferral">{{ __('admin_data.registers.fields.referral') }}</label>
                    <select class="form-select @error('form.referral') is-invalid @enderror" id="xmCaseReferral" wire:model="form.referral" @error('form.referral') aria-invalid="true" aria-describedby="xmCaseReferralError" @enderror>
                        <option value="">{{ __('admin_data.registers.form.no_referral') }}</option>
                        @foreach ($referrals as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                    @error('form.referral')<div class="invalid-feedback d-block" id="xmCaseReferralError">{{ $message }}</div>@enderror
                </div>
                @if ($editId !== 0)
                    <div class="col-md-4">
                        <label class="form-label" for="xmCaseStatus">{{ __('admin_data.registers.fields.status') }}</label>
                        <select class="form-select @error('form.status') is-invalid @enderror" id="xmCaseStatus" wire:model="form.status" @error('form.status') aria-invalid="true" aria-describedby="xmCaseStatusError" @enderror>
                            @foreach ($editStatuses as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        @error('form.status')<div class="invalid-feedback d-block" id="xmCaseStatusError">{{ $message }}</div>@enderror
                    </div>
                @endif
                <div class="col-12">
                    <label class="form-label" for="xmCaseNotes">{{ $editId === 0 ? __('admin_data.registers.fields.notes') : __('admin_data.registers.form.notes_replace') }}</label>
                    <textarea class="form-control @error('form.notes') is-invalid @enderror" id="xmCaseNotes" rows="3" wire:model="form.notes" maxlength="{{ \App\Domain\Cases\CaseNotes::MAX_LENGTH }}" aria-describedby="xmCaseNotesHint @error('form.notes') xmCaseNotesError @enderror"
                              placeholder="{{ __('admin_data.registers.form.notes_placeholder') }}" @error('form.notes') aria-invalid="true" @enderror></textarea>
                    <div class="form-text" id="xmCaseNotesHint">{{ $editId === 0 ? __('admin_data.registers.form.notes_hint', ['min' => \App\Domain\Cases\CaseNotes::MIN_LENGTH]) : __('admin_data.registers.form.notes_keep') }}</div>
                    @error('form.notes')<div class="invalid-feedback d-block" id="xmCaseNotesError">{{ $message }}</div>@enderror
                    @if ($editId !== 0)
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="xmCaseClearNotes" wire:model="clearNotes">
                            <label class="form-check-label" for="xmCaseClearNotes">{{ __('admin_data.registers.form.clear_notes') }}</label>
                        </div>
                    @endif
                </div>
            </div>
        </form>
        <x-slot:footer>
            <button type="submit" form="xmCaseFormEl" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                <i class="bi bi-check2 me-1" aria-hidden="true"></i>{{ __('admin_data.registers.form.save') }}
            </button>
        </x-slot:footer>
    </x-admin.modal>

    {{-- Détail : montée en permanence ; vidée à la fermeture (hidden.bs.modal → closeDetail) pour ne laisser aucune donnée déchiffrée dans la page. --}}
    @php($detail = $this->detail)
    <x-admin.modal id="xmCaseDetail" size="lg" type="form" heading="h2" :title="$detail ? __('admin_data.registers.detail.title', ['code' => $detail['case']->code]) : __('admin_data.registers.actions.view')" x-data x-on:hidden-bs-modal.dot="$wire.closeDetail()">
        @if ($detail)
            @php($case = $detail['case'])
            @foreach ($detail['warnings'] as $warning)
                <div class="alert alert-warning small mb-3" role="note"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ $warning->message }}</div>
            @endforeach
            <dl class="row mb-3 small">
                <dt class="col-sm-4 text-secondary">{{ __('admin_data.registers.columns.kind') }}</dt><dd class="col-sm-8">{{ $case->kind->value }} — {{ $case->kind->label() }}</dd>
                <dt class="col-sm-4 text-secondary">{{ __('admin_data.registers.fields.occurred_on') }}</dt><dd class="col-sm-8">{{ $format->longDate($case->occurred_on) }}</dd>
                <dt class="col-sm-4 text-secondary">{{ __('admin_data.registers.columns.zone') }}</dt><dd class="col-sm-8">{{ $case->zone?->name ?? '—' }}@if ($case->commune) / {{ $case->commune->name }}@endif</dd>
                <dt class="col-sm-4 text-secondary">{{ __('admin_data.registers.columns.population') }}</dt><dd class="col-sm-8">{{ $case->population?->label ?? '—' }}</dd>
                <dt class="col-sm-4 text-secondary">{{ __('admin_data.registers.columns.sex_age') }}</dt><dd class="col-sm-8">{{ $case->sex->label() }} · {{ $case->age_range->label() }}</dd>
                <dt class="col-sm-4 text-secondary">{{ __('admin_data.registers.columns.violence') }}</dt><dd class="col-sm-8">{{ $case->violenceType?->label ?? '—' }}</dd>
                <dt class="col-sm-4 text-secondary">{{ __('admin_data.registers.columns.status') }}</dt><dd class="col-sm-8"><x-badge :color="$case->status->badge()" soft>{{ $case->status->label() }}</x-badge></dd>
                <dt class="col-sm-4 text-secondary">{{ __('admin_data.registers.columns.peer') }}</dt><dd class="col-sm-8">{{ $case->collector?->pseudo ?? '—' }}</dd>
                <dt class="col-sm-4 text-secondary">{{ __('admin_data.registers.columns.referral') }}</dt><dd class="col-sm-8">{{ $case->referral ?: '—' }}</dd>
                <dt class="col-sm-4 text-secondary">{{ __('admin_data.registers.fields.notes') }}</dt>
                <dd class="col-sm-8">
                    @if ($detail['anonymized'])
                        <span class="text-secondary">{{ __('admin_data.registers.detail.anonymized') }}</span>
                    @elseif ($detail['notes'])
                        <span class="d-block text-break" style="white-space: pre-line">{{ $detail['notes'] }}</span>
                    @else
                        —
                    @endif
                </dd>
            </dl>

            @if ($detail['can_update'])
                <form class="row g-2 align-items-end border-top pt-3" wire:submit="changeStatus">
                    <div class="col-sm-6">
                        <label class="form-label small mb-1" for="xmCaseDetailStatus">{{ __('admin_data.registers.detail.change_status') }}</label>
                        <select class="form-select form-select-sm @error('detailStatus') is-invalid @enderror" id="xmCaseDetailStatus" wire:model="detailStatus" @error('detailStatus') aria-invalid="true" aria-describedby="xmCaseDetailStatusError" @enderror>
                            @foreach ($detail['transitions'] as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        @error('detailStatus')<div class="invalid-feedback d-block" id="xmCaseDetailStatusError">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-auto">
                        <button type="submit" class="btn btn-sm btn-primary-soft" wire:loading.attr="disabled" wire:target="changeStatus">{{ __('admin_data.registers.detail.apply') }}</button>
                    </div>
                </form>
            @endif
        @endif
        <x-slot:footer>
            @if ($detail)
                @can('case_followups.manage')
                    <a wire:navigate wire:navigate.hover class="btn btn-outline-primary btn-sm" href="{{ route('admin.suivi', ['dossier' => $detail['case']->code]) }}"><i class="bi bi-person-heart me-1" aria-hidden="true"></i>{{ __('admin_data.registers.detail.followup', ['count' => $detail['followups']]) }}</a>
                @endcan
                @can('case_forms.manage')
                    <a wire:navigate wire:navigate.hover class="btn btn-outline-primary btn-sm" href="{{ route('admin.fiches', ['dossier' => $detail['case']->code]) }}"><i class="bi bi-file-earmark-medical me-1" aria-hidden="true"></i>{{ __('admin_data.registers.detail.forms', ['count' => $detail['forms']]) }}</a>
                @endcan
            @endif
        </x-slot:footer>
    </x-admin.modal>
</div>
