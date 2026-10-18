{{--
    Gabarit sobre (FRONT_END_LAYOUT_INSTRUCTION.md §5 ; design-system/xomiton/pages/signaler-et-sos.md) :
    une colonne de 640 px, fond uni, aucun décor. En-tête minimal (logo, Urgences, Se connecter, Créer un compte).
--}}
@props(['seo' => [], 'bodyClass' => ''])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    @include('partials.head', ['seo' => $seo, 'critical' => true])
    @include('partials.offline', ['sw' => true])
    {{ $head ?? '' }}
</head>
<body @class(['xm-plain', $bodyClass => $bodyClass !== ''])>
    <a class="xm-skip-link" href="#contenu">{{ __('menus.skip_to_content') }}</a>
    <div class="xm-offline-bar" id="xmOfflineBar" role="status"><i class="bi bi-wifi-off me-1" aria-hidden="true"></i> {{ __('menus.offline_banner') }}</div>
    <header class="navbar-light bg-mode sticky-top shadow-sm no-print">
        <nav class="navbar" aria-label="{{ __('menus.menu') }}">
            <div class="container" style="max-width:640px">
                <x-logo class="navbar-brand" :href="route('home')" :adaptive="true" :width="140" img-class="navbar-brand-item" />
                <div class="d-flex gap-2 align-items-center">
                    {{-- Sur /urgences : lien vers la page courante, neutre (rouge réservé au SOS, Q62) et aria-current. --}}
                    <a @class(['btn btn-sm fw-bold', 'btn-secondary-soft' => request()->routeIs('urgences'), 'btn-danger-soft' => ! request()->routeIs('urgences')]) href="{{ route('urgences') }}" @if (request()->routeIs('urgences')) aria-current="page" @endif><i class="bi bi-telephone-fill me-1" aria-hidden="true"></i>{{ __('menus.member.emergency') }}</a>
                    @auth
                        <a class="btn btn-sm btn-primary" href="{{ route('fil') }}">{{ __('menus.public.my_space') }}</a>
                    @else
                        <a class="btn btn-sm btn-outline-primary d-none d-sm-inline-flex" href="{{ route('connexion') }}">{{ __('menus.public.sign_in') }}</a>
                    @endauth
                </div>
            </div>
        </nav>
    </header>
    <main id="contenu" tabindex="-1" class="py-4 py-sm-5">
        <div class="container" style="max-width:640px">
            {{ $slot }}
        </div>
    </main>
    @vite('resources/js/public.js')
    {{ $scripts ?? '' }}
</body>
</html>
