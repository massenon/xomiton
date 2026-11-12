{{--
    Ressources proposées par les membres (P18-12) : composant App\Livewire\Admin\Content\Proposals, gabarit admin.
    Ordre : retour au contenu · introduction · filtres (recherche, statut) · tableau · modale de relecture (Accepter, Refuser).
    Une proposition n'est jamais publique : « Accepter » crée une ressource en brouillon à relire dans /admin/contenu.
--}}
<div class="xm-proposals-admin">
    <p class="mb-3"><a class="small" href="{{ route('admin.contenu') }}" wire:navigate><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('admin_content.proposals.back') }}</a></p>
    <p class="text-secondary mb-4 xm-prose">{{ __('admin_content.proposals.intro') }}</p>

    <section class="card xm-admin-card" aria-labelledby="xmProposalsTitle">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h6 fw-bold mb-0" id="xmProposalsTitle">{{ __('admin_content.proposals.title') }}</h2>
            <span class="small text-secondary">{{ trans_choice('admin_content.proposals.pending_count', $pendingCount, ['count' => $pendingCount]) }}</span>
        </div>
        <div class="card-body border-bottom">
            <x-admin.filters id="xmProposalFilters" :active="$this->activeFilterCount()" :placeholder="__('admin_content.proposals.search_placeholder')">
                <div class="col-6 col-lg-3">
                    <label class="visually-hidden" for="xmProposalStatus">{{ __('admin_content.proposals.columns.status') }}</label>
                    <select class="form-select form-select-sm" id="xmProposalStatus" wire:model.live="status">
                        <option value="">{{ __('admin_content.proposals.all') }}</option>
                        @foreach (\App\Enums\ProposalStatus::cases() as $case)
                            <option value="{{ $case->value }}">{{ $case->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </x-admin.filters>
        </div>
        <x-admin.table id="xmProposalsTable" :caption="__('admin_content.proposals.caption')" :sort="$sortKey" :direction="$sortDirection" :empty="$rows->isEmpty()" :target="$this->dataTableTargets()" :columns="[
            ['key' => 'title', 'label' => __('admin_content.proposals.columns.title'), 'sortable' => true],
            ['key' => 'author', 'label' => __('admin_content.proposals.columns.author'), 'width' => '10rem'],
            ['key' => 'created', 'label' => __('admin_content.proposals.columns.created'), 'sortable' => true, 'width' => '10rem'],
            ['key' => 'status', 'label' => __('admin_content.proposals.columns.status'), 'width' => '8rem'],
            ['key' => 'actions', 'label' => '', 'sr' => __('admin.datatable.actions'), 'class' => 'text-end'],
        ]">
            @foreach ($rows as $proposal)
                @php($status = $proposal->proposalStatus())
                <tr wire:key="proposal-{{ $proposal->id }}">
                    <td data-label="{{ __('admin_content.proposals.columns.title') }}">
                        <div class="fw-semibold text-break">{{ $proposal->title }}</div>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            <x-badge color="neutral" soft :icon="str_replace('bi-', '', $proposal->resourceKind()->icon())">{{ $proposal->resourceKind()->label() }}</x-badge>
                            @if ($proposal->category)<x-badge color="neutral" soft>{{ $proposal->category->label }}</x-badge>@endif
                        </div>
                    </td>
                    <td data-label="{{ __('admin_content.proposals.columns.author') }}" class="small">{{ $proposal->user?->pseudo ?? __('admin_content.proposals.deleted_account') }}</td>
                    <td data-label="{{ __('admin_content.proposals.columns.created') }}" class="text-nowrap small">{{ $format->tableDateTime($proposal->created_at) }}</td>
                    <td data-label="{{ __('admin_content.proposals.columns.status') }}"><x-badge :color="$status->badge()" soft>{{ $status->label() }}</x-badge></td>
                    <td data-label="{{ __('admin.datatable.actions') }}" class="text-end xm-table__actions">
                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="open({{ $proposal->id }})"><i class="bi bi-eye me-1" aria-hidden="true"></i>{{ $status === \App\Enums\ProposalStatus::Proposed ? __('admin_content.proposals.actions.review') : __('admin_content.proposals.actions.view') }}<span class="visually-hidden"> {{ $proposal->title }}</span></button>
                    </td>
                </tr>
            @endforeach
            <x-slot:emptyState><x-admin.empty icon="inbox" :filtered="$this->activeFilterCount() > 1" :title="__('admin_content.proposals.empty')" /></x-slot:emptyState>
            <x-slot:footer><x-admin.pagination id="xmProposalsPager" :paginator="$rows" /></x-slot:footer>
        </x-admin.table>
    </section>

    @php($opened = $this->opened)
    <x-admin.modal id="xmProposal" size="lg" :title="$opened ? __('admin_content.proposals.modal_title', ['title' => $opened->title]) : __('admin_content.proposals.title')">
        @if ($opened)
            @php($pending = $opened->isPending())
            <dl class="row small mb-3">
                <dt class="col-sm-4">{{ __('admin_content.proposals.columns.author') }}</dt>
                <dd class="col-sm-8">{{ $opened->user?->pseudo ?? __('admin_content.proposals.deleted_account') }}</dd>
                <dt class="col-sm-4">{{ __('admin_content.proposals.fields.kind') }}</dt>
                <dd class="col-sm-8">{{ $opened->resourceKind()->label() }}</dd>
                <dt class="col-sm-4">{{ __('admin_content.proposals.fields.category') }}</dt>
                <dd class="col-sm-8">{{ $opened->category?->label ?? __('admin_content.proposals.no_category') }}</dd>
                <dt class="col-sm-4">{{ __('admin_content.proposals.fields.summary') }}</dt>
                <dd class="col-sm-8 text-break">{{ $opened->summary }}</dd>
                @if ($opened->external_url)
                    <dt class="col-sm-4">{{ __('admin_content.proposals.fields.link') }}</dt>
                    <dd class="col-sm-8 text-break"><a href="{{ $opened->external_url }}" target="_blank" rel="noopener noreferrer nofollow">{{ $opened->external_url }}<span class="visually-hidden"> {{ __('microcopy.a11y.opens_new_tab') }}</span></a></dd>
                @endif
                @if ($fileUrl)
                    <dt class="col-sm-4">{{ __('admin_content.proposals.fields.file') }}</dt>
                    <dd class="col-sm-8"><a href="{{ $fileUrl }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>{{ __('admin_content.proposals.open_file') }}<span class="visually-hidden"> {{ __('microcopy.a11y.opens_new_tab') }}</span></a></dd>
                @endif
                <dt class="col-sm-4">{{ __('admin_content.proposals.fields.credit') }}</dt>
                <dd class="col-sm-8">{{ $opened->credit_author ? __('admin_content.proposals.credit_yes') : __('admin_content.proposals.credit_no') }}</dd>
                @if (! $pending)
                    <dt class="col-sm-4">{{ __('admin_content.proposals.columns.status') }}</dt>
                    <dd class="col-sm-8">{{ $opened->proposalStatus()->label() }}@if ($opened->refusal_reason) — {{ $opened->refusal_reason }}@endif</dd>
                @endif
            </dl>
            @if ($pending)
                @if ($opened->credit_author)
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="xmProposalCredit" wire:model="credit">
                        <label class="form-check-label" for="xmProposalCredit">{{ __('admin_content.proposals.fields.keep_credit', ['name' => $opened->user?->pseudo ?? '']) }}</label>
                    </div>
                @endif
                <div class="mb-0">
                    <label class="form-label fw-semibold" for="xmProposalReason">{{ __('admin_content.proposals.fields.reason') }}</label>
                    <textarea class="form-control @error('reason') is-invalid @enderror" id="xmProposalReason" rows="2" wire:model="reason" maxlength="{{ \App\Application\Content\Proposals\ReviewResourceProposal::REASON_MAX }}"
                              aria-describedby="xmProposalReasonHelp @error('reason') xmProposalReasonError @enderror" @error('reason') aria-invalid="true" @enderror></textarea>
                    <div class="form-text" id="xmProposalReasonHelp">{{ __('admin_content.proposals.reason_help') }}</div>
                    @error('reason')<div class="invalid-feedback d-block" id="xmProposalReasonError">{{ $message }}</div>@enderror
                </div>
            @elseif ($opened->resource_id)
                <p class="small mb-0"><a href="{{ route('admin.contenu') }}" wire:navigate>{{ __('admin_content.proposals.open_resource') }}</a></p>
            @endif
        @endif
        <x-slot:footer>
            @if ($opened && $opened->isPending())
                <button type="button" class="btn btn-outline-danger" wire:click="refuse" wire:loading.attr="disabled" wire:target="refuse">{{ __('admin_content.proposals.actions.refuse') }}</button>
                <button type="button" class="btn btn-primary" wire:click="accept" wire:loading.attr="disabled" wire:target="accept"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('admin_content.proposals.actions.accept') }}</button>
            @endif
        </x-slot:footer>
    </x-admin.modal>
</div>
