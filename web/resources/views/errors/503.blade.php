{{--
    Erreur 503 (P4-11) : si la maintenance applicative est active (RG-14), la page de maintenance avec son message et ses
    dates ; sinon (abort(503) ponctuel, base injoignable, panne), la même page autonome (P13-07) en variante `generic` :
    titre neutre « Service momentanément indisponible », bandeau « si le SOS en ligne ne répond pas, appelez
    directement… » avec les numéros, sans carte « Pendant la maintenance » ni promesse que le SOS en ligne fonctionne,
    sans compte à rebours ni vérification de réouverture. Le mode maintenance normal passe par
    App\Http\Middleware\EnsureNotInMaintenance, pas par une exception. La lecture du statut est protégée : un 503 levé
    parce que la base est injoignable doit quand même s'afficher (numéros de la configuration en repli).
--}}
@php
    try {
        $snapshot = \App\Application\Platform\MaintenanceStatus::snapshot();
    } catch (\Throwable) {
        $snapshot = null;
    }
    $generic = ! ($snapshot?->active ?? false);
    $data = \App\Application\Platform\MaintenancePage::data(
        $generic ? new \App\Application\Platform\MaintenanceSnapshot(false, null, (string) __('maintenance.generic.message'), null, null) : $snapshot,
        generic: $generic,
    );
@endphp
@include('maintenance', $data)
