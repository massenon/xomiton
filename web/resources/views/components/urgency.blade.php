{{--
    Niveau d'urgence (COMPOSANTS §Badges : .xm-urgency.xm-urgency--{level}, icône + mot + couleur, jamais la couleur seule ; social-xomiton.css).
    Props : level (low | medium | high | critical).
--}}
@props(['level' => 'low'])
@php
    $icons = ['low' => 'info-circle', 'medium' => 'exclamation-circle', 'high' => 'exclamation-triangle', 'critical' => 'exclamation-octagon-fill'];
    $level = isset($icons[$level]) ? $level : 'low';
@endphp
<span {{ $attributes->class(['xm-urgency', "xm-urgency--$level"]) }}><i class="bi bi-{{ $icons[$level] }}" aria-hidden="true"></i><span class="visually-hidden">{{ __('components.urgency.prefix') }}</span> {{ __("components.urgency.levels.$level") }}</span>
