{{--
    Conversations en BD à relire (P20-08) : composant App\Livewire\Admin\Moderation\ConversationsBd, gabarit admin.
    Ordre : onglets de la modération · introduction · KPI · une carte par création proposée (aperçu rendu des cases et des
    bulles avec leur transcription, résumé, auteur·rice et choix d'affichage, date, contrôles de la grammaire, PDF,
    Refuser / Publier) · pagination · modale de décision toujours montée (motif obligatoire pour refuser).
--}}
<div class="xm-moderation-bd">
    @include('livewire.admin.moderation.partials.tabs', ['active' => 'conversations-bd', 'pending' => null])

    <p class="text-secondary mb-3 xm-prose">{{ __('admin.moderation.bd.intro') }}</p>

    <x-admin.kpi id="xmBdKpis" :items="[
        ['label' => __('admin.moderation.bd.kpi.pending'), 'value' => $format->number($this->stats['pending']), 'icon' => 'chat-square-quote', 'variant' => 'danger'],
        ['label' => __('admin.moderation.bd.kpi.published'), 'value' => $format->number($this->stats['published']), 'icon' => 'check-circle', 'variant' => 'success'],
        ['label' => __('admin.moderation.bd.kpi.refused'), 'value' => $format->number($this->stats['refused']), 'icon' => 'x-circle', 'variant' => 'warning'],
    ]" />

    @if ($rows === [])
        <div class="card xm-admin-card">
            <div class="card-body text-center py-5">
                <i class="bi bi-chat-square-quote fs-1 text-secondary" aria-hidden="true"></i>
                <h2 class="h6 fw-bold mt-2">{{ __('admin.moderation.bd.empty') }}</h2>
                <p class="text-secondary mb-0">{{ __('admin.moderation.bd.empty_text') }}</p>
            </div>
        </div>
    @else
        <div class="vstack gap-4" id="xmBdQueue">
            @foreach ($rows as $row)
                @php($conversation = $row['conversation'])
                <article class="card xm-admin-card" wire:key="bd-{{ $conversation->id }}" aria-labelledby="xmBdTitle{{ $conversation->id }}">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h2 class="h6 fw-bold mb-0" id="xmBdTitle{{ $conversation->id }}">{{ $conversation->title }}</h2>
                        <x-badge :color="$conversation->is_anonymous ? 'secondary' : 'info'" soft>{{ $conversation->is_anonymous ? __('admin.moderation.bd.anonymous') : __('admin.moderation.bd.pseudonym') }}</x-badge>
                    </div>
                    <div class="card-body">
                        @if (trim((string) $conversation->summary) !== '')
                            <p class="text-break">{{ $conversation->summary }}</p>
                        @endif
                        <dl class="row small text-secondary">
                            <dt class="col-sm-3">{{ __('admin.moderation.bd.author') }}</dt>
                            <dd class="col-sm-9">{{ $conversation->author?->pseudo ?? __('admin.moderation.unknown_author') }}</dd>
                            <dt class="col-sm-3">{{ __('admin.moderation.bd.submitted_at') }}</dt>
                            <dd class="col-sm-9">{{ $format->dateTime($conversation->submitted_at) }}</dd>
                            <dt class="col-sm-3">{{ __('admin.moderation.bd.panels') }}</dt>
                            <dd class="col-sm-9">{{ count($row['panels']) }}</dd>
                        </dl>
                        @if ($row['issues'] !== [])
                            <ul class="small ps-3" aria-label="{{ __('admin.moderation.bd.issues') }}">
                                @foreach ($row['issues'] as $issue)
                                    <li @class(['text-danger' => $issue['level'] === 'error', 'text-warning-emphasis' => $issue['level'] !== 'error'])>{{ $issue['message'] }}</li>
                                @endforeach
                            </ul>
                        @endif
                        <div class="row g-3">
                            @foreach ($row['panels'] as $panel)
                                <div class="col-md-6" wire:key="bd-{{ $conversation->id }}-{{ $panel['n'] }}">
                                    <x-bd.panel :bubbles="$panel['bubbles']" :names="$names" :image="$panel['image']" :alt="$panel['alt']" :n="$panel['n']" :count="count($row['panels'])" :title="$conversation->title" />
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="card-footer d-flex flex-wrap gap-2 justify-content-end align-items-center">
                        <a class="btn btn-sm btn-outline-secondary me-auto" href="{{ route('mes-conversations-bd.pdf', ['ulid' => $conversation->ulid]) }}">
                            <i class="bi bi-filetype-pdf me-1" aria-hidden="true"></i>{{ __('admin.moderation.bd.pdf') }}<span class="visually-hidden"> {{ $conversation->title }}</span>
                        </a>
                        @if ($row['own'])
                            <span class="small text-secondary">{{ __('admin.moderation.bd.own') }}</span>
                        @else
                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="openDecision({{ $conversation->id }}, 'refuse')">
                                <i class="bi bi-x-circle me-1" aria-hidden="true"></i>{{ __('admin.moderation.bd.actions.refuse') }}<span class="visually-hidden"> {{ $conversation->title }}</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" wire:click="openDecision({{ $conversation->id }}, 'publish')">
                                <i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ __('admin.moderation.bd.actions.publish') }}<span class="visually-hidden"> {{ $conversation->title }}</span>
                            </button>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
        <div class="mt-3">{{ $paginator->links() }}</div>
    @endif

    <x-admin.modal id="xmBdDecide" size="lg" heading="h2" :title="$decision === 'refuse' ? __('admin.moderation.bd.refuse_title') : __('admin.moderation.bd.publish_title')"
                   x-data x-on:hidden-bs-modal.dot="$wire.closeDecision()">
        @if ($decision === 'publish')
            <x-alert type="info" class="small">{{ __('admin.moderation.bd.publish_effect') }}</x-alert>
            <x-slot:footer>
                <button type="button" class="btn btn-primary" wire:click="publish" wire:loading.attr="disabled" wire:target="publish">{{ __('admin.moderation.bd.actions.publish') }}</button>
            </x-slot:footer>
        @elseif ($decision === 'refuse')
            <x-alert type="info" class="small">{{ __('admin.moderation.bd.refuse_effect') }}</x-alert>
            <form id="xmBdRefuseForm" wire:submit="refuse" novalidate>
                <label class="form-label fw-semibold" for="xmBdReason">{{ __('admin.moderation.bd.reason') }}</label>
                <textarea class="form-control @error('reason') is-invalid @enderror" id="xmBdReason" rows="4" maxlength="{{ $reasonMax }}" wire:model="reason" required
                          aria-describedby="xmBdReasonHelp @error('reason') xmBdReasonError @enderror" @error('reason') aria-invalid="true" @enderror></textarea>
                <div class="form-text" id="xmBdReasonHelp">{{ __('admin.moderation.bd.reason_help', ['max' => $reasonMax]) }}</div>
                @error('reason')<div class="invalid-feedback d-block" id="xmBdReasonError">{{ $message }}</div>@enderror
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-danger" wire:click="refuse" wire:loading.attr="disabled" wire:target="refuse">{{ __('admin.moderation.bd.actions.refuse') }}</button>
            </x-slot:footer>
        @endif
    </x-admin.modal>
</div>
