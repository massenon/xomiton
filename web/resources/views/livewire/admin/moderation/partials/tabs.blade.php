{{-- Entrées de l'écran de modération : signalements · vidéos à relire (Q96) · conversations en BD à relire (P20-08).
     Props : active (reports | videos | conversations-bd), pending (vidéos en attente, null = compté ici). --}}
@php($pending = $pending ?? \App\Models\Post::query()->where('status', \App\Enums\PostStatus::Pending)->count())
@php($pendingBd = \App\Models\BdConversation::query()->where('origin', 'membre')->where('status', 'proposee')->count())
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
        <li class="nav-item">
            <a wire:navigate @class(['nav-link', 'active' => $active === 'conversations-bd']) href="{{ route('admin.moderation.conversations-bd') }}" @if ($active === 'conversations-bd') aria-current="page" @endif>
                {{ __('admin.moderation.tabs.conversations_bd') }}
                @if ($pendingBd > 0)<span class="badge text-bg-danger ms-1">{{ $pendingBd }}<span class="visually-hidden"> {{ __('admin.moderation.tabs.conversations_bd_pending') }}</span></span>@endif
            </a>
        </li>
    </ul>
</nav>
