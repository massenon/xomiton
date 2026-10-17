{{--
    Logo Xomiton (charte §Logo : jamais recomposé en police ; hauteur minimale 24 px en horizontal).
    Fichiers : public/assets/images/xomiton/brand/logo-{variante}.svg.
    Props :
      variant : couleur | sombre | mono-encre | mono-bleu | mono-blanc | vertical-couleur | vertical-sombre
      width   : largeur en px (la hauteur est déduite du ratio de la variante)
      link    : true → lien vers l'accueil (aria-label « Xomiton, accueil »), false → <span>
--}}
@props(['variant' => 'couleur', 'width' => 160, 'link' => true])
@php
    $ratio = str_starts_with($variant, 'vertical') ? 125.14 / 218.17 : 1 / 3.89;
    $height = (int) round($width * $ratio);
    $src = asset("assets/images/xomiton/brand/logo-$variant.svg");
@endphp
@if ($link)
    <a href="{{ url('/') }}" {{ $attributes }} aria-label="{{ __('components.logo.home') }}"><img src="{{ $src }}" alt="{{ __('components.logo.alt') }}" title="{{ __('components.logo.alt') }}" width="{{ (int) $width }}" height="{{ $height }}"></a>
@else
    <span {{ $attributes }}><img src="{{ $src }}" alt="{{ __('components.logo.alt') }}" title="{{ __('components.logo.alt') }}" width="{{ (int) $width }}" height="{{ $height }}"></span>
@endif
