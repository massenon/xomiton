@props([
    'variant' => 'primary',   // primary | secondary | outline | ghost | light | icon
    'size' => 'md',           // sm | md | lg
    'href' => null,
    'type' => 'button',
    'loading' => false,
    'icon' => null,           // nom Bootstrap Icons sans "bi-"
    'label' => null,          // obligatoire pour variant="icon" (aria-label)
])
@php
    $map = ['primary' => 'btn-primary', 'secondary' => 'btn-secondary', 'outline' => 'btn-outline-primary', 'ghost' => 'btn-link text-decoration-none', 'light' => 'btn-light', 'icon' => 'btn-light'];
    $sizes = ['sm' => 'btn-sm', 'md' => '', 'lg' => 'btn-lg'];
    $class = trim('btn '.($map[$variant] ?? 'btn-primary').' '.($sizes[$size] ?? ''));
    if ($variant === 'icon' && ! $label) {
        throw new \InvalidArgumentException('<x-button variant="icon"> exige un attribut label (nom accessible).');
    }
    $tag = $href ? 'a' : 'button';
@endphp
<{{ $tag }}
    {{ $attributes->class([$class, 'd-inline-flex align-items-center justify-content-center gap-2'])->merge($href ? ['href' => $href] : ['type' => $type]) }}
    @if ($label) aria-label="{{ $label }}" @endif
    @if ($loading) aria-busy="true" @if (! $href) disabled @endif @endif>
    @if ($loading)
        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
    @elseif ($icon)
        <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
    @endif
    @if ($variant !== 'icon')<span>{{ $loading ? __('Envoi…') : $slot }}</span>@endif
</{{ $tag }}>
