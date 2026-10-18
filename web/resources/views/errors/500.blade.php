{{-- Erreur 500 (P3-05) : aucun détail technique, l'équipe est prévenue par le journal ; retour à l'accueil et rappel de crise. --}}
<x-error-page :code="500" :title="__('errors.500.title')" :text="__('errors.500.text')"
    :action="['label' => __('errors.500.action'), 'href' => route('home')]"
    icon="tools" />
