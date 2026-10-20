{{--
    Rangée d'indicateurs clés d'un écran d'administration (P10-02, UI_UX_DESIGN_SYSTEM.md §4.1, Q23 b : écart volontaire,
    aucun gabarit dans le prototype). ≥ 768 px : 4 cartes x-stat par ligne en desktop (col-6 col-xl-3), 2 en tablette.
    < 768 px : accordéon « Indicateurs clés » fermé par défaut, le premier KPI restant lisible dans l'en-tête du bouton.
    Le corps garde son état ouvert / fermé entre deux rendus Livewire (wire:ignore.self) ; les valeurs sont mises à jour.
    Props :
      items : [['label' => …, 'value' => …, 'variant' => '', 'icon' => …, 'trend' => …, 'direction' => …, 'sub' => …,
               'href' => …, 'masked' => bool (effectif < 5 : « données masquées »)], …]
      id    : identifiant (défaut xmKpis) ; title : titre (défaut « Indicateurs clés ») ; cols : classes de colonne
--}}
@props(['items' => [], 'id' => 'xmKpis', 'title' => null, 'cols' => 'col-6 col-xl-3'])
@php
    $title ??= __('admin.kpi.title');
    $items = array_values(array_map(static function (array $item): array {
        if (! empty($item['masked'])) {
            $item['value'] = __('admin.kpi.masked');
            $item['sub'] ??= __('admin.kpi.masked_help', ['min' => \App\Support\SmallCellMask::MIN]);
            $item['trend'] = null;
        }

        return $item;
    }, $items));
    $first = $items[0] ?? null;
@endphp
<section {{ $attributes->class('xm-kpis mb-4') }} aria-labelledby="{{ $id }}Title" data-xm-kpis>
    <h2 class="visually-hidden" id="{{ $id }}Title">{{ $title }}</h2>
    <button type="button" class="xm-kpis__toggle d-md-none" data-bs-toggle="collapse" data-bs-target="#{{ $id }}Body" aria-expanded="false" aria-controls="{{ $id }}Body" title="{{ __('admin.kpi.toggle_help') }}" wire:ignore.self>
        <span class="xm-kpis__toggle-title">{{ $title }}</span>
        @if ($first)
            <span class="xm-kpis__summary">{{ __('admin.kpi.summary', ['label' => $first['label'], 'value' => $first['value']]) }}</span>
        @endif
        <i class="bi bi-chevron-down xm-kpis__chevron" aria-hidden="true"></i>
    </button>
    <div class="collapse d-md-block xm-kpis__body" id="{{ $id }}Body" wire:ignore.self>
        <div class="row g-3">
            @foreach ($items as $item)
                <div class="{{ $cols }}">
                    <x-stat :label="$item['label']" :value="$item['value']" :variant="$item['variant'] ?? ''" :icon="$item['icon'] ?? null"
                            :trend="$item['trend'] ?? null" :direction="$item['direction'] ?? 'flat'" :sub="$item['sub'] ?? null" :href="$item['href'] ?? null"
                            :class="empty($item['masked']) ? '' : 'xm-stat--masked'" />
                </div>
            @endforeach
        </div>
    </div>
</section>
