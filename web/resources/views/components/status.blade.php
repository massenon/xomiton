{{-- Statut de cas : couleur + icône + libellé en toutes lettres. --}}
@props(['status' => 'new'])
@php
    $map = [
        'new' => ['Nouveau', 'text-bg-primary', 'stars'],
        'assigned' => ['Assigné', 'xm-status--violet', 'person-check'],
        'in_progress' => ['En cours', 'text-bg-info', 'arrow-repeat'],
        'pending_victim_response' => ['En attente de réponse', 'text-bg-warning', 'hourglass-split'],
        'resolved' => ['Résolu', 'text-bg-success', 'check-circle'],
        'closed' => ['Clos', 'bg-secondary-subtle text-body', 'archive'],
        'unresolved' => ['Non résolu', 'text-bg-danger', 'x-octagon'],
    ];
    [$text, $class, $icon] = $map[$status] ?? $map['new'];
@endphp
<span {{ $attributes->class(['badge d-inline-flex align-items-center gap-1', $class]) }}><i class="bi bi-{{ $icon }}" aria-hidden="true"></i>{{ $text }}</span>
