{{--
    Héro (prototype violences.php l.47-60) : image décorative à gauche (fond CSS du prototype, ici <img> LCP en cover),
    badge, h1, accroche et les trois accès : SOS (seul bouton rouge plein), Signaler, Assistant (offcanvas public pour
    un·e visiteur·se, page assistant membre sinon : même règle que /urgences).
--}}
<section class="card overflow-hidden" aria-labelledby="violences-titre">
    <div class="row g-0">
        <div class="col-md-5">
            <div class="xm-vio-hero h-100">
                <x-img class="xm-img-cover" :src="asset('assets/images/xomiton/hero-13.jpg')" alt="" width="936" height="450" :priority="true" />
            </div>
        </div>
        <div class="col-md-7 card-body p-4">
            <x-badge color="danger" soft class="mb-2">{{ __('pages/violences.hero.badge') }}</x-badge>
            <h1 class="h3" id="violences-titre">{{ __('pages/violences.title') }}</h1>
            <p class="mb-3">{{ __('pages/violences.hero.text') }}</p>
            <nav class="d-flex flex-wrap gap-2" aria-label="{{ __('pages/violences.hero.actions_label') }}">
                <a class="btn btn-danger" href="{{ route('sos') }}"><i class="bi bi-broadcast me-1" aria-hidden="true"></i>{{ __('pages/violences.hero.sos') }}<span class="visually-hidden"> {{ __('common.separator') }} {{ __('pages/violences.hero.sos_hint') }}</span></a>
                <a class="btn btn-primary" href="{{ route('signaler') }}"><i class="bi bi-megaphone me-1" aria-hidden="true"></i>{{ __('pages/violences.hero.report') }}</a>
                @auth
                    <a class="btn btn-primary-soft" href="{{ route('assistant') }}"><i class="bi bi-robot me-1" aria-hidden="true"></i>{{ __('pages/violences.hero.assistant') }}</a>
                @else
                    <button type="button" class="btn btn-primary-soft" data-bs-toggle="offcanvas" data-bs-target="#offcanvasChat" aria-controls="offcanvasChat"><i class="bi bi-robot me-1" aria-hidden="true"></i>{{ __('pages/violences.hero.assistant') }}</button>
                @endauth
            </nav>
        </div>
    </div>
</section>
