{{--
    État vide (prototype ressources.php : .text-center.text-secondary > i.bi.fs-1 + p + action ; UX_GUIDELINES §3 : illustration + une phrase + une action).
    Props :
      title   : phrase principale
      icon    : Bootstrap Icons sans « bi- » (défaut inbox)
      image   : illustration décorative (alt="") à la place de l'icône
      compact : padding réduit (cellule de tableau, liste courte)
    Slots : défaut (phrase d'explication), action (bouton ou lien).
--}}
@props(['title', 'icon' => 'inbox', 'image' => null, 'compact' => false])
<div {{ $attributes->class(['text-center text-secondary xm-empty', $compact ? 'py-4 px-3' : 'py-5 px-3']) }}>
    @if ($image)
        <x-img :src="$image" alt="" width="160" class="mb-3" />
    @else
        <i class="bi bi-{{ $icon }} fs-1" aria-hidden="true"></i>
    @endif
    <p class="h5 text-body mt-2 mb-2">{{ $title }}</p>
    @if (! $slot->isEmpty())<p class="mb-2 mx-auto xm-prose">{{ $slot }}</p>@endif
    @isset($action)<div class="mt-3">{{ $action }}</div>@endisset
</div>
