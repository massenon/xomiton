{{--
    Lieux sûrs (prototype urgences.php l.92-101) : SafePlaces (paramètre `safe_places`, sinon les structures génériques
    de lang, badge « exemples » comme le prototype). Icône de lieu en bleu (rouge réservé au SOS).
--}}
<section id="urgences-lieux" aria-labelledby="urgences-lieux-titre">
    <h2 class="h5" id="urgences-lieux-titre">{{ __('pages/urgences.places.title') }}@if ($places['examples']) <x-badge color="neutral" soft class="ms-1 align-middle">{{ __('pages/urgences.places.examples') }}</x-badge>@endif</h2>
    <p class="small text-secondary">{{ __('pages/urgences.places.intro') }}</p>
    <ul class="row g-3 list-unstyled mb-0">
        @foreach ($places['items'] as $place)
            <li class="col-md-6">
                <div class="card card-body h-100">
                    <p class="fw-bold mb-0"><i class="bi bi-geo-alt-fill text-primary me-1" aria-hidden="true"></i>{{ $place['name'] }}</p>
                    @if ($place['where'] !== '')
                        <p class="small text-secondary mb-0">{{ $place['where'] }}</p>
                    @endif
                    @if ($place['text'] !== '')
                        <p class="small mt-1 mb-0">{{ $place['text'] }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</section>
