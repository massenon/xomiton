{{--
    Indicateur clé (prototype admin/index.php : a > .card.xm-stat[.danger|.success|.warning|.violet].xm-card-hover.h-100 > .card-body.d-flex :
    libellé small text-secondary, valeur .kpi-value (Montserrat 800), icône à droite décorative ; UI_UX_DESIGN_SYSTEM §4.1).
    Props :
      label, value : obligatoires
      variant      : '' (bleu) | danger | success | warning | violet (la couleur porte un sens, jamais décorative)
      icon         : Bootstrap Icons sans « bi- », affichée à droite
      trend        : texte de tendance (« +12 % ») ; direction : up | down | flat (flèche + mot caché pour les lecteurs d'écran)
      sub          : sous-texte facultatif (période, source…)
      navigate     : true → wire:navigate sur le lien (écrans Livewire de l'administration seulement)
      href         : la carte entière devient un lien vers la liste filtrée (+ survol xm-card-hover)
--}}
@props(['label', 'value', 'variant' => '', 'icon' => null, 'trend' => null, 'direction' => 'flat', 'sub' => null, 'href' => null, 'navigate' => false])
@php
    $arrows = ['up' => ['arrow-up-right', __('components.stat.trend_up')], 'down' => ['arrow-down-right', __('components.stat.trend_down')], 'flat' => ['dash', __('components.stat.trend_flat')]];
    [$arrow, $word] = $arrows[$direction] ?? $arrows['flat'];
@endphp
@if ($href)<a href="{{ $href }}" class="text-decoration-none text-body d-block h-100"{!! $navigate ? ' wire:navigate wire:navigate.hover' : '' !!}>@endif
<div {{ $attributes->class(['card xm-stat h-100', $variant => $variant !== '', 'xm-card-hover' => (bool) $href]) }}>
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <div class="small text-secondary">{{ $label }}</div>
            <div class="kpi-value">{{ $value }}</div>
            @if ($trend)<div class="small fw-semibold"><i class="bi bi-{{ $arrow }}" aria-hidden="true"></i> {{ $trend }}<span class="visually-hidden"> ({{ $word }})</span></div>@endif
            @if ($sub)<div class="small text-secondary">{{ $sub }}</div>@endif
            @if ($href)<span class="small text-primary">{{ __('common.see_details') }}</span>@endif
        </div>
        @if ($icon)<i class="bi bi-{{ $icon }} fs-2 text-secondary opacity-50" aria-hidden="true"></i>@endif
    </div>
</div>
@if ($href)</a>@endif
