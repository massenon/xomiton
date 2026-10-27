{{--
    Modération (P12-06, P12-09) : composant App\Livewire\Admin\Moderation\Index, gabarit admin.
    Ordre : introduction · KPI · file des contenus signalés (filtre statut, nature, tableau, pied) · colonne latérale (mots
    interdits, suspensions récentes, charte) · modales de décision et de lecture du message signalé, toujours montées.
    Un contenu = une ligne dans « À traiter » ; la décision vaut pour tous ses signalements en attente.
--}}
@php
    $kindIcon = ['publication' => 'card-text', 'commentaire' => 'chat-left-text', 'message' => 'envelope', 'utilisateur' => 'person-exclamation', 'sujet' => 'chat-square-text', 'reponse' => 'chat-left-dots'];
    $actionColor = ['aucune' => 'neutral', 'masque' => 'info', 'avertissement' => 'warning', 'suspension' => 'danger', 'supprime' => 'danger'];
@endphp
<div class="xm-moderation">
    <p class="text-secondary mb-4 xm-prose">{{ __('admin.moderation.intro') }}</p>

    <x-admin.kpi id="xmModKpis" :items="[
        ['label' => __('admin.moderation.kpi.pending'), 'value' => $format->number($this->stats['pending']), 'icon' => 'flag', 'variant' => 'danger'],
        ['label' => __('admin.moderation.kpi.handled'), 'value' => $format->number($this->stats['handled']), 'icon' => 'check2-all', 'variant' => 'success'],
        ['label' => __('admin.moderation.kpi.suspensions'), 'value' => $format->number($this->stats['suspensions']), 'icon' => 'person-slash', 'variant' => 'warning'],
        ['label' => __('admin.moderation.kpi.words'), 'value' => $format->number($this->stats['words']), 'icon' => 'funnel', 'variant' => 'violet'],
    ]" />

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="card xm-admin-card" aria-labelledby="xmModListTitle">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h2 class="h6 fw-bold mb-0" id="xmModListTitle">{{ __('admin.moderation.queue_title') }}</h2>
                    <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('admin.moderation.filter_label') }}">
                        @foreach (\App\Livewire\Admin\Moderation\Index::FILTERS as $key)
                            <button type="button" @class(['btn btn-outline-primary', 'active' => $filter === $key]) wire:click="$set('filter', '{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}">{{ __('admin.moderation.filters.'.$key) }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="card-body border-bottom">
                    <x-admin.filters id="xmModFilters" :active="$this->activeFilterCount()" :placeholder="__('admin.moderation.search_placeholder')" :search-help="__('admin.moderation.search_help')">
                        <div class="col-12 col-lg-3">
                            <label class="visually-hidden" for="xmModKind">{{ __('admin.moderation.columns.kind') }}</label>
                            <select class="form-select form-select-sm" id="xmModKind" wire:model.live="kind">
                                <option value="">{{ __('admin.moderation.all_kinds') }}</option>
                                @foreach (\App\Enums\ModerationKind::cases() as $case)
                                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </x-admin.filters>
                </div>

                <x-admin.table id="xmModTable" :caption="__('admin.moderation.table_caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
                    ['key' => 'contenu', 'label' => __('admin.moderation.columns.content')],
                    ['key' => 'motif', 'label' => __('admin.moderation.columns.reason'), 'sortable' => true],
                    ['key' => 'date', 'label' => __('admin.moderation.columns.date'), 'sortable' => true, 'width' => '9rem'],
                    ['key' => 'actions', 'label' => '', 'sr' => __('admin.datatable.actions'), 'class' => 'text-end'],
                ]">
                    @foreach ($rows as $report)
                        @php
                            $author = $report->reportedUser;
                            $subject = $report->subject;
                            $hidden = \App\Application\Moderation\ContentVisibility::isHidden($subject);
                            $group = $siblings[$report->subject_type.':'.$report->subject_id] ?? null;
                            $pending = $report->isPending();
                            $protected = $author !== null && $author->can('admin.access');
                            $strikes = $author ? ($warnings[$author->id] ?? 0) : 0;
                        @endphp
                        <tr wire:key="report-{{ $report->id }}">
                            <td data-label="{{ __('admin.moderation.columns.content') }}">
                                <div class="d-flex gap-2 min-w-0">
                                    <i class="bi bi-{{ $kindIcon[$report->kind->value] ?? 'flag' }} fs-5 text-primary" aria-hidden="true"></i>
                                    <div class="min-w-0">
                                        <div class="d-flex flex-wrap gap-1 mb-1">
                                            <x-badge :color="$report->kind->badge()" soft>{{ $report->kind->label() }}</x-badge>
                                            @unless ($pending)
                                                <x-badge :color="$report->status->badge()" soft>{{ $report->status->label() }}</x-badge>
                                                @if ($report->action)<x-badge :color="$actionColor[$report->action] ?? 'neutral'">{{ __('admin.moderation.action_labels.'.$report->action) }}</x-badge>@endif
                                            @endunless
                                            @if ($hidden)<x-badge color="warning" icon="eye-slash">{{ __('admin.moderation.hidden') }}</x-badge>@endif
                                        </div>
                                        <div class="text-break">{{ $report->excerpt ?: ($report->kind === \App\Enums\ModerationKind::Message ? __('admin.moderation.message_excerpt') : __('admin.moderation.no_excerpt')) }}</div>
                                        <div class="small text-secondary mt-1">
                                            {{ __('admin.moderation.author') }} <strong>{{ $author?->pseudo ?? __('admin.moderation.unknown_author') }}</strong>
                                            @if ($author?->status === \App\Enums\UserStatus::Suspended)<x-badge color="danger" soft>{{ __('admin.moderation.suspended') }}</x-badge>@endif
                                            @if ($strikes > 0) · {{ trans_choice('admin.moderation.warnings', $strikes, ['count' => $strikes]) }}@endif
                                            · {{ __('admin.moderation.reported_by_label') }}
                                            @if ($group && $group['people'] > 1){{ trans_choice('admin.moderation.reported_by', $group['people'], ['count' => $group['people']]) }}
                                            @else{{ $report->reporterLabel() }}@endif
                                            @if ($group && $group['reports'] > 1 && $group['people'] <= 1) · {{ trans_choice('admin.moderation.reports_count', $group['reports'], ['count' => $group['reports']]) }}@endif
                                        </div>
                                        @if ($report->details)<div class="small text-secondary fst-italic text-break mt-1">« {{ $report->details }} »</div>@endif
                                        @unless ($pending)
                                            <div class="small text-secondary mt-1">{{ __('admin.moderation.handled_by', ['name' => $report->handledBy?->pseudo ?? '—', 'date' => $format->tableDateTime($report->handled_at)]) }}</div>
                                        @endunless
                                    </div>
                                </div>
                            </td>
                            <td data-label="{{ __('admin.moderation.columns.reason') }}"><x-badge color="danger" soft>{{ $report->reason }}</x-badge></td>
                            <td data-label="{{ __('admin.moderation.columns.date') }}" class="text-nowrap"><time class="small" datetime="{{ $report->created_at?->toIso8601String() }}">{{ $format->ago($report->created_at) }}</time></td>
                            <td data-label="{{ __('admin.datatable.actions') }}" class="text-end xm-table__actions">
                                <div class="d-flex flex-wrap gap-1 justify-content-end">
                                    @if ($pending)
                                        @if ($report->kind === \App\Enums\ModerationKind::Message && $subject)
                                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openRead({{ $report->id }})" wire:loading.attr="disabled" wire:target="openRead({{ $report->id }})"><i class="bi bi-envelope-open me-1" aria-hidden="true"></i>{{ __('admin.moderation.read_message') }}</button>
                                        @endif
                                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="openDecision({{ $report->id }}, 'dismiss')">{{ __('admin.moderation.actions.dismiss') }}<span class="visually-hidden"> {{ __('admin.moderation.sr_report', ['id' => $report->id]) }}</span></button>
                                        @if (\App\Application\Moderation\ContentVisibility::hideable($subject) && ! $hidden)
                                            <button type="button" class="btn btn-sm btn-outline-info" wire:click="openDecision({{ $report->id }}, 'hide')">{{ __('admin.moderation.actions.hide') }}<span class="visually-hidden"> {{ __('admin.moderation.sr_report', ['id' => $report->id]) }}</span></button>
                                        @endif
                                        @if ($author)
                                            <button type="button" class="btn btn-sm btn-outline-warning" wire:click="openDecision({{ $report->id }}, 'warn')">{{ __('admin.moderation.actions.warn') }}<span class="visually-hidden"> {{ __('admin.moderation.sr_report', ['id' => $report->id]) }}</span></button>
                                            @if ($protected)
                                                <span class="btn btn-sm btn-outline-danger disabled" aria-disabled="true" title="{{ __('admin.moderation.errors.admin_target') }}"><i class="bi bi-lock me-1" aria-hidden="true"></i>{{ __('admin.moderation.actions.suspend') }}</span>
                                            @elseif ($author->status !== \App\Enums\UserStatus::Suspended)
                                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="openDecision({{ $report->id }}, 'suspend')">{{ __('admin.moderation.actions.suspend') }}<span class="visually-hidden"> {{ __('admin.moderation.sr_report', ['id' => $report->id]) }}</span></button>
                                            @endif
                                        @endif
                                    @else
                                        @if ($hidden && $subject && ! $subject instanceof \App\Models\Message)
                                            <button type="button" class="btn btn-sm btn-outline-success" wire:click="unhide({{ $report->id }})" wire:confirm="{{ __('admin.moderation.confirm.unhide') }}">{{ __('admin.moderation.actions.unhide') }}</button>
                                        @endif
                                        <button type="button" class="btn btn-sm btn-link" wire:click="reopen({{ $report->id }})" wire:confirm="{{ __('admin.moderation.confirm.reopen') }}">{{ __('admin.moderation.actions.reopen') }}<span class="visually-hidden"> {{ __('admin.moderation.sr_report', ['id' => $report->id]) }}</span></button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    <x-slot:emptyState>
                        <x-admin.empty icon="emoji-smile" :filtered="$this->activeFilterCount() > 0" :title="__('admin.moderation.empty')">{{ __('admin.moderation.empty_text') }}</x-admin.empty>
                    </x-slot:emptyState>
                    <x-slot:footer>
                        <x-admin.pagination id="xmModPager" :paginator="$rows" />
                    </x-slot:footer>
                </x-admin.table>
            </section>
        </div>

        <div class="col-xl-4">
            <section class="card xm-admin-card mb-4" aria-labelledby="xmModWordsTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmModWordsTitle">{{ __('admin.moderation.words.title') }}</h2></div>
                <form class="card-body border-bottom row g-2" wire:submit="addWord" novalidate>
                    <div class="col-12">
                        <label class="form-label small mb-1" for="xmModWord">{{ __('admin.moderation.words.word') }}</label>
                        <input class="form-control form-control-sm @error('wordText') is-invalid @enderror" id="xmModWord" wire:model="wordText" maxlength="40" autocomplete="off" @error('wordText') aria-invalid="true" aria-describedby="xmModWordError" @enderror>
                        @error('wordText')<div class="invalid-feedback d-block" id="xmModWordError">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-7">
                        <label class="visually-hidden" for="xmModSeverity">{{ __('admin.moderation.words.severity') }}</label>
                        <select class="form-select form-select-sm" id="xmModSeverity" wire:model="wordSeverity">
                            @foreach (\App\Enums\BadWordSeverity::cases() as $case)
                                <option value="{{ $case->value }}">{{ __('admin.moderation.words.severity_'.$case->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-5 d-grid"><button type="submit" class="btn btn-sm btn-primary" wire:loading.attr="disabled" wire:target="addWord">{{ __('admin.moderation.words.add') }}</button></div>
                    <div class="col-12 form-text">{{ __('admin.moderation.words.help') }}</div>
                </form>
                <ul class="list-group list-group-flush xm-mod-words">
                    @forelse ($this->words as $word)
                        <li class="list-group-item d-flex align-items-center gap-2" wire:key="word-{{ $word->id }}">
                            <span class="flex-grow-1 text-break">{{ $word->word }}</span>
                            <x-badge :color="$word->severity->badge()" soft>{{ $word->severity->label() }}</x-badge>
                            <button type="button" class="btn btn-sm btn-light text-danger" wire:click="removeWord({{ $word->id }})" wire:confirm="{{ __('admin.moderation.words.confirm_remove') }}" aria-label="{{ __('admin.moderation.words.remove', ['word' => $word->word]) }}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                        </li>
                    @empty
                        <li class="list-group-item text-secondary small">{{ __('admin.moderation.words.empty') }}</li>
                    @endforelse
                </ul>
            </section>

            <section class="card xm-admin-card mb-4" aria-labelledby="xmModSuspTitle">
                <div class="card-header d-flex justify-content-between align-items-center gap-2">
                    <h2 class="h6 fw-bold mb-0" id="xmModSuspTitle">{{ __('admin.moderation.suspensions_title') }}</h2>
                    @can('users.view')<a wire:navigate wire:navigate.hover class="small" href="{{ route('admin.utilisateurs', ['st' => 'suspendu']) }}">{{ __('admin.moderation.suspensions_all') }}</a>@endcan
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($this->suspended as $account)
                        <li class="list-group-item" wire:key="susp-{{ $account->id }}">
                            <span class="fw-semibold">{{ $account->pseudo }}</span>
                            <span class="small text-secondary"> · {{ $format->shortDate($account->suspended_at) }}</span>
                            @if ($account->suspension_reason)<div class="small text-secondary text-break">{{ $account->suspension_reason }}</div>@endif
                        </li>
                    @empty
                        <li class="list-group-item text-secondary small">{{ __('admin.moderation.suspensions_empty') }}</li>
                    @endforelse
                </ul>
            </section>

            <section class="card xm-admin-card" aria-labelledby="xmModCharterTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmModCharterTitle">{{ __('admin.moderation.charter.title') }}</h2></div>
                <ul class="list-group list-group-flush">
                    @foreach ($charter as $item)
                        <li class="list-group-item d-flex gap-3">
                            <i class="bi bi-{{ $item['icon'] }} fs-5 text-primary" aria-hidden="true"></i>
                            <div><div class="fw-semibold">{{ $item['title'] }}</div><div class="small text-secondary">{{ $item['text'] }}</div></div>
                        </li>
                    @endforeach
                </ul>
                <div class="card-footer small text-secondary">{{ __('admin.moderation.charter.sanctions') }}</div>
            </section>
        </div>
    </div>

    {{-- Décision : un seul formulaire pour les quatre décisions ; le motif (écrit pour la personne) n'est demandé que pour avertir et suspendre. --}}
    <x-admin.modal id="xmModerationDecide" size="lg" heading="h2" :title="$decision !== '' ? __('admin.moderation.decide.title.'.$decision) : __('admin.moderation.title')"
                   x-data x-on:hidden-bs-modal.dot="$wire.closeDecision()">
        @if ($report = $this->report)
            <div class="d-flex flex-wrap gap-2 mb-2">
                <x-badge :color="$report->kind->badge()" soft>{{ $report->kind->label() }}</x-badge>
                <x-badge color="danger" soft>{{ $report->reason }}</x-badge>
            </div>
            <p class="text-break mb-2">{{ $report->excerpt ?: __('admin.moderation.no_excerpt') }}</p>
            <p class="small text-secondary">{{ __('admin.moderation.author') }} <strong>{{ $report->reportedUser?->pseudo ?? __('admin.moderation.unknown_author') }}</strong></p>
            <x-alert :type="$decision === 'suspend' ? 'danger' : 'info'" class="small">{{ __('admin.moderation.decide.effect.'.$decision) }}</x-alert>
            @if (in_array($decision, ['warn', 'suspend'], true))
                <form id="xmModerationDecideForm" wire:submit="decide" novalidate>
                    <label class="form-label fw-semibold" for="xmModReason">{{ __('admin.moderation.reason_label.'.$decision) }}</label>
                    <textarea class="form-control @error('reason') is-invalid @enderror" id="xmModReason" rows="3" maxlength="{{ \App\Application\Moderation\DecideReport::REASON_MAX }}" wire:model="reason"
                              aria-describedby="xmModReasonHelp @error('reason') xmModReasonError @enderror" @error('reason') aria-invalid="true" @enderror></textarea>
                    <div class="form-text" id="xmModReasonHelp">{{ __('admin.moderation.decide.reason_help') }}</div>
                    @error('reason')<div class="invalid-feedback d-block" id="xmModReasonError">{{ $message }}</div>@enderror
                </form>
            @endif
            <x-slot:footer>
                <button type="button" @class(['btn', 'btn-danger' => $decision === 'suspend', 'btn-primary' => $decision !== 'suspend']) wire:click="decide" wire:loading.attr="disabled" wire:target="decide">
                    {{ __('admin.moderation.decide.confirm.'.$decision) }}
                </button>
            </x-slot:footer>
        @endif
    </x-admin.modal>

    {{-- Lecture du seul message privé signalé (Q74) : texte rendu côté serveur, jamais conservé ; chaque ouverture est journalisée. --}}
    <x-admin.modal id="xmModerationRead" size="md" heading="h2" :title="__('admin.moderation.read.title')" x-data x-on:hidden-bs-modal.dot="$wire.closeRead()">
        @if ($message = $this->message)
            <x-alert type="warning" icon="shield-lock" class="small">{{ __('admin.moderation.read.warning') }}</x-alert>
            <dl class="row small mb-2">
                <dt class="col-sm-4">{{ __('admin.moderation.read.author') }}</dt><dd class="col-sm-8">{{ $message['author'] ?? __('admin.moderation.unknown_author') }}</dd>
                <dt class="col-sm-4">{{ __('admin.moderation.read.sent_at') }}</dt><dd class="col-sm-8">{{ $message['sent_at'] ? $format->dateTime($message['sent_at']) : '—' }}</dd>
            </dl>
            <blockquote class="border-start border-3 ps-3 mb-0 text-break" style="white-space: pre-line">{{ $message['body'] !== '' ? $message['body'] : __('admin.moderation.read.empty') }}</blockquote>
            @if ($message['has_media'])<p class="small text-secondary mt-2 mb-0"><i class="bi bi-paperclip me-1" aria-hidden="true"></i>{{ __('admin.moderation.read.has_media') }}</p>@endif
        @endif
    </x-admin.modal>
</div>
