{{--
    Logo Xomiton (BRAND_GUIDE §9, charte §Logo : jamais recomposé en police, zone de protection respectée ; hauteur
    minimale 24 px en horizontal). Source unique : public/assets/images/xomiton/brand/{logo,icone-appli}-*.svg (P0-04).
    Props :
      variant  : couleur | sombre | mono-encre | mono-bleu | mono-blanc (alias blanc) | vertical-couleur | vertical-sombre
                 | icone-bleu (alias icone) | icone-blanc | icone-sombre
      adaptive : true → deux <img> (couleur en clair, sombre en sombre) commutés par .light-mode-item / .dark-mode-item
                 du thème Social ; variant est alors ignoré (en-têtes public et sobre)
      width    : largeur en px (la hauteur est déduite du viewBox de la famille : horizontal 218.17 × 56.08,
                 vertical 218.17 × 125.14, icône carrée)
      link     : true → lien vers href (aria-label « Xomiton, accueil »), false → <span>
      href     : cible du lien (défaut : accueil)
      imgClass : classes posées sur l'<img> (ex. navbar-brand-item) ; $attributes vont sur le lien / le <span>
    alt = « Xomiton » (components.logo.alt), title identique.
--}}
@props(['variant' => 'couleur', 'adaptive' => false, 'width' => 160, 'link' => true, 'href' => null, 'imgClass' => ''])
@php
    $aliases = ['blanc' => 'mono-blanc', 'icone' => 'icone-bleu'];
    $variant = $aliases[$variant] ?? $variant;
    $family = match (true) {
        str_starts_with($variant, 'vertical') => 'vertical',
        str_starts_with($variant, 'icone') => 'icone',
        default => 'horizontal',
    };
    $ratio = match ($family) {
        'vertical' => 125.14 / 218.17,
        'icone' => 1.0,
        default => 56.08 / 218.17,
    };
    $width = (int) $width;
    $height = (int) round($width * $ratio);
    $file = fn (string $v): string => asset('assets/images/xomiton/brand/'.($family === 'icone' ? 'icone-appli-'.substr($v, 6) : "logo-$v").'.svg');
    $alt = __('components.logo.alt');
    $imgAttrs = fn (string $v, string $extra = ''): string => sprintf(
        'src="%s" alt="%s" title="%s" width="%d" height="%d"%s',
        e($file($v)), e($alt), e($alt), $width, $height, trim($extra.' '.$imgClass) !== '' ? ' class="'.e(trim($extra.' '.$imgClass)).'"' : ''
    );
@endphp
@if ($link)
    <a href="{{ $href ?? url('/') }}" {{ $attributes }} aria-label="{{ __('components.logo.home') }}">
@else
    <span {{ $attributes }}>
@endif
@if ($adaptive)
<img {!! $imgAttrs('couleur', 'light-mode-item') !!}><img {!! $imgAttrs('sombre', 'dark-mode-item') !!}>
@else
<img {!! $imgAttrs($variant) !!}>
@endif
@if ($link)
    </a>
@else
    </span>
@endif
