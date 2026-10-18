{{--
    Boutons d'appel (prototype urgences.php l.53-63) : numéros nationaux de ListEmergencyContacts (paramètre
    `emergency_numbers`, sinon config ; aucun en dur), carte entière en lien tel:. Icône choisie d'après le libellé
    (le paramètre ne stocke que libellé et numéro) ; rouge réservé au SOS (signaler-et-sos.md) : « Pompiers » passe du
    rouge du prototype au bleu, la ligne d'écoute garde le vert. Sans mouvement au survol (gabarit sobre).
--}}
<section id="urgences-numeros" aria-labelledby="urgences-numeros-titre">
    <h2 class="visually-hidden" id="urgences-numeros-titre">{{ __('pages/urgences.services.title') }}</h2>
    @if ($services === [])
        <p class="mb-0">{{ __('pages/urgences.services.empty') }}</p>
    @else
        <ul class="row g-3 list-unstyled mb-0">
            @foreach ($services as $service)
                @php
                    $label = mb_strtolower($service['label']);
                    [$icon, $color] = match (true) {
                        str_contains($label, 'écoute') => ['bi-headset', 'success'],
                        str_contains($label, 'pompier') || str_contains($label, 'feu') => ['bi-fire', 'primary'],
                        str_contains($label, 'gendarmerie') => ['bi-shield-lock-fill', 'primary'],
                        str_contains($label, 'police') => ['bi-shield-fill-check', 'primary'],
                        default => ['bi-telephone-fill', 'primary'],
                    };
                @endphp
                <li class="col-sm-6">
                    <a class="card card-body flex-row align-items-center gap-3 text-decoration-none h-100 border-{{ $color }} xm-urg-call" href="{{ $service['tel'] }}">
                        <span class="icon-xl bg-{{ $color }} text-white rounded-circle flex-shrink-0" aria-hidden="true"><i class="bi {{ $icon }} fs-4" aria-hidden="true"></i></span>
                        <span class="xm-urg-shrink">
                            <span class="d-block fw-bold text-body">{{ $service['label'] }}</span>
                            <span class="d-block h4 mb-0 text-{{ $color }} xm-urg-number">{{ $service['number'] }}</span>
                        </span>
                        <i class="bi bi-telephone-outbound-fill ms-auto fs-4 text-{{ $color }}" aria-hidden="true"></i>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
