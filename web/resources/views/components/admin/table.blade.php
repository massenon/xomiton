{{--
    Tableau d'administration Livewire (P10-02, UI_UX_DESIGN_SYSTEM.md §4.2) : même structure que x-datatable
    (prototype .xm-table-wrap > table.table.table-hover.align-middle.mb-0) branchée sur le trait WithDataTable :
    en-têtes triables (bouton wire:click="sortBy('clé')", `aria-sort` sur la colonne triée seulement, APG), cartes
    empilées < 768 px (chaque <td> porte data-label="Libellé", la cellule d'actions la classe xm-table__actions, en bas
    de carte), squelettes de lignes pendant recherche / tri / filtres / pagination (wire:loading, aucun spinner plein
    écran), état vide dans le corps du tableau.
    Props :
      caption   : description du tableau (visually-hidden)
      columns   : [['key' => 'at', 'label' => 'Date', 'sortable' => true, 'class' => 'text-end', 'width' => '10rem',
                    'sr' => 'Actions' (colonne sans texte visible)], …]
      sort, direction : tri courant ($this->currentSort())
      empty     : vrai quand il n'y a aucune ligne (le slot `emptyState` est alors rendu)
      target    : cibles wire:target des squelettes ($this->dataTableTargets())
      skeleton  : nombre de lignes de squelette (défaut 5)
      stack     : cartes empilées < 768 px (défaut true)
      id        : identifiant du tableau
    Slots : défaut (<tr> du corps), emptyState (x-admin.empty), footer (x-admin.pagination, dans .card-footer).
--}}
@props(['caption', 'columns', 'sort' => null, 'direction' => 'asc', 'empty' => false, 'target' => null, 'skeleton' => 5, 'stack' => true, 'id' => null])
@php
    $loading = $target ? 'wire:target="'.e($target).'"' : '';
@endphp
<div {{ $attributes->class('xm-table-wrap xm-admin-table') }}>
    <table @class(['table table-hover align-middle mb-0', 'xm-table--stack' => $stack]) @if ($id) id="{{ $id }}" @endif>
        <caption class="visually-hidden">{{ $caption }}</caption>
        <thead>
            <tr>
                @foreach ($columns as $col)
                    @php($sorted = ! empty($col['sortable']) && $sort === $col['key'])
                    <th scope="col" @if (! empty($col['class'])) class="{{ $col['class'] }}" @endif @if (! empty($col['width'])) style="width:{{ $col['width'] }}" @endif
                        @if ($sorted) aria-sort="{{ $direction === 'desc' ? 'descending' : 'ascending' }}" @endif>
                        @if (! empty($col['sortable']))
                            <button type="button" class="btn btn-link btn-sm p-0 xm-sort text-reset text-decoration-none fw-bold" wire:click="sortBy('{{ $col['key'] }}')"
                                    title="{{ __('admin.datatable.sort_by', ['column' => $col['label']]) }}">
                                {{ $col['label'] }}<i @class(['bi ms-1', 'bi-sort-down' => $sorted && $direction === 'desc', 'bi-sort-up' => $sorted && $direction !== 'desc', 'bi-arrow-down-up opacity-50' => ! $sorted]) aria-hidden="true"></i>
                                <span class="visually-hidden">{{ $sorted ? '('.($direction === 'desc' ? __('admin.datatable.sort_desc') : __('admin.datatable.sort_asc')).')' : '' }}</span>
                            </button>
                        @elseif (! empty($col['sr']))
                            <span class="visually-hidden">{{ $col['sr'] }}</span>
                        @else
                            {{ $col['label'] ?? '' }}
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody wire:loading.delay.class="d-none" {!! $loading !!} data-xm-table-body>
            @if ($empty)
                <tr><td colspan="{{ count($columns) }}" class="xm-table__empty">{{ $emptyState ?? __('admin.datatable.empty') }}</td></tr>
            @else
                {{ $slot }}
            @endif
        </tbody>
        <tbody class="d-none xm-table__skeleton" wire:loading.delay.class.remove="d-none" {!! $loading !!} aria-hidden="true">
            @for ($i = 0; $i < $skeleton; $i++)
                <tr class="placeholder-glow">
                    @foreach ($columns as $col)
                        <td data-label="{{ $col['label'] ?? ($col['sr'] ?? '') }}"><span class="placeholder col-{{ [8, 10, 6, 9, 7][($i + $loop->index) % 5] }}"></span></td>
                    @endforeach
                </tr>
            @endfor
        </tbody>
    </table>
    <div class="visually-hidden" role="status" wire:loading.delay {!! $loading !!}>{{ __('admin.datatable.loading') }}</div>
</div>
@isset($footer)
    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2 xm-dt-bar">{{ $footer }}</div>
@endisset
