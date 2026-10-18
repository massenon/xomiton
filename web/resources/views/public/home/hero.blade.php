{{-- Section 1 — Hero (prototype index.php l.105-134) : un seul rouge plein (« Signaler »), CTA selon connexion, image LCP. --}}
<section class="lp-hero" aria-labelledby="heroTitre">
    <div class="container py-5">
        <div class="row align-items-center g-5 py-lg-4">
            <div class="col-lg-6">
                <span class="lp-kicker mb-3"><i class="bi bi-shield-heart" aria-hidden="true"></i> {{ __('pages/accueil.hero.kicker') }}</span>
                <h1 id="heroTitre" class="display-5 mb-3">{{ __('pages/accueil.hero.title') }}</h1>
                <p class="lead mb-4">{{ __('pages/accueil.hero.lead') }}</p>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    @auth
                        <x-button variant="light" size="lg" class="fw-bold text-primary" icon="house-heart" :href="route('fil')">{{ __('buttons.discovery.my_space') }}</x-button>
                    @else
                        <x-button variant="light" size="lg" class="fw-bold text-primary" icon="person-plus" :href="route('inscription')">{{ __('pages/accueil.hero.cta_primary') }}</x-button>
                        <a class="btn btn-outline-light btn-lg" href="{{ route('connexion') }}"><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>{{ __('buttons.discovery.sign_in') }}</a>
                    @endauth
                    <x-button variant="danger" size="lg" class="fw-bold" icon="megaphone" :href="route('signaler')">{{ __('buttons.help.report_short') }}</x-button>
                </div>
                <p class="fst-italic mb-0 lp-hero__quote"><i class="bi bi-chat-quote-fill me-2" aria-hidden="true"></i>{{ __('pages/accueil.hero.quote') }}</p>
            </div>
            <div class="col-lg-6">
                <div class="lp-photos">
                    <x-img class="ph ph1" :src="asset('assets/images/xomiton/05.jpg')" :alt="__('pages/accueil.hero.photo_1_alt')" width="351" height="351" :priority="true" />
                    <x-img class="ph ph2" :src="asset('assets/images/xomiton/04.jpg')" :alt="__('pages/accueil.hero.photo_2_alt')" width="351" height="351" />
                    <div class="badge-float badge-float--1" aria-hidden="true"><i class="bi bi-heart-fill text-danger me-1"></i> {{ __('pages/accueil.hero.proof_human') }}</div>
                    <div class="badge-float badge-float--2" aria-hidden="true"><i class="bi bi-geo-alt-fill text-primary me-1"></i> {{ __('pages/accueil.hero.proof_report') }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="xm-rainbow-line"></div>
</section>
