{{-- Thématiques liées (prototype thematique.php l.97-107) : vignette décorative hors tabulation (le titre porte le lien), largeur 88 px sortie de l'inline (thematiques.css). --}}
<section class="card" id="fiche-liees" aria-labelledby="fiche-liees-titre">
    <div class="card-header pb-0 border-0">
        <h2 class="h5 card-title mb-0" id="fiche-liees-titre">{{ __('pages/thematiques.related_title') }}</h2>
    </div>
    <div class="card-body">
        <ul class="list-unstyled mb-0">
            @foreach ($related as $item)
                <li @class(['d-flex', 'mb-3' => ! $loop->last])>
                    <a class="flex-shrink-0 rounded-2 overflow-hidden d-block xm-th-thumb" href="{{ route('thematiques.show', $item) }}" tabindex="-1" aria-hidden="true">
                        <div class="xm-ratio-16x9"><x-img class="xm-img-cover" :src="$item->cover_url" alt="" width="88" height="50" /></div>
                    </a>
                    <div class="ms-3">
                        <h3 class="h6 card-title mb-0 lh-sm"><a href="{{ route('thematiques.show', $item) }}">{{ $item->title }}</a></h3>
                        <p class="small mb-0 text-secondary">{{ trans_choice('pages/thematiques.minutes', (int) $item->reading_minutes) }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>
