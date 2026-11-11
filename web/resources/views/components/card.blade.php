{{--
    Carte du thème Social (prototype : .card > [.card-header] .card-body [.card-footer] ; UI_UX_DESIGN_SYSTEM §4.7 : une seule famille de cartes,
    survol xm-card-hover (−3 px) réservé aux cartes entièrement cliquables, désactivé en mouvement réduit).
    Props :
      variant  : content | resource | news | event | contact | stat → classe xm-card--{variant} (accent de bordure haute pour event, contact, stat)
      title    : titre de la carte (h3.h5.card-title dans le corps ; h2.h6 dans l'en-tête quand header=true)
      titleTag : balise du titre (défaut h3, h2 avec header)
      href     : lien unique → carte entièrement cliquable (stretched-link + xm-card-hover + h-100)
      badge    : étiquette (.xm-chip) au-dessus du titre
      image / imageAlt : visuel 16/9 via <x-img> (alt obligatoire ; '' si décoratif)
      meta     : ligne secondaire (small text-secondary : date, durée de lecture…)
      header   : true → carte « liste » d'administration (en-tête h2.h6 + actions à droite)
    Slots : défaut (contenu), actions (boutons : dans l'en-tête si header, sinon dans le pied .card-footer).
--}}
@props(['variant' => 'content', 'title' => null, 'titleTag' => null, 'href' => null, 'badge' => null, 'image' => null, 'imageAlt' => '', 'meta' => null, 'header' => false])
@php($tag = $titleTag ?? ($header ? 'h2' : 'h3'))
<article {{ $attributes->class(['card', "xm-card--$variant", 'xm-card-hover h-100' => (bool) $href]) }}>
    @if ($image)
        <div class="xm-ratio-16x9"><x-img :src="$image" :alt="$imageAlt" :title="$imageAlt ?: null" class="card-img-top xm-img-cover" /></div>
    @endif
    @if ($header)
        <div class="card-header d-flex justify-content-between align-items-center gap-2">
            @if ($title)<{{ $tag }} class="h6 mb-0">{{ $title }}</{{ $tag }}>@endif
            @isset($actions)<div class="d-flex flex-wrap gap-2">{{ $actions }}</div>@endisset
        </div>
    @endif
    <div class="card-body">
        @if ($badge)<span class="xm-chip mb-2">{{ $badge }}</span>@endif
        @if ($title && ! $header)
            <{{ $tag }} class="h5 card-title">@if ($href)<a href="{{ $href }}" class="stretched-link text-reset text-decoration-none">{{ $title }}</a>@else{{ $title }}@endif</{{ $tag }}>
        @endif
        @if ($meta)<p class="small text-secondary mb-2">{{ $meta }}</p>@endif
        {{ $slot }}
    </div>
    @if (! $header && isset($actions))
        <div class="card-footer d-flex flex-wrap gap-2 position-relative">{{ $actions }}</div>
    @endif
</article>
