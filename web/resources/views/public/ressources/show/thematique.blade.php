{{-- Thématique liée (markup de thematique.php l.97-107, un seul élément) : vignette décorative hors tabulation, titre lié, durée de lecture. --}}
<section class="card" id="ressource-thematique" aria-labelledby="ressource-thematique-titre">
    <div class="card-header pb-0 border-0">
        <h2 class="h5 card-title mb-0" id="ressource-thematique-titre">{{ __('pages/ressources.thematique_title') }}</h2>
    </div>
    <div class="card-body">
        <div class="d-flex">
            <a class="flex-shrink-0 rounded-2 overflow-hidden d-block xm-res-thumb" href="{{ route('thematiques.show', $thematique) }}" tabindex="-1" aria-hidden="true">
                <div class="xm-ratio-16x9"><x-img class="xm-img-cover" :src="$thematique->cover_url" alt="" width="88" height="50" /></div>
            </a>
            <div class="ms-3">
                <h3 class="h6 card-title mb-0 lh-sm"><a href="{{ route('thematiques.show', $thematique) }}">{{ $thematique->title }}</a></h3>
                <p class="small mb-0 text-secondary">{{ trans_choice('pages/ressources.reading_minutes', max(1, (int) $thematique->reading_minutes)) }}</p>
            </div>
        </div>
    </div>
</section>
