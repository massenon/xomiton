{{--
    Vidéos à relire (Q96) : composant App\Livewire\Admin\Moderation\Videos, gabarit admin.
    Ordre : onglets de la modération · introduction et avertissement métadonnées · KPI · une carte par vidéo (lecteur natif,
    description, auteur·rice, date, poids, Approuver / Refuser) · pagination · modale de décision toujours montée.
    Le lecteur est un <video controls> natif : sous-titres et description restent lisibles, aucun lecteur maison.
--}}
<div class="xm-moderation-videos">
    @include('livewire.admin.moderation.partials.tabs', ['active' => 'videos', 'pending' => $this->stats['pending']])

    <p class="text-secondary mb-3 xm-prose">{{ __('admin.moderation.video.intro') }}</p>
    <x-alert type="warning" icon="shield-exclamation" class="small">{{ __('admin.moderation.video.metadata_warning') }}</x-alert>

    <x-admin.kpi id="xmVideoKpis" :items="[
        ['label' => __('admin.moderation.video.kpi.pending'), 'value' => $format->number($this->stats['pending']), 'icon' => 'camera-video', 'variant' => 'danger'],
        ['label' => __('admin.moderation.video.kpi.rejected'), 'value' => $format->number($this->stats['rejected']), 'icon' => 'camera-video-off', 'variant' => 'warning'],
    ]" />

    @if ($rows === [])
        <div class="card xm-admin-card">
            <div class="card-body text-center py-5">
                <i class="bi bi-camera-video fs-1 text-secondary" aria-hidden="true"></i>
                <h2 class="h6 fw-bold mt-2">{{ __('admin.moderation.video.empty') }}</h2>
                <p class="text-secondary mb-0">{{ __('admin.moderation.video.empty_text') }}</p>
            </div>
        </div>
    @else
        <div class="row g-4" id="xmVideoQueue">
            @foreach ($rows as $row)
                @php($post = $row['post'])
                <div class="col-12 col-xl-6" wire:key="video-{{ $post->id }}">
                    <article class="card xm-admin-card h-100" aria-labelledby="xmVideoTitle{{ $post->id }}">
                        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <h2 class="h6 fw-bold mb-0" id="xmVideoTitle{{ $post->id }}">{{ __('admin.moderation.video.card_title', ['pseudo' => $post->user?->pseudo ?? __('admin.moderation.unknown_author')]) }}</h2>
                            @if ($post->is_anonymous)<x-badge color="secondary" soft>{{ __('admin.moderation.video.anonymous') }}</x-badge>@endif
                        </div>
                        <div class="card-body">
                            @if ($row['url'])
                                <video class="w-100 rounded bg-black mb-3" controls preload="metadata" playsinline aria-describedby="xmVideoDesc{{ $post->id }}" style="max-height: 24rem">
                                    <source src="{{ $row['url'] }}" @if ($row['mime']) type="{{ $row['mime'] }}" @endif>
                                    {{ __('admin.moderation.video.no_player') }}
                                </video>
                            @else
                                <x-alert type="danger" class="small">{{ __('admin.moderation.video.file_missing') }}</x-alert>
                            @endif
                            <h3 class="h6 fw-semibold">{{ __('admin.moderation.video.description') }}</h3>
                            <p class="text-break" id="xmVideoDesc{{ $post->id }}">{{ $row['description'] }}</p>
                            @if (trim((string) $post->body) !== '')
                                <h3 class="h6 fw-semibold">{{ __('admin.moderation.video.text') }}</h3>
                                <p class="text-break" style="white-space: pre-line">{{ $post->body }}</p>
                            @endif
                            <dl class="row small text-secondary mb-0">
                                <dt class="col-5">{{ __('admin.moderation.video.sent_at') }}</dt>
                                <dd class="col-7">{{ $format->dateTime($post->created_at) }}</dd>
                                <dt class="col-5">{{ __('admin.moderation.video.size') }}</dt>
                                <dd class="col-7">{{ $row['size'] ? $format->number(round($row['size'] / 1048576, 1), 1).' '.__('admin.moderation.video.mb') : '—' }}</dd>
                            </dl>
                        </div>
                        <div class="card-footer d-flex flex-wrap gap-2 justify-content-end">
                            @if ($row['own'])
                                <span class="small text-secondary me-auto">{{ __('admin.moderation.video.own') }}</span>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="openDecision({{ $post->id }}, 'reject')">
                                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i>{{ __('admin.moderation.video.actions.reject') }}<span class="visually-hidden"> {{ __('admin.moderation.video.sr_video', ['pseudo' => $post->user?->pseudo ?? '']) }}</span>
                                </button>
                                <button type="button" class="btn btn-sm btn-primary" wire:click="openDecision({{ $post->id }}, 'approve')">
                                    <i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ __('admin.moderation.video.actions.approve') }}<span class="visually-hidden"> {{ __('admin.moderation.video.sr_video', ['pseudo' => $post->user?->pseudo ?? '']) }}</span>
                                </button>
                            @endif
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
        <div class="mt-3">{{ $paginator->links() }}</div>
    @endif

    <x-admin.modal id="xmVideoDecide" size="lg" heading="h2" :title="$decision === 'reject' ? __('admin.moderation.video.reject_title') : __('admin.moderation.video.approve_title')"
                   x-data x-on:hidden-bs-modal.dot="$wire.closeDecision()">
        @if ($decision === 'approve')
            <x-alert type="info" class="small">{{ __('admin.moderation.video.approve_effect') }}</x-alert>
            <x-slot:footer>
                <button type="button" class="btn btn-primary" wire:click="approve" wire:loading.attr="disabled" wire:target="approve">{{ __('admin.moderation.video.actions.approve') }}</button>
            </x-slot:footer>
        @elseif ($decision === 'reject')
            <x-alert type="info" class="small">{{ __('admin.moderation.video.reject_effect', ['days' => (int) config('xomiton.feed.video_rejected_retention_days', 7)]) }}</x-alert>
            <form id="xmVideoRejectForm" wire:submit="reject" novalidate>
                <label class="form-label fw-semibold" for="xmVideoReason">{{ __('admin.moderation.video.reason') }}</label>
                <select class="form-select mb-3 @error('reasonCode') is-invalid @enderror" id="xmVideoReason" wire:model="reasonCode" @error('reasonCode') aria-invalid="true" aria-describedby="xmVideoReasonError" @enderror>
                    <option value="">{{ __('admin.moderation.video.reason_placeholder') }}</option>
                    @foreach ($reasons as $code)
                        <option value="{{ $code }}">{{ __('feed.video.reasons.'.$code) }}</option>
                    @endforeach
                </select>
                @error('reasonCode')<div class="invalid-feedback d-block mb-2" id="xmVideoReasonError">{{ $message }}</div>@enderror
                <label class="form-label fw-semibold" for="xmVideoNote">{{ __('admin.moderation.video.note') }}</label>
                <textarea class="form-control @error('note') is-invalid @enderror" id="xmVideoNote" rows="3" maxlength="{{ $noteMax }}" wire:model="note"
                          aria-describedby="xmVideoNoteHelp @error('note') xmVideoNoteError @enderror" @error('note') aria-invalid="true" @enderror></textarea>
                <div class="form-text" id="xmVideoNoteHelp">{{ __('admin.moderation.video.note_help', ['max' => $noteMax]) }}</div>
                @error('note')<div class="invalid-feedback d-block" id="xmVideoNoteError">{{ $message }}</div>@enderror
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-danger" wire:click="reject" wire:loading.attr="disabled" wire:target="reject">{{ __('admin.moderation.video.actions.reject') }}</button>
            </x-slot:footer>
        @endif
    </x-admin.modal>
</div>
