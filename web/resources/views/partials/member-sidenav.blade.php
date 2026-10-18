{{--
    Sidenav membre (prototype partials/sidenav.php) en col-lg-3, offcanvas sous 992 px : carte profil (couverture, avatar,
    pseudo, département, compteurs), sections Réseau social · Apprendre · Aide & protection · Pair animateur·rice (si
    peer_space.access) · Compte (déconnexion en POST), liens de pied. Textes : lang/fr/menus.php. Variable : $memberNav (clé active).
--}}
@php
    $user = auth()->user();
    $memberNav = $memberNav ?? '';
    $link = static fn (string $key, string $href, string $icon, string $label, string $class = '') => [$key, $href, $icon, $label, $class];
    $sections = [
        [__('menus.member_sections.social'), [
            $link('fil', route('fil'), 'house-heart', __('menus.member_more.feed_full')),
            $link('profil', route('profil'), 'person-circle', __('menus.member.profile')),
            $link('messages', route('messages'), 'chat-dots', __('menus.member_more.messages_full')),
            $link('groupes', route('groupes.index'), 'people', __('menus.member.groups')),
            $link('pages', route('pages.index'), 'flag', __('menus.member.pages')),
            $link('evenements', route('evenements.index'), 'calendar-event', __('menus.member.events')),
            $link('forums', route('forums.index'), 'chat-square-text', __('menus.member.forums')),
            $link('qui-suivre', route('qui-suivre'), 'person-plus', __('menus.member_more.who_to_follow_full')),
        ]],
        [__('menus.member_sections.learn'), [
            $link('thematiques', route('thematiques.index'), 'grid', __('menus.member.thematiques')),
            $link('ressources', route('ressources.index'), 'journal-text', __('menus.member.ressources')),
            $link('do-glegbe', route('do-glegbe'), 'droplet-half', __('menus.member_more.do_glegbe')),
            $link('violences', route('violences'), 'shield-exclamation', __('menus.member_more.violences_short')),
            $link('ccc', route('ccc'), 'collection-play', __('menus.member.ccc')),
        ]],
        [__('menus.member_sections.help'), [
            $link('sos', route('sos'), 'broadcast', __('menus.member_more.sos_full'), 'danger'),
            $link('signaler', route('signaler'), 'megaphone', __('menus.member.report')),
            $link('urgences', route('urgences'), 'telephone-fill', __('menus.member.emergency')),
            $link('annuaire', route('annuaire'), 'person-lines-fill', __('menus.member.directory')),
            $link('assistant', route('assistant'), 'robot', __('menus.member.assistant')),
        ]],
    ];
    if ($user?->can('peer_space.access')) {
        $sections[] = [__('menus.member_sections.peer'), [
            $link('espace-pair', route('espace-pair'), 'clipboard2-pulse', __('menus.member.peer_space')),
            $link('collecte', route('collecte'), 'pencil-square', __('menus.member_more.collecte')),
        ]];
    }
    $account = [
        $link('parametres', route('parametres'), 'gear', __('menus.member.settings')),
        $link('aide', route('aide.index'), 'life-preserver', __('menus.member.help_center')),
    ];
    if ($user?->can('admin.access')) {
        $account[] = $link('admin', route('admin.dashboard'), 'speedometer2', __('menus.member.administration'));
    }
    $counts = $user === null ? ['posts' => 0, 'followers' => 0, 'following' => 0] : [
        'posts' => $user->posts()->count(),
        'followers' => $user->followers()->count(),
        'following' => $user->following()->count(),
    ];
@endphp
<div class="col-lg-3" id="memberSidenav">
    <div class="d-flex align-items-center d-lg-none mb-3">
        <button class="border-0 bg-transparent d-flex align-items-center" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSideNavbar" aria-controls="offcanvasSideNavbar" aria-expanded="false">
            <span class="btn btn-primary"><i class="bi bi-sliders" aria-hidden="true"></i></span>
            <span class="h6 mb-0 fw-bold ms-2">{{ __('menus.menu') }}</span>
        </button>
    </div>

    <nav class="navbar navbar-expand-lg mx-0" aria-label="{{ __('menus.a11y.member_nav') }}">
        <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasSideNavbar">
            <div class="offcanvas-header">
                <button type="button" class="btn-close text-reset ms-auto" data-bs-dismiss="offcanvas" aria-label="{{ __('menus.a11y.close_menu') }}"></button>
            </div>

            <div class="offcanvas-body d-block px-2 px-lg-0">
                <div class="card overflow-hidden">
                    <div class="h-50px" style="background-image:url('{{ asset('assets/images/xomiton/profile-cover.jpg') }}'); background-position:center; background-size:cover;"></div>
                    <div class="card-body pt-0">
                        <div class="text-center">
                            <a href="{{ route('profil') }}" class="d-inline-block mt-n5 mb-3"><x-avatar :name="(string) $user?->pseudo" :src="$user?->avatar_url" size="lg" rounded="2" class="border border-white border-3 rounded" /></a>
                            <h2 class="h5 mb-0"><a href="{{ route('profil') }}">{{ $user?->pseudo }}</a></h2>
                            <small>{{ __('profile.pseudo', ['pseudo' => $user?->pseudo]) }} · {{ $user?->zone?->name ?? 'Bénin' }}</small>
                            <div class="hstack gap-2 gap-xl-3 justify-content-center mt-3">
                                <div><h3 class="h6 mb-0">{{ $counts['posts'] }}</h3><small>{{ __('menus.member_more.stats_posts') }}</small></div>
                                <div class="vr"></div>
                                <div><h3 class="h6 mb-0">{{ $counts['followers'] }}</h3><small>{{ __('menus.member_more.stats_followers') }}</small></div>
                                <div class="vr"></div>
                                <div><h3 class="h6 mb-0">{{ $counts['following'] }}</h3><small>{{ __('menus.member_more.stats_following') }}</small></div>
                            </div>
                        </div>
                        <hr>

                        <div class="d-flex flex-column gap-1">
                            @foreach ($sections as [$title, $links])
                                <div @class(['xm-nav-section', 'pt-0' => $loop->first])>{{ $title }}</div>
                                @foreach ($links as [$key, $href, $icon, $label, $class])
                                    <a @class(['xm-side-link', 'active' => $memberNav === $key, $class => $class !== '']) href="{{ $href }}" @if ($memberNav === $key) aria-current="page" @endif><i class="bi bi-{{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span></a>
                                @endforeach
                            @endforeach

                            <div class="xm-nav-section">{{ __('menus.member_sections.account') }}</div>
                            @foreach ($account as [$key, $href, $icon, $label, $class])
                                <a @class(['xm-side-link', 'active' => $memberNav === $key]) href="{{ $href }}"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span></a>
                            @endforeach
                            <form method="post" action="{{ route('deconnexion') }}">
                                @csrf
                                <button type="submit" class="xm-side-link danger border-0 bg-transparent w-100 text-start"><i class="bi bi-power" aria-hidden="true"></i><span>{{ __('menus.member.logout') }}</span></button>
                            </form>
                        </div>
                    </div>
                </div>

                <ul class="nav small mt-3 justify-content-center lh-1" aria-label="{{ __('menus.a11y.footer_nav') }}">
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#valeurs">{{ __('menus.member_more.about') }}</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('confidentialite') }}">{{ __('menus.member_more.privacy_terms') }}</a></li>
                </ul>
                <p class="small text-center mt-1">{{ __('menus.member_more.copyright', ['year' => now()->format('Y')]) }}</p>
            </div>
        </div>
    </nav>
</div>
