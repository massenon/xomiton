{{--
    Charte, confidentialité et conditions `/confidentialite` (P4-08, ConfidentialiteController ; prototype_social/
    privacy-and-terms.php). Publique et indexable, gabarit public pour tout le monde, registre vous (charte à la première
    personne). Héro avec la date de dernière mise à jour et la version des textes (celle des consentements) ; fil
    d'Ariane ajouté (SEO_CONTENT_GUIDE §7) ; sous-navigation collante (aide.css : `top` sorti de l'inline ; décalage des
    ancres sous l'en-tête : `html { scroll-padding-top: 96px }` du thème), liens directement dans `.list-group-flush`
    comme le prototype, vers les quatre ancres #charte, #confidentialite, #conditions, #signalement (l.69). Icônes de
    titre en primaire (rouge réservé à l'attention, D5) ; le seul bouton rouge plein est « Signaler ».
    Variables : $sections (ancre, icône), $updatedAt (Y-m-d), $version, $seo.
--}}
@php
    $formatter = \App\Support\LocaleFormatter::make();
@endphp
<x-layouts.public :seo="$seo">
    <section class="py-5 text-white xm-hero-bg xm-hero-page" aria-labelledby="legal-titre">
        <div class="container">
            <h1 class="fw-bold text-white" id="legal-titre">{{ __('pages/confidentialite.title') }}</h1>
            <p class="mb-0 xm-hero-sub">{!! __('pages/confidentialite.updated', [
                'date' => '<time datetime="'.e($updatedAt).'">'.e($formatter->longDate($updatedAt)).'</time>',
                'version' => e($version),
            ]) !!}</p>
        </div>
    </section>

    <div class="container py-5">
        <x-breadcrumb class="mb-4" :items="[['label' => __('seo.breadcrumb_home'), 'url' => route('home')], ['label' => __('pages/confidentialite.title')]]" />
        <div class="row g-4">
            <aside class="col-lg-3">
                <nav class="card xm-legal-nav" aria-label="{{ __('pages/confidentialite.nav_label') }}">
                    <div class="list-group list-group-flush">
                        @foreach ($sections as [$anchor, $icon])
                            <a class="list-group-item list-group-item-action" href="#{{ $anchor }}"><i class="bi {{ $icon }} me-2 text-primary" aria-hidden="true"></i>{{ __('pages/confidentialite.sections.'.$anchor) }}</a>
                        @endforeach
                    </div>
                </nav>
            </aside>

            <div class="col-lg-9 vstack gap-4">
                <section class="card xm-legal-section" id="charte" aria-labelledby="charte-titre">
                    <div class="card-body p-4">
                        <h2 class="h4" id="charte-titre"><i class="bi bi-heart text-primary me-2" aria-hidden="true"></i>{{ __('pages/confidentialite.sections.charte') }}</h2>
                        <p>{{ __('pages/confidentialite.charte.intro') }}</p>
                        <ul>
                            @foreach (__('pages/confidentialite.charte.items') as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                        <div class="alert alert-info mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>{{ __('pages/confidentialite.charte.notice') }}</div>
                    </div>
                </section>

                <section class="card xm-legal-section" id="confidentialite" aria-labelledby="confidentialite-titre">
                    <div class="card-body p-4">
                        <h2 class="h4" id="confidentialite-titre"><i class="bi bi-shield-lock text-primary me-2" aria-hidden="true"></i>{{ __('pages/confidentialite.sections.confidentialite') }}</h2>
                        <div class="row g-3">
                            @foreach (__('pages/confidentialite.privacy') as [$title, $text])
                                <div class="col-md-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <h3 class="h6">{{ $title }}</h3>
                                        <p class="small text-secondary mb-0">{{ $text }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <p class="small mt-3 mb-0">
                            <a href="{{ route('legal.privacy') }}">{{ __('pages/confidentialite.full_privacy') }}</a> ·
                            <a href="{{ route('account-deletion.show') }}">{{ __('pages/confidentialite.delete_account') }}</a>
                        </p>
                    </div>
                </section>

                <section class="card xm-legal-section" id="conditions" aria-labelledby="conditions-titre">
                    <div class="card-body p-4">
                        <h2 class="h4" id="conditions-titre"><i class="bi bi-file-text text-primary me-2" aria-hidden="true"></i>{{ __('pages/confidentialite.sections.conditions') }}</h2>
                        <ol class="mb-0">
                            @foreach (__('pages/confidentialite.conditions') as [$term, $text])
                                <li><strong>{{ $term }}</strong> {{ $text }}</li>
                            @endforeach
                        </ol>
                        <p class="small mt-3 mb-0"><a href="{{ route('legal.terms') }}">{{ __('pages/confidentialite.full_terms') }}</a> · <a href="{{ route('legal.notice') }}">{{ __('footer.legal') }}</a></p>
                    </div>
                </section>

                <section class="card xm-legal-section" id="signalement" aria-labelledby="signalement-titre">
                    <div class="card-body p-4">
                        <h2 class="h4" id="signalement-titre"><i class="bi bi-flag text-primary me-2" aria-hidden="true"></i>{{ __('pages/confidentialite.sections.signalement') }}</h2>
                        <p>{{ __('pages/confidentialite.signalement.text') }}</p>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-danger" href="{{ route('signaler') }}"><i class="bi bi-megaphone me-1" aria-hidden="true"></i>{{ __('pages/confidentialite.signalement.report') }}</a>
                            <a class="btn btn-primary-soft" href="{{ route('aide.index') }}">{{ __('pages/confidentialite.signalement.help') }}</a>
                            <a class="btn btn-light" href="{{ route('home') }}">{{ __('pages/confidentialite.signalement.home') }}</a>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-layouts.public>
