{{--
    Gabarit administration (FRONT_END_LAYOUT_INSTRUCTION.md §6, prototype admin-top.php / admin-bottom.php).
    Utilisé par les composants Livewire pleine page (#[Layout('components.layouts.admin')]).
    Props : title, adminPage (clé du menu), breadcrumb, actions (slot), seo.
    Mode maintenance (P13-06, P13-08) : l'administration n'est jamais bloquée ; pendant la maintenance, bandeau rouge
    permanent (le public voit la page de maintenance) et pastille « Actif » du menu ; pendant le délai d'annonce d'une
    fenêtre planifiée, le bandeau d'annonce refermable commun aux gabarits.
--}}
@props(['title' => null, 'adminPage' => '', 'breadcrumb' => [], 'seo' => []])
@php
    try {
        $maintenanceActive = \App\Application\Platform\MaintenanceStatus::active();
    } catch (\Throwable) {
        $maintenanceActive = false;
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    @include('partials.head', ['seo' => ['title' => $title ?? __('menus.admin.breadcrumb_root'), 'index' => false] + $seo, 'css' => ['resources/css/xomiton.css', 'resources/css/admin.css']])
    @livewireStyles
    {{ $head ?? '' }}
</head>
<body class="xm-admin">
    <a class="xm-skip-link" href="#contenu">{{ __('menus.skip_to_content') }}</a>
    <div class="xm-offline-bar" id="xmOfflineBar" role="status"><i class="bi bi-wifi-off me-1" aria-hidden="true"></i> {{ __('menus.offline_banner_admin') }}</div>
    @include('partials.admin-sidebar', ['adminPage' => $adminPage, 'counters' => $counters ?? [], 'maintenanceActive' => $maintenanceActive])
    <div class="xm-admin-main">
        @include('partials.admin-topbar', ['title' => $title ?? __('menus.admin.breadcrumb_root'), 'breadcrumb' => $breadcrumb, 'actions' => $actions ?? null, 'bell' => $bell ?? null])
        <main class="xm-admin-content" id="contenu" tabindex="-1">
            @if ($maintenanceActive)
                <x-alert type="danger" icon="cone-striped" class="xm-maint-banner" data-xm-maintenance-banner>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <span>{{ __('maintenance.admin.banner_active') }}</span>
                        @can('platform.maintenance')
                            @if ($adminPage !== 'maintenance')<a class="btn btn-sm btn-light border" href="{{ route('admin.maintenance') }}">{{ __('maintenance.admin.banner_manage') }}</a>@endif
                        @endcan
                    </div>
                </x-alert>
            @else
                @include('partials.maintenance-notice', ['class' => 'mb-3'])
            @endif
            @if (session('status'))
                <div class="alert alert-{{ session('status_type', 'success') }} alert-dismissible fade show" role="alert">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('common.close') }}"></button></div>
            @endif
            {{ $slot }}
        </main>
        <footer class="px-4 py-3 small text-secondary border-top no-print">
            {{ __('menus.admin.footer', ['version' => config('xomiton.version', '1.0'), 'date' => \App\Support\LocaleFormatter::make()->tableDateTime(now())]) }}
        </footer>
    </div>
    @vite('resources/js/admin.js')
    @livewireScripts
    <script nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}">
        document.getElementById('xmSidebarToggle')?.addEventListener('click', (e) => {
            const open = document.getElementById('xmSidebar').classList.toggle('open');
            e.currentTarget.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.getElementById('xmFullscreen')?.addEventListener('click', () => {
            document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen?.();
        });
        if (window.Chart) { Chart.defaults.font.family = 'Montserrat, Arial, sans-serif'; Chart.defaults.plugins.legend.position = 'bottom'; }
    </script>
    {{ $scripts ?? '' }}
</body>
</html>
