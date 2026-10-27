{{--
    Barre latérale d'administration (P10-01, décision Q14 a : 8 groupes de dashboard-admin.md + Raccourcis du prototype
    partials/admin-top.php ; menu et permissions : App\Application\Admin\AdminNavigation).
    ≥ 992 px : fixe, repliable en icônes seules (bouton « Replier le menu », état mémorisé sur l'appareil, `title` sur
    chaque entrée, libellé gardé pour les lecteurs d'écran). < 992 px : offcanvas Bootstrap (offcanvas-lg : piège du
    focus, Échap, fond), ouvert par le bouton menu de la barre supérieure. Entrée active : `aria-current="page"`.
    Variables : $adminPage (clé du menu, sinon déduite de la route), $maintenanceActive (pastille « Actif »), $groups (facultatif :
    groupes déjà calculés par le gabarit). Persistée par le gabarit (@persist) : entrée active et pastilles remises à jour
    par resources/js/admin/navigation.js d'après #xmNavState (pastille .badge et son texte .visually-hidden).
--}}
@php
    $groups ??= app(\App\Application\Admin\AdminNavigation::class)->groups(auth()->user(), $adminPage ?? '', $maintenanceActive ?? false);
@endphp
<aside class="xm-admin-sidebar offcanvas-lg offcanvas-start" id="xmSidebar" tabindex="-1" aria-labelledby="xmSidebarTitle" data-xm-sidebar>
    <div class="brand d-flex align-items-center justify-content-between gap-2">
        <x-logo variant="sombre" :href="route('admin.dashboard')" wire:navigate :width="117" class="xm-admin-sidebar__logo" />
        <x-logo variant="icone-blanc" :href="route('admin.dashboard')" wire:navigate :width="32" class="xm-admin-sidebar__icon" />
        <span class="badge bg-danger xm-admin-sidebar__badge">{{ __('menus.admin.badge') }}</span>
        <h2 class="visually-hidden" id="xmSidebarTitle">{{ __('menus.admin.nav_title') }}</h2>
        <button type="button" class="btn-close btn-close-white d-lg-none ms-auto" data-bs-dismiss="offcanvas" data-bs-target="#xmSidebar" aria-label="{{ __('admin.nav.close') }}"></button>
        <button type="button" class="btn btn-sm xm-admin-sidebar__collapse d-none d-lg-inline-flex" id="xmSidebarCollapse" aria-controls="xmSidebar" aria-pressed="false"
                data-label-collapse="{{ __('admin.nav.collapse') }}" data-label-expand="{{ __('admin.nav.expand') }}" title="{{ __('admin.nav.collapse') }}">
            <i class="bi bi-chevron-double-left" aria-hidden="true"></i><span class="visually-hidden" data-xm-collapse-label>{{ __('admin.nav.collapse') }}</span>
        </button>
    </div>
    <nav class="xm-admin-sidebar__nav" aria-label="{{ __('menus.a11y.admin_nav') }}">
        @foreach ($groups as $group)
            <div class="group" id="xmNavGroup-{{ $group['key'] }}">{{ $group['label'] }}</div>
            <ul class="list-unstyled mb-0" aria-labelledby="xmNavGroup-{{ $group['key'] }}">
                @foreach ($group['items'] as $item)
                    <li>
                        <a wire:navigate wire:navigate.hover @class(['item', 'active' => $item['current']]) href="{{ $item['href'] }}" title="{{ $item['label'] }}" data-xm-nav="{{ $item['key'] }}" @if ($item['current']) aria-current="page" @endif>
                            <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i><span class="xm-admin-sidebar__label">{{ $item['label'] }}</span>
                            @if ($item['badge'] !== null)
                                <span class="badge bg-{{ $item['badgeColor'] }}" @if ($item['badgeLabel']) aria-hidden="true" @endif>{{ $item['badge'] }}</span>
                                @if ($item['badgeLabel'])<span class="visually-hidden">({{ $item['badgeLabel'] }})</span>@endif
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endforeach
        <div class="group" id="xmNavGroup-shortcuts">{{ __('menus.admin.groups.shortcuts') }}</div>
        <ul class="list-unstyled mb-0" aria-labelledby="xmNavGroup-shortcuts">
            <li><a class="item" href="{{ route('fil') }}" title="{{ __('menus.admin.view_member_site') }}"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i><span class="xm-admin-sidebar__label">{{ __('menus.admin.view_member_site') }}</span></a></li>
            <li><a class="item" href="{{ route('home') }}" target="_blank" rel="noopener" title="{{ __('menus.admin.public_home') }}"><i class="bi bi-globe2" aria-hidden="true"></i><span class="xm-admin-sidebar__label">{{ __('menus.admin.public_home') }}</span></a></li>
            <li>
                <form method="post" action="{{ route('deconnexion') }}">@csrf
                    <button class="item w-100 border-0 bg-transparent text-start" type="submit" title="{{ __('menus.member.logout') }}"><i class="bi bi-power" aria-hidden="true"></i><span class="xm-admin-sidebar__label">{{ __('menus.member.logout') }}</span></button>
                </form>
            </li>
        </ul>
    </nav>
</aside>
