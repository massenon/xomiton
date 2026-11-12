{{--
    Visionneuse de BD accessible (P19-04, P8-12 « x-comic-viewer ») : planches `{image, caption, alt}` dans l'ordre stocké.
    Sans JavaScript : toutes les planches à la suite (figure + légende), imprimables et indexables. Avec JavaScript
    (resources/js/public/comic-viewer.js, xmComic) : une planche à la fois, boutons Précédente / Suivante, compteur annoncé
    (aria-live polite), flèches ← / → et Début / Fin quand le focus est dans la visionneuse, ancre `#planche-n`.
    Chargement paresseux : la première planche est l'image LCP, les autres en `loading="lazy"`. Impression : toutes les
    planches, sans les boutons (comic.css). `alt` par planche (repli : « titre – planche n »), `title` = légende courte.
    Props : pages (list), title (titre de la BD), id (identifiant unique, défaut `bd`).
--}}
@props(['pages', 'title', 'id' => 'bd'])
@php
    $pages = array_values(array_filter((array) $pages, 'is_array'));
    $count = count($pages);
    $options = ['count' => $count, 'texts' => ['counter' => __('pages/bd.viewer.counter')]];
@endphp
<section {{ $attributes->class(['xm-comic']) }} id="{{ $id }}" aria-labelledby="{{ $id }}-titre" x-data='xmComic(@json($options, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE))'>
    <h2 class="visually-hidden" id="{{ $id }}-titre">{{ __('pages/bd.viewer.region', ['title' => $title]) }}</h2>
    <p class="small text-secondary xm-comic-help" id="{{ $id }}-aide" x-show="enhanced" x-cloak>{{ __('pages/bd.viewer.keyboard_help') }}</p>
    <div class="xm-comic-stage" data-xm-comic-stage tabindex="-1" role="group" aria-roledescription="{{ __('pages/bd.viewer.roledescription') }}"
         aria-label="{{ __('pages/bd.viewer.region', ['title' => $title]) }}" aria-describedby="{{ $id }}-aide" x-on:keydown="onKey($event)">
        <ol class="list-unstyled mb-0 vstack gap-4 xm-comic-pages">
            @foreach ($pages as $index => $page)
                @php
                    $n = $index + 1;
                    $alt = trim((string) ($page['alt'] ?? '')) ?: __('pages/bd.viewer.alt_fallback', ['title' => $title, 'n' => $n]);
                @endphp
                <li class="xm-comic-page" id="planche-{{ $n }}" data-xm-comic-page>
                    <figure class="mb-0">
                        <div class="xm-comic-frame rounded-3 overflow-hidden border">
                            <x-img class="img-fluid w-100 d-block" :src="asset((string) ($page['image'] ?? ''))" :alt="$alt"
                                   :title="__('pages/bd.viewer.page_title', ['n' => $n, 'count' => $count, 'title' => $title])" width="1200" height="800" :priority="$index === 0" />
                        </div>
                        <figcaption class="mt-2">
                            <span class="badge bg-primary-subtle text-primary-emphasis me-1">{{ __('pages/bd.viewer.counter', ['n' => $n, 'count' => $count]) }}</span>
                            {{ $page['caption'] ?? '' }}
                        </figcaption>
                    </figure>
                </li>
            @endforeach
        </ol>
    </div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 xm-comic-nav no-print" x-show="enhanced" x-cloak>
        <button type="button" class="btn btn-outline-primary" x-on:click="prev()" x-bind:disabled="isFirst()" aria-controls="{{ $id }}">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('pages/bd.viewer.previous') }}
        </button>
        <p class="mb-0 fw-semibold" aria-live="polite" aria-atomic="true" x-text="counter()">{{ __('pages/bd.viewer.counter', ['n' => 1, 'count' => $count]) }}</p>
        <button type="button" class="btn btn-primary" x-on:click="next()" x-bind:disabled="isLast()" aria-controls="{{ $id }}">
            {{ __('pages/bd.viewer.next') }}<i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
        </button>
    </div>
</section>
