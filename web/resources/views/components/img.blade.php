{{--
    Image (COMPOSANTS §Images, PERFORMANCE.md) : refuse le rendu sans alt (exception) ; alt="" + aria-hidden pour une image décorative ;
    loading="lazy" + decoding="async" par défaut ; priority → image LCP (loading="eager", fetchpriority="high") ;
    width / height contre le décalage de mise en page ; srcset / sizes facultatifs ; sources AVIF / WebP facultatives (<picture> seulement si fournies).
    Props : src, alt (obligatoire), title (défaut = alt, aucun si décorative), width, height, priority (bool), srcset, sizes, webp, avif.
--}}
@props(['src', 'alt' => null, 'title' => null, 'width' => null, 'height' => null, 'priority' => false, 'srcset' => null, 'sizes' => null, 'webp' => null, 'avif' => null])
@php
    if ($alt === null) {
        throw new \InvalidArgumentException(__('components.img.missing_alt'));
    }
    $decorative = $alt === '';
    $img = array_filter([
        'src' => $src, 'alt' => $alt, 'title' => $decorative ? null : ($title ?? $alt),
        'width' => $width, 'height' => $height, 'srcset' => $srcset, 'sizes' => $srcset ? $sizes : null,
        'loading' => $priority ? 'eager' : 'lazy', 'decoding' => 'async',
        'fetchpriority' => $priority ? 'high' : null,
        'aria-hidden' => $decorative ? 'true' : null,
    ], fn ($v) => $v !== null);
@endphp
@if ($avif || $webp)
<picture>
    @if ($avif)<source type="image/avif" srcset="{{ $avif }}" @if ($sizes) sizes="{{ $sizes }}" @endif>@endif
    @if ($webp)<source type="image/webp" srcset="{{ $webp }}" @if ($sizes) sizes="{{ $sizes }}" @endif>@endif
    <img {{ $attributes->merge($img) }}>
</picture>
@else
<img {{ $attributes->merge($img) }}>
@endif
