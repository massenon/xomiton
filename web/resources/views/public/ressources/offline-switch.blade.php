{{--
    Interrupteur « Hors ligne » (prototype ressources.php l.71) : case `role="switch"` + libellé visible et complément
    masqué unique (titre de la ressource) ; état porté par `checked`, jamais la couleur seule ; cible ≥ 44 px
    (.xm-offline-switch). `data-offline-url` (page) et `data-offline-file` (téléchargement stable) sont le contrat avec
    le service worker (P5-10), relayé par l'événement `xm:offline-resources` du composant xmOfflineLibrary.
    Variables : $slug, $pageUrl, $title, $fileUrl (ou null), $small (bool).
--}}
<div @class(['form-check form-switch mb-0 xm-offline-switch', 'small' => $small ?? false])>
    <input class="form-check-input" type="checkbox" role="switch" id="off-{{ $slug }}"
           data-offline-slug="{{ $slug }}" data-offline-url="{{ $pageUrl }}"@if ($fileUrl) data-offline-file="{{ $fileUrl }}"@endif data-offline-title="{{ $title }}"
           x-bind:checked="isSaved(@js($slug))" x-on:change="toggle(@js($slug), $el)">
    <label class="form-check-label" for="off-{{ $slug }}">{{ __('pages/ressources.offline_switch') }}<span class="visually-hidden">&#160;{{ __('pages/ressources.offline_switch_hint', ['title' => $title]) }}</span></label>
</div>
