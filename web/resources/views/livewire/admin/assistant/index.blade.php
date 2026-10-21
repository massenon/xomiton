{{--
    Assistant LLM (P12-07) : composant App\Livewire\Admin\Assistant\Index, gabarit admin.
    Ordre : introduction · état + interrupteur · KPI · consigne système | garde-fous · base de connaissances · conversations signalées | statistiques · modales.
    Le fournisseur LLM reste derrière l'interface AssistantProvider (Q7) ; les garde-fous de crise intégrés s'appliquent toujours.
--}}
<div class="xm-assistant-admin">
    <p class="text-secondary mb-4 xm-prose">{{ __('admin_assistant.intro') }}</p>

    <section @class(['card xm-admin-card mb-4', 'border-success' => $this->state['enabled'], 'border-warning' => ! $this->state['enabled']]) aria-labelledby="xmAssistantStateTitle">
        <div class="card-body p-4 d-flex flex-wrap align-items-center gap-3">
            <div class="flex-grow-1">
                <h2 class="h4 fw-bold mb-1" id="xmAssistantStateTitle"><i @class(['bi me-2', 'bi-robot text-success' => $this->state['enabled'], 'bi-robot text-warning' => ! $this->state['enabled']]) aria-hidden="true"></i>{{ $this->state['enabled'] ? __('admin_assistant.state.on') : __('admin_assistant.state.off') }}</h2>
                <p class="text-secondary mb-1">{{ $this->state['enabled'] ? __('admin_assistant.state.on_text') : __('admin_assistant.state.off_text') }}</p>
                <p class="small mb-0"><span class="text-secondary">{{ __('admin_assistant.state.provider') }}</span> <code>{{ $this->state['provider'] }}</code> · <span class="text-secondary">{{ __('admin_assistant.state.provider_help') }}</span></p>
            </div>
            @if ($this->state['enabled'])
                <button type="button" class="btn btn-outline-warning" wire:click="setEnabled(false)" wire:confirm="{{ __('admin_assistant.confirm.disable') }}" wire:loading.attr="disabled" wire:target="setEnabled"><i class="bi bi-pause-circle me-1" aria-hidden="true"></i>{{ __('admin_assistant.state.disable') }}</button>
            @else
                <button type="button" class="btn btn-success" wire:click="setEnabled(true)" wire:loading.attr="disabled" wire:target="setEnabled"><i class="bi bi-play-circle me-1" aria-hidden="true"></i>{{ __('admin_assistant.state.enable') }}</button>
            @endif
        </div>
    </section>

    <x-admin.kpi id="xmAssistantKpis" :items="[
        ['label' => __('admin_assistant.kpi.conversations'), 'value' => $format->number($this->summary['conversations']), 'icon' => 'chat-dots', 'sub' => __('admin_assistant.kpi.conversations_help')],
        ['label' => __('admin_assistant.kpi.answered'), 'value' => $this->summary['answered_share'] === null ? '—' : $format->number($this->summary['answered_share']).' %', 'icon' => 'check2-circle', 'variant' => 'success'],
        ['label' => __('admin_assistant.kpi.flagged'), 'value' => $format->number($this->summary['flagged']), 'icon' => 'flag', 'variant' => 'danger'],
        ['label' => __('admin_assistant.kpi.escalated'), 'value' => $format->number($this->summary['escalated']), 'icon' => 'person-raised-hand', 'variant' => 'warning'],
    ]" />

    <div class="row g-4 mb-4">
        <div class="col-xl-7">
            <form class="card xm-admin-card h-100" wire:submit="savePrompt" novalidate aria-labelledby="xmAssistantPromptTitle">
                <div class="card-header d-flex justify-content-between align-items-center gap-2">
                    <h2 class="h6 fw-bold mb-0" id="xmAssistantPromptTitle">{{ __('admin_assistant.prompt.title') }}</h2>
                    <span class="small text-secondary">{{ __('admin_assistant.prompt.counter', ['count' => mb_strlen($prompt), 'max' => $promptMax]) }}</span>
                </div>
                <div class="card-body">
                    <p class="small text-secondary">{{ __('admin_assistant.prompt.help') }}</p>
                    <label class="visually-hidden" for="xmAssistantPrompt">{{ __('admin_assistant.prompt.label') }}</label>
                    <textarea class="form-control font-monospace small @error('prompt') is-invalid @enderror" id="xmAssistantPrompt" rows="14" wire:model.blur="prompt" maxlength="{{ $promptMax }}"
                              @error('prompt') aria-invalid="true" aria-describedby="xmAssistantPromptError" @enderror></textarea>
                    @error('prompt')<div class="invalid-feedback d-block" id="xmAssistantPromptError">{{ $message }}</div>@enderror
                </div>
                <div class="card-footer d-flex flex-wrap justify-content-between gap-2">
                    <button type="button" class="btn btn-link" wire:click="resetPrompt" wire:confirm="{{ __('admin_assistant.confirm.reset_prompt') }}">{{ __('admin_assistant.prompt.reset') }}</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="savePrompt"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('admin_assistant.save') }}</button>
                </div>
            </form>
        </div>
        <div class="col-xl-5">
            <form class="card xm-admin-card h-100" wire:submit="saveGuardrails" novalidate aria-labelledby="xmAssistantGuardTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmAssistantGuardTitle">{{ __('admin_assistant.guardrails.title') }}</h2></div>
                <div class="card-body">
                    <p class="small text-secondary">{{ __('admin_assistant.guardrails.help') }}</p>
                    @foreach (\App\Application\Admin\Assistant\AssistantSettings::GUARDRAILS as $key)
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="xmGuard{{ $key }}" wire:model="guardrails.{{ $key }}">
                            <label class="form-check-label" for="xmGuard{{ $key }}">{{ __('admin_assistant.guardrails.items.'.$key) }}</label>
                        </div>
                    @endforeach
                    <hr>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="xmEscalationOn" wire:model.live="escalationOn">
                        <label class="form-check-label fw-semibold" for="xmEscalationOn">{{ __('admin_assistant.guardrails.escalation') }}</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small" for="xmEscalationKeywords">{{ __('admin_assistant.guardrails.keywords') }}</label>
                        <textarea class="form-control form-control-sm @error('escalationKeywords') is-invalid @enderror" id="xmEscalationKeywords" rows="2" wire:model.blur="escalationKeywords" @disabled(! $escalationOn) maxlength="500"></textarea>
                        @error('escalationKeywords')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="form-label small" for="xmEscalationTarget">{{ __('admin_assistant.guardrails.target') }}</label>
                        <select class="form-select form-select-sm @error('escalationTarget') is-invalid @enderror" id="xmEscalationTarget" wire:model="escalationTarget" @disabled(! $escalationOn)>
                            @foreach (\App\Application\Admin\Assistant\AssistantSettings::TARGETS as $target)<option value="{{ $target }}">{{ __('admin_assistant.guardrails.targets.'.$target) }}</option>@endforeach
                        </select>
                        @error('escalationTarget')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveGuardrails"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('admin_assistant.save') }}</button>
                </div>
            </form>
        </div>
    </div>

    <section class="card xm-admin-card mb-4" aria-labelledby="xmAssistantKbTitle">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h6 fw-bold mb-0" id="xmAssistantKbTitle">{{ __('admin_assistant.kb.title') }}</h2>
            <button type="button" class="btn btn-primary btn-sm" wire:click="newKb"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_assistant.kb.new') }}</button>
        </div>
        <div class="card-body border-bottom">
            <x-admin.filters id="xmAssistantFilters" :active="$this->activeFilterCount()" :placeholder="__('admin_assistant.kb.search_placeholder')" :search-help="__('admin_assistant.kb.search_help')">
                <div class="col-6 col-lg-3">
                    <label class="visually-hidden" for="xmAssistantKbState">{{ __('admin_assistant.kb.columns.state') }}</label>
                    <select class="form-select form-select-sm" id="xmAssistantKbState" wire:model.live="kbState">
                        <option value="">{{ __('admin_assistant.kb.states.all') }}</option>
                        <option value="active">{{ __('admin_assistant.kb.states.active') }}</option>
                        <option value="inactive">{{ __('admin_assistant.kb.states.inactive') }}</option>
                    </select>
                </div>
            </x-admin.filters>
        </div>
        <x-admin.table id="xmAssistantKbTable" :caption="__('admin_assistant.kb.caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$entries->isEmpty()" :target="$this->dataTableTargets()" :columns="[
            ['key' => 'question', 'label' => __('admin_assistant.kb.columns.question'), 'sortable' => true],
            ['key' => 'keywords', 'label' => __('admin_assistant.kb.columns.keywords')],
            ['key' => 'hits', 'label' => __('admin_assistant.kb.columns.hits'), 'sortable' => true, 'class' => 'text-end', 'width' => '7rem'],
            ['key' => 'actions', 'label' => '', 'sr' => __('admin.datatable.actions'), 'class' => 'text-end'],
        ]">
            @foreach ($entries as $entry)
                <tr wire:key="kb-{{ $entry->id }}">
                    <td data-label="{{ __('admin_assistant.kb.columns.question') }}">
                        <div class="fw-semibold text-break">{{ $entry->question }} @unless ($entry->is_active)<x-badge color="neutral" soft>{{ __('admin_assistant.kb.inactive') }}</x-badge>@endunless</div>
                        <div class="small text-secondary text-break">{{ \Illuminate\Support\Str::limit($entry->answer, 160) }}</div>
                    </td>
                    <td data-label="{{ __('admin_assistant.kb.columns.keywords') }}">
                        <div class="d-flex flex-wrap gap-1">@foreach ((array) $entry->keywords as $keyword)<x-badge color="primary" soft>{{ $keyword }}</x-badge>@endforeach</div>
                    </td>
                    <td data-label="{{ __('admin_assistant.kb.columns.hits') }}" class="text-end">{{ $format->number((int) $entry->hits_count) }}</td>
                    <td data-label="{{ __('admin.datatable.actions') }}" class="text-end xm-table__actions">
                        <div class="d-flex flex-wrap gap-1 justify-content-end">
                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="editKb({{ $entry->id }})"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('admin_assistant.edit') }}<span class="visually-hidden"> {{ $entry->question }}</span></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="toggleKb({{ $entry->id }})">{{ $entry->is_active ? __('admin_assistant.kb.deactivate') : __('admin_assistant.kb.activate') }}<span class="visually-hidden"> {{ $entry->question }}</span></button>
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteKb({{ $entry->id }})" wire:confirm="{{ __('admin_assistant.confirm.delete_kb') }}"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_assistant.delete') }} {{ $entry->question }}</span></button>
                        </div>
                    </td>
                </tr>
            @endforeach
            <x-slot:emptyState><x-admin.empty icon="journal-text" :filtered="$this->activeFilterCount() > 0" :title="__('admin_assistant.kb.empty')">{{ __('admin_assistant.kb.empty_text') }}</x-admin.empty></x-slot:emptyState>
            <x-slot:footer><x-admin.pagination id="xmAssistantKbPager" :paginator="$entries" /></x-slot:footer>
        </x-admin.table>
    </section>

    <div class="row g-4">
        <div class="col-xl-7">
            <section class="card xm-admin-card h-100" aria-labelledby="xmAssistantFlaggedTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmAssistantFlaggedTitle">{{ __('admin_assistant.flagged.title') }}</h2></div>
                <div class="card-body">
                    <p class="small text-secondary">{{ __('admin_assistant.flagged.help') }}</p>
                    @forelse ($flagged as $conversation)
                        <div class="d-flex flex-wrap align-items-center gap-2 border-top py-2" wire:key="flag-{{ $conversation->id }}">
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><x-badge color="danger" soft>{{ $conversation->flag_reason ?: __('admin_assistant.flagged.no_reason') }}</x-badge></div>
                                <div class="small text-secondary">{{ $conversation->user_id === null ? __('admin_assistant.flagged.visitor') : __('admin_assistant.flagged.member') }} · {{ trans_choice('admin_assistant.flagged.messages', $conversation->messages_count, ['count' => $conversation->messages_count]) }} · {{ $format->ago($conversation->updated_at) }}
                                    @if ($conversation->escalated_at) · <x-badge color="warning" soft>{{ __('admin_assistant.flagged.escalated') }}</x-badge>@endif</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openConversation({{ $conversation->id }})"><i class="bi bi-eye me-1" aria-hidden="true"></i>{{ __('admin_assistant.flagged.read') }}<span class="visually-hidden"> {{ __('admin_assistant.flagged.sr', ['id' => $conversation->id]) }}</span></button>
                        </div>
                    @empty
                        <x-admin.empty icon="emoji-smile" :title="__('admin_assistant.flagged.empty')" />
                    @endforelse
                    @if ($flagged->hasPages())
                        <div class="d-flex justify-content-between align-items-center border-top pt-2">
                            <button type="button" class="btn btn-sm btn-light border" wire:click="previousPage('signalees')" @disabled($flagged->onFirstPage())>{{ __('admin.datatable.previous') }}</button>
                            <span class="small text-secondary">{{ $flagged->currentPage() }} / {{ $flagged->lastPage() }}</span>
                            <button type="button" class="btn btn-sm btn-light border" wire:click="nextPage('signalees')" @disabled(! $flagged->hasMorePages())>{{ __('admin.datatable.next') }}</button>
                        </div>
                    @endif
                </div>
            </section>
        </div>
        <div class="col-xl-5">
            <section class="card xm-admin-card h-100" aria-labelledby="xmAssistantStatsTitle">
                <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmAssistantStatsTitle">{{ __('admin_assistant.stats.title') }}</h2></div>
                <div class="card-body">
                    <p class="small text-secondary">{{ __('admin_assistant.stats.help') }}</p>
                    <dl class="row small mb-3">
                        <dt class="col-7">{{ __('admin_assistant.stats.kb_active') }}</dt><dd class="col-5 text-end">{{ $format->number($this->summary['kb_active']) }}</dd>
                        <dt class="col-7">{{ __('admin_assistant.stats.kb_hits') }}</dt><dd class="col-5 text-end">{{ $format->number($this->summary['kb_hits']) }}</dd>
                    </dl>
                    <h3 class="h6">{{ __('admin_assistant.stats.top') }}</h3>
                    @forelse ($this->topEntries as $top)
                        <div class="d-flex justify-content-between gap-2 small border-top py-1"><span class="text-break">{{ $top->question }}</span><span class="fw-semibold text-nowrap">{{ $format->number((int) $top->hits_count) }}</span></div>
                    @empty
                        <p class="small text-secondary mb-0">{{ __('admin_assistant.stats.top_empty') }}</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    <x-admin.modal id="xmAssistantKb" size="lg" heading="h2" dirty-guard :title="$kbId ? __('admin_assistant.kb.edit_title') : __('admin_assistant.kb.new')" x-data x-on:hidden-bs-modal.dot="$wire.resetKbForm()">
        <form id="xmAssistantKbForm" wire:submit="saveKb" novalidate>
            <div class="row g-3">
                <div class="col-12"><x-field name="kbForm.question" :label="__('admin_assistant.kb.fields.question')" wire:model.blur="kbForm.question" maxlength="255" autocomplete="off" /></div>
                <div class="col-12"><x-field name="kbForm.answer" type="textarea" :label="__('admin_assistant.kb.fields.answer')" wire:model.blur="kbForm.answer" rows="6" maxlength="2000" :hint="__('admin_assistant.kb.answer_help')" /></div>
                <div class="col-12"><x-field name="kbForm.keywords" :label="__('admin_assistant.kb.fields.keywords')" wire:model.blur="kbForm.keywords" autocomplete="off" :hint="__('admin_assistant.kb.keywords_help')" /></div>
                <div class="col-12"><x-field name="kbForm.is_active" type="switch" :label="__('admin_assistant.kb.fields.is_active')" wire:model="kbForm.is_active" /></div>
            </div>
        </form>
        <x-slot:footer>
            <button type="submit" form="xmAssistantKbForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveKb"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('admin_assistant.save') }}</button>
        </x-slot:footer>
    </x-admin.modal>

    <x-admin.modal id="xmAssistantConversation" size="lg" heading="h2" :title="__('admin_assistant.flagged.modal_title')" x-data x-on:hidden-bs-modal.dot="$wire.closeConversation()">
        <p class="small text-secondary">{{ __('admin_assistant.flagged.modal_help') }}</p>
        @forelse ($this->reading as $message)
            <div @class(['rounded p-2 mb-2 small', 'bg-primary-subtle ms-4' => $message->role === 'user', 'bg-body-secondary me-4' => $message->role !== 'user']) wire:key="msg-{{ $message->id }}">
                <div class="fw-semibold">{{ __('admin_assistant.flagged.roles.'.$message->role) }}</div>
                <div class="text-break" style="white-space: pre-line">{{ $message->body }}</div>
            </div>
        @empty
            <p class="small text-secondary mb-0">{{ __('admin_assistant.flagged.no_messages') }}</p>
        @endforelse
        <x-slot:footer>
            @if ($readingId)
                <button type="button" class="btn btn-success" wire:click="resolveConversation({{ $readingId }})" wire:loading.attr="disabled" wire:target="resolveConversation"><i class="bi bi-check2-all me-1" aria-hidden="true"></i>{{ __('admin_assistant.flagged.resolve') }}</button>
            @endif
        </x-slot:footer>
    </x-admin.modal>
</div>
