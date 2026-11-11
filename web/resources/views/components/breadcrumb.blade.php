{{--
    Fil d'Ariane (prototype help-details.php : nav[aria-label="Fil d’Ariane"] > ol.breadcrumb.mb-0.small ; dernier élément actif, aria-current="page").
    Props :
      items  : [['label' => 'Accueil', 'url' => '/'], ['label' => 'Ressources', 'url' => '/ressources'], ['label' => 'Page courante']]
      jsonLd : bool (défaut false). <x-seo :breadcrumb="…"> émet déjà le BreadcrumbList à partir de la même source ;
               passer true seulement sur une page qui n'utilise pas x-seo, pour éviter un doublon JSON-LD.
--}}
@props(['items', 'jsonLd' => false])
@php
    $jsonLdData = $jsonLd ? json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => collect($items)->values()->map(fn ($i, $k) => array_filter([
            '@type' => 'ListItem', 'position' => $k + 1, 'name' => $i['label'],
            'item' => ! empty($i['url']) ? url($i['url']) : null,
        ]))->all(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) : null;
@endphp
<nav {{ $attributes->merge(['aria-label' => __('components.breadcrumb.label')]) }}>
    <ol class="breadcrumb mb-0 small">
        @foreach ($items as $item)
            @if ($loop->last || empty($item['url']))
                <li class="breadcrumb-item active" aria-current="page">{{ $item['label'] }}</li>
            @else
                <li class="breadcrumb-item"><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>
            @endif
        @endforeach
    </ol>
</nav>
@if ($jsonLdData)<script type="application/ld+json">{!! $jsonLdData !!}</script>@endif
