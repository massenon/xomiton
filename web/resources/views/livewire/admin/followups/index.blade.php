{{--
    Suivi des victimes (P11-03) : composant App\Livewire\Admin\Followups\Index, gabarit admin. KPI · colonne principale (frise
    d'un dossier, liste de toutes les étapes) · colonne latérale (ajouter une étape, en retard, prochains suivis).
    Les commentaires (chiffrés) ne se lisent que dans la frise d'un dossier choisi ; jamais dans la liste ni l'export.
--}}
@php
    $stats = $this->stats;
    $timeline = $this->timeline;
    $badgeColor = static fn (\App\Enums\FollowupOutcome $outcome): string => $outcome->badge();
@endphp
<div class="xm-followups">
    <p class="text-secondary mb-4 xm-prose">{{ __('admin_data.followups.intro') }}</p>

    <x-admin.kpi id="xmFollowupsKpis" :items="[
        ['label' => __('admin_data.followups.kpi.done'), 'value' => $format->percent($stats['percent']), 'sub' => __('admin_data.followups.kpi.done_sub', ['done' => $format->number($stats['done']), 'total' => $format->number($stats['total'])]), 'icon' => 'check2-circle', 'variant' => 'success'],
        ['label' => __('admin_data.followups.kpi.overdue'), 'value' => $format->number($stats['overdue']), 'sub' => __('admin_data.followups.kpi.overdue_sub'), 'icon' => 'exclamation-triangle', 'variant' => 'danger'],
        ['label' => __('admin_data.followups.kpi.gap'), 'value' => __('admin_data.followups.kpi.days', ['count' => $format->number($stats['gap_days'])]), 'sub' => __('admin_data.followups.kpi.gap_sub', ['days' => $format->number($stats['first_days'])]), 'icon' => 'hourglass-split'],
        ['label' => __('admin_data.followups.kpi.cases'), 'value' => $format->number($stats['cases']), 'sub' => trans_choice('admin_data.followups.kpi.upcoming', $stats['upcoming'], ['count' => $format->number($stats['upcoming'])]), 'icon' => 'person-heart', 'variant' => 'violet'],
    ]" />

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="card xm-admin-card mb-4" aria-labelledby="xmFollowupsCaseTitle">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-3" id="xmFollowupsCaseTitle">{{ __('admin_data.followups.case_title') }}</h2>
                    <form class="row g-2 align-items-start" wire:submit="showCase" novalidate>
                        <div class="col-sm-8 col-md-6">
                            <label class="form-label small mb-1" for="xmFollowupsCase">{{ __('admin_data.followups.case_label') }}</label>
                            <input class="form-control form-control-sm @error('caseInput') is-invalid @enderror" id="xmFollowupsCase" list="xmFollowupsCodes" wire:model="caseInput" maxlength="12" autocomplete="off" placeholder="VBG-0000"
                                   @error('caseInput') aria-invalid="true" aria-describedby="xmFollowupsCaseError" @enderror>
                            <datalist id="xmFollowupsCodes">@foreach ($caseCodes as $code)<option value="{{ $code }}">@endforeach</datalist>
                            @error('caseInput')<div class="invalid-feedback d-block" id="xmFollowupsCaseError">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-4 col-md-auto d-flex gap-2 align-items-end" style="padding-top: 1.55rem">
                            <button type="submit" class="btn btn-sm btn-primary" wire:loading.attr="disabled" wire:target="showCase">{{ __('admin_data.followups.show') }}</button>
                            @if ($dossier !== '')
                                <button type="button" class="btn btn-sm btn-light border" wire:click="closeCase">{{ __('admin_data.followups.hide') }}</button>
                            @endif
                        </div>
                    </form>
                </div>
            </section>

            @if ($timeline)
                @php($case = $timeline['case'])
                <section class="card xm-admin-card mb-4" aria-labelledby="xmFollowupsTimelineTitle">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h2 class="h6 fw-bold mb-0" id="xmFollowupsTimelineTitle">{{ __('admin_data.followups.timeline_title', ['code' => $case->code]) }}</h2>
                        @can('case_forms.manage')
                            <a class="small" href="{{ route('admin.fiches', ['dossier' => $case->code]) }}">{{ __('admin_data.followups.form_link') }}</a>
                        @endcan
                    </div>
                    <div class="card-body">
                        <p class="small text-secondary mb-3">{{ $case->kind->value }} · {{ $case->sex->label() }}, {{ $case->age_range->label() }} · {{ $case->population?->label }}@if ($case->commune) · {{ $case->commune->name }}@endif · {{ __('admin_data.followups.received', ['date' => $format->shortDate($case->occurred_on)]) }}</p>
                        <x-timeline :label="__('admin_data.followups.timeline_label', ['code' => $case->code])" :empty="__('admin_data.followups.timeline_empty')" :items="$timeline['steps']->map(fn ($step) => [
                            'title' => $step->step,
                            'date' => $format->shortDate($step->date),
                            'datetime' => $step->date?->format('Y-m-d'),
                            'by' => $step->author?->pseudo,
                            'badge' => ['label' => $step->outcome->label(), 'color' => $step->outcome->badge()],
                            'text' => $step->comment,
                            'next' => $step->next_on ? $format->shortDate($step->next_on) : null,
                            'late' => \App\Application\Cases\FollowupBoard::isLate($step),
                            'action' => $step->outcome->isPending() ? ['label' => __('admin_data.followups.mark_done'), 'wire' => 'markDone('.$step->id.')', 'sr' => $step->step] : null,
                        ])->all()" />
                    </div>
                </section>
            @endif

            <section class="card xm-admin-card" aria-labelledby="xmFollowupsListTitle">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h2 class="h6 fw-bold mb-0" id="xmFollowupsListTitle" role="status">{{ trans_choice('admin_data.followups.count', $rows->total(), ['count' => $format->number($rows->total())]) }}</h2>
                    @if ($canExport)
                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="export" wire:loading.attr="disabled" wire:target="export">
                            <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>{{ __('admin.export.csv_filtered', ['count' => $format->number($rows->total())]) }}
                        </button>
                    @endif
                </div>
                <div class="card-body border-bottom">
                    <x-admin.filters id="xmFollowupsFilters" :active="$this->activeFilterCount()" :placeholder="__('admin_data.followups.search_placeholder')" :search-help="__('admin_data.followups.search_help')">
                <div class="w-100 d-none d-lg-block" aria-hidden="true"></div>
                        <div class="col-6 col-md-3">
                            <label class="visually-hidden" for="xmFollowupsOutcome">{{ __('admin_data.followups.columns.outcome') }}</label>
                            <select class="form-select form-select-sm" id="xmFollowupsOutcome" wire:model.live="outcome">
                                <option value="">{{ __('admin_data.followups.filters.all_outcomes') }}</option>
                                @foreach ($outcomes as $option)
                                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-auto d-flex align-items-center">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="xmFollowupsLate" wire:model.live="late">
                                <label class="form-check-label small" for="xmFollowupsLate">{{ __('admin_data.followups.filters.late_only') }}</label>
                            </div>
                        </div>
                    </x-admin.filters>
                </div>
                <x-admin.table id="xmFollowupsTable" :caption="__('admin_data.followups.table_caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
                    ['key' => 'dossier', 'label' => __('admin_data.followups.columns.case'), 'sortable' => true],
                    ['key' => 'etape', 'label' => __('admin_data.followups.columns.step'), 'sortable' => true],
                    ['key' => 'date', 'label' => __('admin_data.followups.columns.date'), 'sortable' => true],
                    ['key' => 'by', 'label' => __('admin_data.followups.columns.by')],
                    ['key' => 'outcome', 'label' => __('admin_data.followups.columns.outcome')],
                    ['key' => 'prochaine', 'label' => __('admin_data.followups.columns.next'), 'sortable' => true],
                ]">
                    @foreach ($rows as $step)
                        @php($isLate = \App\Application\Cases\FollowupBoard::isLate($step))
                        <tr wire:key="step-{{ $step->id }}" @class(['table-danger' => $isLate])>
                            <td data-label="{{ __('admin_data.followups.columns.case') }}"><button type="button" class="btn btn-link btn-sm p-0 fw-semibold" wire:click="showCase('{{ $step->case?->code }}')">{{ $step->case?->code }}</button></td>
                            <td data-label="{{ __('admin_data.followups.columns.step') }}">{{ $step->step }}</td>
                            <td data-label="{{ __('admin_data.followups.columns.date') }}" class="text-nowrap"><time class="small" datetime="{{ $step->date?->format('Y-m-d') }}">{{ $format->shortDate($step->date) }}</time></td>
                            <td data-label="{{ __('admin_data.followups.columns.by') }}" class="small">{{ $step->author?->pseudo ?? '—' }}</td>
                            <td data-label="{{ __('admin_data.followups.columns.outcome') }}"><x-badge :color="$badgeColor($step->outcome)" soft>{{ $step->outcome->label() }}</x-badge></td>
                            <td data-label="{{ __('admin_data.followups.columns.next') }}" @class(['text-nowrap small', 'text-danger fw-semibold' => $isLate])>
                                @if ($step->next_on)<time datetime="{{ $step->next_on->format('Y-m-d') }}">{{ $format->shortDate($step->next_on) }}</time>@if ($isLate) <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i><span class="visually-hidden">{{ __('components.timeline.late') }}</span>@endif @else — @endif
                            </td>
                        </tr>
                    @endforeach
                    <x-slot:emptyState>
                        <x-admin.empty icon="person-heart" :filtered="$this->activeFilterCount() > 0" :title="__('admin_data.followups.empty')">{{ __('admin_data.followups.empty_text') }}</x-admin.empty>
                    </x-slot:emptyState>
                    <x-slot:footer>
                        <x-admin.pagination id="xmFollowupsPager" :paginator="$rows" />
                    </x-slot:footer>
                </x-admin.table>
            </section>
        </div>

        <div class="col-xl-4">
            <section class="card xm-admin-card mb-4" aria-labelledby="xmFollowupsAddTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmFollowupsAddTitle">{{ __('admin_data.followups.add_title') }}</h2></div>
                <div class="card-body">
                    <form wire:submit="add" novalidate>
                        <div class="mb-2">
                            <label class="form-label" for="xmFollowupsFormCase">{{ __('admin_data.followups.fields.case_code') }}</label>
                            <input class="form-control @error('form.case_code') is-invalid @enderror" id="xmFollowupsFormCase" list="xmFollowupsCodes" wire:model="form.case_code" maxlength="12" autocomplete="off" placeholder="VBG-0000" required
                                   @error('form.case_code') aria-invalid="true" aria-describedby="xmFollowupsFormCaseError" @enderror>
                            @error('form.case_code')<div class="invalid-feedback d-block" id="xmFollowupsFormCaseError">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="xmFollowupsFormStep">{{ __('admin_data.followups.fields.step') }}</label>
                            <select class="form-select @error('form.step') is-invalid @enderror" id="xmFollowupsFormStep" wire:model="form.step" required @error('form.step') aria-invalid="true" aria-describedby="xmFollowupsFormStepError" @enderror>
                                @foreach ($steps as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                            </select>
                            @error('form.step')<div class="invalid-feedback d-block" id="xmFollowupsFormStepError">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label" for="xmFollowupsFormDate">{{ __('admin_data.followups.fields.date') }}</label>
                                <input class="form-control @error('form.date') is-invalid @enderror" type="date" id="xmFollowupsFormDate" wire:model="form.date" max="{{ $today }}" required @error('form.date') aria-invalid="true" aria-describedby="xmFollowupsFormDateError" @enderror>
                                @error('form.date')<div class="invalid-feedback d-block" id="xmFollowupsFormDateError">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="xmFollowupsFormOutcome">{{ __('admin_data.followups.fields.outcome') }}</label>
                                <select class="form-select @error('form.outcome') is-invalid @enderror" id="xmFollowupsFormOutcome" wire:model="form.outcome" required @error('form.outcome') aria-invalid="true" aria-describedby="xmFollowupsFormOutcomeError" @enderror>
                                    @foreach ($outcomes as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach
                                </select>
                                @error('form.outcome')<div class="invalid-feedback d-block" id="xmFollowupsFormOutcomeError">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="xmFollowupsFormNext">{{ __('admin_data.followups.fields.next_on') }}</label>
                            <input class="form-control @error('form.next_on') is-invalid @enderror" type="date" id="xmFollowupsFormNext" wire:model="form.next_on" @error('form.next_on') aria-invalid="true" aria-describedby="xmFollowupsFormNextError" @enderror>
                            @error('form.next_on')<div class="invalid-feedback d-block" id="xmFollowupsFormNextError">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="xmFollowupsFormComment">{{ __('admin_data.followups.fields.comment') }}</label>
                            <textarea class="form-control @error('form.comment') is-invalid @enderror" id="xmFollowupsFormComment" rows="2" maxlength="{{ \App\Domain\Cases\FollowupStep::COMMENT_MAX_LENGTH }}" wire:model="form.comment" aria-describedby="xmFollowupsFormCommentHint"
                                      @error('form.comment') aria-invalid="true" @enderror></textarea>
                            <div class="form-text" id="xmFollowupsFormCommentHint">{{ __('admin_data.followups.comment_hint') }}</div>
                            @error('form.comment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled" wire:target="add"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_data.followups.add') }}</button>
                    </form>
                </div>
            </section>

            <section class="card xm-admin-card mb-4" aria-labelledby="xmFollowupsLateTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0 text-danger" id="xmFollowupsLateTitle"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ __('admin_data.followups.late_title', ['count' => $format->number($stats['overdue'])]) }}</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse ($lateSteps as $step)
                        <li class="list-group-item d-flex justify-content-between gap-2" wire:key="late-{{ $step->id }}">
                            <button type="button" class="btn btn-link btn-sm p-0 text-start" wire:click="showCase('{{ $step->case?->code }}')">{{ $step->case?->code }}</button>
                            <span class="small text-danger text-end">{{ $step->step }} · {{ $format->dayMonthShort($step->next_on) }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-secondary">{{ __('admin_data.followups.no_late') }}</li>
                    @endforelse
                </ul>
            </section>

            <section class="card xm-admin-card" aria-labelledby="xmFollowupsSoonTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmFollowupsSoonTitle"><i class="bi bi-calendar3 me-1" aria-hidden="true"></i>{{ __('admin_data.followups.soon_title') }}</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse ($soonSteps as $step)
                        <li class="list-group-item d-flex align-items-center gap-3" wire:key="soon-{{ $step->id }}">
                            <div class="text-center border rounded px-2 py-1" style="min-width: 3.25rem" aria-hidden="true">
                                <div class="fw-bold lh-1">{{ $step->next_on?->format('d') }}</div>
                                <div class="small text-secondary">{{ $format->monthName((int) $step->next_on?->format('n'), true) }}</div>
                            </div>
                            <div class="min-w-0">
                                <button type="button" class="btn btn-link btn-sm p-0 fw-semibold" wire:click="showCase('{{ $step->case?->code }}')">{{ $step->case?->code }}</button>
                                <div class="small text-secondary">{{ $step->step }}@if ($step->author) · {{ $step->author->pseudo }}@endif</div>
                                <span class="visually-hidden">{{ __('admin_data.followups.on_date', ['date' => $format->shortDate($step->next_on)]) }}</span>
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-secondary">{{ __('admin_data.followups.no_soon') }}</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
</div>
