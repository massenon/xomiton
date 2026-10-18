{{--
    Erreur 419 (jeton CSRF / session expirée ; P3-05, Q36) : « vous » pour un·e visiteur·se (errors.419.public), « tu » pour une
    personne connectée (errors.419.member). L'action renvoie vers la page précédente (formulaire à renvoyer) ou la connexion.
--}}
@php
    $registry = auth()->check() ? 'member' : 'public';
    $previous = url()->previous();
    $href = $registry === 'member' ? route('connexion') : ($previous !== '' && $previous !== url()->current() ? $previous : route('home'));
@endphp
<x-error-page :code="419" :title="__('errors.419.'.$registry.'.title')" :text="__('errors.419.'.$registry.'.text')"
    :action="['label' => __('errors.419.'.$registry.'.action'), 'href' => $href]"
    :secondary="['label' => __('common.back_home'), 'href' => route('home')]"
    icon="hourglass-split" />
