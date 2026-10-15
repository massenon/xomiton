@props(['type' => 'info', 'title' => null, 'dismissible' => false])
@php
    $icons = ['info' => 'info-circle', 'success' => 'check-circle', 'warning' => 'exclamation-triangle', 'danger' => 'exclamation-octagon'];
    $role = in_array($type, ['danger', 'warning']) ? 'alert' : 'status';
@endphp
<div {{ $attributes->class(["alert alert-$type d-flex gap-3 align-items-start"]) }} role="{{ $role }}">
    <i class="bi bi-{{ $icons[$type] ?? 'info-circle' }} fs-5" aria-hidden="true"></i>
    <div class="flex-grow-1">@if ($title)<p class="fw-bold mb-1">{{ $title }}</p>@endif{{ $slot }}</div>
    @if ($dismissible)<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Fermer') }}"></button>@endif
</div>
