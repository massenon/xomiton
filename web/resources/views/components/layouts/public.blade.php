{{--
    Gabarit public (FRONT_END_LAYOUT_INSTRUCTION.md §1) : lien d'évitement, bandeau hors connexion, en-tête sticky,
    main#contenu, pied de page 4 colonnes avec newsletter, bouton SOS flottant si connecté·e.
    Props : seo (tableau pour <x-seo>), pubNav (clé du lien actif), bodyClass.
--}}
@props(['seo' => [], 'pubNav' => '', 'bodyClass' => ''])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    @include('partials.head', ['seo' => $seo, 'critical' => true])
    @include('partials.offline', ['sw' => true])
    @include('partials.speculation-rules')
    {{ $head ?? '' }}
</head>
<body @class([$bodyClass => $bodyClass !== ''])>
    <a class="xm-skip-link" href="#contenu">{{ __('menus.skip_to_content') }}</a>
    <div class="xm-offline-bar" id="xmOfflineBar" role="status"><i class="bi bi-wifi-off me-1" aria-hidden="true"></i> {{ __('menus.offline_banner') }}</div>
    @include('partials.public-header', ['pubNav' => $pubNav])
    <main id="contenu" tabindex="-1">
        {{-- P13-08 : annonce préalable d'une maintenance planifiée (refermable, sans bloquer). --}}
        @include('partials.maintenance-notice')
        @auth
            @if (! auth()->user()->hasVerifiedEmail())
                {{-- Q12 c : rappel tant que l'e-mail n'est pas confirmé, avec « Renvoyer le code ». --}}
                <div class="container pt-3">
                    @include('auth.partials.verify-banner', ['user' => auth()->user()])
                </div>
            @endif
        @endauth
        {{ $slot }}
    </main>
    @include('partials.public-footer')
    @auth
        {{-- P5-08 : bouton SOS flottant (maintien 2 s + décompte 5 s, z-index 1060 au-dessus des modales et des toasts). --}}
        <x-sos-fab />
    @endauth
    {{--
        Pas de <link rel="modulepreload"> pour public.js et ses dépendances : ces préchargements sont à priorité « haute » et
        se disputent la bande passante avec la feuille de style, qui bloque le premier rendu (mesure, 4G lente : FCP
        −150 ms, chargement −170 ms, docs/PERFORMANCE.md §11). Le module reste différé et démarre juste après le CSS.
    --}}
    @php(\Illuminate\Support\Facades\Vite::usePreloadTagAttributes(static fn (string $src, string $url): array|false => str_ends_with($url, '.js') ? false : []))
    @vite('resources/js/public.js')
    {{ $scripts ?? '' }}
</body>
</html>
