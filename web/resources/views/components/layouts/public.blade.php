{{--
    Gabarit public (FRONT_END_LAYOUT_INSTRUCTION.md §1) : lien d'évitement, bandeau hors connexion, en-tête sticky,
    main#contenu, pied de page 4 colonnes avec newsletter, bouton SOS flottant si connecté·e.
    Props : seo (tableau pour <x-seo>), pubNav (clé du lien actif), bodyClass.
--}}
@props(['seo' => [], 'pubNav' => '', 'bodyClass' => ''])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    @include('partials.head', ['seo' => $seo])
    {{ $head ?? '' }}
</head>
<body @class([$bodyClass => $bodyClass !== ''])>
    <a class="xm-skip-link" href="#contenu">{{ __('menus.skip_to_content') }}</a>
    <div class="xm-offline-bar" id="xmOfflineBar" role="status"><i class="bi bi-wifi-off me-1" aria-hidden="true"></i> {{ __('menus.offline_banner') }}</div>
    @include('partials.public-header', ['pubNav' => $pubNav])
    <main id="contenu" tabindex="-1">
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
        <a class="xm-sos-fab no-print" href="{{ route('sos') }}" title="{{ __('common.sos_title') }}"><span class="pulse" aria-hidden="true"></span> {{ __('common.sos') }}</a>
    @endauth
    @vite('resources/js/public.js')
    {{ $scripts ?? '' }}
</body>
</html>
