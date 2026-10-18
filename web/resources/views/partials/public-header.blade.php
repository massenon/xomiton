{{-- En-tête public (prototype partials/public-header.php). Variable : $pubNav (clé active). --}}
@php
    $pubNav = $pubNav ?? '';
    $links = [
        ['accueil', route('home'), __('menus.public.home')],
        ['valeurs', route('home').'#valeurs', __('menus.public.about')],
        ['doglegbe', route('do-glegbe'), __('menus.public.do_glegbe')],
        ['blog', route('actualites.index'), __('menus.public.news')],
        ['aide', route('aide.index'), __('menus.public.help')],
    ];
@endphp
<header class="navbar-light bg-mode sticky-top shadow-sm no-print" id="pubHeader">
    <nav class="navbar navbar-expand-lg" aria-label="{{ __('menus.menu') }}">
        <div class="container">
            <x-logo class="navbar-brand" :href="route('home')" :adaptive="true" :width="140" img-class="navbar-brand-item" />
            <button class="navbar-toggler ms-auto icon-md btn btn-light p-0" type="button" data-bs-toggle="collapse" data-bs-target="#pubNavbar" aria-controls="pubNavbar" aria-expanded="false" aria-label="{{ __('menus.menu') }}">
                <span class="navbar-toggler-animation"><span></span><span></span><span></span></span>
            </button>
            <div class="collapse navbar-collapse" id="pubNavbar">
                <ul class="navbar-nav mx-auto">
                    @foreach ($links as [$key, $href, $label])
                        <li class="nav-item"><a class="nav-link{{ $pubNav === $key ? ' active fw-bold' : '' }}" href="{{ $href }}" @if ($pubNav === $key) aria-current="page" @endif>{{ $label }}</a></li>
                    @endforeach
                </ul>
                <div class="d-flex flex-wrap gap-2 py-3 py-lg-0 align-items-center">
                    <a class="btn btn-sm btn-danger-soft fw-bold" href="{{ route('signaler') }}"><i class="bi bi-megaphone me-1" aria-hidden="true"></i>{{ __('menus.public.report') }}</a>
                    @auth
                        <a class="btn btn-sm btn-primary" href="{{ route('fil') }}"><i class="bi bi-house-heart me-1" aria-hidden="true"></i>{{ __('menus.public.my_space') }}</a>
                    @else
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('connexion') }}">{{ __('menus.public.sign_in') }}</a>
                        <a class="btn btn-sm btn-primary" href="{{ route('inscription') }}">{{ __('menus.public.sign_up') }}</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>
    <div class="xm-rainbow-line"></div>
</header>
