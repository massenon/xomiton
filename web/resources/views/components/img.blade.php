{{--
    Image (COMPOSANTS §Images, PERFORMANCE.md §2, P4-12) : refuse le rendu sans alt (exception) ; alt="" + aria-hidden pour une
    image décorative ; loading="lazy" + decoding="async" par défaut ; priority → image LCP (loading="eager", fetchpriority="high") ;
    width / height contre le décalage de mise en page ; largeur utile plafonnée à 1600 px.

    Trois façons de fournir les sources :
      - src seul (+ srcset / sizes facultatifs) : <img> simple ;
      - avif / webp (srcset d'un format) ou variants (['image/avif' => '… 800w, …', 'image/webp' => '…']) : <picture> AVIF → WebP → src ;
      - media (App\Application\Shared\StoredMedia ou sa forme toArray(), disque public) : <picture> construit par
        App\Support\ImageSources depuis ce que MediaUploader produit vraiment — WebP + repli JPEG/PNG en thumb 320 / medium 1024 /
        large 1600 ; aucun AVIF n'est inventé (GD sans libavif sur l'hébergement) ; sizes par défaut « (max-width: 1600px) 100vw, 1600px ».
    Props : src, alt (obligatoire), title (défaut = alt, aucun si décorative), width, height, priority (bool), srcset, sizes, webp, avif, variants, media.
--}}
@props(['src' => null, 'alt' => null, 'title' => null, 'width' => null, 'height' => null, 'priority' => false, 'srcset' => null, 'sizes' => null, 'webp' => null, 'avif' => null, 'variants' => [], 'media' => null])
@php
    if ($alt === null) {
        throw new \InvalidArgumentException(__('components.img.missing_alt'));
    }
    $sources = array_filter(array_merge((array) $variants, array_filter(['image/avif' => $avif, 'image/webp' => $webp])));
    if ($media !== null) {
        $resolved = \App\Support\ImageSources::fromMedia($media);
        $src ??= $resolved['src'];
        $srcset ??= $resolved['srcset'];
        $width ??= $resolved['width'];
        $height ??= $resolved['height'];
        $sources = $sources + $resolved['sources'];
    }
    if ($src === null) {
        throw new \InvalidArgumentException(__('components.img.missing_src'));
    }
    // Image livrée avec l'application : sa version WebP voisine (public/assets/…/x.webp) est proposée d'abord.
    if ($media === null && $srcset === null && $sources === [] && ($bundled = \App\Support\ImageSources::bundledWebp((string) $src)) !== null) {
        $sources = ['image/webp' => $bundled];
    }
    // AVIF d'abord, WebP ensuite, autres types déclarés après (ordre de <picture> : du plus compact au repli).
    $order = ['image/avif' => 0, 'image/webp' => 1];
    uksort($sources, fn (string $a, string $b): int => ($order[$a] ?? 2) <=> ($order[$b] ?? 2));
    $hasCandidates = $srcset !== null || $sources !== [];
    $sizes = $hasCandidates ? ($sizes ?? \App\Support\ImageSources::DEFAULT_SIZES) : null;
    $decorative = $alt === '';
    $img = array_filter([
        'src' => $src, 'alt' => $alt, 'title' => $decorative ? null : ($title ?? $alt),
        'width' => $width, 'height' => $height, 'srcset' => $srcset, 'sizes' => $srcset ? $sizes : null,
        'loading' => $priority ? 'eager' : 'lazy', 'decoding' => 'async',
        'fetchpriority' => $priority ? 'high' : null,
        'aria-hidden' => $decorative ? 'true' : null,
    ], fn ($v) => $v !== null);
@endphp
@if ($sources !== [])
<picture>
@foreach ($sources as $type => $candidates)
    <source type="{{ $type }}" srcset="{{ $candidates }}" sizes="{{ $sizes }}">
@endforeach
    <img {{ $attributes->merge($img) }}>
</picture>
@else
<img {{ $attributes->merge($img) }}>
@endif
