{{--
    Section 6 — Alerte et signalement #alerte (prototype index.php l.267-295) : « Signalement » remplace « Dénonciation »
    (Q29) ; la maquette de téléphone est décorative (données fictives, aria-hidden). Bande rouge pleine à faire valider (plan §7).
--}}
<section class="lp-section pt-0" id="alerte" aria-labelledby="alerteTitre">
    <div class="container">
        <div class="lp-band-red p-4 p-lg-5">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <div class="lp-kicker mb-3"><i class="bi bi-broadcast" aria-hidden="true"></i> {{ __('pages/accueil.alerte.kicker') }}</div>
                    <h2 id="alerteTitre" class="lp-title text-white mb-3 lp-band-title">{{ __('pages/accueil.alerte.title_1') }}<br>{{ __('pages/accueil.alerte.title_2') }}</h2>
                    <p class="lp-band-text">{{ __('pages/accueil.alerte.text') }}</p>
                    <ul class="list-unstyled mb-4">
                        <li class="mb-2"><i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>{{ __('pages/accueil.alerte.proof_anonymous') }}</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>{{ __('pages/accueil.alerte.proof_location') }}</li>
                    </ul>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-light fw-bold text-danger" href="{{ route('signaler') }}"><i class="bi bi-megaphone me-1" aria-hidden="true"></i>{{ __('buttons.help.report') }}</a>
                        <a class="btn btn-outline-light" href="{{ route('urgences') }}"><i class="bi bi-telephone me-1" aria-hidden="true"></i>{{ __('buttons.help.emergency_contacts') }}</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="phone" aria-hidden="true">
                        <div class="scr sos-screen">
                            <div class="fw-bold">{{ __('pages/accueil.alerte.mock_title') }}</div>
                            <div class="sos-btn">{{ __('pages/accueil.alerte.mock_sos') }}</div>
                            <div><i class="bi bi-geo-alt-fill me-1"></i>{{ __('pages/accueil.alerte.mock_position') }}</div>
                            <div class="opacity-75">{{ __('pages/accueil.alerte.mock_routing') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
