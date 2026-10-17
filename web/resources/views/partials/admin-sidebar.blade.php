{{-- Barre latérale d'administration (prototype partials/admin-top.php, menu UI_UX_DESIGN_SYSTEM.md §2). Variable : $adminPage. --}}
@php
    $adminPage = $adminPage ?? '';
    $menu = [
        __('menus.admin.groups.steering') => [
            ['dashboard', 'bi-speedometer2', __('menus.admin.dashboard')],
            ['alertes', 'bi-bell', __('menus.admin.alerts'), $counters['alerts_new'] ?? 0, 'danger'],
            ['indicateurs', 'bi-bar-chart-line', __('menus.admin.indicators')],
            ['rapports', 'bi-file-earmark-bar-graph', __('menus.admin.reports')],
        ],
        __('menus.admin.groups.data') => [
            ['registres', 'bi-journal-bookmark', __('menus.admin.registers')],
            ['fiches', 'bi-file-earmark-medical', __('menus.admin.forms')],
            ['suivi', 'bi-person-heart', __('menus.admin.followup')],
            ['annuaire', 'bi-person-lines-fill', __('menus.admin.directory')],
            ['validation', 'bi-patch-check', __('menus.admin.validation')],
        ],
        __('menus.admin.groups.platform') => [
            ['utilisateurs', 'bi-people', __('menus.admin.users'), $counters['users_pending'] ?? 0, 'primary'],
            ['inscriptions', 'bi-person-plus', __('menus.admin.registrations')],
            ['contenu', 'bi-journal-text', __('menus.admin.content')],
            ['newsletter', 'bi-envelope-paper', __('menus.admin.newsletter')],
            ['ccc', 'bi-collection-play', __('menus.admin.ccc')],
            ['reseau', 'bi-diagram-3', __('menus.admin.network')],
            ['moderation', 'bi-shield-check', __('menus.admin.moderation')],
            ['assistant', 'bi-robot', __('menus.admin.assistant')],
            ['passerelles', 'bi-share', __('menus.admin.gateways')],
        ],
        __('menus.admin.groups.system') => [
            ['synchronisation', 'bi-cloud-arrow-up', __('menus.admin.sync')],
            ['mises-a-jour', 'bi-arrow-repeat', __('menus.admin.updates')],
            ['emails', 'bi-envelope-at', __('menus.admin.emails')],
            ['parametres', 'bi-gear', __('menus.admin.settings')],
            ['journal', 'bi-clock-history', __('menus.admin.audit')],
        ],
    ];
@endphp
<aside class="xm-admin-sidebar" id="xmSidebar" aria-label="{{ __('menus.admin.breadcrumb_root') }}">
    <div class="brand d-flex align-items-center justify-content-between">
        <a href="{{ route('admin.dashboard') }}"><img src="{{ asset('assets/images/logo-dark.svg') }}" alt="{{ __('common.logo_alt') }}" title="Xomiton" style="height:30px;width:auto" width="108" height="30"></a>
        <span class="badge bg-danger">{{ __('menus.admin.badge') }}</span>
    </div>
    @foreach ($menu as $group => $items)
        <div class="group">{{ $group }}</div>
        @foreach ($items as $item)
            <a class="item {{ $adminPage === $item[0] ? 'active' : '' }}" href="{{ route('admin.'.$item[0]) }}" @if ($adminPage === $item[0]) aria-current="page" @endif>
                <i class="bi {{ $item[1] }}" aria-hidden="true"></i><span>{{ $item[2] }}</span>
                @if (! empty($item[3]))<span class="badge bg-{{ $item[4] }}">{{ (int) $item[3] }}</span>@endif
            </a>
        @endforeach
    @endforeach
    <div class="group">{{ __('menus.admin.groups.shortcuts') }}</div>
    <a class="item" href="{{ route('fil') }}"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i><span>{{ __('menus.admin.view_member_site') }}</span></a>
    <a class="item" href="{{ route('home') }}" target="_blank" rel="noopener"><i class="bi bi-globe2" aria-hidden="true"></i><span>{{ __('menus.admin.public_home') }}</span></a>
    <form method="post" action="{{ route('deconnexion') }}">@csrf<button class="item w-100 border-0 bg-transparent text-start" type="submit"><i class="bi bi-power" aria-hidden="true"></i><span>{{ __('menus.member.logout') }}</span></button></form>
</aside>
