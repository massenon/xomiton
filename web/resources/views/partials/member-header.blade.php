{{--
    En-tête membre connecté (prototype partials/header.php) : logo, recherche, menus Fil · Apprendre · Communauté · Aide & protection
    (· Espace pair), puis SOS, messages, réglages, cloche <x-bell> alimentée par la table notifications (P3-02) et avatar avec menu
    (Mon profil, Paramètres, Centre d'aide, Administration si admin.access, déconnexion en POST). Variable : $memberNav (clé active).
--}}
@php
    $user = auth()->user();
    $memberNav = $memberNav ?? '';
    $role = $user?->getRoleNames()->first() ?? 'membre';
    $roleLabel = __('menus.member_roles.'.$role);
    $roleLabel = $roleLabel === 'menus.member_roles.'.$role ? __('menus.member_roles.membre') : $roleLabel;
    $formatter = \App\Support\LocaleFormatter::make();
    $colors = ['securite' => 'primary', 'sos' => 'danger', 'alertes' => 'danger', 'messages' => 'info', 'communaute' => 'primary', 'evenements' => 'success', 'moderation' => 'warning', 'systeme' => 'secondary'];
    $bellItems = $user === null ? [] : $user->notifications()->limit(8)->get()->map(fn ($n): array => [
        'id' => $n->getKey(),
        'icon' => $n->data['icon'] ?? 'bell',
        'color' => $colors[$n->category ?? ''] ?? 'primary',
        'text' => (string) ($n->data['title'] ?? __('notifications.discreet.body')),
        'time' => $formatter->ago($n->created_at),
        'href' => $n->data['url'] ?? route('notifications'),
        'read' => $n->read_at !== null,
    ])->all();
    $learn = [
        ['thematiques', route('thematiques.index'), 'grid', __('menus.member.thematiques')],
        ['ressources', route('ressources.index'), 'journal-text', __('menus.member.ressources')],
        ['violences', route('violences'), 'shield-exclamation', __('menus.member.violences')],
        ['lois', route('lois'), 'bank', __('menus.member.lois')],
        ['ccc', route('ccc'), 'collection-play', __('menus.member.ccc')],
    ];
    $community = [
        ['groupes', route('groupes.index'), 'people', __('menus.member.groups')],
        ['pages', route('pages.index'), 'flag', __('menus.member.pages')],
        ['evenements', route('evenements.index'), 'calendar-event', __('menus.member.events')],
        ['forums', route('forums.index'), 'chat-square-text', __('menus.member.forums')],
        ['qui-suivre', route('qui-suivre'), 'person-plus', __('menus.member.who_to_follow')],
    ];
    $help = [
        ['signaler', route('signaler'), 'megaphone', __('menus.member.report')],
        ['urgences', route('urgences'), 'telephone-fill', __('menus.member.emergency')],
        ['annuaire', route('annuaire'), 'person-lines-fill', __('menus.member.directory')],
        ['assistant', route('assistant'), 'robot', __('menus.member.assistant')],
    ];
@endphp
<header class="navbar-light bg-mode sticky-top shadow-sm no-print header-static" id="memberHeader">
    <nav class="navbar navbar-expand-lg" aria-label="{{ __('menus.a11y.member_nav') }}">
        <div class="container">
            <x-logo class="navbar-brand" :href="route('fil')" :adaptive="true" :width="140" img-class="navbar-brand-item" />

            <button class="navbar-toggler ms-auto icon-md btn btn-light p-0" type="button" data-bs-toggle="collapse" data-bs-target="#memberNavbar" aria-controls="memberNavbar" aria-expanded="false" aria-label="{{ __('menus.a11y.open_menu') }}">
                <span class="navbar-toggler-animation"><span></span><span></span><span></span></span>
            </button>

            <div class="collapse navbar-collapse" id="memberNavbar">
                <div class="nav mt-3 mt-lg-0 flex-nowrap align-items-center px-4 px-lg-0">
                    <div class="nav-item w-100">
                        <form class="rounded position-relative" action="{{ route('ressources.index') }}" method="get" role="search">
                            <label for="memberSearch" class="visually-hidden">{{ __('menus.member.search') }}</label>
                            <input class="form-control ps-5 bg-light" type="search" id="memberSearch" name="q" placeholder="{{ __('menus.member.search_placeholder') }}">
                            <button class="btn bg-transparent px-2 py-0 position-absolute top-50 start-0 translate-middle-y" type="submit" aria-label="{{ __('menus.member.search') }}"><i class="bi bi-search fs-5" aria-hidden="true"></i></button>
                        </form>
                    </div>
                </div>

                <ul class="navbar-nav navbar-nav-scroll ms-auto">
                    <li class="nav-item"><a @class(['nav-link', 'active fw-bold' => $memberNav === 'fil']) href="{{ route('fil') }}" @if ($memberNav === 'fil') aria-current="page" @endif>{{ __('menus.member.feed') }}</a></li>
                    @foreach ([['menuApprendre', __('menus.member.learn'), $learn], ['menuCommunaute', __('menus.member.community'), $community], ['menuAide', __('menus.member.help_protection'), $help]] as [$id, $label, $links])
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="{{ $id }}" role="button" data-bs-toggle="dropdown" aria-expanded="false">{{ $label }}</a>
                            <ul class="dropdown-menu" aria-labelledby="{{ $id }}">
                                @if ($id === 'menuAide')
                                    <li><a class="dropdown-item text-danger fw-bold" href="{{ route('sos') }}"><i class="bi bi-broadcast fa-fw me-2" aria-hidden="true"></i>{{ __('menus.member.sos') }}</a></li>
                                @endif
                                @foreach ($links as [$key, $href, $icon, $text])
                                    <li><a @class(['dropdown-item', 'active' => $memberNav === $key]) href="{{ $href }}"><i class="bi bi-{{ $icon }} fa-fw me-2" aria-hidden="true"></i>{{ $text }}</a></li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                    @if ($user?->can('peer_space.access'))
                        <li class="nav-item"><a @class(['nav-link', 'active fw-bold' => $memberNav === 'espace-pair']) href="{{ route('espace-pair') }}">{{ __('menus.member.peer_space') }}</a></li>
                    @endif
                </ul>
            </div>

            <ul class="nav flex-nowrap align-items-center ms-sm-3 list-unstyled">
                <li class="nav-item ms-2 d-none d-sm-block">
                    <a class="btn btn-danger btn-sm fw-bold" href="{{ route('sos') }}" title="{{ __('common.sos_title') }}"><i class="bi bi-broadcast me-1" aria-hidden="true"></i>{{ __('common.sos') }}</a>
                </li>
                <li class="nav-item ms-2">
                    <a class="nav-link bg-light icon-md btn btn-light p-0" href="{{ route('messages') }}" title="{{ __('menus.member.messages') }}" aria-label="{{ __('menus.member.messages') }}"><i class="bi bi-chat-left-text-fill fs-6" aria-hidden="true"></i></a>
                </li>
                <li class="nav-item ms-2">
                    <a class="nav-link bg-light icon-md btn btn-light p-0" href="{{ route('parametres') }}" title="{{ __('menus.member.settings') }}" aria-label="{{ __('menus.member.settings') }}"><i class="bi bi-gear-fill fs-6" aria-hidden="true"></i></a>
                </li>
                <li class="nav-item ms-2">
                    <x-bell variant="member" id="memberBell" :items="$bellItems" :href="route('notifications')" :href-label="__('notifications.page.see_all')" />
                </li>
                <li class="nav-item ms-2 dropdown">
                    <a class="nav-link btn icon-md p-0" href="#" id="profileDropdown" role="button" data-bs-auto-close="outside" data-bs-display="static" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('microcopy.a11y.user_menu') }}">
                        <img class="avatar-img rounded-2" src="{{ $user?->avatar_url }}" alt="{{ __('components.avatar.alt', ['name' => $user?->pseudo]) }}" title="{{ $user?->pseudo }}" width="40" height="40">
                    </a>
                    <ul class="dropdown-menu dropdown-animation dropdown-menu-end pt-3 small me-md-n3" aria-labelledby="profileDropdown">
                        <li class="px-3">
                            <div class="d-flex align-items-center position-relative">
                                <x-avatar :name="(string) $user?->pseudo" :src="$user?->avatar_url" class="me-3" />
                                <div>
                                    <a class="h6 stretched-link" href="{{ route('profil') }}">{{ $user?->pseudo }}</a>
                                    <p class="small m-0">{{ $roleLabel }}</p>
                                </div>
                            </div>
                            <a class="dropdown-item btn btn-primary-soft btn-sm my-2 text-center" href="{{ route('profil') }}">{{ __('menus.member.view_profile') }}</a>
                        </li>
                        <li><a class="dropdown-item" href="{{ route('parametres') }}"><i class="bi bi-gear fa-fw me-2" aria-hidden="true"></i>{{ __('menus.member.settings_privacy') }}</a></li>
                        <li><a class="dropdown-item" href="{{ route('aide.index') }}"><i class="bi bi-life-preserver fa-fw me-2" aria-hidden="true"></i>{{ __('menus.member.help_center') }}</a></li>
                        @can('admin.access')
                            <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2 fa-fw me-2" aria-hidden="true"></i>{{ __('menus.member.administration') }}</a></li>
                        @endcan
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="{{ route('deconnexion') }}" id="memberLogout">
                                @csrf
                                <button type="submit" class="dropdown-item bg-danger-soft-hover"><i class="bi bi-power fa-fw me-2" aria-hidden="true"></i>{{ __('menus.member.logout') }}</button>
                            </form>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
</header>
