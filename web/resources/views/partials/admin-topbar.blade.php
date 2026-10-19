{{--
    Barre supérieure d'administration (P10-01, prototype partials/admin-top.php) : bouton menu (< 992 px, ouvre l'offcanvas
    #xmSidebar), titre h1 et fil d'Ariane, recherche globale (Ctrl K / ⌘ K, resources/js/admin/shell.js), état en ligne,
    file de synchro, actions de page (slot), plein écran, cloche Livewire (P10-03, polling 30 s), menu du compte.
    Variables : $title, $actions (HTML), $breadcrumb ([[libellé, url], …]).
--}}
<div class="xm-admin-topbar d-flex align-items-center gap-2 gap-md-3 no-print">
    <button class="btn btn-light d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#xmSidebar" aria-controls="xmSidebar" aria-label="{{ __('menus.a11y.open_menu') }}"><i class="bi bi-list fs-5" aria-hidden="true"></i></button>
    <div class="flex-grow-1 min-w-0">
        <h1 class="h5 mb-0 fw-bold text-truncate">{{ $title }}</h1>
        <nav aria-label="{{ __('admin.topbar.breadcrumb') }}">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('menus.admin.breadcrumb_root') }}</a></li>
                @foreach ($breadcrumb ?? [] as [$label, $href])
                    <li class="breadcrumb-item"><a href="{{ $href }}">{{ $label }}</a></li>
                @endforeach
                <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
            </ol>
        </nav>
    </div>
    <form class="xm-gsearch d-none d-md-block" action="{{ route('admin.recherche') }}" method="get" role="search">
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search" aria-hidden="true"></i></span>
            <input class="form-control border-start-0 ps-0" type="search" name="q" id="xmGlobalSearch" value="{{ request()->routeIs('admin.recherche') ? request('q') : '' }}" placeholder="{{ __('menus.admin.global_search_placeholder') }}"
                   aria-label="{{ __('menus.admin.global_search') }}" aria-keyshortcuts="Control+K Meta+K" aria-describedby="xmGlobalSearchHelp" autocomplete="off">
            <span class="input-group-text bg-transparent d-none d-xxl-flex" aria-hidden="true"><kbd class="small">{{ __('admin.topbar.search_shortcut') }}</kbd></span>
        </div>
        <span class="visually-hidden" id="xmGlobalSearchHelp">{{ __('admin.topbar.shortcut_help') }}</span>
    </form>
    <a class="btn btn-light btn-sm d-md-none" href="{{ route('admin.recherche') }}" aria-label="{{ __('menus.admin.global_search') }}" title="{{ __('menus.admin.global_search') }}"><i class="bi bi-search" aria-hidden="true"></i></a>
    <span class="badge bg-success d-none d-sm-inline" data-xm-online-state data-label-online="{{ __('admin.topbar.online') }}" data-label-offline="{{ __('admin.topbar.offline') }}">{{ __('admin.topbar.online') }}</span>
    <span class="badge bg-warning text-dark d-none" data-xm-queue-badge><i class="bi bi-cloud-arrow-up me-1" aria-hidden="true"></i><span data-xm-queue-count>0</span></span>
    {{ $actions ?? '' }}
    <button class="btn btn-light btn-sm position-relative d-none d-sm-inline-block" type="button" id="xmFullscreen" aria-label="{{ __('admin.topbar.fullscreen') }}" title="{{ __('admin.topbar.fullscreen') }}"
            data-label-enter="{{ __('admin.topbar.fullscreen') }}" data-label-exit="{{ __('admin.topbar.exit_fullscreen') }}"><i class="bi bi-arrows-fullscreen" aria-hidden="true"></i></button>
    @auth
        <livewire:admin.notification-bell />
    @endauth
    <div class="dropdown">
        <button type="button" class="btn btn-link p-0 d-flex align-items-center text-decoration-none" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('admin.topbar.account') }}">
            <img src="{{ auth()->user()?->avatar_url ?? asset('assets/images/avatar/placeholder.jpg') }}" class="rounded-circle xm-admin-topbar__avatar" width="36" height="36" alt="" aria-hidden="true">
            <span class="ms-2 small d-none d-md-inline text-body">{{ auth()->user()?->pseudo }}</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ route('profil') }}">{{ __('menus.admin.my_profile') }}</a></li>
            @can('settings.view')<li><a class="dropdown-item" href="{{ route('admin.parametres') }}">{{ __('menus.admin.settings') }}</a></li>@endcan
            <li><hr class="dropdown-divider"></li>
            <li><form method="post" action="{{ route('deconnexion') }}">@csrf<button class="dropdown-item text-danger" type="submit">{{ __('menus.member.logout') }}</button></form></li>
        </ul>
    </div>
</div>
