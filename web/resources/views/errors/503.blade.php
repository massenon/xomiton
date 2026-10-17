{{--
    Erreur 503 (P4-11) : si la maintenance applicative est active (RG-14), la page de maintenance avec son message et ses
    dates ; sinon (abort(503) ponctuel, service indisponible), une page sobre équivalente avec les textes errors.503.
    Le mode maintenance normal passe par App\Http\Middleware\EnsureNotInMaintenance, pas par une exception.
    La lecture du statut est protégée : un 503 levé parce que la base est injoignable doit quand même s'afficher.
--}}
@php
    try {
        $snapshot = \App\Application\Platform\MaintenanceStatus::snapshot();
    } catch (\Throwable) {
        $snapshot = null;
    }
@endphp
@if ($snapshot?->active)
    @include('maintenance', $snapshot->toViewData(false))
@else
    @include('maintenance', [
        'message' => __('errors.503.text'),
        'startsAt' => null,
        'endsAt' => null,
        'disconnected' => false,
    ])
@endif
