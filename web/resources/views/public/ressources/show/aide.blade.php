{{-- « Besoin d’aide ? » (thematique.php l.85-94) : urgences, signalement, SOS ; rouge doux réservé au SOS (CTA_LIBRARY.md). btn-sm gardés, 44 px au pointeur tactile (ressources.css). --}}
<section class="card border-primary" id="aide" aria-labelledby="aide-titre">
    <div class="card-header pb-0 border-0">
        <h2 class="h5 card-title mb-0" id="aide-titre">{{ __('pages/ressources.help_title') }}</h2>
    </div>
    <div class="card-body">
        <div class="vstack gap-2 xm-res-help">
            <a class="btn btn-sm btn-primary-soft text-start" href="{{ route('urgences') }}"><i class="bi bi-telephone me-2" aria-hidden="true"></i>{{ __('pages/ressources.link_emergency') }}</a>
            <a class="btn btn-sm btn-primary-soft text-start" href="{{ route('signaler') }}"><i class="bi bi-megaphone me-2" aria-hidden="true"></i>{{ __('pages/ressources.link_report') }}</a>
            <a class="btn btn-sm btn-danger-soft text-start" href="{{ route('sos') }}"><i class="bi bi-broadcast me-2" aria-hidden="true"></i>{{ __('pages/ressources.link_sos') }}</a>
        </div>
    </div>
</section>
