{{--
    En-tête HTML commun aux gabarits. Variables : $seo (tableau de props pour <x-seo>), $css (chemins Vite CSS),
    $critical (bool : CSS critique resources/css/critical.css inliné avant le CSS principal, gabarits public et sobre, P4-12).
--}}
@php($seo = $seo ?? [])
@php($critical = $critical ?? false)
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<x-seo
    :title="$seo['title'] ?? null"
    :description="$seo['description'] ?? null"
    :image="$seo['image'] ?? 'covers/og-default.jpg'"
    :image-alt="$seo['imageAlt'] ?? null"
    :type="$seo['type'] ?? 'website'"
    :index="$seo['index'] ?? null"
    :breadcrumb="$seo['breadcrumb'] ?? []"
    :schema="$seo['schema'] ?? []"
    :modified="$seo['modified'] ?? null"
/>
<meta name="theme-color" content="#0433C3">
<meta name="application-name" content="Xomiton">
<meta name="apple-mobile-web-app-title" content="Xomiton">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
<link rel="icon" type="image/svg+xml" href="{{ asset('assets/images/xomiton/brand/icone-appli-bleu.svg') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
{{-- Thème appliqué avant le premier rendu pour éviter le clignotement (prototype partials/head-css.php). --}}
<script nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}">(function(){try{var t=localStorage.getItem('theme')||'light';if(t==='auto'){t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'}document.documentElement.setAttribute('data-bs-theme',t)}catch(e){}})();</script>
{{--
    Polices (PERFORMANCE.md §6) : Montserrat est une police variable (graisses 100–900 dans un seul WOFF2 par sous-ensemble,
    fonts.css) ; les titres en 700 / 800 utilisent le fichier latin, préchargé ici. Les sous-ensembles latin étendu et
    vietnamien restent chargés à la demande par unicode-range.
--}}
<link rel="preload" as="font" type="font/woff2" crossorigin href="{{ Illuminate\Support\Facades\Vite::asset('resources/fonts/montserrat/Montserrat-latin-normal.woff2') }}">
@if ($critical)
    {{-- CSS critique en ligne (au-dessus de la ligne de flottaison), avant le CSS principal qui garde le dernier mot. --}}
    <style nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}">{!! Illuminate\Support\Facades\Vite::content('resources/css/critical.css') !!}</style>
@endif
@vite($css ?? ['resources/css/xomiton.css'])
