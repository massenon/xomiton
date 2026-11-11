{{--
    Article (prototype blog-details.php l.31-50) : couverture 16:9 plafonnée (LCP, alt descriptif), badge de catégorie
    (listée seulement), h1, méta (signature « Équipe Xomiton » · date · durée de lecture), résumé, corps HTML nettoyé à
    l'écriture (PurifiedHtml, cast de Resource), avertissement « ne remplace pas l'avis d'un·e professionnel·le » avec
    SOS et contacts d'urgence, retour à la liste et « Partager » (Web Share, sinon copie du lien : public/share.js ;
    bouton masqué sans JavaScript).
--}}
@php
    $categoryColor = match ((string) $category?->color) { '' => 'primary', 'secondary' => 'neutral', default => (string) $category?->color };
    $disclaimer = __('pages/actualites.disclaimer', [
        'sos' => '<a class="alert-link" href="'.e(route('sos')).'">'.e(__('pages/actualites.disclaimer_sos')).'</a>',
        'urgences' => '<a class="alert-link" href="'.e(route('urgences')).'">'.e(__('pages/actualites.disclaimer_urgences')).'</a>',
    ]);
@endphp
<article class="card overflow-hidden" id="actualite" aria-labelledby="actualite-titre">
    <div class="xm-ratio-16x9 xm-news-cover">
        <x-img class="xm-img-cover" :src="$article->cover_url" :alt="__('pages/actualites.cover_alt', ['title' => $article->title])" width="1200" height="675" priority />
    </div>
    <div class="card-body p-4">
        @if ($category)
            <x-badge :color="$categoryColor" soft class="mb-2" :icon="$category->icon ? \Illuminate\Support\Str::after($category->icon, 'bi-') : null">{{ $category->label }}</x-badge>
        @endif
        <h1 class="h3" id="actualite-titre">{{ $article->title }}</h1>
        <p class="small text-secondary mb-4">
            <i class="bi bi-person me-1" aria-hidden="true"></i>{{ __('pages/actualites.author_team') }}
            @if ($article->published_at)
                {{ __('common.separator') }} <time datetime="{{ $fmt->isoDate($article->published_at) }}">{{ __('pages/actualites.published_on', ['date' => $fmt->longDate($article->published_at)]) }}</time>
            @endif
            {{ __('common.separator') }} <i class="bi bi-clock me-1" aria-hidden="true"></i>{{ trans_choice('pages/actualites.reading_time', $minutes) }}
        </p>
        @if ($article->summary)
            <p class="lead fs-6">{{ $article->summary }}</p>
        @endif
        @if ($article->body)
            <div class="xm-prose">{!! $article->body !!}</div>
        @endif

        <div class="alert alert-info mt-4 mb-0 d-flex gap-2">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <p class="mb-0">{!! $disclaimer !!}</p>
        </div>
        <hr>
        <div class="d-flex flex-wrap gap-2 xm-news-actions">
            <a class="btn btn-light btn-sm" href="{{ route('actualites.index') }}"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ __('pages/actualites.back') }}</a>
            <button type="button" class="btn btn-primary-soft btn-sm" hidden data-xm-share
                    data-share-title="{{ $article->title }}" data-share-url="{{ route('actualites.show', $article->slug) }}"
                    data-share-copied="{{ __('pages/actualites.share_copied') }}" data-share-failed="{{ __('pages/actualites.share_failed') }}"><i class="bi bi-share me-1" aria-hidden="true"></i>{{ __('pages/actualites.share') }}</button>
        </div>
    </div>
</article>
