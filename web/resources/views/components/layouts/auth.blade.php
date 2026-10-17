{{--
    Gabarit authentification (FRONT_END_LAYOUT_INSTRUCTION.md §4, prototype sign-in.php) : plein écran,
    illustration à gauche ≥ 992 px, formulaire centré à droite (520 px max). Pas de SOS flottant.
--}}
@props(['seo' => [], 'image' => 'sign-in.jpg'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    @include('partials.head', ['seo' => $seo + ['index' => false]])
</head>
<body>
    <a class="xm-skip-link" href="#contenu">{{ __('menus.skip_to_content') }}</a>
    <main id="contenu" tabindex="-1">
        <div class="container-fluid p-0">
            <div class="row g-0 min-vh-100">
                <div class="col-lg-6 d-none d-lg-flex xm-auth-side align-items-end p-5" style="background-image:url('{{ asset('assets/images/xomiton/'.$image) }}')">
                    <div class="text-white">
                        <img src="{{ asset('assets/images/xomiton/brand/logo-mono-blanc.svg') }}" alt="Xomiton" height="44" width="176" class="mb-4">
                        <p class="display-6 fw-bold">{{ __('auth.side_title') }}</p>
                        <p class="lead mb-0">{{ __('auth.side_text') }}</p>
                    </div>
                </div>
                <div class="col-lg-6 d-flex align-items-center">
                    <div class="w-100 p-4 p-md-5 mx-auto" style="max-width:520px">
                        <a href="{{ route('home') }}" class="d-lg-none d-inline-block mb-4"><img src="{{ asset('assets/images/logo.svg') }}" alt="Xomiton" height="38" width="137"></a>
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </main>
    @vite('resources/js/public.js')
    {{ $scripts ?? '' }}
</body>
</html>
