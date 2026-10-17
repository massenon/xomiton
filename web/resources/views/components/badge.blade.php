{{--
    Badge (prototype inc/x_admin1.php xa_badge() : .badge.bg-{couleur}[.text-dark] ; variante douce du fil : .badge.bg-{c}.bg-opacity-10.text-{c}).
    Toujours un libellé en toutes lettres, jamais la couleur seule ; icône facultative (COMPOSANTS §Badges).
    Props :
      color  : primary | secondary (rouge de marque : attention uniquement) | danger | success | warning | info | violet | neutral
      soft   : fond à 10 % + texte coloré
      icon   : Bootstrap Icons sans « bi- »
      pill   : rounded-pill
      scope + status : badge de statut prêt à l'emploi (libellé, couleur, icône depuis lang/fr/components.php « status ») :
               case (new | assigned | in_progress | pending_victim_response | resolved | closed | unresolved),
               alert (nouveau | en_cours | traite), priority (critique | haute | normale), alert_type (denonciation | besoin | sos)
    Slot : libellé (ignoré quand scope + status sont fournis).
    Note : « neutral » remplace le bg-secondary gris du prototype, car secondary est le rouge de marque dans Xomiton (DECISIONS D5).
    Note : la classe violette s'appelle xm-badge-violet (renommée dans design-system/tokens/components.css) : le thème Social applique un
    point d'état absolu à tout élément dont une classe contient « status- » ([class*=status-]:before), ce qui déformait l'ancien xm-status--violet.
--}}
@props(['color' => 'primary', 'soft' => false, 'icon' => null, 'pill' => false, 'scope' => null, 'status' => null])
@php
    $text = null;
    if ($scope !== null && $status !== null) {
        $def = __("components.status.$scope.$status");
        if (is_array($def)) {
            $text = $def['label'];
            $color = $def['color'];
            $icon = $def['icon'] ?? $icon;
            $soft = $def['soft'] ?? $soft;
        } else {
            $text = (string) $status;
            $color = 'neutral';
        }
    }
    $classes = match (true) {
        $color === 'violet' => 'xm-badge-violet',
        $color === 'neutral' => 'bg-light text-body border',
        (bool) $soft => "bg-$color bg-opacity-10 text-$color",
        $color === 'warning' => 'bg-warning text-dark',
        default => "bg-$color",
    };
@endphp
<span {{ $attributes->class(['badge', $classes, 'rounded-pill' => $pill, 'd-inline-flex align-items-center gap-1' => (bool) $icon]) }}>@if ($icon)<i class="bi bi-{{ $icon }}" aria-hidden="true"></i>@endif{{ $text ?? $slot }}</span>
