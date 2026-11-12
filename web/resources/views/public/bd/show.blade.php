{{--
    Page publique d'une BD `/bd/{key}` (P19-04, BdShowController). Indexable, vous. Ordre : fil d'Ariane → badge + h1 +
    présentation → avertissement de contenu (fiche liée, lien SOS) → visionneuse `x-comic-viewer` (repliée en mode
    discret pour une personne connectée qui l'a choisi) → « Et maintenant ? » (fiche) ; colonne : « Besoin d’aide ? »
    (Signaler, SOS, urgences), autres BD. Impression : planches seules (no-print sur le reste de l'interface).
    Variables : $tool, $fiche, $pages, $warning, $others, $hideImages, $breadcrumb, $seo.
--}}
<x-layouts.public :seo="$seo">
    @if (! $hideImages && $pages !== [])
        <x-slot:head>
            <link rel="preload" as="image" href="{{ asset((string) ($pages[0]['image'] ?? '')) }}" fetchpriority="high">
        </x-slot:head>
    @endif

    <div class="container py-4 py-sm-5">
        <div class="row g-4">
            <div class="col-lg-8 vstack gap-4">
                <x-breadcrumb :items="$breadcrumb" class="no-print" />
                <article class="vstack gap-3" aria-labelledby="bd-titre">
                    <header class="vstack gap-2">
                        <p class="mb-0"><x-badge color="violet" soft icon="book">{{ __('pages/bd.kicker') }}</x-badge></p>
                        <h1 class="h3 mb-0" id="bd-titre">{{ $tool->title }}</h1>
                        @if ($fiche)
                            <p class="small text-secondary mb-0">{{ __('pages/bd.topic', ['title' => $fiche->title]) }}</p>
                        @endif
                        @if (trim((string) $tool->description) !== '')
                            <p class="lead fs-6 mb-0">{{ $tool->description }}</p>
                        @endif
                    </header>

                    @if ($warning)
                        <x-alert type="warning" icon="exclamation-triangle" role="none" class="mb-0">
                            {{ $warning }}
                            <a href="{{ route('sos') }}" class="alert-link ms-1">{{ __('pages/bd.warning_sos_link') }}</a>
                        </x-alert>
                    @endif

                    @if ($hideImages)
                        <details class="card">
                            <summary class="card-body fw-semibold">
                                <i class="bi bi-eye-slash me-1" aria-hidden="true"></i>{{ __('pages/bd.discreet_title') }} — {{ __('pages/bd.discreet_show') }}
                                <span class="d-block small fw-normal text-secondary">{{ __('pages/bd.discreet_text') }}</span>
                            </summary>
                            <div class="card-body pt-0"><x-comic-viewer :pages="$pages" :title="$tool->title" /></div>
                        </details>
                    @else
                        <x-comic-viewer :pages="$pages" :title="$tool->title" />
                    @endif

                    @if ($fiche)
                        <section class="card no-print" aria-labelledby="bd-suite-titre">
                            <div class="card-body vstack gap-2">
                                <h2 class="h5 mb-0" id="bd-suite-titre">{{ __('pages/bd.after_title') }}</h2>
                                <p class="mb-0 text-secondary">{{ __('pages/bd.after_text') }}</p>
                                <p class="mb-0"><a class="btn btn-primary" href="{{ route('thematiques.show', ['slug' => $fiche->slug]) }}"><i class="bi bi-journal-text me-1" aria-hidden="true"></i>{{ __('pages/bd.after_fiche', ['title' => $fiche->title]) }}</a></p>
                            </div>
                        </section>
                    @endif
                </article>
            </div>

            <aside class="col-lg-4 no-print" aria-label="{{ __('pages/bd.help_title') }}">
                <div class="vstack gap-4">
                    <section class="card border-primary" aria-labelledby="bd-aide-titre">
                        <div class="card-header pb-0 border-0"><h2 class="h5 card-title mb-0" id="bd-aide-titre">{{ __('pages/bd.help_title') }}</h2></div>
                        <div class="card-body vstack gap-2">
                            <a class="btn btn-primary w-100" href="{{ route('signaler') }}"><i class="bi bi-megaphone me-1" aria-hidden="true"></i>{{ __('pages/bd.help_report') }}</a>
                            <a class="btn btn-sm btn-primary-soft text-start" href="{{ route('sos') }}"><i class="bi bi-broadcast me-2" aria-hidden="true"></i>{{ __('pages/bd.help_sos') }}</a>
                            <a class="btn btn-sm btn-primary-soft text-start" href="{{ route('urgences') }}"><i class="bi bi-telephone-fill me-2" aria-hidden="true"></i>{{ __('pages/bd.help_emergency') }}</a>
                            <a class="btn btn-sm btn-primary-soft text-start" href="{{ route('violences-numeriques') }}"><i class="bi bi-shield-check me-2" aria-hidden="true"></i>{{ __('pages/violences_numeriques.hub_link') }}</a>
                        </div>
                    </section>
                    @if ($others->isNotEmpty())
                        <section aria-labelledby="bd-autres-titre">
                            <h2 class="h5" id="bd-autres-titre">{{ __('pages/bd.others_title') }}</h2>
                            <ul class="list-unstyled vstack gap-3 mb-0">
                                @foreach ($others as $other)
                                    <li>@include('public.bd.card', ['comic' => $other, 'heading' => 'h3'])</li>
                                @endforeach
                            </ul>
                            <p class="small mt-3 mb-0"><a href="{{ route('bd.index') }}">{{ __('pages/bd.all_comics') }}</a></p>
                        </section>
                    @endif
                </div>
            </aside>
        </div>
    </div>
</x-layouts.public>
