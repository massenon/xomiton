{{--
    Carte d'une BD publique (/bd, carrefour, autres BD) : lien unique sur la carte, couverture 3:2 (alt court), titre,
    sujet (fiche liée), nombre de planches. Variables : $comic (CccTool, `thematique` chargée), $heading (h2 | h3), $priority.
--}}
@php($topic = $comic->thematique)
<a href="{{ route('bd.show', ['key' => $comic->key]) }}" class="card h-100 xm-card-hover overflow-hidden text-decoration-none">
    <x-img class="img-fluid w-100" :src="\App\Support\MediaUrl::resolve($comic->image_path, 'assets/images/xomiton/covers/og-default.jpg')" :alt="__('pages/bd.cover_alt', ['title' => $comic->title])" width="1200" height="800" :priority="$priority ?? false" />
    <div class="card-body">
        <{{ $heading ?? 'h2' }} class="h6 mb-1">{{ $comic->title }}</{{ $heading ?? 'h2' }}>
        @if ($topic && ! $topic->trashed())
            <p class="small text-secondary mb-0">{{ __('pages/bd.topic', ['title' => $topic->title]) }}</p>
        @endif
    </div>
    <div class="card-footer border-0 pt-0 bg-transparent small text-secondary"><i class="bi bi-images me-1" aria-hidden="true"></i>{{ trans_choice('pages/bd.pages_count', $comic->pagesCount(), ['count' => $comic->pagesCount()]) }}</div>
</a>
