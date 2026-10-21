{{--
    Fiches de référence VBG / VDH (P11-02) : composant App\Livewire\Admin\Forms\Index, gabarit admin. Introduction · carte
    « liste » (filtres, tableau, pied) · modale « Nouvelle fiche » (cinq sections) · modale « Détail » (lecture, PDF A4,
    suppression). Récit, auteur·e des faits et signataire (chiffrés) ne figurent que dans la modale « Détail » et le PDF.
--}}
<div class="xm-forms">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
        <p class="text-secondary mb-0 xm-prose">{{ __('admin_data.forms.intro') }}</p>
        <button type="button" class="btn btn-primary btn-sm" wire:click="openCreate" wire:loading.attr="disabled" wire:target="openCreate">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_data.forms.new') }}
        </button>
    </div>

    <section class="card xm-admin-card" aria-labelledby="xmFormsListTitle">
        <div class="card-header">
            <h2 class="h6 fw-bold mb-0" id="xmFormsListTitle" role="status">{{ trans_choice('admin_data.forms.count', $rows->total(), ['count' => $format->number($rows->total())]) }}</h2>
        </div>
        <div class="card-body border-bottom">
            <x-admin.filters id="xmFormsFilters" :active="$this->activeFilterCount()" :placeholder="__('admin_data.forms.search_placeholder')" :search-help="__('admin_data.forms.search_help')">
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="visually-hidden" for="xmFormsDossier">{{ __('admin_data.forms.columns.case') }}</label>
                    <input class="form-control form-control-sm" id="xmFormsDossier" wire:model.live.debounce.400ms="dossier" maxlength="12" autocomplete="off" placeholder="{{ __('admin_data.forms.filters.case') }}" list="xmFormsCodes">
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="visually-hidden" for="xmFormsKind">{{ __('admin_data.forms.columns.kind') }}</label>
                    <select class="form-select form-select-sm" id="xmFormsKind" wire:model.live="kind">
                        <option value="">{{ __('admin_data.forms.filters.all_kinds') }}</option>
                        <option value="VBG">VBG</option>
                        <option value="VDH">VDH</option>
                    </select>
                </div>
            </x-admin.filters>
        </div>

        <x-admin.table id="xmFormsTable" :caption="__('admin_data.forms.table_caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
            ['key' => 'reference', 'label' => __('admin_data.forms.columns.reference'), 'sortable' => true],
            ['key' => 'dossier', 'label' => __('admin_data.forms.columns.case'), 'sortable' => true],
            ['key' => 'date', 'label' => __('admin_data.forms.columns.created'), 'sortable' => true],
            ['key' => 'referral', 'label' => __('admin_data.forms.columns.referral')],
            ['key' => 'consent', 'label' => __('admin_data.forms.columns.consent')],
            ['key' => 'actions', 'label' => '', 'sr' => __('admin_data.forms.columns.actions'), 'class' => 'text-end'],
        ]">
            @foreach ($rows as $form)
                <tr wire:key="form-{{ $form->id }}">
                    <td data-label="{{ __('admin_data.forms.columns.reference') }}" class="fw-semibold">{{ $form->reference }}</td>
                    <td data-label="{{ __('admin_data.forms.columns.case') }}">
                        <div class="min-w-0">
                            <span class="d-block">{{ $form->case?->code }}</span>
                            @if ($form->case)<x-badge :color="$form->case->kind->badge()" soft>{{ $form->case->kind->value }}</x-badge>@endif
                        </div>
                    </td>
                    <td data-label="{{ __('admin_data.forms.columns.created') }}" class="text-nowrap"><time class="small" datetime="{{ $form->created_at?->toIso8601String() }}">{{ $format->tableDateTime($form->created_at) }}</time></td>
                    <td data-label="{{ __('admin_data.forms.columns.referral') }}" class="small">{{ $form->referral_label ?? __('admin_data.forms.no_referral') }}</td>
                    <td data-label="{{ __('admin_data.forms.columns.consent') }}">
                        @if ($form->consent_given)
                            <x-badge color="success" soft icon="check2-circle">{{ $form->consent_sharing ? __('admin_data.forms.consent_shared') : __('admin_data.forms.consent_given') }}</x-badge>
                        @else
                            <x-badge color="danger" soft>{{ __('admin_data.forms.consent_missing') }}</x-badge>
                        @endif
                    </td>
                    <td data-label="{{ __('admin_data.forms.columns.actions') }}" class="text-end xm-table__actions">
                        <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                            <button type="button" class="btn btn-sm btn-light border" wire:click="openDetail({{ $form->id }})" wire:loading.attr="disabled" wire:target="openDetail({{ $form->id }})" title="{{ __('admin_data.forms.actions.view') }}">
                                <i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_data.forms.actions.view') }} {{ $form->reference }}</span>
                            </button>
                            <a class="btn btn-sm btn-light border" href="{{ route('admin.fiches.pdf', $form) }}" target="_blank" rel="noopener" title="{{ __('admin_data.forms.actions.pdf') }}">
                                <i class="bi bi-printer" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_data.forms.actions.pdf') }} {{ $form->reference }}</span>
                            </a>
                            <button type="button" class="btn btn-sm btn-light border text-danger" wire:click="delete({{ $form->id }})"
                                    wire:confirm="{{ __('admin_data.forms.confirm_delete', ['reference' => $form->reference]) }}" title="{{ __('admin_data.forms.actions.delete') }}">
                                <i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_data.forms.actions.delete') }} {{ $form->reference }}</span>
                            </button>
                        </div>
                    </td>
                </tr>
            @endforeach
            <x-slot:emptyState>
                <x-admin.empty icon="file-earmark-medical" :filtered="$this->activeFilterCount() > 0" :title="__('admin_data.forms.empty')">
                    {{ __('admin_data.forms.empty_text') }}
                    <x-slot:action><button type="button" class="btn btn-sm btn-primary" wire:click="openCreate"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_data.forms.new') }}</button></x-slot:action>
                </x-admin.empty>
            </x-slot:emptyState>
            <x-slot:footer>
                <x-admin.pagination id="xmFormsPager" :paginator="$rows" />
            </x-slot:footer>
        </x-admin.table>
    </section>

    <datalist id="xmFormsCodes">@foreach ($caseCodes as $code)<option value="{{ $code }}">@endforeach</datalist>

    {{-- Nouvelle fiche : cinq sections du Module 4 ; le consentement de la personne est obligatoire. --}}
    <x-admin.modal id="xmFormEditor" size="xl" type="form" heading="h2" :title="__('admin_data.forms.form.title')" :dirty-guard="true">
        <form id="xmFormEditorEl" wire:submit="save" novalidate>
            <div class="alert alert-warning small d-flex gap-2" role="note">
                <i class="bi bi-shield-exclamation" aria-hidden="true"></i>
                <span>{{ __('admin_data.forms.form.warning') }}</span>
            </div>

            <h3 class="h6 text-primary">{{ __('admin_data.forms.sections.identification') }}</h3>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label" for="xmFormCase">{{ __('admin_data.forms.fields.case_code') }}</label>
                    <input class="form-control @error('form.case_code') is-invalid @enderror" id="xmFormCase" list="xmFormsCodes" wire:model.live.debounce.400ms="form.case_code" maxlength="12" autocomplete="off" placeholder="VBG-0000" required
                           @error('form.case_code') aria-invalid="true" aria-describedby="xmFormCaseError" @enderror>
                    @error('form.case_code')<div class="invalid-feedback d-block" id="xmFormCaseError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="xmFormPseudo">{{ __('admin_data.forms.fields.pseudo') }}</label>
                    <input class="form-control @error('form.pseudo') is-invalid @enderror" id="xmFormPseudo" wire:model="form.pseudo" maxlength="60" autocomplete="off" placeholder="{{ __('admin_data.forms.form.pseudo_placeholder') }}"
                           @error('form.pseudo') aria-invalid="true" aria-describedby="xmFormPseudoError" @enderror>
                    @error('form.pseudo')<div class="invalid-feedback d-block" id="xmFormPseudoError">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    @php($case = $this->selectedCase)
                    <div class="alert alert-light border small mb-0" role="status" aria-live="polite">
                        @if ($case)
                            <strong>{{ $case->code }}</strong> · {{ $case->sex->label() }}, {{ $case->age_range->label() }} · {{ $case->population?->label }} · {{ $case->zone?->name }}@if ($case->commune) / {{ $case->commune->name }}@endif · {{ $case->violenceType?->label }}
                        @else
                            {{ __('admin_data.forms.form.case_hint') }}
                        @endif
                    </div>
                </div>
            </div>

            <h3 class="h6 text-primary">{{ __('admin_data.forms.sections.facts') }}</h3>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label" for="xmFormFactsDate">{{ __('admin_data.forms.fields.facts_date') }}</label>
                    <input class="form-control @error('form.facts_date') is-invalid @enderror" type="date" id="xmFormFactsDate" wire:model="form.facts_date" max="{{ $today }}" required @error('form.facts_date') aria-invalid="true" aria-describedby="xmFormFactsDateError" @enderror>
                    @error('form.facts_date')<div class="invalid-feedback d-block" id="xmFormFactsDateError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="xmFormFactsPlace">{{ __('admin_data.forms.fields.facts_place') }}</label>
                    <input class="form-control @error('form.facts_place') is-invalid @enderror" id="xmFormFactsPlace" wire:model="form.facts_place" maxlength="120" @error('form.facts_place') aria-invalid="true" aria-describedby="xmFormFactsPlaceError" @enderror>
                    @error('form.facts_place')<div class="invalid-feedback d-block" id="xmFormFactsPlaceError">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="xmFormFactsAuthor">{{ __('admin_data.forms.fields.facts_author') }}</label>
                    <input class="form-control @error('form.facts_author') is-invalid @enderror" id="xmFormFactsAuthor" wire:model="form.facts_author" maxlength="120" autocomplete="off" placeholder="{{ __('admin_data.forms.form.author_placeholder') }}" @error('form.facts_author') aria-invalid="true" aria-describedby="xmFormFactsAuthorError" @enderror>
                    @error('form.facts_author')<div class="invalid-feedback d-block" id="xmFormFactsAuthorError">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="xmFormFactsDescription">{{ __('admin_data.forms.fields.facts_description') }}</label>
                    <textarea class="form-control @error('form.facts_description') is-invalid @enderror" id="xmFormFactsDescription" rows="3" maxlength="3000" wire:model="form.facts_description" required aria-describedby="xmFormFactsDescriptionHint @error('form.facts_description') xmFormFactsDescriptionError @enderror"
                              @error('form.facts_description') aria-invalid="true" @enderror></textarea>
                    <div class="form-text" id="xmFormFactsDescriptionHint">{{ __('admin_data.forms.form.description_hint', ['min' => \App\Domain\Cases\CaseNotes::MIN_LENGTH]) }}</div>
                    @error('form.facts_description')<div class="invalid-feedback d-block" id="xmFormFactsDescriptionError">{{ $message }}</div>@enderror
                </div>
            </div>

            <fieldset class="mb-4">
                <legend class="h6 text-primary">{{ __('admin_data.forms.sections.services') }}</legend>
                <div class="row g-1">
                    @foreach ($services as $key)
                        <div class="col-sm-6 col-lg-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="xmFormService-{{ $key }}" wire:model="form.services.{{ $key }}">
                                <label class="form-check-label" for="xmFormService-{{ $key }}">{{ \App\Models\CaseForm::serviceLabel($key) }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
                <label class="visually-hidden" for="xmFormServicesNote">{{ __('admin_data.forms.fields.services_note') }}</label>
                <input class="form-control form-control-sm mt-2 @error('form.services_note') is-invalid @enderror" id="xmFormServicesNote" wire:model="form.services_note" maxlength="1000" placeholder="{{ __('admin_data.forms.form.services_note_placeholder') }}">
                @error('form.services_note')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </fieldset>

            <h3 class="h6 text-primary">{{ __('admin_data.forms.sections.referral') }}</h3>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label" for="xmFormContact">{{ __('admin_data.forms.fields.referral_contact_id') }}</label>
                    <select class="form-select @error('form.referral_contact_id') is-invalid @enderror" id="xmFormContact" wire:model="form.referral_contact_id" @error('form.referral_contact_id') aria-invalid="true" aria-describedby="xmFormContactError" @enderror>
                        <option value="">{{ __('admin_data.forms.form.no_contact') }}</option>
                        @foreach ($contacts as $contact)
                            <option value="{{ $contact->id }}">{{ $contact->name }} — {{ $contact->role->label() }}@if ($contact->zone) ({{ $contact->zone->name }})@endif</option>
                        @endforeach
                    </select>
                    @error('form.referral_contact_id')<div class="invalid-feedback d-block" id="xmFormContactError">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="xmFormReferralDate">{{ __('admin_data.forms.fields.referral_date') }}</label>
                    <input class="form-control @error('form.referral_date') is-invalid @enderror" type="date" id="xmFormReferralDate" wire:model="form.referral_date" @error('form.referral_date') aria-invalid="true" aria-describedby="xmFormReferralDateError" @enderror>
                    @error('form.referral_date')<div class="invalid-feedback d-block" id="xmFormReferralDateError">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="xmFormUrgency">{{ __('admin_data.forms.fields.referral_urgency') }}</label>
                    <select class="form-select @error('form.referral_urgency') is-invalid @enderror" id="xmFormUrgency" wire:model="form.referral_urgency" @error('form.referral_urgency') aria-invalid="true" aria-describedby="xmFormUrgencyError" @enderror>
                        @foreach ($urgencies as $urgency)<option value="{{ $urgency }}">{{ __('admin_data.forms.urgencies.'.$urgency) }}</option>@endforeach
                    </select>
                    @error('form.referral_urgency')<div class="invalid-feedback d-block" id="xmFormUrgencyError">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="xmFormReason">{{ __('admin_data.forms.fields.referral_reason') }}</label>
                    <input class="form-control @error('form.referral_reason') is-invalid @enderror" id="xmFormReason" wire:model="form.referral_reason" maxlength="255" @error('form.referral_reason') aria-invalid="true" aria-describedby="xmFormReasonError" @enderror>
                    @error('form.referral_reason')<div class="invalid-feedback d-block" id="xmFormReasonError">{{ $message }}</div>@enderror
                </div>
            </div>

            <fieldset class="mb-0">
                <legend class="h6 text-primary">{{ __('admin_data.forms.sections.consent') }}</legend>
                <div class="form-check">
                    <input class="form-check-input @error('form.consent_given') is-invalid @enderror" type="checkbox" id="xmFormConsent" wire:model="form.consent_given" required @error('form.consent_given') aria-invalid="true" aria-describedby="xmFormConsentError" @enderror>
                    <label class="form-check-label" for="xmFormConsent">{{ __('admin_data.forms.form.consent_given') }}</label>
                    @error('form.consent_given')<div class="invalid-feedback d-block" id="xmFormConsentError">{{ $message }}</div>@enderror
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="xmFormSharing" wire:model="form.consent_sharing">
                    <label class="form-check-label" for="xmFormSharing">{{ __('admin_data.forms.form.consent_sharing') }}</label>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-sm-6">
                        <label class="form-label small mb-1" for="xmFormMode">{{ __('admin_data.forms.fields.consent_mode') }}</label>
                        <select class="form-select form-select-sm @error('form.consent_mode') is-invalid @enderror" id="xmFormMode" wire:model="form.consent_mode">
                            @foreach (\App\Application\Cases\CaseFormRules::CONSENT_MODES as $mode)<option value="{{ $mode }}">{{ __('admin_data.forms.modes.'.$mode) }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small mb-1" for="xmFormSignatory">{{ __('admin_data.forms.fields.consent_signatory') }}</label>
                        <input class="form-control form-control-sm @error('form.consent_signatory') is-invalid @enderror" id="xmFormSignatory" wire:model="form.consent_signatory" maxlength="80" autocomplete="off" placeholder="{{ __('admin_data.forms.form.signatory_placeholder') }}">
                        @error('form.consent_signatory')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </fieldset>
        </form>
        <x-slot:footer>
            <button type="submit" form="xmFormEditorEl" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save"><i class="bi bi-save me-1" aria-hidden="true"></i>{{ __('admin_data.forms.form.save') }}</button>
        </x-slot:footer>
    </x-admin.modal>

    {{-- Détail : montée en permanence ; vidée à la fermeture (hidden.bs.modal → closeDetail). --}}
    @php($detail = $this->detail)
    <x-admin.modal id="xmFormDetail" size="xl" type="form" heading="h2" :title="$detail ? __('admin_data.forms.detail.title', ['reference' => $detail['sheet']['reference']]) : __('admin_data.forms.actions.view')" x-data x-on:hidden-bs-modal.dot="$wire.closeDetail()">
        @if ($detail)
            @php($sheet = $detail['sheet'])
            <p class="small text-secondary">{{ __('admin_data.forms.pdf.title', ['kind' => $sheet['kind']]) }} · {{ __('admin_data.forms.pdf.case', ['code' => $sheet['case_code']]) }} · {{ __('admin_data.forms.pdf.established', ['date' => $sheet['created_at'] ? $format->shortDate($sheet['created_at']) : '—', 'author' => $sheet['author'] ?? '—']) }}</p>
            <div class="row g-3 small">
                <div class="col-md-6">
                    <h3 class="h6 text-primary">{{ __('admin_data.forms.sections.identification') }}</h3>
                    <ul class="list-unstyled mb-0">
                        <li>{{ __('admin_data.forms.fields.pseudo') }}&nbsp;: <strong>{{ $sheet['identification']['pseudo'] ?? '—' }}</strong></li>
                        <li>{{ __('admin_data.forms.pdf.sex_age') }}&nbsp;: {{ $sheet['identification']['sex'] ?? '—' }} · {{ $sheet['identification']['age'] ?? '—' }}</li>
                        <li>{{ __('admin_data.forms.pdf.population') }}&nbsp;: {{ $sheet['identification']['population'] ?? '—' }}</li>
                        <li>{{ __('admin_data.forms.pdf.location') }}&nbsp;: {{ $sheet['identification']['zone'] ?? '—' }} / {{ $sheet['identification']['commune'] ?? '—' }}</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h3 class="h6 text-primary">{{ __('admin_data.forms.sections.facts') }}</h3>
                    <ul class="list-unstyled mb-0">
                        <li>{{ __('admin_data.forms.pdf.date_place') }}&nbsp;: {{ $sheet['facts']['date'] ? $format->shortDate($sheet['facts']['date']) : '—' }} · {{ $sheet['facts']['place'] ?? '—' }}</li>
                        <li>{{ __('admin_data.forms.pdf.type') }}&nbsp;: {{ $sheet['facts']['type'] ?? '—' }}</li>
                        <li>{{ __('admin_data.forms.fields.facts_author') }}&nbsp;: {{ $sheet['facts']['author'] ?? '—' }}</li>
                        <li class="mt-1 text-break" style="white-space: pre-line">{{ $sheet['facts']['description'] }}</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h3 class="h6 text-primary">{{ __('admin_data.forms.sections.services') }}</h3>
                    @if ($sheet['services']['items'] === [])
                        <p class="mb-1">{{ __('admin_data.forms.pdf.none') }}</p>
                    @else
                        <ul class="mb-1">@foreach ($sheet['services']['items'] as $item)<li>{{ $item }}</li>@endforeach</ul>
                    @endif
                    @if ($sheet['services']['note'])<p class="mb-0">{{ $sheet['services']['note'] }}</p>@endif
                </div>
                <div class="col-md-6">
                    <h3 class="h6 text-primary">{{ __('admin_data.forms.sections.referral') }}</h3>
                    <ul class="list-unstyled mb-0">
                        <li>{{ __('admin_data.forms.pdf.to') }}&nbsp;: <strong>{{ $sheet['referral']['label'] ?? '—' }}</strong></li>
                        <li>{{ __('admin_data.forms.pdf.date_urgency') }}&nbsp;: {{ $sheet['referral']['date'] ? $format->shortDate($sheet['referral']['date']) : '—' }} · {{ $sheet['referral']['urgency'] ? __('admin_data.forms.urgencies.'.$sheet['referral']['urgency']) : '—' }}</li>
                        <li>{{ __('admin_data.forms.fields.referral_reason') }}&nbsp;: {{ $sheet['referral']['reason'] ?? '—' }}</li>
                    </ul>
                </div>
                <div class="col-12">
                    <h3 class="h6 text-primary">{{ __('admin_data.forms.sections.consent') }}</h3>
                    <p class="mb-0">
                        {{ $sheet['consent']['given'] ? __('admin_data.forms.pdf.consent_given', ['mode' => __('admin_data.forms.modes.'.$sheet['consent']['mode'])]) : __('admin_data.forms.pdf.consent_missing') }}.
                        {{ __('admin_data.forms.pdf.sharing') }}&nbsp;: <strong>{{ $sheet['consent']['sharing'] ? __('admin_data.forms.pdf.allowed') : __('admin_data.forms.pdf.not_allowed') }}</strong>.
                        {{ __('admin_data.forms.fields.consent_signatory') }}&nbsp;: {{ $sheet['consent']['signatory'] ?? '—' }}
                    </p>
                </div>
            </div>
        @endif
        <x-slot:footer>
            @if ($detail)
                <button type="button" class="btn btn-outline-danger btn-sm me-auto" wire:click="delete({{ $detail['id'] }})" wire:confirm="{{ __('admin_data.forms.confirm_delete', ['reference' => $detail['sheet']['reference']]) }}">
                    <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('admin_data.forms.actions.delete') }}
                </button>
                <a class="btn btn-primary btn-sm" href="{{ route('admin.fiches.pdf', $detail['id']) }}" target="_blank" rel="noopener"><i class="bi bi-filetype-pdf me-1" aria-hidden="true"></i>{{ __('admin_data.forms.actions.pdf') }}</a>
            @endif
        </x-slot:footer>
    </x-admin.modal>
</div>
