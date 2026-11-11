{{-- Section 10 — Appel final (prototype index.php l.378-395) ; variante membre connecté·e (P4-01 « CTA selon connexion », plan §7). --}}
<section class="py-4 py-sm-5" aria-labelledby="ctaTitre">
    <div class="container">
        <div class="card card-body bg-light shadow-none border-0 p-4 p-sm-5 text-center">
            <div class="col-lg-8 mx-auto">
                @auth
                    <h2 id="ctaTitre" class="h1">{{ __('pages/accueil.cta.title_member') }}</h2>
                    <p class="lead mb-4">{{ __('pages/accueil.cta.text_member') }}</p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <x-button size="lg" :href="route('fil')">{{ __('buttons.discovery.my_space') }}</x-button>
                        <x-button variant="outline" size="lg" :href="route('thematiques.index')">{{ __('buttons.discovery.explore_themes') }}</x-button>
                    </div>
                @else
                    <h2 id="ctaTitre" class="h1">{{ __('pages/accueil.cta.title') }}</h2>
                    <p class="lead mb-4">{{ __('pages/accueil.cta.text') }}</p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <x-button size="lg" :href="route('inscription')">{{ __('buttons.discovery.sign_up') }}</x-button>
                        <x-button variant="outline" size="lg" :href="route('connexion')">{{ __('buttons.discovery.sign_in') }}</x-button>
                    </div>
                @endauth
                <ul class="nav nav-divider justify-content-center mt-4">
                    <li class="nav-item">{{ __('pages/accueil.cta.free') }}</li>
                    <li class="nav-item">{{ __('pages/accueil.cta.secure') }}</li>
                </ul>
            </div>
        </div>
    </div>
</section>
