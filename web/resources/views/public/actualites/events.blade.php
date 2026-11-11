{{--
    « Prochains événements » (prototype blog.php l.43-52) : membres connecté·es seulement (UpcomingEvents, Q35) ; lien
    vers la fiche membre par ULID (ADR-27), titre, date et « En ligne » le cas échéant ; jamais `place` (adresse exacte
    réservée aux participant·es confirmé·es, P8-04) ; sans vignette (aucune couverture publique tant que Q35 n'est pas
    tranché). Groupes privés et pages non vérifiées exclus par la requête.
    Variables : $events, $fmt.
--}}
<section class="card" id="actualites-evenements" aria-labelledby="actualites-evenements-titre">
    <div class="card-header">
        <h2 class="h6 mb-0" id="actualites-evenements-titre"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>{{ __('pages/actualites.events_title') }}</h2>
    </div>
    @if ($events->isEmpty())
        <p class="card-body small text-secondary mb-0">{{ __('pages/actualites.events_empty') }}</p>
    @else
        <ul class="list-group list-group-flush">
            @foreach ($events as $event)
                <li class="list-group-item">
                    <a class="small fw-bold d-block" href="{{ route('evenements.show', $event->ulid) }}">{{ $event->title }}</a>
                    <span class="small text-secondary">{{ implode(' '.__('common.separator').' ', array_filter([
                        $event->starts_at ? $fmt->longDate($event->starts_at) : null,
                        $event->is_online ? __('pages/actualites.events_online') : null,
                    ])) }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</section>
