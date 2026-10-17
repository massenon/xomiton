{{-- Variantes : couleur | sombre | mono-encre | mono-bleu | mono-blanc | vertical-couleur | vertical-sombre.
     Hauteur minimale imposée par la charte : 24 px (horizontal). Fichiers : public/assets/images/brand/logo-*.svg --}}
@props(['variant' => 'couleur', 'width' => 160, 'link' => true])
@php
    $ratio = str_starts_with($variant, 'vertical') ? 125.14 / 218.17 : 1 / 3.89;
    $img = '<img src="'.e(asset("assets/images/brand/logo-$variant.svg")).'" alt="Xomiton" title="Xomiton" width="'.(int) $width.'" height="'.round($width * $ratio).'">';
@endphp
@if ($link)<a href="{{ url('/') }}" {{ $attributes }} aria-label="{{ __('Xomiton, accueil') }}">{!! $img !!}</a>@else<span {{ $attributes }}>{!! $img !!}</span>@endif
