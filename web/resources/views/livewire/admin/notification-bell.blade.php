{{--
    Cloche de l'administration (P10-03) : composant App\Livewire\Admin\NotificationBell, monté dans la barre supérieure.
    HTML du prototype (partials/admin-top.php #xmNotif, mêmes classes que x-bell) ; onglets servis par le serveur
    (wire:click="setTab"), polling 30 s. Le bouton et le panneau gardent l'état ouvert posé par Bootstrap entre deux
    rendus (wire:ignore.self) ; seuls leur contenu et le compteur sont mis à jour. Le polling nu (sans méthode) ne compte
    pas comme activité : la session expire après `session.lifetime` minutes sans action réelle (EnforceIdleTimeout).
--}}
<div class="dropdown xm-bell" id="xmNotif" wire:poll.{{ \App\Livewire\Admin\NotificationBell::POLL_SECONDS }}s data-xm-admin-bell>
    {{-- Nom accessible = texte masqué (« Notifications, 3 non lues »), mis à jour à chaque rendu : pas d'aria-label, que
         wire:ignore.self figerait et qui masquerait le nombre aux lecteurs d'écran. --}}
    <button class="btn btn-light btn-sm position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
            title="{{ __('components.bell.label') }}" wire:ignore.self>
        <i class="bi bi-bell" aria-hidden="true"></i>
        <span class="visually-hidden" data-xm-bell-label>{{ trans_choice('admin.bell.button', $unread, ['count' => $unread]) }}</span>
        <span @class(['position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger', 'd-none' => ! $unread]) data-xm-bell-count aria-hidden="true">{{ $unread }}</span>
    </button>
    <div class="dropdown-menu dropdown-menu-end p-0 shadow-lg xm-notif-menu" wire:ignore.self>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
            <h2 class="h6 fw-bold mb-0">{{ __('components.bell.label') }}</h2>
            @if ($unread > 0)
                <button type="button" class="btn btn-link btn-sm p-0" wire:click="markAllRead">{{ __('components.bell.mark_all_read') }}</button>
            @endif
        </div>
        <div class="nav nav-pills nav-fill small p-2 gap-1" role="tablist" aria-label="{{ __('components.bell.filter') }}">
            @foreach (['unread', 'read', 'all'] as $key)
                <button @class(['nav-link py-1', 'active' => $tab === $key]) type="button" role="tab" id="xmNotifTab-{{ $key }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                        aria-controls="xmNotifList" tabindex="{{ $tab === $key ? '0' : '-1' }}" wire:click="setTab('{{ $key }}')">{{ __("components.bell.tabs.$key") }}</button>
            @endforeach
        </div>
        <div class="list-group list-group-flush xm-notif-list" id="xmNotifList" role="tabpanel" aria-labelledby="xmNotifTab-{{ $tab }}">
            @forelse ($items as $item)
                <button type="button" @class(['list-group-item list-group-item-action d-flex gap-2 align-items-start text-start', 'xm-notif-unread' => ! $item['read']])
                        wire:click="open('{{ $item['id'] }}')" wire:key="notif-{{ $item['id'] }}" data-notif-id="{{ $item['id'] }}" data-notif-read="{{ $item['read'] ? '1' : '0' }}">
                    <span class="icon-md rounded-circle bg-{{ $item['color'] }} bg-opacity-10 text-{{ $item['color'] }} flex-shrink-0"><i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i></span>
                    <span class="small flex-grow-1">
                        <span class="d-block fw-semibold">{{ $item['title'] }}</span>
                        @if ($item['body'] !== '')<span class="d-block">{{ $item['body'] }}</span>@endif
                        <span class="text-secondary">{{ $item['ago'] }}</span>
                        @unless ($item['read'])<span class="visually-hidden"> {{ __('components.bell.unread_item') }}</span>@endunless
                    </span>
                    <span class="xm-notif-dot" aria-hidden="true"></span>
                </button>
            @empty
                <div class="list-group-item text-center text-secondary small py-4" data-xm-bell-empty>{{ __('components.bell.empty') }}</div>
            @endforelse
        </div>
        @if ($canSeeAudit)
            <div class="p-2 border-top text-center"><a wire:navigate wire:navigate.hover class="small" href="{{ route('admin.journal') }}">{{ __('menus.admin.see_audit') }}</a></div>
        @endif
    </div>
</div>
