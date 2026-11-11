{{-- Section 7 — Expert·es et assistant #assistant (prototype index.php l.298-328) : promesse requalifiée (Q36), ouvre l'offcanvas. --}}
<section class="py-4 py-sm-5 position-relative" id="assistant" aria-labelledby="assistantTitre">
    <div class="position-absolute top-0 start-0 mt-n5 pt-n5 d-none d-lg-block" aria-hidden="true">
        <x-img class="h-300px blur-9" :src="asset('assets/images/elements/06.svg')" alt="" width="300" height="300" />
    </div>
    <div class="container">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="row g-4 align-items-center position-relative z-index-1">
                    <div class="col-md-6">
                        <div class="me-4">
                            <h2 id="assistantTitre" class="h1 mb-4 lp-assistant-title">{{ __('pages/accueil.experts.title_1') }}<br>{{ __('pages/accueil.experts.title_2') }}</h2>
                            <p>{{ __('pages/accueil.experts.text') }}</p>
                            <div class="mb-3 d-flex align-items-start">
                                <x-img class="h-40px" :src="asset('assets/images/elements/04.svg')" alt="" width="40" height="40" />
                                <div class="ms-4">
                                    <h3 class="h5 mt-2">{{ __('pages/accueil.experts.arg_1_title') }}</h3>
                                    <p class="mb-0">{{ __('pages/accueil.experts.arg_1_text') }} <a href="{{ route('signaler', ['tab' => 'besoin']) }}">{{ __('pages/accueil.experts.arg_1_cta') }}</a></p>
                                </div>
                            </div>
                            <div class="mb-3 d-flex align-items-start">
                                <x-img class="h-40px" :src="asset('assets/images/elements/10.svg')" alt="" width="40" height="40" />
                                <div class="ms-4">
                                    <h3 class="h5 mt-2">{{ __('pages/accueil.experts.arg_2_title') }}</h3>
                                    <p class="mb-0">{{ __('pages/accueil.experts.arg_2_text') }}</p>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#offcanvasChat" aria-controls="offcanvasChat"><i class="bi bi-chat-heart me-2" aria-hidden="true"></i>{{ __('buttons.help.chat_assistant') }}</button>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-5 position-relative">
                        <x-img class="w-100 lp-mock" :src="asset('assets/images/xomiton/mockup-A.gif')" :alt="__('pages/accueil.experts.mock_alt')" width="480" height="640" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
