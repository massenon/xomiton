{{-- Section 8 — Do Glegbe #fonctionnalites (prototype index.php l.331-356) ; classes dg-* de theme/xomiton.css. --}}
<section class="lp-section dg-section" id="fonctionnalites" aria-labelledby="dgTitre">
    <div class="container position-relative">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-lg-4">
                <div class="dg-logo-card">
                    <x-img :src="asset('assets/images/xomiton/doglegbexo.png')" :alt="__('pages/accueil.do_glegbe.logo_alt')" :title="__('pages/accueil.do_glegbe.logo_title')" width="314" height="258" />
                    <span class="dg-badge"><i class="bi bi-droplet-half me-1" aria-hidden="true"></i>{{ __('pages/accueil.do_glegbe.badge') }}</span>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="dg-eyebrow mb-2">{{ __('pages/accueil.do_glegbe.eyebrow') }}</div>
                <h2 id="dgTitre" class="dg-lead mb-3">{{ __('pages/accueil.do_glegbe.title') }}</h2>
                <p class="text-secondary mb-3">{{ __('pages/accueil.do_glegbe.text') }}</p>
                <ul class="row g-3 mb-4 list-unstyled">
                    @foreach (__('pages/accueil.do_glegbe.benefits') as $benefit)
                        <li class="col-sm-6"><div class="dg-benefit"><span class="dg-ico" aria-hidden="true"><i class="bi bi-{{ $benefit['icon'] }}"></i></span><b class="align-self-center">{{ $benefit['text'] }}</b></div></li>
                    @endforeach
                </ul>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-dg" href="{{ route('do-glegbe') }}">{{ __('buttons.discovery.discover_do_glegbe') }} <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
                    <a class="btn btn-outline-danger" href="{{ route('thematiques.index', ['cat' => 'regles']) }}">{{ __('pages/accueil.do_glegbe.cta_rules') }}</a>
                </div>
            </div>
        </div>
    </div>
</section>
