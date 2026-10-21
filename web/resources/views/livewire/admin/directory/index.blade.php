{{--
    Annuaire dynamique (P11-04) : composant App\Livewire\Admin\Directory\Index, gabarit admin. Introduction · KPI · carte
    « liste » (import / export, filtres, tableau, pied) · aperçu du routage (composant enfant) · modales « Contact » et
    « Importer un fichier CSV ». Les coordonnées d'une fiche ne sont jamais cherchées (nom, organisation, e-mail seulement).
--}}
<div class="xm-directory">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
        <p class="text-secondary mb-0 xm-prose">{{ __('admin_data.directory.intro') }}</p>
        <button type="button" class="btn btn-primary btn-sm" wire:click="openCreate" wire:loading.attr="disabled" wire:target="openCreate">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_data.directory.new') }}
        </button>
    </div>

    <x-admin.kpi id="xmDirectoryKpis" :items="[
        ['label' => __('admin_data.directory.kpi.contacts'), 'value' => $format->number($stats['total']), 'icon' => 'person-lines-fill'],
        ['label' => __('admin_data.directory.kpi.active'), 'value' => $format->number($stats['active']), 'icon' => 'check-circle', 'variant' => 'success'],
        ['label' => __('admin_data.directory.kpi.focal'), 'value' => $format->number($stats['focal']), 'icon' => 'geo-alt', 'variant' => 'violet'],
        ['label' => __('admin_data.directory.kpi.trio'), 'value' => $stats['trio'], 'icon' => 'heart-pulse', 'variant' => 'warning'],
    ]" />

    <section class="card xm-admin-card mb-4" aria-labelledby="xmDirectoryListTitle">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h6 fw-bold mb-0" id="xmDirectoryListTitle" role="status">{{ trans_choice('admin_data.directory.count', $rows->total(), ['count' => $format->number($rows->total())]) }}</h2>
            <div class="d-flex flex-wrap gap-2">
                @if ($canExport)
                    <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="export" wire:loading.attr="disabled" wire:target="export">
                        <i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('admin_data.directory.export', ['count' => $format->number($rows->total())]) }}
                    </button>
                @endif
                <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="openImport" wire:loading.attr="disabled" wire:target="openImport">
                    <i class="bi bi-upload me-1" aria-hidden="true"></i>{{ __('admin_data.directory.import.button') }}
                </button>
            </div>
        </div>
        <div class="card-body border-bottom">
            <x-admin.filters id="xmDirectoryFilters" :active="$this->activeFilterCount()" :placeholder="__('admin_data.directory.search_placeholder')" :search-help="__('admin_data.directory.search_help')">
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="visually-hidden" for="xmDirectoryRole">{{ __('admin_data.directory.columns.role') }}</label>
                    <select class="form-select form-select-sm" id="xmDirectoryRole" wire:model.live="role">
                        <option value="">{{ __('admin_data.directory.filters.all_roles') }}</option>
                        @foreach ($roles as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="visually-hidden" for="xmDirectoryZone">{{ __('admin.filters.zone') }}</label>
                    <select class="form-select form-select-sm" id="xmDirectoryZone" wire:model.live="zone">
                        <option value="">{{ __('admin.filters.all_zones') }}</option>
                        @foreach ($zones as $option)<option value="{{ $option['id'] }}">{{ $option['name'] }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="visually-hidden" for="xmDirectoryStatus">{{ __('admin_data.directory.columns.active') }}</label>
                    <select class="form-select form-select-sm" id="xmDirectoryStatus" wire:model.live="status">
                        <option value="">{{ __('admin.filters.all_statuses') }}</option>
                        <option value="actif">{{ __('admin_data.directory.filters.active') }}</option>
                        <option value="inactif">{{ __('admin_data.directory.filters.inactive') }}</option>
                    </select>
                </div>
            </x-admin.filters>
        </div>

        <x-admin.table id="xmDirectoryTable" :caption="__('admin_data.directory.table_caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
            ['key' => 'nom', 'label' => __('admin_data.directory.columns.contact'), 'sortable' => true],
            ['key' => 'role', 'label' => __('admin_data.directory.columns.role'), 'sortable' => true],
            ['key' => 'zone', 'label' => __('admin_data.directory.columns.zone'), 'sortable' => true],
            ['key' => 'coordonnees', 'label' => __('admin_data.directory.columns.details')],
            ['key' => 'actif', 'label' => __('admin_data.directory.columns.active'), 'sortable' => true],
            ['key' => 'actions', 'label' => '', 'sr' => __('admin_data.directory.columns.actions'), 'class' => 'text-end'],
        ]">
            @foreach ($rows as $contact)
                <tr wire:key="contact-{{ $contact->id }}" @class(['text-secondary' => ! $contact->is_active])>
                    <td data-label="{{ __('admin_data.directory.columns.contact') }}">
                        <div class="min-w-0">
                            <span class="d-block fw-semibold text-break">{{ $contact->name }}</span>
                            @if ($contact->org)<span class="d-block small text-secondary">{{ $contact->org }}</span>@endif
                            <span class="d-flex flex-wrap gap-1 mt-1">
                                @if ($contact->is_national_focal)<x-badge color="danger" soft>{{ __('admin_data.directory.flags.national_focal') }}</x-badge>@endif
                                @if ($contact->is_emergency)<x-badge color="warning" soft>{{ __('admin_data.directory.flags.emergency') }}</x-badge>@endif
                                @unless ($contact->is_public)<x-badge color="neutral" icon="lock">{{ __('admin_data.directory.flags.private') }}</x-badge>@endunless
                                @if ($contact->user)<x-badge color="info" soft icon="person-check">{{ __('admin_data.directory.flags.account', ['pseudo' => $contact->user->pseudo]) }}</x-badge>@endif
                            </span>
                        </div>
                    </td>
                    <td data-label="{{ __('admin_data.directory.columns.role') }}"><x-badge :color="$contact->role->badge()" soft>{{ $contact->role->label() }}</x-badge></td>
                    <td data-label="{{ __('admin_data.directory.columns.zone') }}">
                        <div class="min-w-0">
                            <span class="d-block">{{ $contact->zone?->name ?? __('admin_data.directory.national') }}</span>
                            @if ($contact->commune)<span class="d-block small text-secondary">{{ $contact->commune->name }}</span>@endif
                        </div>
                    </td>
                    <td data-label="{{ __('admin_data.directory.columns.details') }}" class="small">
                        <div class="min-w-0">
                            @if ($contact->phone)<span class="d-block"><i class="bi bi-telephone me-1" aria-hidden="true"></i>{{ $contact->phone }}</span>@endif
                            @if ($contact->email)<span class="d-block text-break"><i class="bi bi-envelope me-1" aria-hidden="true"></i>{{ $contact->email }}</span>@endif
                            @if ($contact->availability)<span class="d-block text-secondary">{{ $contact->availability }}</span>@endif
                        </div>
                    </td>
                    <td data-label="{{ __('admin_data.directory.columns.active') }}">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="xmDirectoryActive-{{ $contact->id }}" @checked($contact->is_active) wire:click="toggle({{ $contact->id }})" wire:loading.attr="disabled" wire:target="toggle({{ $contact->id }})">
                            <label class="form-check-label visually-hidden" for="xmDirectoryActive-{{ $contact->id }}">{{ __('admin_data.directory.toggle', ['name' => $contact->name]) }}</label>
                        </div>
                    </td>
                    <td data-label="{{ __('admin_data.directory.columns.actions') }}" class="text-end xm-table__actions">
                        <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                            <button type="button" class="btn btn-sm btn-light border" wire:click="openEdit({{ $contact->id }})" wire:loading.attr="disabled" wire:target="openEdit({{ $contact->id }})" title="{{ __('admin_data.directory.actions.edit') }}">
                                <i class="bi bi-pencil" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_data.directory.actions.edit') }} {{ $contact->name }}</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-light border text-danger" wire:click="delete({{ $contact->id }})" wire:confirm="{{ __('admin_data.directory.confirm_delete', ['name' => $contact->name]) }}" title="{{ __('admin_data.directory.actions.delete') }}">
                                <i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_data.directory.actions.delete') }} {{ $contact->name }}</span>
                            </button>
                        </div>
                    </td>
                </tr>
            @endforeach
            <x-slot:emptyState>
                <x-admin.empty icon="person-lines-fill" :filtered="$this->activeFilterCount() > 0" :title="__('admin_data.directory.empty')">{{ __('admin_data.directory.empty_text') }}</x-admin.empty>
            </x-slot:emptyState>
            <x-slot:footer>
                <x-admin.pagination id="xmDirectoryPager" :paginator="$rows" />
            </x-slot:footer>
        </x-admin.table>
    </section>

    <livewire:admin.directory.routing-preview />

    {{-- Contact : création et modification dans la même modale. --}}
    <x-admin.modal id="xmDirectoryForm" size="lg" type="form" heading="h2" :title="$editId === 0 ? __('admin_data.directory.form.title_new') : __('admin_data.directory.form.title_edit')" :dirty-guard="true">
        <form id="xmDirectoryFormEl" wire:submit="save" novalidate>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="xmDirName">{{ __('admin_data.directory.fields.name') }}</label>
                    <input class="form-control @error('form.name') is-invalid @enderror" id="xmDirName" wire:model="form.name" maxlength="120" required autocomplete="off" @error('form.name') aria-invalid="true" aria-describedby="xmDirNameError" @enderror>
                    @error('form.name')<div class="invalid-feedback d-block" id="xmDirNameError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="xmDirRole">{{ __('admin_data.directory.fields.role') }}</label>
                    <select class="form-select @error('form.role') is-invalid @enderror" id="xmDirRole" wire:model="form.role" required @error('form.role') aria-invalid="true" aria-describedby="xmDirRoleError" @enderror>
                        @foreach ($roles as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
                    </select>
                    @error('form.role')<div class="invalid-feedback d-block" id="xmDirRoleError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="xmDirOrg">{{ __('admin_data.directory.fields.org') }}</label>
                    <input class="form-control @error('form.org') is-invalid @enderror" id="xmDirOrg" wire:model="form.org" maxlength="120" @error('form.org') aria-invalid="true" aria-describedby="xmDirOrgError" @enderror>
                    @error('form.org')<div class="invalid-feedback d-block" id="xmDirOrgError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="xmDirAvailability">{{ __('admin_data.directory.fields.availability') }}</label>
                    <input class="form-control @error('form.availability') is-invalid @enderror" id="xmDirAvailability" wire:model="form.availability" maxlength="120" placeholder="{{ __('admin_data.directory.form.availability_placeholder') }}" @error('form.availability') aria-invalid="true" aria-describedby="xmDirAvailabilityError" @enderror>
                    @error('form.availability')<div class="invalid-feedback d-block" id="xmDirAvailabilityError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="xmDirPhone">{{ __('admin_data.directory.fields.phone') }}</label>
                    <input class="form-control @error('form.phone') is-invalid @enderror" id="xmDirPhone" type="tel" wire:model="form.phone" maxlength="30" placeholder="+229 01 97 38 88 47" autocomplete="off" @error('form.phone') aria-invalid="true" aria-describedby="xmDirPhoneError" @enderror>
                    @error('form.phone')<div class="invalid-feedback d-block" id="xmDirPhoneError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="xmDirEmail">{{ __('admin_data.directory.fields.email') }}</label>
                    <input class="form-control @error('form.email') is-invalid @enderror" id="xmDirEmail" type="email" wire:model="form.email" maxlength="190" autocomplete="off" @error('form.email') aria-invalid="true" aria-describedby="xmDirEmailError" @enderror>
                    @error('form.email')<div class="invalid-feedback d-block" id="xmDirEmailError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="xmDirWhatsapp">{{ __('admin_data.directory.fields.whatsapp') }}</label>
                    <input class="form-control @error('form.whatsapp') is-invalid @enderror" id="xmDirWhatsapp" type="tel" wire:model="form.whatsapp" maxlength="30" autocomplete="off" @error('form.whatsapp') aria-invalid="true" aria-describedby="xmDirWhatsappError" @enderror>
                    @error('form.whatsapp')<div class="invalid-feedback d-block" id="xmDirWhatsappError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="xmDirAccount">{{ __('admin_data.directory.fields.user_ref') }}</label>
                    <input class="form-control @error('form.user_ref') is-invalid @enderror" id="xmDirAccount" wire:model="form.user_ref" maxlength="190" autocomplete="off" aria-describedby="xmDirAccountHint @error('form.user_ref') xmDirAccountError @enderror" @error('form.user_ref') aria-invalid="true" @enderror>
                    <div class="form-text" id="xmDirAccountHint">{{ __('admin_data.directory.form.account_hint') }}</div>
                    @error('form.user_ref')<div class="invalid-feedback d-block" id="xmDirAccountError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="xmDirZone">{{ __('admin_data.directory.fields.zone_id') }}</label>
                    <select class="form-select @error('form.zone_id') is-invalid @enderror" id="xmDirZone" wire:model.live="form.zone_id" @error('form.zone_id') aria-invalid="true" aria-describedby="xmDirZoneError" @enderror>
                        <option value="">{{ __('admin_data.directory.national') }}</option>
                        @foreach ($zones as $option)<option value="{{ $option['id'] }}">{{ $option['name'] }}</option>@endforeach
                    </select>
                    @error('form.zone_id')<div class="invalid-feedback d-block" id="xmDirZoneError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="xmDirCommune">{{ __('admin_data.directory.fields.commune_id') }}</label>
                    <select class="form-select @error('form.commune_id') is-invalid @enderror" id="xmDirCommune" wire:model="form.commune_id" @error('form.commune_id') aria-invalid="true" aria-describedby="xmDirCommuneError" @enderror>
                        <option value="">—</option>
                        @foreach ($communes as $option)<option value="{{ $option['id'] }}">{{ $option['name'] }}</option>@endforeach
                    </select>
                    @error('form.commune_id')<div class="invalid-feedback d-block" id="xmDirCommuneError">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="xmDirActive" wire:model="form.is_active">
                        <label class="form-check-label" for="xmDirActive">{{ __('admin_data.directory.form.is_active') }}</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="xmDirPublic" wire:model="form.is_public">
                        <label class="form-check-label" for="xmDirPublic">{{ __('admin_data.directory.form.is_public') }}</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="xmDirEmergency" wire:model="form.is_emergency">
                        <label class="form-check-label" for="xmDirEmergency">{{ __('admin_data.directory.form.is_emergency') }}</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input @error('form.is_national_focal') is-invalid @enderror" type="checkbox" role="switch" id="xmDirNational" wire:model="form.is_national_focal" @error('form.is_national_focal') aria-invalid="true" aria-describedby="xmDirNationalError" @enderror>
                        <label class="form-check-label" for="xmDirNational">{{ __('admin_data.directory.form.is_national_focal') }}</label>
                        @error('form.is_national_focal')<div class="invalid-feedback d-block" id="xmDirNationalError">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </form>
        <x-slot:footer>
            <button type="submit" form="xmDirectoryFormEl" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save"><i class="bi bi-check2 me-1" aria-hidden="true"></i>{{ __('admin_data.directory.form.save') }}</button>
        </x-slot:footer>
    </x-admin.modal>

    {{-- Import CSV : modèle téléchargeable, rapport des lignes rejetées. --}}
    <x-admin.modal id="xmDirectoryImport" size="lg" type="form" heading="h2" :title="__('admin_data.directory.import.title')">
        <p class="small text-secondary">{{ __('admin_data.directory.import.help') }}
            <button type="button" class="btn btn-link btn-sm p-0 align-baseline" wire:click="downloadTemplate">{{ __('admin_data.directory.import.template') }}</button>.</p>
        <form id="xmDirectoryImportEl" wire:submit="import" novalidate>
            <label class="form-label" for="xmDirCsv">{{ __('admin_data.directory.import.file') }}</label>
            <input class="form-control @error('csv') is-invalid @enderror" type="file" id="xmDirCsv" wire:model="csv" accept=".csv,text/csv,text/plain" required @error('csv') aria-invalid="true" aria-describedby="xmDirCsvError" @enderror>
            <div wire:loading wire:target="csv" class="form-text">{{ __('admin_data.directory.import.uploading') }}</div>
            @error('csv')<div class="invalid-feedback d-block" id="xmDirCsvError">{{ $message }}</div>@enderror
        </form>
        @if ($importReport)
            <div class="mt-3" role="status" aria-live="polite">
                <p class="mb-2 fw-semibold">{{ __('admin_data.directory.import.report', ['created' => $importReport['created'], 'updated' => $importReport['updated'], 'rejected' => count($importReport['rejected'])]) }}</p>
                @if ($importReport['rejected'] !== [])
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <caption class="visually-hidden">{{ __('admin_data.directory.import.rejected_caption') }}</caption>
                            <thead><tr><th scope="col">{{ __('admin_data.directory.import.line') }}</th><th scope="col">{{ __('admin_data.directory.columns.contact') }}</th><th scope="col">{{ __('admin_data.directory.import.reason') }}</th></tr></thead>
                            <tbody>
                                @foreach ($importReport['rejected'] as $rejected)
                                    <tr><td>{{ $rejected['line'] }}</td><td class="text-break">{{ $rejected['name'] !== '' ? $rejected['name'] : '—' }}</td><td class="small text-danger">{{ implode(' ', $rejected['reasons']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
        <x-slot:footer>
            <button type="submit" form="xmDirectoryImportEl" class="btn btn-primary" wire:loading.attr="disabled" wire:target="import,csv"><i class="bi bi-upload me-1" aria-hidden="true"></i>{{ __('admin_data.directory.import.submit') }}</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
