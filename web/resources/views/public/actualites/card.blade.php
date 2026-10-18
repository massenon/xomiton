{{--
    Carte d'actualité (prototype blog.php l.23-34) : couverture décorative hors tabulation (le titre porte le lien), 16:9
    à la une (pleine largeur, sans filtre) sinon 3:2, badge doux de catégorie (listée seulement, ListNews), titre h3 (la
    grille a un h2), résumé, pied : signature « Équipe Xomiton » · date, durée de lecture (`reading_minutes`, ListNews : même
    calcul que la fiche). Rouge de marque
    (`secondary`) affiché en `neutral` comme les ressources (plan §7, Q60).
    Variables : $article (catégorie et thématique chargées), $featured (bool), $priority (bool : image LCP), $fmt.
--}}
@php
    $url = route('actualites.show', $article->slug);
    $cardCategory = $article->category;
    $categoryColor = match ((string) $cardCategory?->color) { '' => 'primary', 'secondary' => 'neutral', default => (string) $cardCategory?->color };
@endphp
<li @class(['col-12' => $featured, 'col-md-6' => ! $featured])>
    <article class="card h-100 overflow-hidden xm-card-hover">
        <a @class(['d-block', 'xm-ratio-16x9' => $featured, 'xm-ratio-3x2' => ! $featured]) href="{{ $url }}" tabindex="-1" aria-hidden="true">
            <x-img class="xm-img-cover" :src="$article->cover_url" alt="" width="1200" :height="$featured ? 675 : 800" :priority="$priority ?? false" />
        </a>
        <div class="card-body">
            @if ($cardCategory)
                <x-badge :color="$categoryColor" soft class="mb-2" :icon="$cardCategory->icon ? \Illuminate\Support\Str::after($cardCategory->icon, 'bi-') : null">{{ $cardCategory->label }}</x-badge>
            @endif
            <h3 @class(['h4' => $featured, 'h6' => ! $featured])><a class="text-reset" href="{{ $url }}">{{ $article->title }}</a></h3>
            <p class="small text-secondary mb-0">{{ $article->summary }}</p>
        </div>
        <div class="card-footer small text-secondary d-flex flex-wrap justify-content-between gap-2">
            <span>{{ __('pages/actualites.author_team') }} {{ __('common.separator') }} @if ($article->published_at)<time datetime="{{ $fmt->isoDate($article->published_at) }}">{{ $fmt->longDate($article->published_at) }}</time>@endif</span>
            <span><i class="bi bi-clock me-1" aria-hidden="true"></i>{{ trans_choice('pages/actualites.minutes', (int) $article->reading_minutes) }}</span>
        </div>
    </article>
</li>
