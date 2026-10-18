{{--
    « Besoin d’aide ? » (prototype thematique.php l.85-94, x_thematique_content() l.126-132) : contacts d'annuaire liés,
    actifs et publics seulement (Q19) — nom, organisation, département, disponibilité, rôle, « Appeler », WhatsApp ; jamais
    l'e-mail ni les notes. Annuaire et assistant réservés aux membres (routes `auth`) ; CTA principal « J’ai besoin d’aide »
    → /signaler (CTA_LIBRARY.md). Aucun bouton rouge : le seul rouge de la page est le SOS flottant (membres).
--}}
<section class="card border-primary" id="fiche-aide" aria-labelledby="fiche-aide-titre">
    <div class="card-header pb-0 border-0">
        <h2 class="h5 card-title mb-0" id="fiche-aide-titre">{{ __('pages/thematiques.help_title') }}</h2>
    </div>
    <div class="card-body vstack gap-3">
        @if ($contacts->isNotEmpty())
            <ul class="list-unstyled mb-0 vstack gap-2 xm-th-contacts" aria-label="{{ __('pages/thematiques.contacts_label') }}">
                @foreach ($contacts as $contact)
                    @php($badge = $contact->role->badge())
                    <li class="border rounded p-2">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <div class="fw-semibold">{{ $contact->name }}</div>
                                <div class="small text-secondary">{{ collect([$contact->org, $contact->zone?->name, $contact->availability])->filter()->implode(' · ') }}</div>
                            </div>
                            <x-badge soft :color="$badge === 'secondary' ? 'neutral' : $badge">{{ $contact->role->label() }}</x-badge>
                        </div>
                        @if ($contact->phone || $contact->whatsapp)
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                @if ($contact->phone)
                                    <a class="btn btn-sm btn-primary-soft" href="tel:{{ preg_replace('/[^\d+]/', '', (string) $contact->phone) }}"><i class="bi bi-telephone me-1" aria-hidden="true"></i>{{ __('pages/thematiques.call') }}<span class="visually-hidden"> {{ $contact->name }}</span></a>
                                @endif
                                @if ($contact->whatsapp)
                                    <a class="btn btn-sm btn-light" href="https://wa.me/{{ preg_replace('/\D/', '', (string) $contact->whatsapp) }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-1" aria-hidden="true"></i>{{ __('common.whatsapp') }}<span class="visually-hidden"> {{ $contact->name }} {{ __('microcopy.a11y.opens_new_tab') }}</span></a>
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="vstack gap-2 xm-th-links">
            @auth
                <a class="btn btn-sm btn-primary-soft text-start" href="{{ route('annuaire') }}"><i class="bi bi-person-lines-fill me-2" aria-hidden="true"></i>{{ __('pages/thematiques.link_directory') }}</a>
            @endauth
            <a class="btn btn-sm btn-primary-soft text-start" href="{{ route('urgences') }}"><i class="bi bi-telephone-fill me-2" aria-hidden="true"></i>{{ __('pages/thematiques.link_emergency') }}</a>
            <a class="btn btn-sm btn-primary-soft text-start" href="{{ route('sos') }}"><i class="bi bi-broadcast me-2" aria-hidden="true"></i>{{ __('pages/thematiques.link_sos') }}</a>
            @auth
                @if ($showAssistant)
                    <a class="btn btn-sm btn-primary-soft text-start" href="{{ route('assistant') }}"><i class="bi bi-robot me-2" aria-hidden="true"></i>{{ __('pages/thematiques.link_assistant') }}</a>
                @endif
            @endauth
        </div>
        <a class="btn btn-primary w-100" href="{{ route('signaler') }}"><i class="bi bi-megaphone me-1" aria-hidden="true"></i>{{ __('buttons.help.need_help') }}</a>
    </div>
</section>
