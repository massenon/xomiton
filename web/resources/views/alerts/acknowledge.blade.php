{{--
    Accusé de réception par lien signé (P5-02) pour un contact de l'annuaire sans compte : gabarit sobre 640 px (Q18 a),
    vous (VOICE_AND_TONE.md §3, destinataire de l'équipe), noindex. États : confirm (GET : aucun effet), already (autre
    fiche), mine (cette fiche a déjà accusé : nouveau POST pour revoir les coordonnées), closed, done (après POST : moyen
    de contact et position révélés au seul destinataire vérifié). Priorité : libellé et badge d'AlertPriority.
--}}
@php
    $formatter = \App\Support\LocaleFormatter::make();
    $code = $alert->displayCode();
    $contact = $state === 'done' ? $alert->contact : null;
    $isEmail = $contact !== null && filter_var($contact, FILTER_VALIDATE_EMAIL) !== false;
    $coordinates = $state === 'done' && $alert->isSos() ? $alert->coordinates() : null;
    $place = collect([$alert->commune?->name, $alert->zone?->name])->filter()->implode(', ');
@endphp
<x-layouts.plain :seo="['title' => __('alerts.acknowledge.title', ['code' => $code]), 'index' => false]">
    <h1 class="h3 fw-bold mb-3">{{ __('alerts.acknowledge.title', ['code' => $code]) }}</h1>

    <x-card class="mb-4">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <x-badge :color="$alert->type->badge()" :icon="\Illuminate\Support\Str::after($alert->type->icon(), 'bi-')">{{ $alert->type->label() }}</x-badge>
            <x-badge :color="$alert->priority->badge()">{{ __('alerts.acknowledge.priority', ['priority' => $alert->priority->label()]) }}</x-badge>
        </div>
        <ul class="list-unstyled mb-0">
            <li>{{ __('alerts.acknowledge.received_on', ['date' => $formatter->dateTime($alert->created_at)]) }}</li>
            @if ($place !== '')<li>{{ $place }}</li>@endif
            @if ($needs !== [])<li>{{ implode(' · ', $needs) }}</li>@endif
        </ul>
    </x-card>

    @switch($state)
        @case('closed')
            <x-alert type="info">{{ __('alerts.acknowledge.closed') }}</x-alert>
            @break

        @case('already')
            <x-alert type="info">{{ __('alerts.acknowledge.already', ['date' => $formatter->dateTime($alert->acknowledged_at)]) }}</x-alert>
            @break

        @case('mine')
            <x-alert type="info">{{ __('alerts.acknowledge.mine', ['date' => $formatter->dateTime($alert->acknowledged_at)]) }}</x-alert>
            <form method="post" action="{{ request()->fullUrl() }}">
                @csrf
                <x-button type="submit" variant="primary" size="lg" icon="person-lines-fill">{{ __('alerts.acknowledge.reveal') }}</x-button>
            </form>
            @break

        @case('done')
            <x-alert type="success" tabindex="-1" aria-live="polite" x-data x-init="$el.focus()">{{ __('alerts.acknowledge.done') }}</x-alert>

            <x-card :title="__('alerts.acknowledge.contact_title')" title-tag="h2" class="mb-3">
                @if ($contact !== null)
                    <p class="fs-5 mb-2">
                        <a class="btn btn-primary" href="{{ $isEmail ? 'mailto:'.$contact : 'tel:'.preg_replace('/[^\d+]/', '', $contact) }}">
                            <i class="bi bi-{{ $isEmail ? 'envelope' : 'telephone' }} me-1" aria-hidden="true"></i>{{ $contact }}
                        </a>
                    </p>
                    @if ($alert->preferred_channel)
                        <p class="mb-0 text-body-secondary">{{ __('alerts.acknowledge.preferred_channel', ['channel' => $alert->preferred_channel->label()]) }}</p>
                    @endif
                @else
                    <p class="mb-0">{{ __('alerts.acknowledge.no_contact') }}</p>
                @endif

                @if ($coordinates !== null)
                    <p class="mt-3 mb-1">
                        <a class="btn btn-primary-soft" target="_blank" rel="noopener" href="https://www.google.com/maps?q={{ $coordinates['lat'] }},{{ $coordinates['lng'] }}">
                            <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ __('alerts.sos.done.maps') }}
                        </a>
                    </p>
                    @if ($alert->location_accuracy !== null)
                        <p class="small text-body-secondary mb-0">{{ __('alerts.sos.done.accuracy', ['meters' => $alert->location_accuracy]) }}</p>
                    @endif
                @endif

                @if (filled($alert->description))
                    <blockquote class="blockquote fs-6 border-start ps-3 mt-3 mb-0">{{ $alert->description }}</blockquote>
                @endif
            </x-card>
            @break

        @default
            <p class="fs-5">{{ __('alerts.acknowledge.text') }}</p>
            <form method="post" action="{{ request()->fullUrl() }}">
                @csrf
                <x-button type="submit" variant="primary" size="lg" icon="check2-circle">{{ __('alerts.acknowledge.confirm') }}</x-button>
            </form>
    @endswitch
</x-layouts.plain>
