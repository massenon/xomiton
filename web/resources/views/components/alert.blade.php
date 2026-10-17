{{--
    Message contextuel Bootstrap (prototype flash_html() : .alert.alert-{type}[.alert-dismissible.fade.show][role=alert] + .btn-close).
    Props :
      type        : info | success | warning | danger | primary
      title       : phrase en gras au-dessus du message
      dismissible : bouton « Fermer » (data-bs-dismiss)
      icon        : nom Bootstrap Icons sans « bi- » (null = icône par défaut du type, false = aucune)
    Rôle : status (info, success) ou alert (warning, danger : annonce immédiate). Jamais la couleur seule : icône + texte.
--}}
@props(['type' => 'info', 'title' => null, 'dismissible' => false, 'icon' => null])
@php
    $icons = ['primary' => 'info-circle', 'info' => 'info-circle', 'success' => 'check-circle', 'warning' => 'exclamation-triangle', 'danger' => 'exclamation-octagon'];
    $icon = $icon === false ? null : ($icon ?? $icons[$type] ?? 'info-circle');
    $role = in_array($type, ['danger', 'warning'], true) ? 'alert' : 'status';
@endphp
<div {{ $attributes->class(['alert', "alert-$type", 'd-flex align-items-start gap-2', 'alert-dismissible fade show' => $dismissible]) }} role="{{ $role }}">
    @if ($icon)<i class="bi bi-{{ $icon }} flex-shrink-0" aria-hidden="true"></i>@endif
    <div class="flex-grow-1">@if ($title)<p class="fw-bold mb-1">{{ $title }}</p>@endif{{ $slot }}</div>
    @if ($dismissible)<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('common.close') }}"></button>@endif
</div>
