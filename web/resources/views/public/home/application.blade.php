{{-- Section « Application mobile » : annonce « bientôt disponible » avec les badges App Store et Google Play.
     Les badges ne sont pas des liens tant que l'application n'est pas publiée : à relier aux fiches des boutiques
     (config xomiton.stores) le jour de la mise en ligne. --}}
<section class="py-4 py-sm-5" aria-labelledby="applicationTitre">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="badge bg-primary bg-opacity-10 text-primary mb-2">{{ __('pages/accueil.application.badge') }}</span>
                <h2 id="applicationTitre" class="h1">{{ __('pages/accueil.application.title') }}</h2>
                <p class="lead mb-0">{{ __('pages/accueil.application.text') }}</p>
            </div>
            <div class="col-lg-5">
                <ul class="list-unstyled d-flex flex-wrap justify-content-lg-end gap-3 mb-0 lp-stores">
                    <li>
                        <img src="{{ asset('assets/images/stores/app-store.svg') }}" width="128" height="40" loading="lazy"
                             alt="{{ __('pages/accueil.application.app_store_alt') }}" title="{{ __('pages/accueil.application.app_store_alt') }}" class="lp-store">
                    </li>
                    <li>
                        <img src="{{ asset('assets/images/stores/google-play.svg') }}" width="128" height="40" loading="lazy"
                             alt="{{ __('pages/accueil.application.google_play_alt') }}" title="{{ __('pages/accueil.application.google_play_alt') }}" class="lp-store">
                    </li>
                </ul>
            </div>
        </div>
    </div>
</section>
