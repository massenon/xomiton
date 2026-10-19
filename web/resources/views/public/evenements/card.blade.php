{{--
    Carte d'événement public (prototype events.php l.68-86) : visuel décoratif hors tabulation (le titre porte le lien),
    pastille de date (jour + mois court), catégorie, titre h3 (la grille a un h2), heure · ville ou « En ligne » (jamais
    l'adresse exacte), nombre d'inscrit·es (jamais leur liste), lien « Voir l'événement ». Variables : $event, $fmt.
--}}
@php
    $url = route('evenements.show', $event->ulid);
    $location = \App\Http\Resources\Events\EventResource::locationLabel($event);
@endphp
<li class="col-sm-6 col-xl-4">
    <article class="card h-100 overflow-hidden xm-card-hover">
        <a href="{{ $url }}" class="position-relative d-block" tabindex="-1" aria-hidden="true">
            <div class="xm-ratio-16x9"><x-img class="xm-img-cover" :src="\App\Support\MediaUrl::resolve($event->image_path, \App\Models\Event::DEFAULT_COVER)" alt="" width="640" height="360" /></div>
            <span class="position-absolute top-0 start-0 m-2 bg-body rounded text-center px-2 py-1 shadow-sm"><span class="d-block h5 mb-0 lh-1">{{ $fmt->pattern('d', $event->starts_at) }}</span><small class="text-uppercase">{{ $fmt->monthName((int) $fmt->pattern('M', $event->starts_at), true) }}</small></span>
        </a>
        <div class="card-body">
            @if ($event->category)
                <span class="xm-chip mb-2">{{ $event->category->label }}</span>
            @endif
            <h3 class="h6 mb-2"><a href="{{ $url }}">{{ $event->title }}</a></h3>
            <p class="small mb-1"><time datetime="{{ $event->starts_at->toIso8601String() }}"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>{{ $fmt->longDateWithDay($event->starts_at) }}</time></p>
            <p class="small mb-1"><i class="bi bi-clock me-1" aria-hidden="true"></i>{{ $fmt->time($event->starts_at) }} {{ __('common.separator') }} <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ $location }}</p>
            <p class="small text-secondary mb-0"><i class="bi bi-people me-1" aria-hidden="true"></i>{{ trans_choice('pages/evenements.participants', (int) $event->going_count) }}</p>
        </div>
        <div class="card-footer bg-transparent border-0 pt-0">
            <a class="btn btn-sm btn-primary-soft w-100" href="{{ $url }}" aria-label="{{ __('pages/evenements.details_label', ['title' => $event->title]) }}">{{ __('pages/evenements.details') }}</a>
        </div>
    </article>
</li>
