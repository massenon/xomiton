{{--
    Liste publique `/ressources` (P4-03, RessourcesIndexController ; prototype_social/ressources.php l.40-93).
    Ordre (FRONT_END_SECTION_ORDER_INSTRUCTION.md) : h1 + introduction + compteur hors connexion → recherche et type →
    puces de catégorie → grille #liste → « Charger plus » (lien serveur rel=nofollow, amélioré par load-more.js) →
    « Fiches à télécharger » (sans filtre) → FAQ de la catégorie (avec `cat`) → CTA assistant. Publique et indexable
    (Q19 a), gabarit public pour tout le monde (Q18) ; registre vous. Première carte = image LCP (priority).
    Hors connexion (si $offlineEnabled, P5-10) : composant Alpine xmOfflineLibrary (resources/js/public/ressources.js) —
    bouton-bascule « enregistrées seulement » (nom stable masqué + compteur visible), interrupteurs par carte ; en mode
    « enregistrées seulement », la grille laisse place à la liste #offList construite depuis le stockage local et les
    ressources marquées par l'équipe (cartes chargées ou non), message #offEmpty si elle est vide.
    Type : aucune soumission au changement du select (WCAG 3.2.2 : au clavier, une flèche rechargerait la page), bouton
    « Filtrer » (plan §7, Q60).
    Variables : $categories (puces : ids/slug/label/chip/icon/color/count), $category, $kind, $kinds, $query, $resources,
                $total, $totalAll, $shown, $hasMore, $hasFilters, $step, $nextUrl, $files, $faqItems, $offlineEnabled,
                $offlineOptions, $fmt, $seo.
--}}
<x-layouts.public :seo="$seo" pub-nav="ressources">
    <div class="container py-4 py-sm-5">
        <div class="vstack gap-4" @if ($offlineEnabled) x-data="xmOfflineLibrary(@js($offlineOptions))" @endif>
            <section class="card card-body" id="ressources-entete" aria-labelledby="ressources-titre">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                    <div>
                        <h1 class="h3 mb-0" id="ressources-titre">{{ __('pages/ressources.title') }}</h1>
                        <p class="text-secondary mb-0">{{ __('pages/ressources.intro') }}</p>
                    </div>
                    @if ($offlineEnabled)
                        {{-- Prototype l.43 : badge compteur, devenu bouton-bascule « ressources enregistrées seulement » (plan §7). --}}
                        {{-- Nom accessible stable (action) suivi du compteur visible (Label in Name) ; état par aria-pressed. --}}
                        <button type="button" class="btn btn-sm bg-success bg-opacity-10 text-success align-self-start xm-res-offline-toggle" aria-controls="liste" aria-pressed="false"
                                x-on:click="only = ! only" x-bind:aria-pressed="only ? 'true' : 'false'">
                            <i class="bi bi-cloud-check me-1" aria-hidden="true"></i><span class="visually-hidden">{{ __('pages/ressources.offline_filter_label') }}&#160;: </span><span x-text="countLabel()">{{ trans_choice('pages/ressources.offline_count', 0) }}</span>
                        </button>
                    @endif
                </div>
                <form class="row g-2" method="get" action="{{ route('ressources.index') }}" role="search">
                    @if ($category)
                        <input type="hidden" name="cat" value="{{ $category['slug'] }}">
                    @endif
                    <div class="col-md-6">
                        <label class="visually-hidden" for="res-q">{{ __('pages/ressources.search_label') }}</label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true"><i class="bi bi-search" aria-hidden="true"></i></span>
                            <input class="form-control" type="search" id="res-q" name="q" value="{{ $query }}" maxlength="100" placeholder="{{ __('pages/ressources.search_placeholder') }}" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="visually-hidden" for="res-type">{{ __('pages/ressources.type_label') }}</label>
                        <select class="form-select" id="res-type" name="type">
                            <option value="">{{ __('pages/ressources.type_all') }}</option>
                            @foreach ($kinds as $item)
                                <option value="{{ $item->value }}" @selected($kind === $item)>{{ $item->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-primary" type="submit">{{ __('common.filter') }}</button>
                    </div>
                </form>
                @if ($hasFilters)
                    <p class="small mt-2 mb-0"><a href="{{ route('ressources.index') }}">{{ __('pages/ressources.reset_filters') }}</a></p>
                @endif
                @if ($categories !== [])
                    <nav class="mt-3" id="ressources-filtres" aria-label="{{ __('pages/ressources.filters_label') }}">
                        <ul class="list-unstyled d-flex flex-wrap gap-2 mb-0 xm-res-filters">
                            <li>
                                <a @class(['xm-chip text-decoration-none', 'bg-primary text-white' => ! $category, 'bg-light' => (bool) $category]) href="{{ route('ressources.index', array_filter(['q' => $query, 'type' => $kind?->value])) }}"@if (! $category) aria-current="true"@endif>{{ __('pages/ressources.filter_all') }}</a>
                            </li>
                            @foreach ($categories as $item)
                                @php($active = $category !== null && $category['slug'] === $item['slug'])
                                <li>
                                    <a @class(['xm-chip text-decoration-none', 'bg-primary text-white' => $active, 'bg-light' => ! $active]) href="{{ route('ressources.index', array_filter(['cat' => $item['slug'], 'q' => $query, 'type' => $kind?->value])) }}"@if ($active) aria-current="true"@endif>@if ($item['icon'])<i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>@endif{{ $item['chip'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @endif
            </section>

            <section id="liste" aria-labelledby="ressources-liste-titre">
                <h2 class="h5 mb-3" id="ressources-liste-titre" @if ($offlineEnabled) x-show="! only" @endif>{{ $query === '' ? trans_choice('pages/ressources.results', $total) : trans_choice('pages/ressources.results_query', $total, ['query' => $query]) }}</h2>
                <div @if ($offlineEnabled) x-show="! only" @endif>
                    @if ($resources->isEmpty())
                        <div class="card">
                            @if ($totalAll === 0 && ! $hasFilters)
                                <x-empty-state icon="journal-text" :title="__('pages/ressources.empty_none')" />
                            @else
                                <x-empty-state icon="search" :title="__('pages/ressources.empty_title')">
                                    {{ __('pages/ressources.empty_text') }}
                                    <x-slot:action>
                                        <a class="btn btn-primary-soft" href="{{ route('ressources.index') }}">{{ __('pages/ressources.reset_filters') }}</a>
                                    </x-slot:action>
                                </x-empty-state>
                            @endif
                        </div>
                    @else
                        <ul class="row g-3 list-unstyled mb-0" id="resGrid" data-xm-items>
                            @foreach ($resources as $resource)
                                @include('public.ressources.card', ['resource' => $resource, 'priority' => $loop->first])
                            @endforeach
                        </ul>
                    @endif
                </div>
                @if ($offlineEnabled)
                    {{-- Mode « enregistrées seulement » : liste construite côté client depuis le stockage local et les ressources marquées (cartes chargées ou non). --}}
                    <div x-show="only" x-cloak>
                        <h2 class="h5 mb-3" id="offList-titre">{{ __('pages/ressources.offline_list_title') }}</h2>
                        <ul class="list-group mb-0" id="offList" aria-labelledby="offList-titre" x-show="savedList().length > 0">
                            <template x-for="entry in savedList()" :key="entry.slug">
                                <li class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <a class="fw-semibold" x-bind:href="entry.url" x-text="entry.title"></a>
                                    <span x-show="entry.marked"><x-badge color="success" soft icon="wifi-off">{{ __('microcopy.offline.available_offline') }}</x-badge></span>
                                    <button type="button" class="btn btn-sm btn-light xm-res-offline-remove" x-show="! entry.marked" x-on:click="remove(entry.slug)">{{ __('pages/ressources.offline_remove') }}<span class="visually-hidden" x-text="removeHint(entry)"></span></button>
                                </li>
                            </template>
                        </ul>
                        <p class="small text-secondary mb-0" id="offEmpty" role="status" x-show="savedList().length === 0">{{ __('pages/ressources.offline_only_empty') }}</p>
                    </div>
                @endif
            </section>

            @if ($nextUrl)
                <div class="xm-load-more" data-xm-more @if ($offlineEnabled) x-show="! only" @endif>
                    <a class="btn btn-primary-soft px-4" href="{{ $nextUrl }}" rel="nofollow">{{ __('common.load_more') }} <span class="small text-secondary ms-1">({{ __('pages/ressources.shown_of', ['shown' => $shown, 'total' => $total]) }})</span></a>
                </div>
            @elseif ($hasMore)
                <p class="text-center small text-secondary mb-0" data-xm-more>{{ __('pages/ressources.shown_capped', ['shown' => $shown, 'total' => $total]) }}</p>
            @elseif ($total > $step)
                <p class="text-center small text-secondary mb-0" data-xm-more>{{ trans_choice('pages/ressources.end_of_list', $total) }}</p>
            @endif

            @if ($files->isNotEmpty())
                @include('public.ressources.files')
            @endif

            @if ($faqItems !== [])
                <section class="card" id="faq" aria-labelledby="faq-titre">
                    <div class="card-header">
                        <h2 class="h5 mb-0" id="faq-titre">{{ __('pages/ressources.faq_title', ['category' => $category['label']]) }}</h2>
                    </div>
                    <div class="card-body">
                        <x-accordion id="res-faq" level="h3" :open="0" :items="$faqItems" />
                    </div>
                </section>
            @endif

            @include('public.ressources.assistant')
        </div>
    </div>
    <x-assistant-offcanvas :fab="false" />
</x-layouts.public>
