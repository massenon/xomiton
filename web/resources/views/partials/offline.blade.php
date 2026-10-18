{{--
    Hors connexion web (P5-10, ADR-26) : contrat avec resources/js/shared/outbox-boot.js et sw-register.js.
    `content` : adresse du service worker, renseignée par les gabarits public et sobre seulement ($sw vrai ; ni
    l'administration ni l'espace connecté ne l'enregistrent) ; `data-texts` : textes neutres des avis de la boîte d'envoi
    (lang/fr/offline.php) et liens de reprise, pour tous les gabarits qui chargent public.js ; `data-account` : empreinte
    du compte connecté (OutboxAccount, vide sans compte), gardée avec chaque envoi mis en file ; `data-ttl-days` : durée
    de vie d'un envoi en attente sur l'appareil (xomiton.alerts.draft_days).
--}}
@php
    $offlineTexts = (array) trans('offline.outbox') + [
        'report_url' => route('signaler', absolute: false),
        'urgences_url' => route('urgences', absolute: false),
    ];
@endphp
<meta name="xm-offline" content="{{ ($sw ?? false) ? route('service-worker', absolute: false) : '' }}" data-account="{{ \App\Application\Offline\OutboxAccount::fingerprint(auth()->user()) }}" data-ttl-days="{{ (int) config('xomiton.alerts.draft_days', 7) }}" data-texts="{{ json_encode($offlineTexts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
