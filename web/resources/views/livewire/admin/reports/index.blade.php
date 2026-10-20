{{--
    Rapports (P10-07) : composant App\Livewire\Admin\Reports\Index, gabarit admin ; reprend admin/rapports.php.
    Colonne gauche : assistant « Paramètres du rapport » et « Planification automatique » ; colonne droite : aperçu à
    l'écran, puis historique (suivi de l'état toutes les 5 s tant qu'un rapport est en cours, téléchargement par lien
    signé). Cellule `null` = effectif de 1 à 4 : « données masquées ».
--}}
@php
    $masked = (string) __('steering.reports.masked');
    $sectionKeys = \App\Application\Steering\Reports\ReportCatalog::SECTIONS;
    $dimensionKeys = \App\Application\Steering\Reports\ReportDimensions::KEYS;
    $statusColor = ['pending' => 'neutral', 'running' => 'info', 'done' => 'success', 'failed' => 'danger'];
@endphp
<div class="xm-reports" @if ($hasUnfinished) wire:poll.5s @endif>
    <p class="text-secondary xm-prose mb-4">{{ __('steering.reports.intro') }}</p>

    <div class="row g-4">
        <div class="col-xl-4">
            <section class="card xm-admin-card mb-4" aria-labelledby="xmRepWizardTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmRepWizardTitle"><i class="bi bi-sliders me-1" aria-hidden="true"></i>{{ __('steering.reports.wizard.title') }}</h2></div>
                <div class="card-body">
                    <form wire:submit="generate" novalidate id="xmRepForm">
                        <div class="mb-3">
                            <label class="form-label" for="xmRepKind">{{ __('steering.reports.fields.kind') }}</label>
                            <select class="form-select @error('kind') is-invalid @enderror" id="xmRepKind" wire:model.live="kind">
                                @foreach ($kinds as $k)<option value="{{ $k->value }}">{{ $k->label() }}</option>@endforeach
                            </select>
                            @error('kind')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="xmRepFrom">{{ __('steering.reports.fields.from') }}</label>
                                <input class="form-control @error('from') is-invalid @enderror" type="date" id="xmRepFrom" wire:model.live="from" @error('from') aria-invalid="true" @enderror>
                                @error('from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="xmRepUntil">{{ __('steering.reports.fields.until') }}</label>
                                <input class="form-control @error('until') is-invalid @enderror" type="date" id="xmRepUntil" wire:model.live="until" @error('until') aria-invalid="true" aria-describedby="xmRepUntilError" @enderror>
                            </div>
                            @error('until')<div class="col-12"><p class="small text-danger mb-0" id="xmRepUntilError" role="alert"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $message }}</p></div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="xmRepTitle">{{ __('steering.reports.fields.title') }}</label>
                            <input class="form-control @error('title') is-invalid @enderror" id="xmRepTitle" maxlength="120" wire:model.blur="title" @error('title') aria-invalid="true" @enderror>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <fieldset class="mb-3">
                            <legend class="form-label fs-6">{{ __('steering.reports.fields.dimensions') }}</legend>
                            @foreach ($dimensionKeys as $key)
                                <div class="form-check"><input class="form-check-input" type="checkbox" value="{{ $key }}" id="xmRepDim-{{ $key }}" wire:model.live="dimensions"><label class="form-check-label" for="xmRepDim-{{ $key }}">{{ __('steering.reports.dimensions.'.$key) }}</label></div>
                            @endforeach
                        </fieldset>
                        <fieldset class="mb-3">
                            <legend class="form-label fs-6">{{ __('steering.reports.fields.sections') }}</legend>
                            @foreach ($sectionKeys as $key)
                                <div class="form-check"><input class="form-check-input" type="checkbox" value="{{ $key }}" id="xmRepSec-{{ $key }}" wire:model.live="sections"><label class="form-check-label" for="xmRepSec-{{ $key }}">{{ __('steering.reports.sections.'.$key) }}</label></div>
                            @endforeach
                        </fieldset>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="xmRepDetail" wire:model.live="detail" @disabled(! $canDetail) aria-describedby="xmRepDetailHelp">
                                <label class="form-check-label" for="xmRepDetail">{{ __('steering.reports.fields.detail') }}</label>
                            </div>
                            <div class="form-text" id="xmRepDetailHelp">{{ $canDetail ? __('steering.reports.fields.detail_help', ['pdf' => $format->number($pdfMax), 'excel' => $format->number($excelMax)]) : __('steering.reports.fields.detail_locked') }}</div>
                            @error('detail')<p class="small text-danger mt-1 mb-0" role="alert"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $message }}</p>@enderror
                        </div>
                        <fieldset class="mb-3">
                            <legend class="form-label fs-6">{{ __('steering.reports.fields.format') }}</legend>
                            @foreach ($formats as $f)
                                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="fileFormat" value="{{ $f->value }}" id="xmRepFmt-{{ $f->value }}" wire:model.live="fileFormat"><label class="form-check-label" for="xmRepFmt-{{ $f->value }}">{{ $f->label() }}</label></div>
                            @endforeach
                            @error('fileFormat')<p class="small text-danger mt-1 mb-0" role="alert"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ $message }}</p>@enderror
                        </fieldset>
                        <div class="mb-3">
                            <label class="form-label" for="xmRepRecipients">{{ __('steering.reports.fields.recipients') }}<span class="text-secondary fw-normal"> {{ __('steering.reports.optional') }}</span></label>
                            <textarea class="form-control form-control-sm @error('recipients') is-invalid @enderror" id="xmRepRecipients" rows="2" wire:model.blur="recipients" placeholder="{{ __('steering.reports.fields.recipients_placeholder') }}" aria-describedby="xmRepRecipientsHelp"></textarea>
                            <div class="form-text" id="xmRepRecipientsHelp">{{ __('steering.reports.fields.recipients_help') }}</div>
                            @error('recipients')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="generate"><i class="bi bi-lightning-charge me-1" aria-hidden="true"></i>{{ __('steering.reports.wizard.generate') }}</button>
                            <button type="button" class="btn btn-outline-secondary" wire:click="preview" wire:loading.attr="disabled" wire:target="preview"><i class="bi bi-eye me-1" aria-hidden="true"></i>{{ __('steering.reports.wizard.preview') }}</button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="card xm-admin-card" id="plan" aria-labelledby="xmRepPlanTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmRepPlanTitle"><i class="bi bi-calendar-check me-1" aria-hidden="true"></i>{{ __('steering.reports.plan.title') }}</h2></div>
                <div class="card-body">
                    <form wire:submit="saveSchedule" novalidate>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label" for="xmPlanKind">{{ __('steering.reports.fields.kind') }}</label>
                                <select class="form-select form-select-sm" id="xmPlanKind" wire:model="sKind">@foreach ($scheduleKinds as $k)<option value="{{ $k->value }}">{{ $k->label() }}</option>@endforeach</select>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="xmPlanFreq">{{ __('steering.reports.fields.frequency') }}</label>
                                <select class="form-select form-select-sm" id="xmPlanFreq" wire:model.live="sFrequency">@foreach (\App\Models\ReportSchedule::FREQUENCIES as $f)<option value="{{ $f }}">{{ __('steering.reports.frequencies.'.$f) }}</option>@endforeach</select>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="xmPlanDay">{{ $sFrequency === \App\Models\ReportSchedule::FREQUENCY_WEEKLY ? __('steering.reports.fields.weekday') : __('steering.reports.fields.day') }}</label>
                                <input class="form-control form-control-sm @error('sDay') is-invalid @enderror" type="number" min="1" max="{{ $sFrequency === \App\Models\ReportSchedule::FREQUENCY_WEEKLY ? 7 : 28 }}" id="xmPlanDay" wire:model.blur="sDay" @error('sDay') aria-invalid="true" @enderror>
                                @error('sDay')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-6">
                                <span class="form-label d-block" id="xmPlanFormatsLabel">{{ __('steering.reports.fields.formats') }}</span>
                                <div role="group" aria-labelledby="xmPlanFormatsLabel">
                                    @foreach ($formats as $f)
                                        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" value="{{ $f->value }}" id="xmPlanFmt-{{ $f->value }}" wire:model="sFormats"><label class="form-check-label small" for="xmPlanFmt-{{ $f->value }}">{{ $f->label() }}</label></div>
                                    @endforeach
                                </div>
                                @error('sFormats')<p class="small text-danger mb-0" role="alert">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <fieldset class="mb-2">
                            <legend class="form-label small mb-1">{{ __('steering.reports.fields.dimensions') }}</legend>
                            @foreach ($dimensionKeys as $key)
                                <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" value="{{ $key }}" id="xmPlanDim-{{ $key }}" wire:model="sDimensions"><label class="form-check-label small" for="xmPlanDim-{{ $key }}">{{ __('steering.reports.dimensions_short.'.$key) }}</label></div>
                            @endforeach
                        </fieldset>
                        <div class="mb-2">
                            <label class="form-label" for="xmPlanRecipients">{{ __('steering.reports.fields.recipients') }}</label>
                            <textarea class="form-control form-control-sm @error('sRecipients') is-invalid @enderror" id="xmPlanRecipients" rows="2" wire:model.blur="sRecipients" placeholder="{{ __('steering.reports.fields.recipients_placeholder') }}" required></textarea>
                            @error('sRecipients')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <p class="form-text">{{ __('steering.reports.plan.aggregates_only') }}</p>
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100" wire:loading.attr="disabled" wire:target="saveSchedule"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('steering.reports.plan.add') }}</button>
                    </form>
                    <hr>
                    @forelse ($schedules as $schedule)
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3 small" wire:key="plan-{{ $schedule->id }}">
                            <div class="min-w-0">
                                <x-badge :color="$schedule->is_active ? 'success' : 'neutral'" soft>{{ $schedule->is_active ? __('steering.reports.plan.active') : __('steering.reports.plan.paused') }}</x-badge>
                                <strong>{{ $schedule->kind->label() }}</strong> · {{ mb_strtolower(__('steering.reports.frequencies.'.$schedule->frequency)) }}, {{ $schedule->frequency === \App\Models\ReportSchedule::FREQUENCY_WEEKLY ? __('steering.reports.plan.weekday', ['day' => $format->dayName((int) $schedule->day_of_period)]) : __('steering.reports.plan.day', ['day' => $schedule->day_of_period]) }}
                                <div class="text-secondary text-break">{{ collect($schedule->formatList())->map(fn ($f) => $f->label())->implode(', ') }} → {{ implode(', ', (array) $schedule->recipients) }}</div>
                                @if ($schedule->is_active && $schedule->next_run_at)<div class="text-secondary">{{ __('steering.reports.plan.next', ['date' => $format->mediumDate($schedule->next_run_at)]) }}</div>@endif
                            </div>
                            <div class="text-nowrap">
                                <button type="button" class="btn btn-sm btn-light border" wire:click="toggleSchedule({{ $schedule->id }})"><i class="bi bi-{{ $schedule->is_active ? 'pause' : 'play' }}" aria-hidden="true"></i><span class="visually-hidden">{{ $schedule->is_active ? __('steering.reports.plan.pause') : __('steering.reports.plan.resume') }}</span></button>
                                <button type="button" class="btn btn-sm btn-light text-danger border" wire:click="deleteSchedule({{ $schedule->id }})" wire:confirm="{{ __('steering.reports.plan.confirm_delete') }}"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ __('steering.reports.plan.delete') }}</span></button>
                            </div>
                        </div>
                    @empty
                        <p class="small text-secondary mb-0">{{ __('steering.reports.plan.none') }}</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="col-xl-8">
            @if ($preview)
                <section class="card xm-admin-card mb-4" aria-labelledby="xmRepPreviewTitle">
                    <div class="card-body d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div><h2 class="h5 mb-0" id="xmRepPreviewTitle">{{ $preview->title }}</h2><div class="small text-secondary">{{ __('steering.reports.period') }} : {{ $preview->periodLabel }} · {{ __('steering.reports.generated_at') }} : {{ $preview->generatedAt }}</div></div>
                        <x-badge color="info" soft icon="eye">{{ __('steering.reports.preview_badge') }}</x-badge>
                    </div>
                    @if ($preview->hasMasked)<p class="small text-secondary px-3 mb-2"><i class="bi bi-eye-slash me-1" aria-hidden="true"></i>{{ __('steering.reports.masked_note', ['min' => \App\Support\SmallCellMask::MIN]) }}</p>@endif
                    <div class="px-3 pb-3">
                        @foreach ($preview->tables as $table)
                            <div class="table-responsive mb-3" wire:key="prev-{{ $loop->index }}">
                                <table class="table table-sm align-middle mb-0">
                                    <caption class="fw-semibold text-body">{{ $table['title'] }}</caption>
                                    <thead><tr>@foreach ($table['head'] as $label)<th scope="col" @class(['text-end' => ! $loop->first])>{{ $label }}</th>@endforeach</tr></thead>
                                    <tbody>
                                        @foreach ($table['rows'] as $row)
                                            <tr>
                                                @foreach ($row as $cell)
                                                    @if ($loop->first)<th scope="row" class="fw-normal">{{ $cell }}</th>
                                                    @elseif ($cell === null)<td class="text-end text-secondary fst-italic">{{ $masked }}</td>
                                                    @else<td class="text-end">{{ is_int($cell) ? $format->number($cell) : $cell }}</td>@endif
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                        @if ($preview->tables === [])<p class="text-secondary mb-0">{{ __('steering.reports.preview_empty') }}</p>@endif
                    </div>
                </section>
            @else
                <section class="card xm-admin-card mb-4"><div class="card-body text-center text-secondary py-5"><i class="bi bi-file-earmark-bar-graph display-5" aria-hidden="true"></i><p class="mt-2 mb-0">{{ __('steering.reports.preview_hint') }}</p></div></section>
            @endif

            <section class="card xm-admin-card" aria-labelledby="xmRepHistoryTitle">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h2 class="h6 fw-bold mb-0" id="xmRepHistoryTitle"><i class="bi bi-clock-history me-1" aria-hidden="true"></i>{{ __('steering.reports.history.title') }}</h2>
                    @if ($rows->total() > 0)
                        <button type="button" class="btn btn-sm btn-light border" wire:click="clearHistory" wire:confirm="{{ __('steering.reports.history.confirm_clear') }}" wire:loading.attr="disabled" wire:target="clearHistory">{{ __('steering.reports.history.clear') }}</button>
                    @endif
                </div>
                <div class="card-body border-bottom">
                    <x-admin.filters id="xmRepFilters" :active="$this->activeFilterCount()" :placeholder="__('steering.reports.history.search_placeholder')" :search-help="__('steering.reports.history.search_help')">
                        <div class="col-6 col-sm-4 col-lg-3">
                            <label class="visually-hidden" for="xmRepStatus">{{ __('steering.reports.history.columns.status') }}</label>
                            <select class="form-select form-select-sm" id="xmRepStatus" wire:model.live="status">
                                <option value="">{{ __('steering.reports.history.all_statuses') }}</option>
                                @foreach (\App\Enums\JobStatus::cases() as $s)<option value="{{ $s->value }}">{{ $s->label() }}</option>@endforeach
                            </select>
                        </div>
                    </x-admin.filters>
                </div>
                <x-admin.table id="xmRepHistory" :caption="__('steering.reports.history.caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
                    ['key' => 'date', 'label' => __('steering.reports.history.columns.date'), 'sortable' => true, 'width' => '10rem'],
                    ['key' => 'titre', 'label' => __('steering.reports.history.columns.report'), 'sortable' => true],
                    ['key' => 'periode', 'label' => __('steering.reports.history.columns.period'), 'sortable' => true],
                    ['key' => 'format', 'label' => __('steering.reports.history.columns.format')],
                    ['key' => 'statut', 'label' => __('steering.reports.history.columns.status'), 'sortable' => true],
                    ['key' => 'actions', 'label' => '', 'sr' => __('admin.alerts.columns.actions'), 'class' => 'text-end'],
                ]">
                    @foreach ($rows as $report)
                        <tr wire:key="rep-{{ $report->id }}">
                            <td data-label="{{ __('steering.reports.history.columns.date') }}" class="small text-nowrap"><time datetime="{{ $report->created_at?->toIso8601String() }}">{{ $format->tableDateTime($report->created_at) }}</time></td>
                            <td data-label="{{ __('steering.reports.history.columns.report') }}">
                                <div class="min-w-0"><span class="d-block fw-semibold text-break">{{ $report->title }}</span><span class="d-block small text-secondary">{{ $report->kind->label() }}@if ($report->generatedBy) · {{ $report->generatedBy->pseudo }}@else · {{ __('steering.reports.history.automatic') }}@endif</span></div>
                            </td>
                            <td data-label="{{ __('steering.reports.history.columns.period') }}" class="small">{{ $report->periodLabel() }}</td>
                            <td data-label="{{ __('steering.reports.history.columns.format') }}"><x-badge :color="$report->format->badge()" soft>{{ $report->format->label() }}</x-badge>@if ($report->file_size)<span class="d-block small text-secondary">{{ $format->fileSize($report->file_size) }}</span>@endif</td>
                            <td data-label="{{ __('steering.reports.history.columns.status') }}">
                                <div class="min-w-0">
                                    <x-badge :color="$statusColor[$report->status->value] ?? 'neutral'" soft>{{ $report->status->label() }}</x-badge>
                                    @if ($report->status === \App\Enums\JobStatus::Failed || $report->error)<span class="d-block small text-danger">{{ $report->error }}</span>@endif
                                    @if ($report->isExpired() && $report->status === \App\Enums\JobStatus::Done)<span class="d-block small text-secondary">{{ __('steering.reports.history.expired') }}</span>@endif
                                </div>
                            </td>
                            <td data-label="{{ __('admin.alerts.columns.actions') }}" class="text-end text-nowrap xm-table__actions">
                                @if (isset($links[$report->id]))
                                    <a class="btn btn-sm btn-primary" href="{{ $links[$report->id] }}"><i class="bi bi-download me-1" aria-hidden="true"></i>{{ __('steering.reports.history.download') }}<span class="visually-hidden"> {{ $report->title }}</span></a>
                                    @if (! empty($report->recipients) && ! $report->hasDetail())
                                        <button type="button" class="btn btn-sm btn-light border" wire:click="resend({{ $report->id }})" wire:loading.attr="disabled" wire:target="resend({{ $report->id }})"><i class="bi bi-envelope me-1" aria-hidden="true"></i>{{ __('steering.reports.history.resend') }}<span class="visually-hidden"> {{ $report->title }}</span></button>
                                    @endif
                                @endif
                                @if ($report->isFinished())
                                    <button type="button" class="btn btn-sm btn-light text-danger border" wire:click="deleteReport({{ $report->id }})" wire:confirm="{{ __('steering.reports.history.confirm_delete') }}"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ __('steering.reports.history.delete') }} {{ $report->title }}</span></button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    <x-slot:emptyState>
                        <x-admin.empty icon="file-earmark-bar-graph" :filtered="$this->activeFilterCount() > 0" :title="__('steering.reports.history.empty')">{{ __('steering.reports.history.empty_text') }}</x-admin.empty>
                    </x-slot:emptyState>
                    <x-slot:footer>
                        <x-admin.pagination id="xmRepPager" :paginator="$rows" />
                    </x-slot:footer>
                </x-admin.table>
            </section>
        </div>
    </div>
</div>
