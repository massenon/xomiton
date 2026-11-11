{{--
    Page `/do-glegbe` (P4-04, DoGlegbeController ; prototype_social/do-glegbe.php). Publique et indexable, gabarit public
    pour tout le monde (Q18 a), registre vous ; classes dg-* de theme/xomiton.css (identité Do Glegbe du prototype).
    Ordre du prototype : fil d'Ariane → bannière → mission → pourquoi → services, puis fiches (thématiques de la
    catégorie Do Glegbe) et quiz vrai / faux (MODULE_SPECIFICATIONS.md §A : « fiches, quiz ») → engagez-vous.
    Ateliers, groupes, forums : routes membres, le middleware `auth` renvoie un·e visiteur·se vers la connexion
    (le prototype passait par sign-in.php?next=) ; mention « réservé aux membres » pour les lecteurs d'écran.
    Variables : $sheets (Thematique), $sheetsUrl, $sheetsTotal, $quiz (statement, answer, explanation), $seo.
--}}
@php
    $guestHint = auth()->guest() ? ' ('.__('pages/do_glegbe.services.members_only').')' : '';
    $services = [
        'sheets' => $sheetsUrl,
        'workshops' => route('evenements.index'),
        'groups' => route('groupes.index'),
    ];
@endphp
<x-layouts.public :seo="$seo" pub-nav="doglegbe">
    <x-slot:head>
        <link rel="preload" as="image" href="{{ asset('assets/images/xomiton/doglegbexo.png') }}" fetchpriority="high">
    </x-slot:head>

    <div class="container py-4 py-sm-5">
        <div class="vstack gap-4">
            <x-breadcrumb :items="[['label' => __('seo.breadcrumb_home'), 'url' => route('home')], ['label' => __('pages/do_glegbe.title')]]" />

            {{-- Bannière (prototype l.22-36) --}}
            <section class="dg-hero rounded-4 p-4 p-lg-5" aria-labelledby="dg-titre">
                <div class="row align-items-center g-4 xm-dg-inner">
                    <div class="col-md-7 col-lg-8">
                        <span class="badge bg-white text-danger mb-3"><i class="bi bi-droplet-half me-1" aria-hidden="true"></i>{{ __('pages/do_glegbe.hero.badge') }}</span>
                        <h1 class="display-5 fw-bold mb-3" id="dg-titre">{{ __('pages/do_glegbe.title') }}</h1>
                        <p class="lead mb-4">{{ __('pages/do_glegbe.hero.lead') }}</p>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-light fw-bold text-danger" href="{{ $sheetsUrl }}">{{ __('pages/do_glegbe.hero.sheets') }} <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
                            <a class="btn btn-outline-light" href="{{ route('evenements.index') }}">{{ __('pages/do_glegbe.hero.workshops') }}@if ($guestHint !== '')<span class="visually-hidden">{{ $guestHint }}</span>@endif</a>
                        </div>
                    </div>
                    <div class="col-md-5 col-lg-4">
                        <div class="dg-logo-card">
                            <x-img :src="asset('assets/images/xomiton/doglegbexo.png')" :alt="__('pages/do_glegbe.hero.logo_alt')" :title="__('pages/do_glegbe.hero.logo_title')" width="314" height="258" :priority="true" />
                        </div>
                    </div>
                </div>
            </section>

            {{-- Qu'est-ce que (prototype l.38-44) --}}
            <section class="card card-body p-4 p-md-5" aria-labelledby="dg-quoi">
                <div class="dg-eyebrow mb-2">{{ __('pages/do_glegbe.mission.eyebrow') }}</div>
                <h2 class="h4 mb-3" id="dg-quoi">{{ __('pages/do_glegbe.mission.title') }}</h2>
                <p class="dg-lead">{{ __('pages/do_glegbe.mission.lead') }}</p>
                <p class="mb-0">{{ __('pages/do_glegbe.mission.text') }}</p>
            </section>

            {{-- Pourquoi (prototype l.46-61) --}}
            <section class="card card-body p-4 p-md-5" aria-labelledby="dg-pourquoi">
                <div class="dg-eyebrow mb-2">{{ __('pages/do_glegbe.why.eyebrow') }}</div>
                <h2 class="h4 mb-2" id="dg-pourquoi">{{ __('pages/do_glegbe.why.title') }}</h2>
                <p class="text-secondary mb-4">{{ __('pages/do_glegbe.why.intro') }}</p>
                <ul class="row g-3 list-unstyled mb-0">
                    @foreach ((array) __('pages/do_glegbe.why.benefits') as $benefit)
                        <li class="col-md-6">
                            <div class="dg-benefit"><span class="dg-ico" aria-hidden="true"><i class="bi bi-{{ $benefit['icon'] }}"></i></span><div><b>{{ $benefit['title'] }}</b><span class="small text-secondary">{{ $benefit['text'] }}</span></div></div>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- Services (prototype l.63-80) --}}
            <section aria-labelledby="dg-services">
                <div class="dg-eyebrow mb-2">{{ __('pages/do_glegbe.services.eyebrow') }}</div>
                <h2 class="h4 mb-3" id="dg-services">{{ __('pages/do_glegbe.services.title') }}</h2>
                <ol class="row g-3 list-unstyled mb-0">
                    @foreach ($services as $key => $url)
                        <li class="col-md-4">
                            <div class="dg-step d-flex flex-column">
                                <span class="dg-num" aria-hidden="true">{{ $loop->iteration }}</span>
                                <h3 class="h6">{{ __("pages/do_glegbe.services.items.$key.title") }}</h3>
                                <p class="small text-secondary flex-grow-1">{{ __("pages/do_glegbe.services.items.$key.text") }}</p>
                                <a class="small fw-bold text-danger" href="{{ $url }}">{{ __("pages/do_glegbe.services.items.$key.cta") }}@if ($key !== 'sheets' && $guestHint !== '')<span class="visually-hidden">{{ $guestHint }}</span>@endif <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>

            @if ($sheets->isNotEmpty())
                {{-- Fiches : thématiques publiées de la catégorie Do Glegbe (cartes de /thematiques) --}}
                <section aria-labelledby="dg-fiches">
                    <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
                        <div>
                            <h2 class="h4 mb-1" id="dg-fiches">{{ __('pages/do_glegbe.sheets.title') }}</h2>
                            <p class="text-secondary mb-0">{{ __('pages/do_glegbe.sheets.intro') }}</p>
                        </div>
                        @if ($sheetsTotal > $sheets->count())
                            <a class="btn btn-sm btn-outline-danger" href="{{ $sheetsUrl }}">{{ __('pages/do_glegbe.sheets.all') }} <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></a>
                        @endif
                    </div>
                    <ul class="row g-4 list-unstyled mb-0">
                        @foreach ($sheets as $thematique)
                            <li class="col-sm-6 col-lg-3">@include('public.thematiques.card', ['thematique' => $thematique, 'priority' => false])</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($quiz !== [])
                @include('public.do-glegbe.quiz')
            @endif

            {{-- Engagez-vous (prototype l.82-93) --}}
            <section class="card card-body text-center p-4 p-md-5 border-0 dg-section" aria-labelledby="dg-engage">
                <h2 class="h4" id="dg-engage">{{ __('pages/do_glegbe.engage.title') }}</h2>
                <p class="mx-auto mb-4 xm-dg-narrow">{{ __('pages/do_glegbe.engage.text') }}</p>
                <p class="dg-quote mx-auto mb-4 text-start xm-dg-narrow">{{ __('pages/do_glegbe.engage.quote') }}</p>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <a class="btn btn-dg" href="{{ $sheetsUrl }}">{{ __('pages/do_glegbe.engage.thematiques') }}</a>
                    <a class="btn btn-outline-danger" href="{{ route('forums.index') }}">{{ __('pages/do_glegbe.engage.question') }}@if ($guestHint !== '')<span class="visually-hidden">{{ $guestHint }}</span>@endif</a>
                    <a class="btn btn-light" href="{{ route('urgences') }}">{{ __('pages/do_glegbe.engage.help') }}</a>
                </div>
            </section>
        </div>
    </div>
</x-layouts.public>
