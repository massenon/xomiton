{{--
    Gabarit administration (FRONT_END_LAYOUT_INSTRUCTION.md §6, prototype admin-top.php / admin-bottom.php).
    Utilisé par les composants Livewire pleine page (#[Layout('components.layouts.admin')]).
    Props : title, adminPage (clé du menu, sinon déduite de la route), breadcrumb, actions (slot), seo.
    Socle P10-01 à P10-03 : barre latérale Q14 (partials/admin-sidebar), barre supérieure et cloche Livewire
    (partials/admin-topbar), messages flashés rejoués en toasts SweetAlert2, piles @stack('adminHead') et
    @stack('adminScripts') pour les écrans qui chargent Chart.js, Quill, Choices ou flatpickr à la demande.
    Mode maintenance (P13-06, P13-08) : l'administration n'est jamais bloquée ; pendant la maintenance, bandeau rouge
    permanent (le public voit la page de maintenance) et pastille « Actif » du menu ; pendant le délai d'annonce d'une
    fenêtre planifiée, le bandeau d'annonce refermable commun aux gabarits.
    @persist est écrit à la main (<div x-persist="nom">, ce que la directive produit) : la directive appelle
    Livewire::forceAssetInjection(), un drapeau statique qui injecterait Livewire dans toute page servie ensuite par le
    même processus (inject_assets=false : le public ne reçoit jamais Livewire). Noms uniques obligatoires.
    Navigation sans rechargement (wire:navigate, resources/js/admin/navigation.js) : Livewire remplace le <body> et fusionne
    le <head>. admin.js est posé dans le <head> (data-navigate-track, chargé une fois) ; la barre latérale (@persist) garde
    son défilement et son état, son entrée active (aria-current) et ses pastilles sont recalculées d'après #xmNavState ;
    la cloche est persistée (compteur et sondage ne repartent pas de zéro) ; la région aria-live #xmRouteAnnouncer annonce
    « Page : <titre> » et le focus passe au h1. Le bandeau SOS vit dans l'écran tableau de bord (wire:poll) : il est
    rendu par la page, pas persisté, pour ne jamais afficher un SOS périmé. Les scripts en ligne portent le nonce de la
    requête ; le nonce d'une navigation est aligné sur celui du document par navigation.js.
--}}
@props(['title' => null, 'adminPage' => '', 'breadcrumb' => [], 'seo' => []])
@php
    try {
        $maintenanceActive = \App\Application\Platform\MaintenanceStatus::active();
    } catch (\Throwable) {
        $maintenanceActive = false;
    }
    $navGroups = app(\App\Application\Admin\AdminNavigation::class)->groups(auth()->user(), $adminPage, $maintenanceActive);
    $navState = ['current' => null, 'badges' => []];
    foreach ($navGroups as $navGroup) {
        foreach ($navGroup['items'] as $navItem) {
            if ($navItem['current']) {
                $navState['current'] = $navItem['key'];
            }
            if ($navItem['badge'] !== null) {
                $navState['badges'][$navItem['key']] = ['text' => $navItem['badge'], 'color' => $navItem['badgeColor'], 'label' => $navItem['badgeLabel']];
            }
        }
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    @include('partials.head', ['seo' => ['title' => $title ?? __('menus.admin.breadcrumb_root'), 'index' => false] + $seo, 'css' => ['resources/css/xomiton.css', 'resources/css/admin.css']])
    @livewireStyles
    @vite('resources/js/admin.js')
    {{-- Barre latérale repliée mémorisée sur l'appareil (P10-01) : posée avant le premier rendu, sans saut de mise en page. --}}
    <script nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}">try { if (localStorage.getItem('xm-admin-sidebar') === 'collapsed') document.documentElement.classList.add('xm-sidebar-collapsed'); } catch (e) {}</script>
    {{ $head ?? '' }}
    @stack('adminHead')
</head>
<body class="xm-admin">
    <a class="xm-skip-link" href="#contenu">{{ __('menus.skip_to_content') }}</a>
    <div class="xm-offline-bar" id="xmOfflineBar" role="status"><i class="bi bi-wifi-off me-1" aria-hidden="true"></i> {{ __('menus.offline_banner_admin') }}</div>
    <div x-persist="admin-sidebar">
        @include('partials.admin-sidebar', ['adminPage' => $adminPage, 'maintenanceActive' => $maintenanceActive, 'groups' => $navGroups])
    </div>
    {{-- Annonce vocale du changement de page (navigation sans rechargement) : région persistée, donc présente avant chaque annonce. --}}
    <div x-persist="admin-announcer">
        <div class="visually-hidden" id="xmRouteAnnouncer" role="status" aria-live="polite" aria-atomic="true"></div>
    </div>
    <div class="xm-admin-main">
        @include('partials.admin-topbar', ['title' => $title ?? __('menus.admin.breadcrumb_root'), 'breadcrumb' => $breadcrumb, 'actions' => $actions ?? null])
        <main class="xm-admin-content" id="contenu" tabindex="-1">
            @if ($maintenanceActive)
                <x-alert type="danger" icon="cone-striped" class="xm-maint-banner" data-xm-maintenance-banner>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <span>{{ __('maintenance.admin.banner_active') }}</span>
                        @can('platform.maintenance')
                            @if ($adminPage !== 'maintenance')<a wire:navigate wire:navigate.hover class="btn btn-sm btn-light border" href="{{ route('admin.maintenance') }}">{{ __('maintenance.admin.banner_manage') }}</a>@endif
                        @endcan
                    </div>
                </x-alert>
            @else
                @include('partials.maintenance-notice', ['class' => 'mb-3'])
            @endif
            {{ $slot }}
        </main>
        <footer class="px-4 py-3 small text-secondary border-top no-print">
            {{ __('menus.admin.footer', ['version' => config('xomiton.version', '1.0'), 'date' => \App\Support\LocaleFormatter::make()->tableDateTime(now())]) }}
        </footer>
    </div>
    {{-- Message flashé avant une redirection (toast / status) : rejoué en toast SweetAlert2 par admin.js (P10-03). --}}
    @php
        $flash = session('toast') ?? session('status');
        $flashType = session('toast') !== null ? session('toast_type', 'success') : session('status_type', 'success');
    @endphp
    @if (is_string($flash) && $flash !== '')
        <template data-xm-flash-toast data-type="{{ in_array($flashType, ['success', 'info', 'warning', 'error', 'danger'], true) ? $flashType : 'success' }}">{{ $flash }}</template>
    @endif
    @php
        $adminI18n = [
            'close' => __('common.close'),
            'confirmTitle' => __('admin.confirm.title'),
            'confirm' => __('admin.confirm.confirm'),
            'cancel' => __('admin.confirm.cancel'),
            'prompt' => __('admin.confirm.prompt'),
            'promptLabel' => __('admin.confirm.prompt_label'),
            'mismatch' => __('admin.confirm.mismatch'),
            'pageAnnounce' => __('admin.nav.page_announce'),
            'navReload' => __('admin.nav.reloading'),
        ];
    @endphp
    <script type="application/json" id="xmAdminI18n">@json($adminI18n)</script>
    <script type="application/json" id="xmNavState">@json($navState)</script>
    @livewireScripts
    {{-- Scripts d'écran (ADR-18) : <script type="module" nonce> ; bibliothèques à la demande par `await XM.admin.load('chart')`. --}}
    @stack('adminScripts')
    {{ $scripts ?? '' }}
</body>
</html>
