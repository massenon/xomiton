{{--
    Calendrier des événements (P18-14) : composant App\Livewire\Admin\Events\Index, gabarit admin.
    Ordre : introduction · réglage de la file d'approbation · file « En attente » · navigation du mois · semaines du mois
    (liste par semaine, lundi → dimanche, heure du Bénin) · modale de refus.
--}}
<div class="xm-events-admin">
    <p class="text-secondary mb-4 xm-prose">{{ __('admin_events.intro') }}</p>

    <section class="card xm-admin-card mb-4" aria-labelledby="xmEvApprovalTitle">
        <div class="card-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <div>
                <h2 class="h6 fw-bold mb-1" id="xmEvApprovalTitle">{{ __('admin_events.approval.title') }}</h2>
                <p class="small text-secondary mb-0">{{ $approvalRequired ? __('admin_events.approval.on') : __('admin_events.approval.off') }}</p>
            </div>
            @if ($canConfigure)
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="xmEvApproval" wire:click="toggleApproval" @checked($approvalRequired) aria-describedby="xmEvApprovalTitle">
                    <label class="form-check-label" for="xmEvApproval">{{ __('admin_events.approval.switch') }}</label>
                </div>
            @endif
        </div>
    </section>

    @if ($this->pending->isNotEmpty())
        <section class="card xm-admin-card mb-4" aria-labelledby="xmEvPendingTitle">
            <div class="card-header"><h2 class="h6 fw-bold mb-0" id="xmEvPendingTitle">{{ trans_choice('admin_events.pending.title', $this->pending->count(), ['count' => $this->pending->count()]) }}</h2></div>
            <ul class="list-group list-group-flush">
                @foreach ($this->pending as $event)
                    <li class="list-group-item d-flex flex-wrap gap-2 align-items-center justify-content-between" wire:key="pending-{{ $event->id }}">
                        <div>
                            <div class="fw-semibold text-break">{{ $event->title }}</div>
                            <div class="small text-secondary">{{ $format->tableDateTime($event->starts_at) }} · {{ $event->eventType()->label() }} · {{ __('admin_events.by', ['name' => $event->organizerLabel()]) }}</div>
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('evenements.show', $event->ulid) }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>{{ __('admin_events.actions.view') }}<span class="visually-hidden"> {{ $event->title }} {{ __('microcopy.a11y.opens_new_tab') }}</span></a>
                            @if ($canDecide)
                                <button type="button" class="btn btn-sm btn-success" wire:click="approve({{ $event->id }})"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i>{{ __('admin_events.actions.approve') }}<span class="visually-hidden"> {{ $event->title }}</span></button>
                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="askRefuse({{ $event->id }})">{{ __('admin_events.actions.refuse') }}<span class="visually-hidden"> {{ $event->title }}</span></button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="card xm-admin-card" aria-labelledby="xmEvMonthTitle">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h2 class="h6 fw-bold mb-0 text-capitalize" id="xmEvMonthTitle" aria-live="polite">{{ $monthLabel }}</h2>
            <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('admin_events.nav_label') }}">
                <button type="button" class="btn btn-outline-secondary" wire:click="shift(-1)"><i class="bi bi-chevron-left" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_events.previous') }}</span></button>
                <button type="button" class="btn btn-outline-secondary" wire:click="today">{{ __('admin_events.current') }}</button>
                <button type="button" class="btn btn-outline-secondary" wire:click="shift(1)"><i class="bi bi-chevron-right" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_events.next') }}</span></button>
            </div>
        </div>
        <div class="card-body">
            @if ($this->events->isEmpty())
                <x-admin.empty icon="calendar-event" :title="__('admin_events.empty')" />
            @else
                @foreach ($weeks as $week)
                    <h3 class="h6 mt-3 mb-2 text-secondary">{{ __('admin_events.week', ['start' => $format->dayMonth($week['start']), 'end' => $format->dayMonth($week['end'])]) }}</h3>
                    @if ($week['events'] === [])
                        <p class="small text-secondary mb-0">{{ __('admin_events.week_empty') }}</p>
                    @else
                        <ul class="list-unstyled mb-0">
                            @foreach ($week['events'] as $event)
                                @php($status = $event->status)
                                <li class="d-flex flex-wrap gap-2 align-items-start border-bottom py-2" wire:key="event-{{ $event->id }}">
                                    <time class="small fw-semibold text-nowrap" style="min-width: 9rem" datetime="{{ \Carbon\CarbonImmutable::parse((string) $event->getRawOriginal('starts_at'), 'UTC')->toIso8601String() }}">{{ $format->tableDateTime($event->starts_at) }}</time>
                                    <div class="flex-grow-1">
                                        <a class="fw-semibold text-break" href="{{ route('evenements.show', $event->ulid) }}" target="_blank" rel="noopener noreferrer">{{ $event->title }}<span class="visually-hidden"> {{ __('microcopy.a11y.opens_new_tab') }}</span></a>
                                        <div class="small text-secondary">{{ $event->place ?: ($event->is_online ? __('admin_events.online') : '—') }} · {{ trans_choice('admin_events.going', (int) $event->going_count, ['count' => (int) $event->going_count]) }}</div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1">
                                        <x-badge color="primary" soft :icon="$event->eventType()->icon()">{{ $event->eventType()->label() }}</x-badge>
                                        <x-badge :color="$status->badge()" soft>{{ $status->label() }}</x-badge>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endforeach
            @endif
        </div>
    </section>

    <x-admin.modal id="xmEventRefuse" size="md" :title="__('admin_events.refuse_title')" x-on:shown-bs-modal.dot="$el.querySelector('textarea')?.focus()">
        <form id="xmEventRefuseForm" wire:submit="refuse" novalidate>
            <label class="form-label fw-semibold" for="xmEventReason">{{ __('admin_events.fields.reason') }}</label>
            <textarea class="form-control @error('reason') is-invalid @enderror" id="xmEventReason" rows="3" wire:model="reason" maxlength="{{ \App\Application\Community\Events\ReviewEvent::REASON_MAX }}" required
                      aria-describedby="xmEventReasonHelp @error('reason') xmEventReasonError @enderror" @error('reason') aria-invalid="true" @enderror></textarea>
            <div class="form-text" id="xmEventReasonHelp">{{ __('admin_events.reason_help') }}</div>
            @error('reason')<div class="invalid-feedback d-block" id="xmEventReasonError">{{ $message }}</div>@enderror
        </form>
        <x-slot:footer>
            <button type="submit" form="xmEventRefuseForm" class="btn btn-danger" wire:loading.attr="disabled" wire:target="refuse">{{ __('admin_events.actions.refuse') }}</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
