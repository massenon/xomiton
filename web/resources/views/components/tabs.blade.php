{{-- $tabs = ['infos' => 'Informations', 'notes' => 'Notes'] ; panneaux via slots nommés du même nom ; onglet actif dans ?tab= --}}
@props(['tabs', 'label' => 'Onglets'])
<div x-data="{ tab: new URLSearchParams(location.search).get('tab') || @js(array_key_first($tabs)),
               set(t) { this.tab = t; const u = new URL(location); u.searchParams.set('tab', t); history.replaceState(null, '', u); },
               move(e) { const k = Object.keys(@js($tabs)); let i = k.indexOf(this.tab); i = e.key === 'ArrowRight' ? (i + 1) % k.length : (i - 1 + k.length) % k.length; this.set(k[i]); this.$nextTick(() => document.getElementById('tab-' + this.tab).focus()); } }">
    <div class="nav nav-pills gap-2 mb-4" role="tablist" aria-label="{{ $label }}" @keydown.right.prevent="move($event)" @keydown.left.prevent="move($event)">
        @foreach ($tabs as $key => $title)
            <button type="button" role="tab" id="tab-{{ $key }}" class="nav-link" aria-controls="panel-{{ $key }}"
                    x-bind:class="tab === '{{ $key }}' && 'active'" x-bind:aria-selected="tab === '{{ $key }}'" x-bind:tabindex="tab === '{{ $key }}' ? 0 : -1"
                    @click="set('{{ $key }}')">{{ $title }}</button>
        @endforeach
    </div>
    @foreach ($tabs as $key => $title)
        <div role="tabpanel" id="panel-{{ $key }}" aria-labelledby="tab-{{ $key }}" tabindex="0" x-show="tab === '{{ $key }}'" x-cloak>{{ ${$key} ?? '' }}</div>
    @endforeach
</div>
