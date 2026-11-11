{{--
    Navigation d'étape (JavaScript seulement : sans lui, toutes les étapes sont affichées) : « Retour » toujours présent
    (vers l'accueil à la première étape : le brouillon local est alors effacé, leave()), un seul bouton primaire « Continuer ». `hidden` sur un conteneur sans d-flex
    (la classe utilitaire, !important, l'emporterait sur [hidden]).
--}}
<div class="xm-report__nav mt-4" hidden x-bind:hidden="false">
    <div class="d-flex flex-wrap justify-content-between gap-2">
        @if ($n === 1)
            <a class="btn btn-light border" href="{{ route('home') }}" x-on:click="leave($event)"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('common.back') }}</a>
        @else
            <button type="button" class="btn btn-light border" x-on:click="back()"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('common.back') }}</button>
        @endif
        <button type="button" class="btn btn-primary ms-auto" x-on:click="next()">{{ __('common.next') }}<i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></button>
    </div>
</div>
