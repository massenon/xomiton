{{--
    Visionneuse d'une conversation en BD (P20-06) : même composant Alpine que les BD (`xmComic`, comic-viewer.js, ancre
    `#case-n`) autour des cases `x-bd.panel`. Sans JavaScript : toutes les cases à la suite (lisibles, imprimables,
    indexables). Avec JavaScript : une case à la fois, boutons Précédente / Suivante, compteur annoncé (aria-live),
    flèches ← / → et Début / Fin quand le focus est dans la visionneuse.
    Variables : $panels, $names, $title, $id.
--}}
@php($count = count($panels))
<section class="xm-comic xm-bdc-viewer" id="{{ $id }}" aria-labelledby="{{ $id }}-titre" x-data='xmComic(@json(['count' => $count, 'anchor' => 'case', 'texts' => ['counter' => __('pages/conversations_bd.viewer.counter')]], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE))'>
    <h2 class="visually-hidden" id="{{ $id }}-titre">{{ __('pages/conversations_bd.viewer.region', ['title' => $title]) }}</h2>
    <p class="small text-secondary xm-comic-help" id="{{ $id }}-aide" x-show="enhanced" x-cloak>{{ __('pages/conversations_bd.viewer.keyboard_help') }}</p>
    <div class="xm-comic-stage" data-xm-comic-stage tabindex="-1" role="group" aria-roledescription="{{ __('pages/conversations_bd.viewer.roledescription') }}"
         aria-label="{{ __('pages/conversations_bd.viewer.region', ['title' => $title]) }}" aria-describedby="{{ $id }}-aide" x-on:keydown="onKey($event)">
        <ol class="list-unstyled mb-0 vstack gap-4 xm-comic-pages">
            @foreach ($panels as $index => $panel)
                <li class="xm-comic-page" id="case-{{ $panel['n'] }}" data-xm-comic-page>
                    <x-bd.panel :bubbles="$panel['bubbles']" :names="$names" :image="$panel['image']" :alt="$panel['alt']" :n="$panel['n']" :count="$count" :title="$title" :priority="$index === 0" />
                </li>
            @endforeach
        </ol>
    </div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 xm-comic-nav no-print" x-show="enhanced" x-cloak>
        <button type="button" class="btn btn-outline-primary" x-on:click="prev()" x-bind:disabled="isFirst()" aria-controls="{{ $id }}">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('pages/conversations_bd.viewer.previous') }}
        </button>
        <p class="mb-0 fw-semibold" aria-live="polite" aria-atomic="true" x-text="counter()">{{ __('pages/conversations_bd.viewer.counter', ['n' => 1, 'count' => $count]) }}</p>
        <button type="button" class="btn btn-primary" x-on:click="next()" x-bind:disabled="isLast()" aria-controls="{{ $id }}">
            {{ __('pages/conversations_bd.viewer.next') }}<i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
        </button>
    </div>
</section>
