{{-- Refuse le rendu sans alt. Décorative : alt="" explicite (+ aria-hidden). LCP : :priority="true". --}}
@props(['src', 'alt', 'title' => null, 'width' => null, 'height' => null, 'priority' => false, 'webp' => null, 'avif' => null])
@php
    if (! isset($alt)) {
        throw new \InvalidArgumentException('<x-img> exige un attribut alt (utiliser alt="" pour une image décorative).');
    }
    $decorative = $alt === '';
@endphp
<picture>
    @if ($avif)<source type="image/avif" srcset="{{ $avif }}">@endif
    @if ($webp)<source type="image/webp" srcset="{{ $webp }}">@endif
    <img {{ $attributes->merge([
        'src' => $src, 'alt' => $alt, 'title' => $decorative ? null : ($title ?? $alt),
        'width' => $width, 'height' => $height,
        'loading' => $priority ? 'eager' : 'lazy', 'decoding' => 'async',
        'fetchpriority' => $priority ? 'high' : null,
        'aria-hidden' => $decorative ? 'true' : null,
    ]) }}>
</picture>
