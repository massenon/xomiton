{{--
    Onglets d'un écran d'administration Livewire (P12-03, P12-04, P12-05) : navigation entre sections d'une même page,
    pilotée par le serveur (`wire:click="setTab('clé')"`, l'onglet vit dans l'URL `?onglet=`). Ce n'est pas un widget ARIA
    « tablist » (les panneaux ne sont pas tous dans la page) mais une barre de navigation : `aria-current="page"` sur
    l'onglet actif, défilement horizontal sur mobile.
    Props :
      tabs    : ['clé' => ['label' => 'Ressources', 'icon' => 'folder2-open', 'count' => 12], …]
      current : clé active ; label : nom accessible de la barre ; method : méthode Livewire (défaut setTab)
--}}
@props(['tabs', 'current', 'label' => null, 'method' => 'setTab'])
<nav {{ $attributes->class('mb-4') }} aria-label="{{ $label ?? __('admin_content.tabs_label') }}">
    <ul class="nav nav-tabs flex-nowrap overflow-auto">
        @foreach ($tabs as $key => $tab)
            <li class="nav-item">
                <button type="button" @class(['nav-link text-nowrap', 'active' => (string) $key === (string) $current]) wire:click="{{ $method }}('{{ $key }}')" wire:key="tab-{{ $key }}"
                        @if ((string) $key === (string) $current) aria-current="page" @endif>
                    @if (! empty($tab['icon']))<i class="bi bi-{{ $tab['icon'] }} me-1" aria-hidden="true"></i>@endif{{ $tab['label'] }}
                    @isset($tab['count'])<span class="badge bg-secondary-subtle text-secondary-emphasis ms-1">{{ $tab['count'] }}</span>@endisset
                </button>
            </li>
        @endforeach
    </ul>
</nav>
