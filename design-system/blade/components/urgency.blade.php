@props(['level' => 'low'])
@php
    $levels = [
        'low' => ['Faible', 'info-circle'],
        'medium' => ['Moyenne', 'exclamation-circle'],
        'high' => ['Élevée', 'exclamation-triangle'],
        'critical' => ['Critique', 'exclamation-octagon-fill'],
    ];
    [$text, $icon] = $levels[$level] ?? $levels['low'];
    $level = isset($levels[$level]) ? $level : 'low';
@endphp
<span {{ $attributes->class(['xm-urgency', "xm-urgency--$level"]) }}><i class="bi bi-{{ $icon }}" aria-hidden="true"></i><span class="visually-hidden">{{ __('Urgence :') }}</span> {{ $text }}</span>
