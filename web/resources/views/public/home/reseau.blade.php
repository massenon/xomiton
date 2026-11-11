{{-- Section 4 — Réseau social #reseau (prototype index.php l.201-235) : six fonctionnalités et deux maquettes animées. --}}
<section class="lp-section bg-light" id="reseau" aria-labelledby="reseauTitre">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6 order-lg-2">
                <div class="lp-eyebrow mb-2">{{ __('pages/accueil.reseau.eyebrow') }}</div>
                <h2 id="reseauTitre" class="lp-title mb-3">{{ __('pages/accueil.reseau.title') }}</h2>
                <p class="text-secondary">{{ __('pages/accueil.reseau.text') }}</p>
                <ul class="row g-3 mt-1 list-unstyled">
                    @foreach (__('pages/accueil.reseau.features') as $feature)
                        <li class="col-sm-6">
                            <div class="lp-card p-3 bg-body">
                                <i class="bi bi-{{ $feature['icon'] }} fs-4 text-primary" aria-hidden="true"></i>
                                <h3 class="h6 mt-2 mb-1">{{ $feature['title'] }}</h3>
                                <p class="small text-secondary mb-0">{{ $feature['text'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="col-lg-6 order-lg-1">
                <div class="lp-mocks">
                    <x-img class="lp-mock m1" :src="asset('assets/images/xomiton/mockup-B.gif')" :alt="__('pages/accueil.reseau.mock_b_alt')" width="480" height="640" />
                    <x-img class="lp-mock m2" :src="asset('assets/images/xomiton/mockup-C.gif')" :alt="__('pages/accueil.reseau.mock_c_alt')" width="480" height="640" />
                </div>
            </div>
        </div>
    </div>
</section>
