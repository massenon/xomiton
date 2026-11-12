{{--
    Carte d'une conversation en BD (liste, « D'autres conversations ») : lien unique sur la carte, première case illustrée
    (sinon repli rose pâle décoratif, sans image cassée), titre, thème, nombre de cases.
    Variables : $conversation (BdConversation, `panels.scene` chargées, `panels_count`), $catalog, $heading, $priority.
--}}
@php($cover = $catalog->cover($conversation))
<a href="{{ route('conversations-bd.show', ['slug' => $conversation->slug]) }}" class="card h-100 xm-card-hover overflow-hidden text-decoration-none">
    @if ($cover)
        <x-img class="img-fluid w-100" :src="$cover" :alt="__('pages/conversations_bd.cover_alt', ['title' => $conversation->title])" width="1200" height="800" :priority="$priority ?? false" />
    @else
        <div class="xm-bdc-card-fallback" aria-hidden="true"><i class="bi bi-chat-square-quote"></i></div>
    @endif
    <div class="card-body">
        <{{ $heading ?? 'h2' }} class="h6 mb-1">{{ $conversation->title }}</{{ $heading ?? 'h2' }}>
        @if ($conversation->theme)
            <p class="small text-secondary mb-0">{{ __('pages/conversations_bd.theme', ['theme' => $conversation->theme]) }}</p>
        @endif
    </div>
    <div class="card-footer border-0 pt-0 bg-transparent small text-secondary"><i class="bi bi-chat-square-text me-1" aria-hidden="true"></i>{{ trans_choice('pages/conversations_bd.panels_count', (int) $conversation->panels_count, ['count' => (int) $conversation->panels_count]) }}</div>
</a>
