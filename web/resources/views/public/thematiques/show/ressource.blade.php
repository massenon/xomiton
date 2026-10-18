{{-- Ressource principale (SEO_CONTENT_GUIDE_INSTRUCTION.md : chaque fiche renvoie vers sa ressource) : la plus récente rattachée à la fiche (plan §7), ancre = titre. --}}
<section class="card" id="fiche-ressource" aria-labelledby="fiche-ressource-titre">
    <div class="card-header pb-0 border-0">
        <h2 class="h5 card-title mb-0" id="fiche-ressource-titre">{{ __('pages/thematiques.resource_title') }}</h2>
    </div>
    <div class="card-body">
        <div class="d-flex gap-3">
            <i class="bi {{ $primaryResource->kind->icon() }} fs-2 text-primary flex-shrink-0" aria-hidden="true"></i>
            <div>
                <h3 class="h6 mb-1"><a href="{{ route('ressources.show', $primaryResource->slug) }}">{{ $primaryResource->title }}</a></h3>
                <p class="small text-secondary mb-1">{{ collect([
                    $primaryResource->kind->label(),
                    $resourceSize,
                    $primaryResource->duration_minutes ? trans_choice('pages/thematiques.minutes', (int) $primaryResource->duration_minutes) : null,
                ])->filter()->implode(' · ') }}</p>
                @if ($primaryResource->summary)
                    <p class="small mb-0">{{ $primaryResource->summary }}</p>
                @endif
                @if ($primaryResource->is_offline_available)
                    <span class="badge bg-success bg-opacity-10 text-success mt-2"><i class="bi bi-wifi-off me-1" aria-hidden="true"></i>{{ __('microcopy.offline.available_offline') }}</span>
                @endif
            </div>
        </div>
    </div>
</section>
