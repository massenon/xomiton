{{--
    Bloc « Vous êtes en danger ? » du centre d'aide (prototype help.php l.71-74) : seul bloc rouge de la page (attention,
    DECISIONS.md D5), un seul bouton plein (SOS). Liens : /sos, /urgences, /signaler (écrans de crise publics).
--}}
<section class="card border-danger" aria-labelledby="aide-danger-titre">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h2 class="h5 mb-1 text-danger" id="aide-danger-titre"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ __('pages/aide.danger_title') }}</h2>
            <p class="mb-0 small">{{ __('pages/aide.danger_text') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-danger" href="{{ route('sos') }}"><i class="bi bi-broadcast me-1" aria-hidden="true"></i>{{ __('pages/aide.danger_sos') }}</a>
            <a class="btn btn-outline-danger" href="{{ route('urgences') }}">{{ __('pages/aide.danger_contacts') }}</a>
            <a class="btn btn-light" href="{{ route('signaler') }}">{{ __('pages/aide.danger_report') }}</a>
        </div>
    </div>
</section>
