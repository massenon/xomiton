{{--
    Décor des pages de connexion et d'inscription : ruban arc-en-ciel en haut, petites illustrations en bas
    (mêmes six couleurs atténuées et même composition que le prototype mobile et Flutter XmAuthDecor).
    Purement décoratif : aria-hidden, pointer-events:none, statique (aucune animation), derrière le contenu,
    positionné en absolu dans un parent `position:relative` donc sans hauteur ajoutée. Styles : css/app/auth.css (.xm-auth-decor).
    À ne jamais utiliser sur SOS, signalement, cas ni tableaux de bord.
--}}
@php
    $topRibbon = collect(range(1, 6))->map(fn ($i) => '<path d="M-10 '.(7 + ($i - 1) * 6.5).' C 90 '.(17 + ($i - 1) * 6.5).', 220 '.(-3 + ($i - 1) * 6.5).', 400 '.(11 + ($i - 1) * 6.5).'" style="stroke:var(--xm-rainbow-'.$i.')"/>')->implode('');
    $arcs = collect(range(1, 6))->map(fn ($i) => '<path d="M'.(14 + ($i - 1) * 6).' 84 A '.(64 - ($i - 1) * 6).' '.(64 - ($i - 1) * 6).' 0 0 1 '.(142 - ($i - 1) * 6).' 84" style="stroke:var(--xm-rainbow-'.$i.')"/>')->implode('');
@endphp
<div {{ $attributes->class(['xm-auth-decor']) }} aria-hidden="true">
    <svg class="xm-auth-decor__top" viewBox="0 0 390 52" preserveAspectRatio="none" focusable="false">
        <g fill="none" stroke-width="7.5" opacity=".62">{!! $topRibbon !!}</g>
    </svg>
    <svg class="xm-auth-decor__end" viewBox="0 0 390 84" preserveAspectRatio="xMaxYMax slice" focusable="false">
        <g fill="none" stroke-width="6" opacity=".55">{!! $arcs !!}</g>
        <path d="M138 84v-8a11 11 0 0 1 11-11 14 14 0 0 1 27 2 9 9 0 0 1 10 9v8z" style="fill:var(--xm-neutral-0);stroke:var(--xm-neutral-200)" stroke-width="1.5" opacity=".8"/>
        <path d="M316 40c-9-7-17-1-14 7 2 5 9 10 14 14 5-4 12-9 14-14 3-8-5-14-14-7z" style="fill:var(--xm-rainbow-1)" opacity=".5"/>
        <path d="M262 34l2.6 6 6.4.6-4.9 4.2 1.5 6.3-5.6-3.4-5.6 3.4 1.5-6.3-4.9-4.2 6.4-.6z" style="fill:var(--xm-rainbow-3)" opacity=".75"/>
        <path d="M356 18l1.8 4.2 4.5.4-3.4 3 1 4.4-3.9-2.4-3.9 2.4 1-4.4-3.4-3 4.5-.4z" style="fill:var(--xm-rainbow-6)" opacity=".7"/>
        <circle cx="224" cy="66" r="3" style="fill:var(--xm-rainbow-5)" opacity=".7"/>
        <circle cx="372" cy="70" r="4" style="fill:var(--xm-rainbow-4)" opacity=".7"/>
    </svg>
</div>
