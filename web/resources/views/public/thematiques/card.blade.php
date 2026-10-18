{{--
    Carte de fiche (liste /thematiques ; prototype thematiques.php l.61-69) : lien unique sur toute la carte, couverture
    16:9, badge doux de catégorie, titre h3.h6 (la grille a un h2 masqué), résumé, durée de lecture.
    Pas <x-card> (titre h5, puce .xm-chip, pas de pied) : fidélité au prototype, décision consignée au plan §7.
    Variables : $thematique (catégorie chargée), $priority (bool : image LCP de la liste).
--}}
@php($cardCategory = $thematique->category)
<a href="{{ route('thematiques.show', $thematique) }}" class="card h-100 xm-card-hover overflow-hidden text-decoration-none">
    <div class="xm-ratio-16x9">
        <x-img class="xm-img-cover" :src="$thematique->cover_url" :alt="__('pages/thematiques.cover_alt', ['title' => $thematique->title])" width="640" height="360" :priority="$priority ?? false" />
    </div>
    <div class="card-body">
        @if ($cardCategory)
            <x-badge :color="$cardCategory->color ?: 'primary'" soft :icon="$cardCategory->icon ? \Illuminate\Support\Str::after($cardCategory->icon, 'bi-') : null" class="mb-2">{{ $cardCategory->label }}</x-badge>
        @endif
        <h3 class="h6 mb-2">{{ $thematique->title }}</h3>
        <p class="small text-secondary mb-0">{{ $thematique->summary }}</p>
    </div>
    <div class="card-footer border-0 pt-0 bg-transparent small text-secondary"><i class="bi bi-clock me-1" aria-hidden="true"></i>{{ trans_choice('pages/thematiques.reading_minutes', (int) $thematique->reading_minutes) }}</div>
</a>
