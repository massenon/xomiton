{{--
    Suivi par code (P5-04, FR-ALT-01) sans compte : saisie du code (POST, limiteur `tracking`), puis statut, phrase de
    suivi par statut, dates et étapes (FindAlertByTrackingCode, mêmes champs que AlertTrackingResource : du contact
    routé, le rôle seulement). Code inconnu : un seul message neutre. Gabarit sobre (Q18 a), tu (Q47), non indexée.
--}}
@php
    $formatter = \App\Support\LocaleFormatter::make();
@endphp
<x-layouts.plain :seo="$seo">
    <h1 class="h3 fw-bold mb-2">{{ __('alerts.report.tracking.title') }}</h1>
    <p class="xm-report__lead mb-4">{{ __('alerts.report.tracking.intro') }}</p>

    @if ($searched && $alert === null)
        <x-alert type="warning" class="mb-4">{{ __('alerts.report.tracking.not_found', ['days' => (int) config('xomiton.alerts.tracking_visible_days', 90)]) }}</x-alert>
    @endif

    @if ($alert !== null)
        @php
            $history = $alert->statusChanges->map(fn ($change): array => [
                'title' => $change->to_status->label(),
                'date' => $formatter->dateTime($change->created_at),
                'datetime' => $change->created_at?->toIso8601String(),
                'kind' => 'status',
            ])->values()->all();
        @endphp
        <section class="card card-body mb-4" aria-labelledby="suivi-resultat">
            <h2 class="h5 fw-bold mb-3" id="suivi-resultat">{{ __('alerts.report.tracking.result_title', ['code' => $alert->displayCode()]) }}</h2>
            <p class="mb-2">
                <x-badge :color="$alert->status->badge()">{{ __('alerts.report.tracking.status', ['status' => $alert->status->label()]) }}</x-badge>
            </p>
            <p class="xm-report__lead">{{ __('alerts.report.status_text.'.$alert->status->value) }}</p>
            <ul class="list-unstyled small text-secondary mb-3">
                <li>{{ __('alerts.report.tracking.received_on', ['date' => $formatter->dateTime($alert->created_at)]) }}</li>
                <li>{{ __('alerts.report.tracking.updated', ['date' => $formatter->dateTime($alert->updated_at)]) }}</li>
                @if ($alert->isAcknowledged() && $alert->routedContact !== null)
                    <li>{{ __('alerts.report.tracking.handled_by') }} ({{ $alert->routedContact->role->label() }})</li>
                @endif
            </ul>
            @if ($history !== [])
                <h3 class="h6 fw-bold">{{ __('alerts.report.tracking.history') }}</h3>
                <x-timeline :items="$history" :label="__('alerts.report.tracking.history')" />
            @endif
        </section>
    @endif

    <form method="POST" action="{{ route('signaler.suivi.show') }}" class="card card-body mb-4" novalidate>
        @csrf
        <x-field name="tracking_code" :label="__('alerts.report.tracking.code')" :placeholder="__('alerts.report.tracking.code_placeholder')"
                 :value="$code" :hint="__('alerts.report.tracking.no_code')" autocomplete="off" :maxlength="40" required autocapitalize="characters" spellcheck="false" />
        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">{{ __('alerts.report.tracking.submit') }}</button>
        </div>
    </form>

    <p class="small text-center mb-0">
        <a href="{{ route('signaler') }}">{{ __('buttons.help.report_short') }}</a> · <a href="{{ route('urgences') }}">{{ __('buttons.help.emergency_contacts') }}</a>
    </p>
</x-layouts.plain>
