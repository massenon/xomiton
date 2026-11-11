{{--
    Bandeau d'annonce préalable d'une maintenance (P13-08) : gabarits public, connexion et administration, pendant le
    délai d'annonce d'une fenêtre planifiée (P13-06). Refermable sans bloquer : fermé, il reste masqué pour la session
    de l'onglet (sessionStorage, clé par fenêtre, resources/js/shared/dismissible.js ; même clé dans l'espace Vue).
    `role="status"` : une annonce planifiée n'est jamais lue de façon assertive (ACCESSIBILITE §3 : alert réservé aux
    erreurs et au SOS) ; texte ocre foncé --xm-warning-text (5:1, social-xomiton.css).
    Variables : $notice (MaintenancePage::notice(), facultatif : lu ici sinon), $class (classes du conteneur).
--}}
@php($notice ??= \App\Application\Platform\MaintenancePage::notice())
@if ($notice)
    <div @class([$class ?? 'container pt-3']) data-xm-maintenance-notice>
        <x-alert type="warning" role="status" icon="megaphone" :title="$notice['title']" dismissible class="mb-0" data-xm-dismiss-key="{{ $notice['key'] }}">
            <p class="mb-0">{{ $notice['text'] }}</p>
            @if ($notice['message'])<p class="mb-0 mt-1 small">{{ $notice['message'] }}</p>@endif
        </x-alert>
    </div>
@endif
