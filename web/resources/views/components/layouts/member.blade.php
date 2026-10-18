{{--
    Gabarit membre minimal (FRONT_END_LAYOUT_INSTRUCTION.md §2 ; reste de P2 « gabarit membre avec déconnexion ») : coquille
    Blade temporaire avant l'espace Vue + Inertia (P6, D9). En-tête connecté porté de prototype partials/header.php
    (logo, recherche, SOS, messages, réglages, cloche x-bell, avatar avec menu et formulaire de déconnexion), sidenav de
    partials/sidenav.php en col-lg-3, contenu en col-lg-9, bandeau « Confirme ton e-mail » (Q12 c), SOS flottant.
    Props : seo (tableau pour <x-seo>, jamais indexé), memberNav (clé du lien actif), bodyClass.
--}}
@props(['seo' => [], 'memberNav' => '', 'bodyClass' => ''])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    @include('partials.head', ['seo' => ['index' => false] + $seo])
    {{ $head ?? '' }}
</head>
<body @class(['xm-member', $bodyClass => $bodyClass !== ''])>
    <a class="xm-skip-link" href="#contenu">{{ __('menus.skip_to_content') }}</a>
    <div class="xm-offline-bar" id="xmOfflineBar" role="status"><i class="bi bi-wifi-off me-1" aria-hidden="true"></i> {{ __('menus.member_more.offline_banner') }}</div>
    @include('partials.member-header', ['memberNav' => $memberNav])
    <main id="contenu" tabindex="-1" class="py-4">
        <div class="container">
            <div class="row g-4">
                @include('partials.member-sidenav', ['memberNav' => $memberNav])
                <div class="col-lg-9">
                    @auth
                        {{-- Q12 c : rappel tant que l'e-mail n'est pas confirmé, avec « Renvoyer le code ». --}}
                        @include('auth.partials.verify-banner', ['user' => auth()->user()])
                    @endauth
                    {{ $slot }}
                </div>
            </div>
        </div>
    </main>
    <x-toast :message="session('toast')" :type="session('toast_type', 'primary')" />
    <a class="xm-sos-fab no-print" href="{{ route('sos') }}" title="{{ __('common.sos_title') }}"><span class="pulse" aria-hidden="true"></span> {{ __('common.sos') }}</a>
    @vite('resources/js/public.js')
    {{ $scripts ?? '' }}
</body>
</html>
