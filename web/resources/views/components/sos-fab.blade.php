{{--
    Bouton SOS flottant des espaces connectés (P5-08 ; gabarits public connecté et membre, jamais sur /sos ni en
    authentification) : x-sos-button fixe (.xm-sos-fab, 64 px, z-index 1060 au-dessus des modales, D8) en mode managed,
    piloté par le composant Alpine xmSosFab (resources/js/public/sos.js). Au terme du maintien 2 s + décompte 5 s : envoi
    immédiat par POST /api/v1/sos (Idempotency-Key), sans GPS (Permissions-Policy n'accorde la géolocalisation qu'à /sos
    et /signaler) mais avec le département et la commune du profil, puis ouverture du suivi /sos?alerte={id}. Hors ligne :
    message, appel du premier numéro d'urgence, nouvel essai au retour du réseau (data-outbox : boîte d'envoi P5-10).
    Sans JavaScript (ou avant le démarrage d'Alpine, ou si sos.js ne se charge pas) : lien vers /sos à la même place ;
    le bouton Alpine, inerte sans script, reste masqué (x-cloak) et prend la place du lien au démarrage.
    Props : user (défaut : compte connecté).
--}}
@props(['user' => null])
@php
    $user ??= auth()->user();
    $number = \App\Application\Shared\KnowledgeBaseAnswerer::emergencyNumbers()[0]['number'] ?? null;
    $config = [
        'endpoint' => route('api.sos.store'),
        'csrfUrl' => url('/sanctum/csrf-cookie'),
        'followUrl' => route('sos', ['alerte' => '__ID__']),
        'pageUrl' => route('sos'),
        'zoneId' => $user?->zone_id === null ? null : (int) $user->zone_id,
        'communeId' => $user?->commune_id === null ? null : (int) $user->commune_id,
        'number' => $number,
        'texts' => [
            'sent' => __('components.sos.sent'),
            'sentText' => __('alerts.sos.fab.sent_text'),
            'detached' => __('alerts.sos.fab.detached'),
            'offlineTitle' => __('alerts.sos.fab.offline_title'),
            'offline' => __('alerts.sos.fab.offline'),
            'failedTitle' => __('alerts.sos.fab.failed_title'),
            'failed' => __('alerts.sos.fab.failed'),
            'call' => __('alerts.sos.fab.call'),
            'openPage' => __('alerts.sos.fab.open_page'),
        ],
    ];
@endphp
<div x-data="xmSosFab(@js($config))" x-on:sos-fab-send="onSend($event.detail)" data-outbox="sos" {{ $attributes->class(['no-print']) }}>
    <x-sos-button class="xm-sos-fab" id="xmSosFab" action="sos-fab-send" :managed="true" :confirm="__('alerts.sos.fab.confirm')" x-cloak />
    <div class="xm-sos-wrap xm-sos-wrap--fixed xm-sos-fab" x-init="$el.remove()">
        <a class="xm-sos" href="{{ route('sos') }}" aria-label="{{ __('alerts.sos.fab_title') }}">{{ __('components.sos.label') }}</a>
    </div>
</div>
