{{--
    Centre d'aide `/aide` (P4-05, AideIndexController ; prototype_social/help.php). Public et indexable, gabarit public
    pour tout le monde (Q18 a), registre vous. Recherche GET `q` (le bouton « Chercher » passe en primaire : rouge réservé
    à l'attention, D5), cartes de catégorie = filtres `?cat=` (catégories `faq` qui ont au moins une question), accordéon
    des questions (première ouverte, « Lire la suite » vers l'article), lien discret « Créer un compte » pour les
    visiteur·ses (CTA secondaire de CONTENT_STRATEGY §1, absent du prototype), bloc « en danger ». Fil d'Ariane ajouté
    (SEO_CONTENT_GUIDE §7), titres h1 › h2 › h3 sans saut (le prototype passait de h1 à h5 / h6).
    Variables : $listing, $categories (avec count), $category, $query, $faqs, $breadcrumb (même source que le JSON-LD),
    $seo.
--}}
@php
    $items = $faqs->map(fn ($faq) => [
        'q' => (string) $faq->question,
        // Réponse en texte brut (DATABASE_DICTIONARY.md), échappée ici : <x-accordion> l'insère telle quelle.
        'a' => '<p>'.e((string) $faq->answer).'</p><a class="small" href="'.e(route('aide.show', $faq->slug)).'">'.e(__('pages/aide.read_more'))
            .'<span class="visually-hidden">'.e(__('pages/aide.read_more_context', ['question' => $faq->question])).'</span>'
            .' <i class="bi bi-arrow-right" aria-hidden="true"></i></a>',
    ])->values()->all();
@endphp
<x-layouts.public :seo="$seo" pub-nav="aide">
    <div class="container py-4 py-sm-5">
        <div class="vstack gap-4">
            <x-breadcrumb :items="$breadcrumb" />

            <section class="card overflow-hidden" aria-labelledby="aide-titre">
                <div class="p-4 p-lg-5 text-white text-center xm-hero-bg xm-hero-page">
                    <h1 class="h2 fw-bold text-white" id="aide-titre">{{ __('pages/aide.hero_title') }}</h1>
                    <form class="mx-auto mt-3 xm-help-search" method="get" action="{{ route('aide.index') }}" role="search">
                        @if ($category)
                            <input type="hidden" name="cat" value="{{ $category['slug'] }}">
                        @endif
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-body" aria-hidden="true"><i class="bi bi-search"></i></span>
                            <input type="search" class="form-control" id="aide-q" name="q" value="{{ $query }}" maxlength="100" autocomplete="off" placeholder="{{ __('pages/aide.search_placeholder') }}" aria-label="{{ __('pages/aide.search_label') }}">
                            <button class="btn btn-primary" type="submit">{{ __('pages/aide.search_submit') }}</button>
                        </div>
                    </form>
                </div>
            </section>

            @if ($categories !== [])
                <nav aria-labelledby="aide-categories-titre">
                    <h2 class="visually-hidden" id="aide-categories-titre">{{ __('pages/aide.categories_title') }}</h2>
                    <ul class="row g-3 list-unstyled mb-0">
                        @foreach ($categories as $item)
                            @php($active = $category !== null && $category['id'] === $item['id'])
                            @php($color = in_array($item['color'], ['primary', 'danger', 'success', 'info', 'warning'], true) ? $item['color'] : 'primary')
                            <li class="col-6 col-lg-3">
                                <a @class(['card h-100 text-reset text-center xm-card-hover text-decoration-none', 'border-primary border-2' => $active]) href="{{ route('aide.index', ['cat' => $item['slug']]) }}"@if ($active) aria-current="true"@endif>
                                    <span class="card-body">
                                        <span class="xm-feature-icon mx-auto mb-2 bg-{{ $color }} bg-opacity-10 text-{{ $color }}" aria-hidden="true"><i class="bi {{ $item['icon'] ?: 'bi-question-circle' }}"></i></span>
                                        <span class="d-block h6 mb-1">{{ $item['label'] }}</span>
                                        <small class="text-secondary">{{ trans_choice('pages/aide.category_count', $item['count']) }}</small>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif

            <section class="card" id="questions" aria-labelledby="aide-liste-titre">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="h5 mb-0" id="aide-liste-titre">{{ $category['label'] ?? __('pages/aide.list_title') }}</h2>
                    @if ($listing->isFiltered())
                        <a class="btn btn-sm btn-light" href="{{ route('aide.index') }}"><i class="bi bi-x-lg me-1" aria-hidden="true"></i>{{ __('pages/aide.clear_filters') }}</a>
                    @endif
                </div>
                <div class="card-body">
                    @if ($listing->searched && $faqs->isNotEmpty())
                        <p class="small text-secondary" role="status">{{ trans_choice('pages/aide.results_count', $faqs->count()) }}</p>
                    @endif
                    @if ($listing->totalAll === 0)
                        <x-empty-state icon="question-circle" :title="__('pages/aide.empty_none')" compact />
                    @elseif ($faqs->isEmpty())
                        <x-empty-state icon="search" :title="__('pages/aide.empty_title', ['query' => $query])" compact>
                            {{ __('pages/aide.empty_text') }}
                            <x-slot:action>
                                <a class="btn btn-primary-soft" href="{{ route('signaler', ['tab' => 'besoin']) }}">{{ __('pages/aide.empty_contact') }}</a>
                            </x-slot:action>
                        </x-empty-state>
                    @else
                        <x-accordion id="aide-questions" :items="$items" level="h3" />
                    @endif
                </div>
                @guest
                    <div class="card-footer small text-secondary">
                        {{ __('pages/aide.account_text') }}
                        <a href="{{ route('inscription') }}">{{ __('buttons.discovery.sign_up') }}</a>
                    </div>
                @endguest
            </section>

            @include('public.aide.danger')
        </div>
    </div>
</x-layouts.public>
