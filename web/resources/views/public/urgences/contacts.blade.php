{{--
    Fiches de l'annuaire (prototype urgences.php l.66-90) : ListEmergencyContacts — actives, `is_public`, point focal,
    juriste, psychologue ou point focal national (Q19, Q59), celles du département choisi d'abord ; nom, rôle, commune
    et département (ou « National »), disponibilité, Appeler (tel:) et WhatsApp ; jamais l'e-mail ni les notes.
    Avatar à initiales (aucune photo de fiche). Département : formulaire GET + bouton (pas de soumission au changement,
    WCAG 3.2.2) ; « Tout l'annuaire » pour un·e membre seulement (l'annuaire complet reste membre, Q19).
    Liste en `data-nosnippet` : page indexable, mais noms et numéros des personnes hors des extraits de recherche tant
    que le choix explicite `is_emergency` (Q59 a) n'existe pas.
--}}
<section id="urgences-contacts" aria-labelledby="urgences-contacts-titre">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h2 class="h5 mb-0" id="urgences-contacts-titre">{{ __('pages/urgences.contacts.title') }}</h2>
        @auth
            <a class="small no-print" href="{{ route('annuaire') }}">{{ __('pages/urgences.contacts.directory_all') }}</a>
        @endauth
    </div>
    <p class="small text-secondary">{{ __('pages/urgences.contacts.intro') }}</p>

    @if ($zones !== [])
        <form class="row g-2 align-items-end mb-3 no-print" method="get" action="{{ route('urgences') }}#urgences-contacts">
            <div class="col">
                <label class="form-label small mb-1" for="urg-zone">{{ __('pages/urgences.contacts.zone_label') }}</label>
                <select class="form-select" id="urg-zone" name="zone">
                    <option value="">{{ __('pages/urgences.contacts.zone_all') }}</option>
                    @foreach ($zones as $item)
                        <option value="{{ $item['id'] }}" @selected($zone !== null && (int) $zone->getKey() === $item['id'])>{{ $item['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary-soft" type="submit">{{ __('pages/urgences.contacts.zone_submit') }}</button>
            </div>
        </form>
    @endif
    @if ($zone !== null)
        <p class="small mb-3">{{ __('pages/urgences.contacts.zone_current', ['zone' => $zone->name]) }}</p>
    @endif

    @if ($contacts->isEmpty())
        <p class="text-secondary mb-0">{{ __('pages/urgences.contacts.empty') }}</p>
    @else
        <ul class="row g-3 list-unstyled mb-0" data-nosnippet>
            @foreach ($contacts as $contact)
                @php
                    $badge = $contact->role->badge();
                    $place = $contact->zone_id === null || $contact->is_national_focal
                        ? __('pages/urgences.contacts.national')
                        : collect([$contact->commune?->name, $contact->zone?->name])->filter()->implode(', ');
                @endphp
                <li class="col-md-6">
                    <article class="card card-body h-100" aria-labelledby="urg-contact-{{ $contact->getKey() }}">
                        <div class="d-flex gap-3">
                            <x-avatar :name="(string) $contact->name" class="flex-shrink-0" aria-hidden="true" />
                            <div class="flex-grow-1 xm-urg-shrink">
                                <h3 class="h6 fw-bold mb-1" id="urg-contact-{{ $contact->getKey() }}">{{ $contact->name }}</h3>
                                <p class="small text-secondary mb-0">
                                    <x-badge soft :color="in_array($badge, ['secondary', 'danger'], true) ? 'primary' : $badge">{{ $contact->role->label() }}</x-badge>
                                    {{ $place }}
                                </p>
                                @if ($contact->org)
                                    <p class="small text-secondary mb-0">{{ $contact->org }}</p>
                                @endif
                                @if ($contact->availability)
                                    <p class="small mt-1 mb-0"><i class="bi bi-clock me-1" aria-hidden="true"></i>{{ $contact->availability }}</p>
                                @endif
                            </div>
                        </div>
                        @if ($contact->phone || $contact->whatsapp)
                            <div class="d-flex gap-2 mt-3 xm-urg-contact-actions">
                                @if ($contact->phone)
                                    <a class="btn btn-sm btn-success flex-fill" href="{{ \App\Application\Shared\KnowledgeBaseAnswerer::telHref((string) $contact->phone) }}"><i class="bi bi-telephone-fill me-1" aria-hidden="true"></i>{{ __('pages/urgences.contacts.call') }}<span class="visually-hidden"> {{ $contact->name }}</span></a>
                                @endif
                                @if ($contact->whatsapp)
                                    <a class="btn btn-sm btn-success-soft flex-fill" href="https://wa.me/{{ preg_replace('/\D/', '', (string) $contact->whatsapp) }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp me-1" aria-hidden="true"></i>{{ __('pages/urgences.contacts.whatsapp') }}<span class="visually-hidden"> {{ $contact->name }} {{ __('microcopy.a11y.opens_new_tab') }}</span></a>
                                @endif
                            </div>
                        @endif
                    </article>
                </li>
            @endforeach
        </ul>
        @if ($truncated)
            <p class="small text-secondary mt-3 mb-0">{{ __('pages/urgences.contacts.truncated') }}</p>
        @endif
    @endif
</section>
