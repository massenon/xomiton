{{--
    Page `/lois` (P4-04, LoisController ; prototype_social/lois-benin.php). Publique et indexable (Q19 a), gabarit public
    pour tout le monde (Q18 a), registre vous. Fil d'Ariane Accueil › Lois (le prototype passait par « Outils CCC »,
    réservé aux membres). Outils (recherche, catégorie, « Lire en version simple ») révélés par le composant Alpine
    xmLaws (resources/js/public/lois.js, chargé à la demande) : filtrage sur ce balisage, sans requête réseau ; préférence
    de lecture gardée sur l'appareil (localStorage `xm_law_simple`, clé du prototype). Sans JavaScript : tous les textes,
    version juridique, panneaux de l'accordéon ouverts (rendus `collapse show` ici). Avec JavaScript, xmLaws.init les
    replie : un texte ouvert à la fois, seul celui de l'ancre `#texte-{slug}` reste ouvert au chargement.
    Lien officiel externe : rel « noopener noreferrer » (le site externe ne reçoit pas l'origine Xomiton).
    Variables : $laws (slug, title, subtitle, summary, simple, category, official_url), $categories, $seo.
--}}
@php
    $juristUrl = auth()->check() ? route('annuaire') : route('urgences').'#urgences-contacts';
    $lawsConfig = [
        'total' => count($laws),
        'texts' => [
            'none' => __('pages/lois.count_none'),
            'one' => __('pages/lois.count_one'),
            'many' => __('pages/lois.count_many'),
        ],
    ];
@endphp
<x-layouts.public :seo="$seo">
    <div class="container py-4 py-sm-5">
        <div class="vstack gap-4" x-data="xmLaws(@js($lawsConfig))">
            <x-breadcrumb :items="[['label' => __('seo.breadcrumb_home'), 'url' => route('home')], ['label' => __('pages/lois.title')]]" />

            <section class="card card-body" aria-labelledby="lois-titre">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <h1 class="h3 mb-0" id="lois-titre">{{ __('pages/lois.title') }}</h1>
                        <p class="text-secondary mb-0">{{ __('pages/lois.intro') }}</p>
                    </div>
                    @if ($laws !== [])
                        <div class="form-check form-switch align-self-start mb-0" data-law-tool hidden x-bind:hidden="false">
                            <input class="form-check-input" type="checkbox" role="switch" id="lois-simple" aria-describedby="lois-simple-aide" x-model="simple">
                            <label class="form-check-label" for="lois-simple">{{ __('pages/lois.simple_switch') }}</label>
                            <div class="form-text mt-0" id="lois-simple-aide">{{ __('pages/lois.simple_hint') }}</div>
                        </div>
                    @endif
                </div>
                @if ($laws !== [])
                    <div class="row g-2 mt-1" role="search" aria-label="{{ __('pages/lois.tools_label') }}" data-law-tool hidden x-bind:hidden="false">
                        <div class="col-md-7">
                            <label class="visually-hidden" for="lois-q">{{ __('pages/lois.search_label') }}</label>
                            <div class="input-group">
                                <span class="input-group-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                                <input class="form-control" type="search" id="lois-q" maxlength="100" autocomplete="off" placeholder="{{ __('pages/lois.search_placeholder') }}" x-model.debounce.150ms="q">
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label class="visually-hidden" for="lois-cat">{{ __('pages/lois.category_label') }}</label>
                            <select class="form-select" id="lois-cat" x-model="cat">
                                <option value="">{{ __('pages/lois.category_all') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}">{{ $category }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <p class="small text-secondary mt-2 mb-0" aria-live="polite" data-law-count x-text="countLabel()"></p>
                @endif
            </section>

            <section aria-labelledby="lois-liste-titre">
                <h2 class="visually-hidden" id="lois-liste-titre">{{ __('pages/lois.list_title') }}</h2>
                @if ($laws === [])
                    <div class="card"><x-empty-state icon="bank" :title="__('pages/lois.none')" /></div>
                @else
                    <div class="accordion" id="lois-liste">
                        @foreach ($laws as $law)
                            @php($id = 'texte-'.$law['slug'])
                            <div class="accordion-item" id="{{ $id }}" data-law data-cat="{{ $law['category'] }}" data-text="{{ mb_strtolower(implode(' ', array_filter([$law['title'], $law['subtitle'], $law['summary'], $law['simple'], $law['category']]))) }}">
                                <h3 class="accordion-header" id="{{ $id }}-titre">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $id }}-texte" aria-expanded="true" aria-controls="{{ $id }}-texte">
                                        <i class="bi bi-bank2 text-primary me-3 fs-5" aria-hidden="true"></i>
                                        <span class="me-auto">
                                            <span class="d-block fw-semibold">{{ $law['title'] }}</span>
                                            @if ($law['subtitle'])
                                                <span class="d-block small text-secondary fw-normal">{{ $law['subtitle'] }}</span>
                                            @endif
                                        </span>
                                        <span class="badge bg-primary bg-opacity-10 text-primary mx-2">{{ $law['category'] }}</span>
                                    </button>
                                </h3>
                                <div id="{{ $id }}-texte" class="accordion-collapse collapse show" data-bs-parent="#lois-liste" aria-labelledby="{{ $id }}-titre" role="region">
                                    <div class="accordion-body">
                                        <p class="mb-2" data-law-legal><span class="visually-hidden">{{ __('pages/lois.legal_label') }} {{ __('common.separator') }} </span>{{ $law['summary'] }}</p>
                                        <p class="mb-2" data-law-simple hidden><i class="bi bi-chat-heart text-success me-1" aria-hidden="true"></i><span class="visually-hidden">{{ __('pages/lois.simple_label') }} {{ __('common.separator') }} </span>{{ $law['simple'] }}</p>
                                        <div class="d-flex flex-wrap gap-2">
                                            @if ($law['official_url'])
                                                <a class="btn btn-sm btn-light" href="{{ $law['official_url'] }}" target="_blank" rel="noopener noreferrer external">{{ __('pages/lois.official') }} <i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i><span class="visually-hidden"> ({{ __('pages/lois.official_hint') }})</span></a>
                                            @endif
                                            <a class="btn btn-sm btn-danger-soft" href="{{ route('urgences') }}"><i class="bi bi-telephone-fill me-1" aria-hidden="true"></i>{{ __('pages/lois.urgent') }}</a>
                                            <a class="btn btn-sm btn-primary-soft" href="{{ $juristUrl }}"><i class="bi bi-person-lines-fill me-1" aria-hidden="true"></i>{{ __('pages/lois.jurist') }}</a>
                                        </div>
                                        <p class="small text-secondary mt-3 mb-0">{{ __('pages/lois.disclaimer') }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="card card-body text-center text-secondary mt-3" data-law-empty hidden x-bind:hidden="visible !== 0">
                        <i class="bi bi-search fs-1" aria-hidden="true"></i>
                        <p class="mb-2">{{ __('pages/lois.empty') }}</p>
                        <div><button type="button" class="btn btn-sm btn-primary-soft" x-on:click="clear()">{{ __('pages/lois.clear') }}</button></div>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-layouts.public>
