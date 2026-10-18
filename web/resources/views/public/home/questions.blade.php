{{-- Section 3 — « Je me pose des questions » #questions (prototype index.php l.160-198) ; registre tu (VOICE_AND_TONE.md §3). --}}
<section class="lp-section pt-0" id="questions" aria-labelledby="questionsTitre">
    <div class="container">
        <div class="row g-4 g-lg-5 align-items-center">
            <div class="col-lg-4">
                <h2 id="questionsTitre" class="lp-title">{{ __('pages/accueil.questions.title') }}</h2>
                <p class="mb-4">{{ __('pages/accueil.questions.text') }}</p>
                <x-button :href="route('thematiques.index')">{{ __('buttons.discovery.explore_themes') }}</x-button>
            </div>
            <div class="col-lg-8">
                <div class="card card-body bg-light shadow-none border-0 p-4 p-sm-5 pb-sm-0 overflow-hidden lp-radius">
                    <div class="row g-4 align-items-center">
                        <div class="col-md-6">
                            <x-img class="lp-ico-50" :src="asset('assets/images/elements/05.svg')" alt="" width="50" height="50" />
                            <h3 class="h4 mt-4">{{ __('pages/accueil.questions.report_title') }}</h3>
                            <p class="mb-4">{{ __('pages/accueil.questions.report_text') }}</p>
                            <x-button variant="danger" class="mb-3" :href="route('signaler')">{{ __('buttons.help.report_abuse') }}</x-button>
                        </div>
                        <div class="col-md-6 text-end">
                            <x-img class="img-fluid rounded-top-4 lp-report-img" :src="asset('assets/images/xomiton/6033855.jpg')" :alt="__('pages/accueil.questions.report_img_alt')" width="332" height="332" />
                        </div>
                    </div>
                </div>
            </div>
            @foreach (__('pages/accueil.questions.services') as $service)
                <div class="col-md-4">
                    <div class="card card-body bg-light shadow-none border-0 p-4 p-lg-5 h-100 lp-radius">
                        <div><x-img class="lp-ico-50" :src="asset('assets/images/elements/'.$service['icon'])" alt="" width="50" height="50" /></div>
                        <h3 class="h4 mt-4">{{ $service['title'] }}</h3>
                        <p class="mb-0">{{ $service['text'] }}</p>
                        @isset($service['cta'])
                            {{-- « Réponse humaine sous 24 h » : parcours « Exprimer un besoin » de /signaler (l'assistant n'enregistre rien). --}}
                            <x-button variant="primary-soft" size="sm" class="mt-3" :href="route('signaler', ['tab' => 'besoin'])">{{ $service['cta'] }}</x-button>
                        @endisset
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
