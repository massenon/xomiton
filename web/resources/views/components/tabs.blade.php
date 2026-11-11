{{--
    Onglets (pattern ARIA tablist via Bootstrap Tab : aria-selected, tabindex et flèches gauche/droite gérés ; prototype : .nav.nav-pills.gap-2).
    L'onglet actif est mémorisé dans l'URL (?tab=) par resources/js/public/xomiton-ui.js (data-xm-tabs) ; côté serveur, ?tab= sélectionne l'onglet au rendu.
    Props :
      tabs   : ['infos' => 'Informations', 'notes' => 'Notes'] (les clés sont aussi les noms de slots)
      label  : nom accessible de la liste d'onglets
      id     : préfixe des identifiants (défaut tab)
      active : clé active par défaut (?tab= prime)
      pills  : true (défaut, nav-pills) | false (nav-tabs)
      param  : nom du paramètre d'URL (défaut tab)
    Slots : un slot nommé par clé (<x-slot:infos>…</x-slot:infos>) pour chaque panneau.
--}}
@props(['tabs', 'label' => null, 'id' => 'tab', 'active' => null, 'pills' => true, 'param' => 'tab'])
@php
    $current = request()->query($param);
    $active = ($current !== null && array_key_exists($current, $tabs)) ? $current : ($active ?? array_key_first($tabs));
@endphp
<div {{ $attributes->class('xm-tabs') }} data-xm-tabs="{{ $param }}">
    <ul class="nav {{ $pills ? 'nav-pills' : 'nav-tabs' }} gap-2 mb-4" role="tablist" aria-label="{{ $label ?? __('components.tabs.label') }}">
        @foreach ($tabs as $key => $title)
            @php($on = (string) $key === (string) $active)
            <li class="nav-item" role="presentation">
                <button @class(['nav-link', 'active' => $on]) id="{{ $id }}-{{ $key }}" data-bs-toggle="tab" data-bs-target="#{{ $id }}-{{ $key }}-panel" type="button" role="tab" aria-controls="{{ $id }}-{{ $key }}-panel" aria-selected="{{ $on ? 'true' : 'false' }}"{!! $on ? '' : ' tabindex="-1"' !!} data-xm-tab="{{ $key }}">{{ $title }}</button>
            </li>
        @endforeach
    </ul>
    <div class="tab-content">
        @foreach ($tabs as $key => $title)
            @php($on = (string) $key === (string) $active)
            <div @class(['tab-pane fade', 'show active' => $on]) id="{{ $id }}-{{ $key }}-panel" role="tabpanel" aria-labelledby="{{ $id }}-{{ $key }}" tabindex="0">{{ ${$key} ?? '' }}</div>
        @endforeach
    </div>
</div>
