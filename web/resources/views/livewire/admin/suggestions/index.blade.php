{{--
    Boîte à suggestions (P18-14) : composant App\Livewire\Admin\Suggestions\Index, gabarit admin.
    Ordre : introduction · filtres (recherche, statut, catégorie) · tableau · modale (message, statut, réponse de l'équipe).
    Une suggestion anonyme n'affiche jamais son compte.
--}}
<div class="xm-suggestions-admin">
    <p class="text-secondary mb-4 xm-prose">{{ __('admin_suggestions.intro') }}</p>

    <section class="card xm-admin-card" aria-labelledby="xmSugTitle">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h6 fw-bold mb-0" id="xmSugTitle">{{ __('admin_suggestions.list_title') }}</h2>
            <span class="small text-secondary">{{ trans_choice('admin_suggestions.new_count', $newCount, ['count' => $newCount]) }}</span>
        </div>
        <div class="card-body border-bottom">
            <x-admin.filters id="xmSugFilters" :active="$this->activeFilterCount()" :placeholder="__('admin_suggestions.search_placeholder')">
                <div class="col-6 col-lg-3">
                    <label class="visually-hidden" for="xmSugStatus">{{ __('admin_suggestions.columns.status') }}</label>
                    <select class="form-select form-select-sm" id="xmSugStatus" wire:model.live="status">
                        <option value="">{{ __('admin_suggestions.all_statuses') }}</option>
                        @foreach (\App\Enums\SuggestionStatus::cases() as $case)<option value="{{ $case->value }}">{{ $case->label() }}</option>@endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-3">
                    <label class="visually-hidden" for="xmSugCategory">{{ __('admin_suggestions.columns.category') }}</label>
                    <select class="form-select form-select-sm" id="xmSugCategory" wire:model.live="category">
                        <option value="">{{ __('admin_suggestions.all_categories') }}</option>
                        @foreach (\App\Enums\SuggestionCategory::cases() as $case)<option value="{{ $case->value }}">{{ $case->label() }}</option>@endforeach
                    </select>
                </div>
            </x-admin.filters>
        </div>
        <x-admin.table id="xmSugTable" :caption="__('admin_suggestions.caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
            ['key' => 'message', 'label' => __('admin_suggestions.columns.message')],
            ['key' => 'category', 'label' => __('admin_suggestions.columns.category'), 'width' => '9rem'],
            ['key' => 'author', 'label' => __('admin_suggestions.columns.author'), 'width' => '9rem'],
            ['key' => 'created', 'label' => __('admin_suggestions.columns.created'), 'sortable' => true, 'width' => '9rem'],
            ['key' => 'status', 'label' => __('admin_suggestions.columns.status'), 'width' => '8rem'],
            ['key' => 'actions', 'label' => '', 'sr' => __('admin.datatable.actions'), 'class' => 'text-end'],
        ]">
            @foreach ($rows as $suggestion)
                @php($status = $suggestion->suggestionStatus())
                <tr wire:key="suggestion-{{ $suggestion->id }}">
                    <td data-label="{{ __('admin_suggestions.columns.message') }}" class="text-break">{{ \Illuminate\Support\Str::limit($suggestion->message, 140) }}@if ($suggestion->reply)<div class="small text-success mt-1"><i class="bi bi-reply me-1" aria-hidden="true"></i>{{ __('admin_suggestions.replied') }}</div>@endif</td>
                    <td data-label="{{ __('admin_suggestions.columns.category') }}" class="small">{{ $suggestion->suggestionCategory()->label() }}</td>
                    <td data-label="{{ __('admin_suggestions.columns.author') }}" class="small">{{ $suggestion->is_anonymous ? __('admin_suggestions.anonymous') : ($suggestion->user?->pseudo ?? __('admin_suggestions.deleted_account')) }}</td>
                    <td data-label="{{ __('admin_suggestions.columns.created') }}" class="small text-nowrap">{{ $format->tableDateTime($suggestion->created_at) }}</td>
                    <td data-label="{{ __('admin_suggestions.columns.status') }}"><x-badge :color="$status->badge()" soft>{{ $status->label() }}</x-badge></td>
                    <td data-label="{{ __('admin.datatable.actions') }}" class="text-end xm-table__actions">
                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="open({{ $suggestion->id }})"><i class="bi bi-envelope-open me-1" aria-hidden="true"></i>{{ __('admin_suggestions.actions.open') }}<span class="visually-hidden"> {{ __('admin_suggestions.sr_row', ['id' => $suggestion->id]) }}</span></button>
                    </td>
                </tr>
            @endforeach
            <x-slot:emptyState><x-admin.empty icon="lightbulb" :filtered="$this->activeFilterCount() > 0" :title="__('admin_suggestions.empty')" /></x-slot:emptyState>
            <x-slot:footer><x-admin.pagination id="xmSugPager" :paginator="$rows" /></x-slot:footer>
        </x-admin.table>
    </section>

    @php($opened = $this->opened)
    <x-admin.modal id="xmSuggestion" size="lg" :title="__('admin_suggestions.modal_title')">
        @if ($opened)
            <p class="small text-secondary mb-2">{{ $opened->suggestionCategory()->label() }} · {{ $opened->is_anonymous ? __('admin_suggestions.anonymous') : ($opened->user?->pseudo ?? __('admin_suggestions.deleted_account')) }} · {{ $format->tableDateTime($opened->created_at) }}</p>
            <blockquote class="border-start border-3 ps-3 mb-4 text-break" style="white-space: pre-line">{{ $opened->message }}</blockquote>
            <form class="row g-2 align-items-end mb-4" wire:submit="saveStatus" novalidate>
                <div class="col-sm-8">
                    <label class="form-label fw-semibold" for="xmSugNewStatus">{{ __('admin_suggestions.fields.status') }}</label>
                    <select class="form-select" id="xmSugNewStatus" wire:model="newStatus">
                        @foreach (\App\Enums\SuggestionStatus::cases() as $case)<option value="{{ $case->value }}">{{ $case->label() }}</option>@endforeach
                    </select>
                </div>
                <div class="col-sm-4"><button type="submit" class="btn btn-outline-primary w-100" wire:loading.attr="disabled" wire:target="saveStatus">{{ __('admin_suggestions.actions.save_status') }}</button></div>
            </form>
            <form id="xmSugReplyForm" wire:submit="sendReply" novalidate>
                <label class="form-label fw-semibold" for="xmSugReply">{{ __('admin_suggestions.fields.reply') }}</label>
                <textarea class="form-control @error('reply') is-invalid @enderror" id="xmSugReply" rows="3" wire:model="reply" maxlength="{{ \App\Application\Community\Suggestions\Suggestions::REPLY_MAX }}"
                          aria-describedby="xmSugReplyHelp @error('reply') xmSugReplyError @enderror" @error('reply') aria-invalid="true" @enderror></textarea>
                <div class="form-text" id="xmSugReplyHelp">{{ $opened->replied_at ? __('admin_suggestions.reply_sent', ['date' => $format->tableDateTime($opened->replied_at)]) : __('admin_suggestions.reply_help') }}</div>
                @error('reply')<div class="invalid-feedback d-block" id="xmSugReplyError">{{ $message }}</div>@enderror
            </form>
        @endif
        <x-slot:footer>
            <button type="submit" form="xmSugReplyForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="sendReply"><i class="bi bi-send me-1" aria-hidden="true"></i>{{ __('admin_suggestions.actions.reply') }}</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
