{{-- $items = [['label' => 'Accueil', 'url' => '/'], ['label' => 'Ressources']] : même source pour le HTML et le JSON-LD. --}}
@props(['items'])
<nav aria-label="{{ __("Fil d'Ariane") }}">
    <ol class="breadcrumb mb-0">
        @foreach ($items as $item)
            @if ($loop->last || empty($item['url']))
                <li class="breadcrumb-item active" aria-current="page">{{ $item['label'] }}</li>
            @else
                <li class="breadcrumb-item"><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>
            @endif
        @endforeach
    </ol>
</nav>
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => collect($items)->values()->map(fn ($i, $k) => array_filter([
        '@type' => 'ListItem', 'position' => $k + 1, 'name' => $i['label'],
        'item' => isset($i['url']) ? url($i['url']) : null,
    ]))->all(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
