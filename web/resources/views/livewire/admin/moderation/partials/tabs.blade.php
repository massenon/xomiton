{{-- Entrées de l'écran de modération : signalements · vidéos à relire (Q96). Props : active (reports | videos), pending (vidéos en attente). --}}
<nav class="mb-4" aria-label="{{ __('admin.moderation.tabs.label') }}">
    <ul class="nav nav-pills">
        <li class="nav-item">
            <a wire:navigate @class(['nav-link', 'active' => $active === 'reports']) href="{{ route('admin.moderation') }}" @if ($active === 'reports') aria-current="page" @endif>{{ __('admin.moderation.tabs.reports') }}</a>
        </li>
        <li class="nav-item">
            <a wire:navigate @class(['nav-link', 'active' => $active === 'videos']) href="{{ route('admin.moderation.videos') }}" @if ($active === 'videos') aria-current="page" @endif>
                {{ __('admin.moderation.tabs.videos') }}
                @if ($pending > 0)<span class="badge text-bg-danger ms-1">{{ $pending }}<span class="visually-hidden"> {{ __('admin.moderation.tabs.videos_pending') }}</span></span>@endif
            </a>
        </li>
    </ul>
</nav>
