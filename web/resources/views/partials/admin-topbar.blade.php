{{-- Barre supérieure d'administration (prototype partials/admin-top.php). Variables : $title, $actions (HTML), $breadcrumb. --}}
<div class="xm-admin-topbar d-flex align-items-center gap-3 no-print">
    <button class="btn btn-light d-lg-none" type="button" id="xmSidebarToggle" aria-label="{{ __('menus.menu') }}" aria-controls="xmSidebar" aria-expanded="false"><i class="bi bi-list fs-5" aria-hidden="true"></i></button>
    <div class="flex-grow-1">
        <h1 class="h5 mb-0 fw-bold">{{ $title }}</h1>
        <nav aria-label="breadcrumb">
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
            <input class="form-control border-start-0 ps-0" type="search" name="q" id="xmGlobalSearch" value="{{ request('q') }}" placeholder="{{ __('menus.admin.global_search_placeholder') }}" aria-label="{{ __('menus.admin.global_search') }}" autocomplete="off">
            <span class="input-group-text bg-transparent d-none d-xxl-flex"><kbd class="small">Ctrl K</kbd></span>
        </div>
    </form>
    <a class="btn btn-light btn-sm d-md-none" href="{{ route('admin.recherche') }}" aria-label="{{ __('menus.admin.global_search') }}" title="{{ __('menus.admin.global_search') }}"><i class="bi bi-search" aria-hidden="true"></i></a>
    <span class="badge bg-success d-none d-sm-inline" data-xm-online-state>{{ __('menus.admin.online') }}</span>
    <span class="badge bg-warning text-dark d-none" data-xm-queue-badge><i class="bi bi-cloud-arrow-up me-1" aria-hidden="true"></i><span data-xm-queue-count>0</span></span>
    {{ $actions ?? '' }}
    <button class="btn btn-light btn-sm position-relative" type="button" id="xmFullscreen" aria-label="{{ __('menus.admin.fullscreen') }}" title="{{ __('menus.admin.fullscreen') }}"><i class="bi bi-arrows-fullscreen" aria-hidden="true"></i></button>
    {{-- Cloche : composant Livewire branché en P10-03 ; emplacement conservé. --}}
    {{ $bell ?? '' }}
    <div class="dropdown">
        <a href="#" class="d-flex align-items-center text-decoration-none" data-bs-toggle="dropdown" aria-expanded="false">
            <img src="{{ auth()->user()?->avatar_url ?? asset('assets/images/avatar/placeholder.jpg') }}" class="rounded-circle" style="width:36px;height:36px;object-fit:cover" width="36" height="36" alt="" aria-hidden="true">
            <span class="ms-2 small d-none d-md-inline">{{ auth()->user()?->pseudo }}</span>
            <span class="visually-hidden">{{ __('menus.admin.my_profile') }}</span>
        </a>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ route('profil') }}">{{ __('menus.admin.my_profile') }}</a></li>
            <li><a class="dropdown-item" href="{{ route('admin.parametres') }}">{{ __('menus.admin.settings') }}</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><form method="post" action="{{ route('deconnexion') }}">@csrf<button class="dropdown-item text-danger" type="submit">{{ __('menus.member.logout') }}</button></form></li>
        </ul>
    </div>
</div>
