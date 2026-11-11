{{--
    Pied de tableau d'administration (P10-02, Q23 : pagination **serveur** 10 / 25 / 50, 25 par défaut ; prototype
    xomiton-admin.js : « Affichage de 1 à 10 sur 57 entrées » + Précédent / 1 2 … 11 / Suivant). Boutons Livewire
    (WithPagination : gotoPage, previousPage, nextPage), taille de page `wire:model.live="perPage"` (URL ?par=).
    Fenêtre de pages : 1, 2, p-1, p, p+1, n-1, n et « … » entre deux trous, comme le prototype.
    Props :
      paginator : LengthAwarePaginator de l'écran
      options   : tailles proposées (défaut 10, 25, 50)
      pageName  : nom du paramètre de page (défaut page)
      id        : préfixe des identifiants (défaut xmPager)
--}}
@props(['paginator', 'options' => [10, 25, 50], 'pageName' => 'page', 'id' => 'xmPager'])
@php
    $total = $paginator->total();
    $current = $paginator->currentPage();
    $last = max(1, $paginator->lastPage());
    $format = \App\Support\LocaleFormatter::make();
    $showing = match (true) {
        $total === 0 => __('admin.datatable.showing_none'),
        $total === 1 => __('admin.datatable.showing_one'),
        default => __('admin.datatable.showing', ['from' => $format->number((int) $paginator->firstItem()), 'to' => $format->number((int) $paginator->lastItem()), 'total' => $format->number($total)]),
    };
    $pages = [];
    $previous = 0;
    foreach (array_unique(array_filter([1, 2, $current - 1, $current, $current + 1, $last - 1, $last], fn (int $p): bool => $p >= 1 && $p <= $last)) as $page) {
        $pages[] = $page;
    }
    sort($pages);
    $window = [];
    foreach ($pages as $page) {
        if ($page - $previous > 1) {
            $window[] = null;
        }
        $window[] = $page;
        $previous = $page;
    }
@endphp
<div {{ $attributes->class('d-flex flex-wrap justify-content-between align-items-center gap-2 w-100 xm-pager') }}>
    <div class="d-flex flex-wrap align-items-center gap-3 small text-secondary">
        <span role="status" data-xm-pager-status>{{ $showing }}</span>
        <label class="d-flex align-items-center gap-1 mb-0" for="{{ $id }}PerPage">{{ __('admin.datatable.per_page') }}
            <select class="form-select form-select-sm w-auto" id="{{ $id }}PerPage" wire:model.live="perPage">
                @foreach ($options as $size)
                    <option value="{{ $size }}">{{ $size }}</option>
                @endforeach
            </select>
        </label>
    </div>
    @if ($last > 1)
        <nav aria-label="{{ __('admin.datatable.pagination') }}">
            <ul class="pagination pagination-sm mb-0 flex-wrap xm-dt-pager">
                <li @class(['page-item', 'disabled' => $current <= 1])>
                    <button type="button" class="page-link" wire:click="previousPage('{{ $pageName }}')" @disabled($current <= 1)>{{ __('admin.datatable.previous') }}</button>
                </li>
                @foreach ($window as $page)
                    @if ($page === null)
                        <li class="page-item disabled"><span class="page-link">{{ __('admin.datatable.ellipsis') }}</span></li>
                    @else
                        <li @class(['page-item', 'active' => $page === $current]) wire:key="{{ $id }}-p{{ $page }}">
                            <button type="button" class="page-link" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')"
                                    aria-label="{{ $page === $current ? __('admin.datatable.current_page', ['page' => $page]) : __('admin.datatable.goto_page', ['page' => $page]) }}"
                                    @if ($page === $current) aria-current="page" @endif>{{ $page }}</button>
                        </li>
                    @endif
                @endforeach
                <li @class(['page-item', 'disabled' => $current >= $last])>
                    <button type="button" class="page-link" wire:click="nextPage('{{ $pageName }}')" @disabled($current >= $last)>{{ __('admin.datatable.next') }}</button>
                </li>
            </ul>
        </nav>
    @endif
</div>
