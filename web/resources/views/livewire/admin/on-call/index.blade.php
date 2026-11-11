{{--
    Planning de garde (P1-17) : composant App\Livewire\Admin\OnCall\Index, gabarit admin. Introduction · KPI · carte « en ce
    moment / prochaine » · liste de la semaine ou du mois (précédent, aujourd'hui, suivant) · modale « Garde » (création et
    modification) · modale de confirmation de suppression (avertissement si la garde est en cours). Heures dans le fuseau
    d'affichage, enregistrées en UTC.
--}}
<div class="xm-oncall">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
        <p class="text-secondary mb-0 xm-prose">{{ __('admin_oncall.intro') }}</p>
        <button type="button" class="btn btn-primary btn-sm" wire:click="openCreate" wire:loading.attr="disabled" wire:target="openCreate">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_oncall.new') }}
        </button>
    </div>

    <x-admin.kpi id="xmOnCallKpis" :items="[
        ['label' => __('admin_oncall.kpi.now'), 'value' => $format->number($current->count()), 'icon' => 'broadcast', 'variant' => $current->isEmpty() ? 'warning' : 'success'],
        ['label' => __('admin_oncall.kpi.next'), 'value' => $upcoming ? $format->tableDateTime($upcoming->starts_at) : '—', 'icon' => 'calendar-event', 'sub' => $upcoming?->user?->pseudo],
        ['label' => __('admin_oncall.kpi.period'), 'value' => $format->number($shifts->count()), 'icon' => 'calendar-week', 'variant' => 'violet'],
    ]" cols="col-6 col-xl-4" />

    <section class="card xm-admin-card mb-4" aria-labelledby="xmOnCallNowTitle">
        <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmOnCallNowTitle">{{ __('admin_oncall.now.title') }}</h2></div>
        <div class="card-body">
            @if ($current->isEmpty())
                <x-alert type="warning" icon="exclamation-triangle" role="status" class="mb-0">{{ __('admin_oncall.now.none') }}</x-alert>
            @else
                @if ($fallbackActive)
                    <x-alert type="info" icon="info-circle" class="mb-3">{{ __('admin_oncall.now.no_admin') }}</x-alert>
                @endif
                <ul class="list-unstyled mb-0">
                    @foreach ($current as $shift)
                        <li class="d-flex flex-wrap align-items-center gap-2 py-1" wire:key="now-{{ $shift->id }}">
                            <span class="fw-semibold">{{ $shift->user?->pseudo }}</span>
                            <x-badge :color="$shift->isFocal() ? 'violet' : 'primary'" soft>{{ __('admin_oncall.kinds.'.$shift->kind) }}</x-badge>
                            @if ($shift->isFocal())<span class="small text-secondary">{{ $shift->zone?->name ?? __('admin_oncall.national') }}</span>@endif
                            <span class="small text-secondary">{{ __('admin_oncall.until', ['date' => $format->tableDateTime($shift->ends_at)]) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($upcoming)
                <p class="small text-secondary mt-3 mb-0">{{ __('admin_oncall.now.next', ['name' => (string) $upcoming->user?->pseudo, 'date' => $format->tableDateTime($upcoming->starts_at)]) }}</p>
            @endif
        </div>
    </section>

    <section class="card xm-admin-card mb-4" aria-labelledby="xmOnCallListTitle">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h6 fw-bold mb-0" id="xmOnCallListTitle" role="status">{{ __('admin_oncall.list.title', ['range' => $rangeLabel]) }}</h2>
            <div class="d-flex flex-wrap gap-2">
                <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('admin_oncall.view.label') }}">
                    <button type="button" class="btn btn-outline-secondary @if ($view === 'semaine') active @endif" wire:click="setView('semaine')" aria-pressed="{{ $view === 'semaine' ? 'true' : 'false' }}">{{ __('admin_oncall.view.week') }}</button>
                    <button type="button" class="btn btn-outline-secondary @if ($view === 'mois') active @endif" wire:click="setView('mois')" aria-pressed="{{ $view === 'mois' ? 'true' : 'false' }}">{{ __('admin_oncall.view.month') }}</button>
                </div>
                <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('admin_oncall.nav.label') }}">
                    <button type="button" class="btn btn-outline-secondary" wire:click="previous" title="{{ __('admin_oncall.nav.previous') }}"><i class="bi bi-chevron-left" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_oncall.nav.previous') }}</span></button>
                    <button type="button" class="btn btn-outline-secondary" wire:click="today" @disabled($isToday)>{{ __('admin_oncall.nav.today') }}</button>
                    <button type="button" class="btn btn-outline-secondary" wire:click="next" title="{{ __('admin_oncall.nav.next') }}"><i class="bi bi-chevron-right" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_oncall.nav.next') }}</span></button>
                </div>
            </div>
        </div>

        <x-admin.table id="xmOnCallTable" :caption="__('admin_oncall.table_caption', ['range' => $rangeLabel])" :empty="$shifts->isEmpty()" :columns="[
            ['key' => 'personne', 'label' => __('admin_oncall.columns.person')],
            ['key' => 'type', 'label' => __('admin_oncall.columns.kind')],
            ['key' => 'zone', 'label' => __('admin_oncall.columns.zone')],
            ['key' => 'debut', 'label' => __('admin_oncall.columns.starts')],
            ['key' => 'fin', 'label' => __('admin_oncall.columns.ends')],
            ['key' => 'etat', 'label' => __('admin_oncall.columns.state')],
            ['key' => 'actions', 'label' => '', 'sr' => __('admin_oncall.columns.actions'), 'class' => 'text-end'],
        ]">
            @foreach ($shifts as $shift)
                @php
                    $isActive = $shift->isActive($now);
                    $isFinished = $shift->ends_at->lessThanOrEqualTo($now);
                    $label = ($shift->user?->pseudo ?? '').' · '.$format->tableDateTime($shift->starts_at);
                @endphp
                <tr wire:key="shift-{{ $shift->id }}" @class(['text-secondary' => $isFinished])>
                    <td data-label="{{ __('admin_oncall.columns.person') }}"><span class="fw-semibold text-break">{{ $shift->user?->pseudo }}</span>
                        @if ($shift->notes)<span class="d-block small text-secondary text-break">{{ $shift->notes }}</span>@endif
                    </td>
                    <td data-label="{{ __('admin_oncall.columns.kind') }}"><x-badge :color="$shift->isFocal() ? 'violet' : 'primary'" soft>{{ __('admin_oncall.kinds.'.$shift->kind) }}</x-badge></td>
                    <td data-label="{{ __('admin_oncall.columns.zone') }}">{{ $shift->isFocal() ? ($shift->zone?->name ?? __('admin_oncall.national')) : '—' }}</td>
                    <td data-label="{{ __('admin_oncall.columns.starts') }}">{{ $format->tableDateTime($shift->starts_at) }}</td>
                    <td data-label="{{ __('admin_oncall.columns.ends') }}">{{ $format->tableDateTime($shift->ends_at) }}</td>
                    <td data-label="{{ __('admin_oncall.columns.state') }}">
                        @if ($isActive)<x-badge color="success" soft icon="broadcast">{{ __('admin_oncall.state.active') }}</x-badge>
                        @elseif ($isFinished)<x-badge color="neutral">{{ __('admin_oncall.state.finished') }}</x-badge>
                        @else<x-badge color="info" soft>{{ __('admin_oncall.state.upcoming') }}</x-badge>@endif
                    </td>
                    <td data-label="{{ __('admin_oncall.columns.actions') }}" class="text-end xm-table__actions">
                        <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                            @unless ($isFinished)
                                <button type="button" class="btn btn-sm btn-light border" wire:click="openEdit({{ $shift->id }})" wire:loading.attr="disabled" wire:target="openEdit({{ $shift->id }})" title="{{ __('admin_oncall.actions.edit') }}">
                                    <i class="bi bi-pencil" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_oncall.actions.edit_of', ['label' => $label]) }}</span>
                                </button>
                            @endunless
                            <button type="button" class="btn btn-sm btn-light border text-danger" wire:click="confirmDelete({{ $shift->id }})" wire:loading.attr="disabled" wire:target="confirmDelete({{ $shift->id }})" title="{{ __('admin_oncall.actions.delete') }}">
                                <i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_oncall.actions.delete_of', ['label' => $label]) }}</span>
                            </button>
                        </div>
                    </td>
                </tr>
            @endforeach
            <x-slot:emptyState>
                <x-admin.empty icon="calendar-week" :title="__('admin_oncall.empty')">{{ __('admin_oncall.empty_text') }}</x-admin.empty>
            </x-slot:emptyState>
        </x-admin.table>
    </section>

    {{-- Garde : création et modification dans la même modale. --}}
    <x-admin.modal id="xmOnCallForm" size="lg" type="form" heading="h2" :title="$editId === 0 ? __('admin_oncall.form.title_new') : __('admin_oncall.form.title_edit')" :dirty-guard="true">
        <form id="xmOnCallFormEl" wire:submit="save" novalidate>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="xmOcKind">{{ __('admin_oncall.fields.kind') }}</label>
                    <select class="form-select @error('form.kind') is-invalid @enderror" id="xmOcKind" wire:model.live="form.kind" required aria-describedby="xmOcKindHint @error('form.kind') xmOcKindError @enderror" @error('form.kind') aria-invalid="true" @enderror>
                        @foreach ($kinds as $option)<option value="{{ $option }}">{{ __('admin_oncall.kinds.'.$option) }}</option>@endforeach
                    </select>
                    <div class="form-text" id="xmOcKindHint">{{ __('admin_oncall.form.kind_hint_'.($form['kind'] === 'focal' ? 'focal' : 'admin')) }}</div>
                    @error('form.kind')<div class="invalid-feedback d-block" id="xmOcKindError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="xmOcUser">{{ __('admin_oncall.fields.user_id') }}</label>
                    <select class="form-select @error('form.user_id') is-invalid @enderror" id="xmOcUser" wire:model="form.user_id" required @error('form.user_id') aria-invalid="true" aria-describedby="xmOcUserError" @enderror>
                        <option value="">{{ __('admin_oncall.form.choose_person') }}</option>
                        @foreach ($users as $option)<option value="{{ $option->id }}">{{ $option->pseudo }}</option>@endforeach
                    </select>
                    @error('form.user_id')<div class="invalid-feedback d-block" id="xmOcUserError">{{ $message }}</div>@enderror
                </div>
                @if ($form['kind'] === 'focal')
                    <div class="col-md-6">
                        <label class="form-label" for="xmOcZone">{{ __('admin_oncall.fields.zone_id') }}</label>
                        <select class="form-select @error('form.zone_id') is-invalid @enderror" id="xmOcZone" wire:model="form.zone_id" @error('form.zone_id') aria-invalid="true" aria-describedby="xmOcZoneError" @enderror>
                            <option value="">{{ __('admin_oncall.national') }}</option>
                            @foreach ($zones as $option)<option value="{{ $option['id'] }}">{{ $option['name'] }}</option>@endforeach
                        </select>
                        @error('form.zone_id')<div class="invalid-feedback d-block" id="xmOcZoneError">{{ $message }}</div>@enderror
                    </div>
                @endif
                <div class="col-md-6">
                    <label class="form-label" for="xmOcStart">{{ __('admin_oncall.fields.starts_at') }}</label>
                    <input class="form-control @error('form.starts_at') is-invalid @enderror" id="xmOcStart" type="datetime-local" wire:model="form.starts_at" required aria-describedby="xmOcTzHint @error('form.starts_at') xmOcStartError @enderror" @error('form.starts_at') aria-invalid="true" @enderror>
                    @error('form.starts_at')<div class="invalid-feedback d-block" id="xmOcStartError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="xmOcEnd">{{ __('admin_oncall.fields.ends_at') }}</label>
                    <input class="form-control @error('form.ends_at') is-invalid @enderror" id="xmOcEnd" type="datetime-local" wire:model="form.ends_at" required aria-describedby="xmOcTzHint @error('form.ends_at') xmOcEndError @enderror" @error('form.ends_at') aria-invalid="true" @enderror>
                    @error('form.ends_at')<div class="invalid-feedback d-block" id="xmOcEndError">{{ $message }}</div>@enderror
                </div>
                <div class="col-12"><div class="form-text mt-0" id="xmOcTzHint">{{ __('admin_oncall.form.timezone_hint', ['timezone' => $timezone]) }}</div></div>
                <div class="col-12">
                    <label class="form-label" for="xmOcNotes">{{ __('admin_oncall.fields.notes') }}</label>
                    <input class="form-control @error('form.notes') is-invalid @enderror" id="xmOcNotes" wire:model="form.notes" maxlength="200" autocomplete="off" @error('form.notes') aria-invalid="true" aria-describedby="xmOcNotesError" @enderror>
                    @error('form.notes')<div class="invalid-feedback d-block" id="xmOcNotesError">{{ $message }}</div>@enderror
                </div>
            </div>
        </form>
        <x-slot:footer>
            <button type="submit" form="xmOnCallFormEl" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save"><i class="bi bi-check2 me-1" aria-hidden="true"></i>{{ __('admin_oncall.form.save') }}</button>
        </x-slot:footer>
    </x-admin.modal>

    {{-- Suppression : confirmation, avec avertissement quand la garde est en cours. --}}
    <x-admin.modal id="xmOnCallDelete" type="delete" size="md" :title="__('admin_oncall.delete.title')" static>
        @if ($deleting)
            <p>{{ __('admin_oncall.delete.text', ['name' => (string) $deleting->user?->pseudo, 'from' => $format->tableDateTime($deleting->starts_at), 'to' => $format->tableDateTime($deleting->ends_at)]) }}</p>
            @if ($deleteWarning)
                <x-alert type="warning" icon="exclamation-triangle" class="mb-0" role="alert">{{ $deleteWarning }}</x-alert>
            @endif
        @endif
        <x-slot:footer>
            <button type="button" class="btn btn-danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('admin_oncall.delete.confirm') }}</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
