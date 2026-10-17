{{--
    Structure de tableau d'administration (prototype admin/*.php : .xm-table-wrap > table.table.table-hover.align-middle.mb-0 ; pied .card-footer.xm-dt-bar ;
    UI_UX_DESIGN_SYSTEM §4.2). Le tri, les filtres et la pagination sont fournis par le composant Livewire appelant ; ici : caption,
    en-têtes scope="col" + aria-sort, état vide, cartes empilées < 768 px (xm-table--stack : chaque <td> porte data-label="Libellé").
    Props :
      caption        : description du tableau (visually-hidden par défaut ; captionVisible=true pour l'afficher)
      columns        : [['key' => 'id', 'label' => 'N°', 'sort' => null | 'none' | 'asc' | 'desc', 'href' => url de tri, 'class' => 'text-end', 'width' => '8rem', 'sr' => 'Actions' (libellé caché d'une colonne sans texte)], …]
      empty          : texte de l'état vide (affiché quand le slot est vide)
      stack          : true (défaut) → cartes empilées sous 768 px ; false → défilement horizontal seul (prototype)
      id             : identifiant du tableau
    Slots : défaut (les <tr> du corps), footer (« Affichage de 1 à 20 sur 57 entrées » + pagination, rendu dans .card-footer.xm-dt-bar).
    Tri : un en-tête triable est un lien (href) ou un bouton data-xm-sort="{key}" (le composant Livewire écoute le clic) ; aria-sort annonce l'ordre courant.
--}}
@props(['caption', 'columns', 'empty' => null, 'stack' => true, 'captionVisible' => false, 'id' => null])
@php
    $sortMap = ['asc' => 'ascending', 'desc' => 'descending', 'none' => 'none'];
    $sortIcon = ['asc' => 'bi-sort-up', 'desc' => 'bi-sort-down'];
@endphp
<div {{ $attributes->class('xm-table-wrap') }}>
    <table @class(['table table-hover align-middle mb-0', 'xm-table--stack' => $stack]){!! $id ? ' id="'.e($id).'"' : '' !!}>
        <caption class="{{ $captionVisible ? 'small text-secondary' : 'visually-hidden' }}">{{ $caption }}</caption>
        <thead>
            <tr>
                @foreach ($columns as $col)
                    @php($thAttrs = (! empty($col['class']) ? ' class="'.e($col['class']).'"' : '').(! empty($col['width']) ? ' style="width:'.e($col['width']).'"' : '').(isset($col['sort']) ? ' aria-sort="'.($sortMap[$col['sort']] ?? 'none').'"' : ''))
                    <th scope="col"{!! $thAttrs !!}>
                        @if (isset($col['sort']))
                            @if (! empty($col['href']))
                                <a class="xm-sort text-reset text-decoration-none fw-bold" href="{{ $col['href'] }}" title="{{ __('components.datatable.sort', ['column' => $col['label']]) }}">{{ $col['label'] }} <i class="bi {{ $sortIcon[$col['sort']] ?? 'bi-arrow-down-up' }} ms-1" aria-hidden="true"></i></a>
                            @else
                                <button type="button" class="btn btn-link btn-sm p-0 xm-sort text-reset text-decoration-none fw-bold" data-xm-sort="{{ $col['key'] }}" title="{{ __('components.datatable.sort', ['column' => $col['label']]) }}">{{ $col['label'] }} <i class="bi {{ $sortIcon[$col['sort']] ?? 'bi-arrow-down-up' }} ms-1" aria-hidden="true"></i></button>
                            @endif
                        @elseif (! empty($col['sr']))
                            <span class="visually-hidden">{{ $col['sr'] }}</span>
                        @else
                            {{ $col['label'] ?? '' }}
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @if (trim((string) $slot) === '')
                <tr><td colspan="{{ count($columns) }}" class="text-center text-secondary py-4 xm-table__empty">{{ $empty ?? __('components.datatable.empty') }}</td></tr>
            @else
                {{ $slot }}
            @endif
        </tbody>
    </table>
</div>
@isset($footer)
    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2 xm-dt-bar">{{ $footer }}</div>
@endisset
