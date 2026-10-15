@props(['label', 'value', 'trend' => null, 'direction' => 'flat', 'href' => null, 'icon' => null])
@php
    $arrows = ['up' => ['arrow-up-right', 'en hausse'], 'down' => ['arrow-down-right', 'en baisse'], 'flat' => ['dash', 'stable']];
    [$arrow, $word] = $arrows[$direction] ?? $arrows['flat'];
@endphp
<div {{ $attributes->class('xm-card xm-card--stat h-100') }}>
    <p class="xm-hint mb-1 d-flex align-items-center gap-2">@if ($icon)<i class="bi bi-{{ $icon }}" aria-hidden="true"></i>@endif{{ $label }}</p>
    <p class="mb-1" style="font-family:var(--xm-font-heading);font-weight:800;font-size:2.25rem;line-height:1.1">{{ $value }}</p>
    @if ($trend)<p class="mb-1 fw-bold small"><i class="bi bi-{{ $arrow }}" aria-hidden="true"></i> {{ $trend }} <span class="visually-hidden">({{ $word }})</span></p>@endif
    @if ($href)<a href="{{ $href }}" class="small">{{ __('Voir le détail') }}</a>@endif
</div>
