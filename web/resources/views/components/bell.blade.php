{{--
    Cloche de notifications (prototype partials/admin-top.php #xmNotif : bouton bi-bell + badge compteur rond, panneau Non lus / Lues / Toutes,
    « Tout marquer comme lu », liste .xm-notif-list, état vide, lien de pied). NOTIFICATIONS_INSTRUCTION §3.
    Sans données réelles pour l'instant : la liste vient des props ; le filtrage des onglets et le marquage local sont faits par
    resources/js/public/xomiton-ui.js (data-xm-bell). Mise à jour réelle : polling Livewire 30 s (DECISIONS D7, P10-03).
    Props :
      items     : [['id' => 'al12', 'icon' => 'bell-fill', 'color' => 'danger', 'text' => 'Alerte SOS · Cotonou', 'time' => 'il y a 5 min', 'href' => '/admin/alertes', 'read' => false], …]
      unread    : nombre de non lus (défaut : calculé depuis items)
      href      : lien de pied de panneau (journal d'audit, page Notifications) ; hrefLabel : son libellé
      id        : identifiant (défaut xmNotif)
      variant   : admin (btn-light btn-sm, prototype admin) | member (nav-link icon-md du header membre)
--}}
@props(['items' => [], 'unread' => null, 'href' => null, 'hrefLabel' => null, 'id' => 'xmNotif', 'variant' => 'admin'])
@php
    $unread ??= count(array_filter($items, fn ($n) => empty($n['read'])));
@endphp
<div {{ $attributes->class('dropdown xm-bell') }} id="{{ $id }}" data-xm-bell>
    <button class="{{ $variant === 'member' ? 'nav-link bg-light icon-md btn btn-light p-0 position-relative' : 'btn btn-light btn-sm position-relative' }}" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="{{ __('components.bell.label') }}" title="{{ __('components.bell.label') }}">
        <i class="bi bi-bell" aria-hidden="true"></i>
        <span @class(['position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger', 'd-none' => ! $unread]) data-xm-bell-count>{{ $unread }}<span class="visually-hidden"> {{ trans_choice('components.bell.unread', $unread) }}</span></span>
    </button>
    <div class="dropdown-menu dropdown-menu-end p-0 shadow-lg xm-notif-menu">
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
            <strong>{{ __('components.bell.label') }}</strong>
            <button type="button" class="btn btn-link btn-sm p-0" data-xm-bell-read-all>{{ __('components.bell.mark_all_read') }}</button>
        </div>
        <ul class="nav nav-pills nav-fill small p-2 gap-1" role="tablist" aria-label="{{ __('components.bell.filter') }}">
            @foreach (['unread', 'read', 'all'] as $tab)
                <li class="nav-item" role="presentation"><button @class(['nav-link py-1', 'active' => $loop->first]) type="button" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-controls="{{ $id }}List" data-xm-bell-tab="{{ $tab }}">{{ __("components.bell.tabs.$tab") }}</button></li>
            @endforeach
        </ul>
        <div class="list-group list-group-flush xm-notif-list" id="{{ $id }}List" role="tabpanel" data-xm-bell-list>
            @foreach ($items as $n)
                @php($read = ! empty($n['read']))
                <a @class(['list-group-item list-group-item-action d-flex gap-2 align-items-start', 'xm-notif-unread' => ! $read]) href="{{ $n['href'] ?? '#' }}" data-notif-id="{{ $n['id'] }}" data-notif-read="{{ $read ? '1' : '0' }}">
                    <span class="icon-md rounded-circle bg-{{ $n['color'] ?? 'primary' }} bg-opacity-10 text-{{ $n['color'] ?? 'primary' }} flex-shrink-0"><i class="bi bi-{{ $n['icon'] ?? 'bell' }}" aria-hidden="true"></i></span>
                    <span class="small flex-grow-1"><span class="d-block">{{ $n['text'] }}</span>@if (! empty($n['time']))<span class="text-secondary">{{ $n['time'] }}</span>@endif@unless ($read)<span class="visually-hidden"> {{ __('components.bell.unread_item') }}</span>@endunless</span>
                    <span class="xm-notif-dot" aria-hidden="true"></span>
                </a>
            @endforeach
            <div @class(['list-group-item text-center text-secondary small', 'd-none' => count($items) > 0]) data-xm-bell-empty>{{ __('components.bell.empty') }}</div>
        </div>
        @if ($href)<div class="p-2 border-top text-center"><a class="small" href="{{ $href }}">{{ $hrefLabel ?? __('components.bell.see_all') }}</a></div>@endif
    </div>
</div>
