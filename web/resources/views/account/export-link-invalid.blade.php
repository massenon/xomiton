{{--
    Lien d'export RGPD expiré ou altéré (GET /mes-donnees/export/{token}, P6-06) : page d'erreur sobre au tutoiement de
    l'espace connecté, avec le retour vers les paramètres pour redemander un export (POST me/export).
--}}
<x-error-page
    :code="403"
    :title="__('account.export.link_invalid.title')"
    :text="__('account.export.link_invalid.text', ['expiry' => trans_choice('account.export.expiry', $hours, ['hours' => $hours])])"
    :action="['label' => __('account.export.link_invalid.action'), 'href' => route('parametres')]"
    icon="clock-history"
/>
