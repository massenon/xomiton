{{--
    Page de maintenance (RG-14, P2-11, Q28) : gabarit plain, une colonne, aucun décor. Rendue avec le statut 503 par
    App\Http\Middleware\EnsureNotInMaintenance et par resources/views/errors/503.blade.php.
    Variables : message (texte personnalisable `maint_msg` ou fenêtre), startsAt / endsAt (Carbon|null), disconnected (bool).
    Les parcours garantis par RG-01 (SOS, signalement, urgences) restent accessibles et sont mis en avant.
--}}
@php
    $disconnected = $disconnected ?? false;
    $fmtDate = static fn (\Illuminate\Support\Carbon $d): string => $d->copy()->locale('fr')->isoFormat('Do MMMM YYYY');
    $fmtTime = static fn (\Illuminate\Support\Carbon $d): string => $d->format('G').' h'.($d->format('i') !== '00' ? ' '.$d->format('i') : '');
    $numbers = \App\Support\Settings::array('emergency_numbers', (array) config('xomiton.assistant.emergency_numbers', []));
@endphp
<x-layouts.plain :seo="['title' => __('maintenance.page_title'), 'index' => false]" body-class="xm-maintenance">
    <section class="text-center" data-xm-maintenance>
        <div class="display-3 text-primary"><i class="bi bi-tools" aria-hidden="true"></i></div>
        <h1 class="h2 fw-bold mt-3">{{ __('maintenance.title') }}</h1>
        <p class="lead mb-3">{{ $message }}</p>

        @if ($disconnected)
            <x-alert type="info" class="text-start">{{ __('maintenance.disconnected') }}</x-alert>
        @endif

        @if ($startsAt)
            <p class="text-body-secondary mb-1">{{ __('maintenance.since', ['date' => $fmtDate($startsAt), 'time' => $fmtTime($startsAt)]) }}</p>
        @endif
        <p class="fw-bold">
            @if ($endsAt)
                {{ __('maintenance.until', ['date' => $fmtDate($endsAt), 'time' => $fmtTime($endsAt)]) }}
            @else
                {{ __('maintenance.until_unknown') }}
            @endif
        </p>

        <a class="btn btn-outline-primary" href="{{ url()->current() }}"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>{{ __('maintenance.refresh') }}</a>
    </section>

    <section class="card border-danger-subtle mt-4" aria-labelledby="maintenance-help-title">
        <div class="card-body">
            <h2 class="h5 fw-bold text-danger" id="maintenance-help-title"><i class="bi bi-heart-pulse-fill me-1" aria-hidden="true"></i>{{ __('maintenance.always_available.title') }}</h2>
            <p class="mb-3">{{ __('maintenance.always_available.text') }}</p>
            <div class="d-flex flex-column flex-sm-row gap-2">
                <a class="btn btn-danger fw-bold flex-fill" href="{{ route('sos') }}"><i class="bi bi-exclamation-octagon-fill me-1" aria-hidden="true"></i>{{ __('maintenance.always_available.sos') }}</a>
                <a class="btn btn-outline-danger flex-fill" href="{{ route('signaler') }}"><i class="bi bi-flag-fill me-1" aria-hidden="true"></i>{{ __('maintenance.always_available.report') }}</a>
                <a class="btn btn-outline-secondary flex-fill" href="{{ route('urgences') }}"><i class="bi bi-telephone-fill me-1" aria-hidden="true"></i>{{ __('maintenance.always_available.emergency') }}</a>
            </div>

            @if ($numbers !== [])
                <h3 class="h6 fw-bold mt-4 mb-2">{{ __('maintenance.always_available.numbers_title') }}</h3>
                <ul class="list-unstyled mb-0">
                    @foreach ($numbers as $entry)
                        @if (is_array($entry) && isset($entry['label'], $entry['number']))
                            <li class="d-flex justify-content-between align-items-center py-2 border-top">
                                <span>{{ $entry['label'] }}</span>
                                <a class="fw-bold text-decoration-none" href="tel:{{ preg_replace('/[^+0-9]/', '', (string) $entry['number']) }}">{{ $entry['number'] }}</a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <p class="text-center small text-body-secondary mt-4 mb-1"><a href="{{ route('connexion') }}">{{ __('maintenance.admin_login') }}</a></p>
    <p class="text-center small text-body-secondary mb-0"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>{{ __('maintenance.lock_screen_notice') }}</p>
</x-layouts.plain>
