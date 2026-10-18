{{--
    Gabarit authentification (design-system/xomiton/pages/authentification.md, prime sur le prototype — décision Q20 a) :
    plein écran, ≥ 992 px deux colonnes : illustration 5/12 (logo blanc + promesse) · formulaire 7/12 centré, 440 px
    (prop maxWidth bornée 440–520) ; < 992 px : formulaire seul, logo au-dessus. Pas de SOS flottant, pas de rouge
    hors erreurs. Mention discrète « Aucune donnée n'est visible depuis l'écran de verrouillage » sous le formulaire.
    Écart consigné (design-system/DECISIONS.md, Q20) : sign-in.php est en 6/6 et 520 px, sign-up.php en 5/7 et 640 px,
    forgot-password.php en colonne centrée.
    Props : seo (lang/fr/seo.php ; noindex par défaut, /connexion et /inscription passent index => true), image
    (public/assets/images/xomiton/), maxWidth, slot `side` (contenu de l'illustration).
--}}
@props(['seo' => [], 'image' => 'sign-in.jpg', 'maxWidth' => 440])
@php
    $maxWidth = max(440, min(520, (int) $maxWidth));
    // Écrans haute densité : variante `{nom}-2x.webp` (recadrage portrait de l'original, colonne 5/12 pleine hauteur)
    // servie par image-set() quand elle existe ; une image de fond n'a pas de srcset. Repli : l'image 1x seule.
    $sideImage = asset('assets/images/xomiton/'.$image);
    $sideHiDpi = 'assets/images/xomiton/'.pathinfo($image, PATHINFO_FILENAME).'-2x.webp';
    $sideStyle = "background-image:url('{$sideImage}')";
    if (is_file(public_path($sideHiDpi))) {
        $set = "url('{$sideImage}') 1x, url('".asset($sideHiDpi)."') 2x";
        $sideStyle .= ";background-image:-webkit-image-set({$set});background-image:image-set({$set})";
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    @include('partials.head', ['seo' => $seo + ['index' => false]])
    @include('partials.offline')
    {{ $head ?? '' }}
</head>
<body class="xm-auth">
    <a class="xm-skip-link" href="#contenu">{{ __('menus.skip_to_content') }}</a>
    <main id="contenu" tabindex="-1">
        <div class="container-fluid p-0">
            <div class="row g-0 min-vh-100">
                <div class="col-lg-5 d-none d-lg-flex xm-auth-side align-items-end p-5" style="{{ $sideStyle }}">
                    <div class="text-white xm-auth-side__content">
                        <a href="{{ route('home') }}" class="d-inline-block mb-4" aria-label="{{ __('auth.layout.home') }}"><img src="{{ asset('assets/images/xomiton/brand/logo-mono-blanc.svg') }}" alt="Xomiton" height="44" width="171"></a>
                        @isset($side)
                            {{ $side }}
                        @else
                            <p class="display-6 fw-bold">{{ __('auth.side_title') }}</p>
                            <p class="lead mb-0">{{ __('auth.side_text') }}</p>
                        @endisset
                    </div>
                </div>
                <div class="col-lg-7 d-flex align-items-center">
                    <div class="w-100 p-4 p-md-5 mx-auto xm-auth-form" style="max-width:{{ $maxWidth }}px">
                        <x-logo class="d-lg-none d-inline-block mb-4" :href="route('home')" :width="148" />
                        {{ $slot }}
                        <p class="xm-lock-notice small text-body-secondary mt-4 mb-0"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>{{ __('auth.lock_screen_notice') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <x-toast :message="session('toast')" :type="session('toast_type', 'primary')" />
    @vite('resources/js/public.js')
    {{ $scripts ?? '' }}
</body>
</html>
