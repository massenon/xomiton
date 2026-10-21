{{--
    Règles de validation (P11-05) : composant App\Livewire\Admin\Validation\Index, gabarit admin. Introduction · KPI · colonne
    principale (catalogue, bac à sable) · colonne latérale (doublons suspects, plausibilité) · rapport de qualité. Les règles
    verrouillées (`required`, `password_strong`) ont un interrupteur grisé et toujours actif.
--}}
@php
    $lockedKeys = array_map(static fn (\App\Domain\Cases\ValidationRuleKey $key): string => $key->value, $locked);
    $fieldLabels = (array) __('admin_data.validation.duplicates.fields');
    $missingLabels = (array) __('admin_data.validation.report.fields');
    $reportHasCases = $report['cases'] > 0;
@endphp
<div class="xm-validation">
    <p class="text-secondary mb-4 xm-prose">{{ __('admin_data.validation.intro') }}</p>

    <x-admin.kpi id="xmValidationKpis" :items="[
        ['label' => __('admin_data.validation.kpi.rules'), 'value' => $activeCount.' / '.($rules->count() + 2), 'icon' => 'patch-check', 'variant' => 'success'],
        ['label' => __('admin_data.validation.kpi.completeness'), 'value' => $reportHasCases ? $format->percent($report['completeness'], 1) : '—', 'sub' => trans_choice('admin_data.validation.kpi.cases', $report['cases'], ['count' => $format->number($report['cases'])]), 'icon' => 'clipboard-check'],
        ['label' => __('admin_data.validation.kpi.duplicates'), 'value' => $format->number($report['duplicate_groups']), 'sub' => trans_choice('admin_data.validation.kpi.duplicate_cases', $report['duplicate_cases'], ['count' => $format->number($report['duplicate_cases'])]), 'icon' => 'copy', 'variant' => 'warning'],
        ['label' => __('admin_data.validation.kpi.score'), 'value' => $reportHasCases ? $report['score'].' / 100' : '—', 'icon' => 'speedometer', 'variant' => $report['score'] >= 80 || ! $reportHasCases ? 'success' : 'danger'],
    ]" />

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="card xm-admin-card mb-4" aria-labelledby="xmValidationCatalogTitle">
                <form wire:submit="saveRules" novalidate>
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h2 class="h6 fw-bold mb-0" id="xmValidationCatalogTitle">{{ __('admin_data.validation.catalog_title') }}</h2>
                        <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="saveRules"><i class="bi bi-check2 me-1" aria-hidden="true"></i>{{ __('admin_data.validation.save') }}</button>
                    </div>
                    <div class="xm-table-wrap xm-admin-table">
                        <table class="table align-middle mb-0 xm-table--stack" id="xmValidationTable">
                            <caption class="visually-hidden">{{ __('admin_data.validation.table_caption') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col" style="width: 5rem">{{ __('admin_data.validation.columns.active') }}</th>
                                    <th scope="col">{{ __('admin_data.validation.columns.rule') }}</th>
                                    <th scope="col">{{ __('admin_data.validation.columns.forms') }}</th>
                                    <th scope="col"><span class="visually-hidden">{{ __('admin_data.validation.columns.actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rules as $rule)
                                    @php($isLocked = $rule->is_locked || in_array($rule->key, $lockedKeys, true))
                                    <tr wire:key="rule-{{ $rule->key }}">
                                        <td data-label="{{ __('admin_data.validation.columns.active') }}">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" role="switch" id="xmRule-{{ $rule->key }}" wire:model="active.{{ $rule->key }}" @checked($isLocked || ($active[$rule->key] ?? false)) @disabled($isLocked)>
                                                <label class="form-check-label visually-hidden" for="xmRule-{{ $rule->key }}">{{ __('admin_data.validation.toggle', ['rule' => $rule->label]) }}</label>
                                            </div>
                                        </td>
                                        <td data-label="{{ __('admin_data.validation.columns.rule') }}">
                                            <div class="min-w-0">
                                                <span class="fw-semibold">{{ $rule->label }}</span> <code class="small">{{ $rule->key }}</code>
                                                @if ($isLocked)<x-badge color="neutral" icon="lock" class="ms-1">{{ __('admin_data.validation.locked') }}</x-badge>@endif
                                                <span class="d-block small text-secondary">{{ $rule->description }}</span>
                                                <span class="d-block small mt-1">
                                                    @if ($rule->valid_example)<span class="text-success"><i class="bi bi-check2" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_data.validation.valid_example') }}</span> {{ $rule->valid_example }}</span>@endif
                                                    @if ($rule->invalid_example) · <span class="text-danger"><i class="bi bi-x" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_data.validation.invalid_example') }}</span> {{ $rule->invalid_example }}</span>@endif
                                                </span>
                                            </div>
                                        </td>
                                        <td data-label="{{ __('admin_data.validation.columns.forms') }}">
                                            @foreach ((array) $rule->param('forms', []) as $form)<x-badge color="neutral" class="me-1 mb-1">{{ $form }}</x-badge>@endforeach
                                        </td>
                                        <td data-label="{{ __('admin_data.validation.columns.actions') }}" class="text-end xm-table__actions">
                                            @if (in_array($rule->key, array_map(static fn ($k) => $k->value, $sandboxRules), true))
                                                <button type="button" class="btn btn-sm btn-light border" wire:click="tryRule('{{ $rule->key }}')">{{ __('admin_data.validation.try') }}<span class="visually-hidden"> {{ $rule->label }}</span></button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer small text-secondary">{{ __('admin_data.validation.catalog_note') }}</div>
                </form>
            </section>

            <section class="card xm-admin-card mb-4" aria-labelledby="xmValidationSandboxTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmValidationSandboxTitle"><i class="bi bi-flask me-2" aria-hidden="true"></i>{{ __('admin_data.validation.sandbox.title') }}</h2></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label" for="xmSandboxRule">{{ __('admin_data.validation.sandbox.rule') }}</label>
                            <select class="form-select" id="xmSandboxRule" wire:model.live="sandboxRule">
                                @foreach ($sandboxRules as $key)<option value="{{ $key->value }}">{{ $rules->firstWhere('key', $key->value)?->label ?? $key->value }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label" for="xmSandboxValue">{{ __('admin_data.validation.sandbox.value') }}</label>
                            <input class="form-control" id="xmSandboxValue" wire:model.live.debounce.300ms="sandboxValue" maxlength="500" autocomplete="off" placeholder="{{ __('admin_data.validation.sandbox.placeholder') }}" aria-describedby="xmSandboxResult">
                        </div>
                        <div class="col-12">
                            @php($status = $sandboxResult['status'])
                            <div id="xmSandboxResult" role="status" aria-live="polite" @class(['alert mb-0', 'alert-success' => $status === 'valid', 'alert-danger' => $status === 'invalid', 'alert-warning' => $status === 'disabled', 'alert-secondary' => ! in_array($status, ['valid', 'invalid', 'disabled'], true)])>
                                @if ($status === 'valid')<i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ __('admin_data.validation.sandbox.valid') }}
                                @elseif ($status === 'invalid')<i class="bi bi-x-circle me-1" aria-hidden="true"></i>{{ $sandboxResult['message'] }}
                                @elseif ($status === 'disabled'){{ __('admin_data.validation.sandbox.disabled') }}
                                @elseif ($status === 'empty'){{ __('admin_data.validation.sandbox.empty') }}
                                @else{{ __('admin_data.validation.sandbox.hint') }}@endif
                            </div>
                        </div>
                    </div>
                    <p class="form-text mt-2 mb-0">{{ __('admin_data.validation.sandbox.not_testable') }}</p>
                </div>
            </section>
        </div>

        <div class="col-xl-4">
            <section class="card xm-admin-card mb-4" aria-labelledby="xmValidationDupTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmValidationDupTitle">{{ __('admin_data.validation.duplicates.title') }}</h2></div>
                <form class="card-body" wire:submit="saveDuplicates" novalidate>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="xmDupOn" wire:model="duplicates.is_active">
                        <label class="form-check-label" for="xmDupOn">{{ __('admin_data.validation.duplicates.enabled') }}</label>
                    </div>
                    <fieldset>
                        <legend class="small text-secondary mb-2">{{ __('admin_data.validation.duplicates.legend') }}</legend>
                        @foreach ($availableFields as $field)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="xmDupField-{{ $field }}" value="{{ $field }}" wire:model="duplicates.fields">
                                <label class="form-check-label" for="xmDupField-{{ $field }}">{{ $fieldLabels[$field] ?? $field }}</label>
                            </div>
                        @endforeach
                        @error('duplicates.fields')<div class="text-danger small mt-2" role="alert"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $message }}</div>@enderror
                    </fieldset>
                    <button type="submit" class="btn btn-primary btn-sm mt-3" wire:loading.attr="disabled" wire:target="saveDuplicates">{{ __('admin_data.validation.save_short') }}</button>
                </form>
            </section>

            <section class="card xm-admin-card mb-4" aria-labelledby="xmValidationPlausTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmValidationPlausTitle">{{ __('admin_data.validation.plausibility.title') }}</h2></div>
                <form class="card-body" wire:submit="savePlausibility" novalidate>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="xmPlausOn" wire:model="plausibility.is_active">
                        <label class="form-check-label" for="xmPlausOn">{{ __('admin_data.validation.plausibility.enabled') }}</label>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small" for="xmPlausMin">{{ __('admin_data.validation.plausibility.fields.age_min') }}</label>
                            <input class="form-control form-control-sm @error('plausibility.age_min') is-invalid @enderror" id="xmPlausMin" inputmode="numeric" wire:model="plausibility.age_min" @error('plausibility.age_min') aria-invalid="true" aria-describedby="xmPlausMinError" @enderror>
                            @error('plausibility.age_min')<div class="invalid-feedback d-block" id="xmPlausMinError">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label small" for="xmPlausMax">{{ __('admin_data.validation.plausibility.fields.age_max') }}</label>
                            <input class="form-control form-control-sm @error('plausibility.age_max') is-invalid @enderror" id="xmPlausMax" inputmode="numeric" wire:model="plausibility.age_max" @error('plausibility.age_max') aria-invalid="true" aria-describedby="xmPlausMaxError" @enderror>
                            @error('plausibility.age_max')<div class="invalid-feedback d-block" id="xmPlausMaxError">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <label class="form-label small" for="xmPlausYears">{{ __('admin_data.validation.plausibility.fields.max_past_years') }}</label>
                    <input class="form-control form-control-sm mb-3 @error('plausibility.max_past_years') is-invalid @enderror" id="xmPlausYears" inputmode="numeric" wire:model="plausibility.max_past_years" @error('plausibility.max_past_years') aria-invalid="true" aria-describedby="xmPlausYearsError" @enderror>
                    @error('plausibility.max_past_years')<div class="invalid-feedback d-block mb-2" id="xmPlausYearsError">{{ $message }}</div>@enderror
                    <table class="table table-sm small mb-3">
                        <caption class="visually-hidden">{{ __('admin_data.validation.plausibility.effect') }}</caption>
                        <tbody>
                            <tr><th scope="row" class="fw-normal">{{ __('admin_data.validation.plausibility.age') }}</th><td>{{ __('admin_data.validation.plausibility.age_value', ['min' => $plausibilityEffect->ageMin, 'max' => $plausibilityEffect->ageMax]) }}</td></tr>
                            <tr><th scope="row" class="fw-normal">{{ __('admin_data.validation.plausibility.future') }}</th><td>{{ trans_choice('admin_data.validation.plausibility.future_value', $report['future'], ['count' => $format->number($report['future'])]) }}</td></tr>
                            <tr><th scope="row" class="fw-normal">{{ __('admin_data.validation.plausibility.old', ['years' => $plausibilityEffect->maxPastYears]) }}</th><td>{{ trans_choice('admin_data.validation.plausibility.old_value', $report['old'], ['count' => $format->number($report['old'])]) }}</td></tr>
                            <tr><th scope="row" class="fw-normal">{{ __('admin_data.validation.plausibility.gps') }}</th><td>{{ __('admin_data.validation.plausibility.gps_value', ['lat0' => $plausibilityEffect->lat[0], 'lat1' => $plausibilityEffect->lat[1], 'lng0' => $plausibilityEffect->lng[0], 'lng1' => $plausibilityEffect->lng[1]]) }}</td></tr>
                        </tbody>
                    </table>
                    <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" wire:target="savePlausibility">{{ __('admin_data.validation.save_short') }}</button>
                </form>
            </section>
        </div>
    </div>

    <section class="card xm-admin-card mt-2" aria-labelledby="xmValidationReportTitle">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h6 fw-bold mb-0" id="xmValidationReportTitle">{{ trans_choice('admin_data.validation.report.title', $report['cases'], ['count' => $format->number($report['cases'])]) }}</h2>
            <button type="button" class="btn btn-outline-secondary btn-sm" x-data x-on:click="window.print()"><i class="bi bi-printer me-1" aria-hidden="true"></i>{{ __('admin_data.validation.report.print') }}</button>
        </div>
        <div class="card-body">
            @if (! $reportHasCases)
                <x-empty-state compact icon="clipboard-check" :title="__('admin_data.validation.report.empty')">{{ __('admin_data.validation.report.empty_text') }}</x-empty-state>
            @else
                <div class="row g-4">
                    <div class="col-lg-5">
                        <h3 class="h6">{{ __('admin_data.validation.report.missing') }}</h3>
                        @foreach ($report['missing'] as $field => $count)
                            @php($percent = round($count / max(1, $report['cases']) * 100, 1))
                            <div class="mb-2">
                                <div class="d-flex justify-content-between small"><span>{{ $missingLabels[$field] ?? $field }}</span><span>{{ $format->number($count) }} · {{ $format->percent($percent, 1) }}</span></div>
                                <div class="progress" style="height: 6px" role="progressbar" aria-label="{{ $missingLabels[$field] ?? $field }}" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                                    <div @class(['progress-bar', 'bg-danger' => $percent > 10, 'bg-warning' => $percent > 3 && $percent <= 10, 'bg-success' => $percent <= 3]) style="width: {{ max(2, $percent) }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-lg-7">
                        <h3 class="h6">{{ __('admin_data.validation.report.duplicates') }}</h3>
                        @if ($report['duplicates'] === [])
                            <p class="text-success mb-0"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ __('admin_data.validation.report.no_duplicates') }}</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <caption class="visually-hidden">{{ __('admin_data.validation.report.duplicates_caption') }}</caption>
                                    <thead><tr><th scope="col">{{ __('admin_data.validation.report.cases') }}</th><th scope="col">{{ __('admin_data.validation.report.shared') }}</th></tr></thead>
                                    <tbody>
                                        @foreach ($report['duplicates'] as $group)
                                            <tr>
                                                <td>{{ implode(', ', $group['codes']) }}@if ($group['more'] > 0) {{ __('admin_data.validation.report.more', ['count' => $group['more']]) }}@endif</td>
                                                <td class="small">{{ implode(' · ', $group['values']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if ($report['duplicate_groups'] > count($report['duplicates']))
                                <p class="small text-secondary mb-0">{{ __('admin_data.validation.report.more_groups', ['count' => $report['duplicate_groups'] - count($report['duplicates'])]) }}</p>
                            @endif
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </section>
</div>
