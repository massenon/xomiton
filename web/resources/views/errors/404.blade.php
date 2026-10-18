{{-- Erreur 404 (P3-05, P4-11 ; prototype error-404.php) : page sobre, retour à l'accueil et centre d'aide. --}}
<x-error-page :code="404" :title="__('errors.404.title')" :text="__('errors.404.text')"
    :action="['label' => __('errors.404.action'), 'href' => route('home')]"
    :secondary="['label' => __('errors.404.secondary'), 'href' => route('aide.index')]"
    icon="signpost-split" />
