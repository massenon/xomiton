{{--
    Liste publique `/thematiques` (P4-02, ThematiquesIndexController ; prototype_social/thematiques.php l.36-74) :
    en-tête (h1, compteur, recherche) et filtres par catégorie dans une carte, grille #liste, « Charger plus » côté serveur
    (lien rel=nofollow, amélioré sans rechargement par resources/js/public/load-more.js). Publique et indexable (Q19 a),
    gabarit public pour tout le monde (Q18) ; registre vous. Première carte = image LCP (priority).
    Variables : $categories (tableaux id/slug/label/icon/color/count), $category (tableau ou null), $query, $thematiques,
                $total, $totalAll, $shown, $hasMore, $step, $nextUrl, $seo. Au plafond `max_per_load` (plus de lien), le bas de
                liste dit combien de fiches sont affichées sur le total au lieu de « Vous avez tout vu ».
--}}
<x-layouts.public :seo="$seo" pub-nav="thematiques">
    <div class="container py-4 py-sm-5">
        <div class="vstack gap-4">
            <section class="card card-body" id="thematiques-entete" aria-labelledby="thematiques-titre">
                <div class="d-sm-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h1 class="h4 mb-1" id="thematiques-titre">{{ __('pages/thematiques.title') }}</h1>
                        <p class="mb-0 small text-secondary">{{ trans_choice('pages/thematiques.count', $totalAll) }}</p>
                    </div>
                    <form class="mt-3 mt-sm-0" method="get" action="{{ route('thematiques.index') }}" role="search">
                        @if ($category)
                            <input type="hidden" name="cat" value="{{ $category['slug'] }}">
                        @endif
                        <label class="visually-hidden" for="th-q">{{ __('pages/thematiques.search_label') }}</label>
                        <div class="input-group">
                            <input class="form-control" type="search" id="th-q" name="q" value="{{ $query }}" maxlength="100" placeholder="{{ __('pages/thematiques.search_placeholder') }}" autocomplete="off">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">{{ __('common.search') }}</span></button>
                        </div>
                    </form>
                </div>
                <nav id="thematiques-filtres" aria-label="{{ __('pages/thematiques.filters_label') }}">
                    <ul class="list-unstyled d-flex flex-wrap gap-2 mb-0 xm-th-filters">
                        <li>
                            <a @class(['btn btn-sm', 'btn-primary' => ! $category, 'btn-light' => (bool) $category]) href="{{ route('thematiques.index', array_filter(['q' => $query])) }}"@if (! $category) aria-current="true"@endif>{{ __('pages/thematiques.filter_all', ['count' => $totalAll]) }}</a>
                        </li>
                        @foreach ($categories as $item)
                            @php($active = $category !== null && $category['id'] === $item['id'])
                            <li>
                                <a @class(['btn btn-sm', 'btn-primary' => $active, 'btn-light' => ! $active]) href="{{ route('thematiques.index', array_filter(['cat' => $item['slug'], 'q' => $query])) }}"@if ($active) aria-current="true"@endif>@if ($item['icon'])<i class="bi {{ $item['icon'] }} me-1" aria-hidden="true"></i>@endif{{ $item['label'] }} ({{ $item['count'] }})</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            </section>

            <section id="liste" aria-labelledby="thematiques-liste-titre">
                <h2 class="visually-hidden" id="thematiques-liste-titre">{{ __('pages/thematiques.list_title') }}</h2>
                @if ($thematiques->isEmpty())
                    <div class="card">
                        @if ($totalAll === 0)
                            <x-empty-state icon="journal-text" :title="__('pages/thematiques.empty_none')" />
                        @else
                            <x-empty-state icon="search" :title="__('pages/thematiques.empty_title')">
                                {{ __('pages/thematiques.empty_text') }}
                                <x-slot:action>
                                    <a class="btn btn-primary-soft" href="{{ route('thematiques.index') }}">{{ __('buttons.reset_filters') }}</a>
                                </x-slot:action>
                            </x-empty-state>
                        @endif
                    </div>
                @else
                    <ul class="row g-4 list-unstyled mb-0" data-xm-items>
                        @foreach ($thematiques as $thematique)
                            <li class="col-sm-6 col-xl-4">
                                @include('public.thematiques.card', ['thematique' => $thematique, 'priority' => $loop->first])
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if ($nextUrl)
                <div class="xm-load-more" data-xm-more>
                    <a class="btn btn-primary-soft px-4" href="{{ $nextUrl }}" rel="nofollow">{{ __('common.load_more') }} <span class="small text-secondary ms-1">({{ __('pages/thematiques.shown_of', ['shown' => $shown, 'total' => $total]) }})</span></a>
                </div>
            @elseif ($hasMore)
                <p class="text-center small text-secondary mb-0" data-xm-more>{{ __('pages/thematiques.shown_capped', ['shown' => $shown, 'total' => $total]) }}</p>
            @elseif ($total > $step)
                <p class="text-center small text-secondary mb-0" data-xm-more>{{ trans_choice('pages/thematiques.end_of_list', $total) }}</p>
            @endif
        </div>
    </div>
</x-layouts.public>
