{{--
    Liste publique `/actualites` (P4-10, ActualitesIndexController ; prototype_social/blog.php). Gabarit public pour tout
    le monde (Q18 a), registre vous. Ordre du prototype : h1 + introduction + puces de catégorie → grille #liste
    (première carte à la une en pleine largeur sans filtre, image LCP) → « Charger plus » (lien serveur rel=nofollow,
    amélioré par load-more.js) ; colonne : prochains événements (membres seulement, Q35), « Une question ? ».
    Variables : $categories, $category, $articles, $total, $totalAll, $shown, $hasMore, $step, $nextUrl, $events
                (null pour un·e visiteur·se), $fmt, $seo.
--}}
<x-layouts.public :seo="$seo" pub-nav="blog">
    <div class="container py-4 py-sm-5">
        <div class="vstack gap-4">
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-2">
                <div>
                    <h1 class="h3 mb-1">{{ __('pages/actualites.title') }}</h1>
                    <p class="text-secondary mb-0">{{ __('pages/actualites.intro') }}</p>
                </div>
                @if ($categories !== [])
                    <nav aria-label="{{ __('pages/actualites.filters_label') }}">
                        <ul class="list-unstyled d-flex flex-wrap gap-1 mb-0 xm-news-filters">
                            <li><a @class(['btn btn-sm', 'btn-primary' => ! $category, 'btn-light' => (bool) $category]) href="{{ route('actualites.index') }}"@if (! $category) aria-current="true"@endif>{{ __('pages/actualites.filter_all') }}</a></li>
                            @foreach ($categories as $item)
                                @php($active = $category !== null && $category['slug'] === $item['slug'])
                                <li><a @class(['btn btn-sm', 'btn-primary' => $active, 'btn-light' => ! $active]) href="{{ route('actualites.index', ['cat' => $item['slug']]) }}"@if ($active) aria-current="true"@endif>{{ $item['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                @endif
            </div>

            <div class="row g-4">
                <div class="col-lg-8 vstack gap-4">
                    <section id="liste" aria-labelledby="actualites-liste-titre">
                        <h2 class="visually-hidden" id="actualites-liste-titre">{{ __('pages/actualites.list_title') }}</h2>
                        @if ($articles->isEmpty())
                            <div class="card">
                                @if ($totalAll === 0)
                                    <x-empty-state icon="newspaper" :title="__('pages/actualites.empty_none')" />
                                @else
                                    <x-empty-state icon="newspaper" :title="__('pages/actualites.empty_category')">
                                        <x-slot:action>
                                            <a class="btn btn-primary-soft" href="{{ route('actualites.index') }}">{{ __('pages/actualites.show_all') }}</a>
                                        </x-slot:action>
                                    </x-empty-state>
                                @endif
                            </div>
                        @else
                            <ul class="row g-4 list-unstyled mb-0" data-xm-items>
                                @foreach ($articles as $article)
                                    @include('public.actualites.card', ['article' => $article, 'featured' => $loop->first && $category === null, 'priority' => $loop->first])
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    @if ($nextUrl)
                        <div class="xm-load-more" data-xm-more>
                            <a class="btn btn-primary-soft px-4" href="{{ $nextUrl }}" rel="nofollow">{{ __('common.load_more') }} <span class="small text-secondary ms-1">({{ __('pages/actualites.shown_of', ['shown' => $shown, 'total' => $total]) }})</span></a>
                        </div>
                    @elseif ($hasMore)
                        <p class="text-center small text-secondary mb-0" data-xm-more>{{ __('pages/actualites.shown_capped', ['shown' => $shown, 'total' => $total]) }}</p>
                    @elseif ($total > $step)
                        <p class="text-center small text-secondary mb-0" data-xm-more>{{ trans_choice('pages/actualites.end_of_list', $total) }}</p>
                    @endif
                </div>

                <aside class="col-lg-4 vstack gap-4" aria-label="{{ __('pages/actualites.aside_label') }}">
                    @if ($events !== null)
                        @include('public.actualites.events')
                    @endif
                    @include('public.actualites.question')
                </aside>
            </div>
        </div>
    </div>
</x-layouts.public>
