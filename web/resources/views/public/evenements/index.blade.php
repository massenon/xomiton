{{--
    Liste publique `/evenements` (P8-04, Q35 a ; EventsPageController::index pour un·e visiteur·se ; prototype events.php).
    Gabarit public, registre vous, indexable. Seuls les événements **publics** à venir (`events.is_public`, hors groupe
    privé) ; ville ou « En ligne », jamais l'adresse exacte ni le lien de connexion. Ordre du prototype : h1 + introduction
    + puces de catégorie → grille #liste → « Charger plus » (lien serveur rel=nofollow, amélioré par load-more.js) ;
    colonne : encart « Participer » (connexion, inscription).
    Variables : $categories, $category, $events, $total, $nextUrl, $fmt, $seo.
--}}
<x-layouts.public :seo="$seo">
    <div class="container py-4 py-sm-5">
        <div class="vstack gap-4">
            <div class="d-flex flex-wrap align-items-end justify-content-between gap-2">
                <div>
                    <h1 class="h3 mb-1">{{ __('pages/evenements.title') }}</h1>
                    <p class="text-secondary mb-0">{{ __('pages/evenements.intro') }}</p>
                </div>
                @if ($categories !== [])
                    <nav aria-label="{{ __('pages/evenements.filters_label') }}">
                        <ul class="list-unstyled d-flex flex-wrap gap-1 mb-0">
                            <li><a @class(['btn btn-sm', 'btn-primary' => ! $category, 'btn-light' => (bool) $category]) href="{{ route('evenements.index') }}"@if (! $category) aria-current="true"@endif>{{ __('pages/evenements.filter_all') }}</a></li>
                            @foreach ($categories as $item)
                                @php($active = $category !== null && $category['slug'] === $item['slug'])
                                <li><a @class(['btn btn-sm', 'btn-primary' => $active, 'btn-light' => ! $active]) href="{{ route('evenements.index', ['cat' => $item['slug']]) }}"@if ($active) aria-current="true"@endif>{{ $item['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                @endif
            </div>

            <div class="row g-4">
                <div class="col-lg-8 vstack gap-4">
                    <section id="liste" aria-labelledby="evenements-liste-titre">
                        <h2 class="visually-hidden" id="evenements-liste-titre">{{ __('pages/evenements.list_title') }}</h2>
                        @if ($events->isEmpty())
                            <div class="card">
                                @if ($category === null)
                                    <x-empty-state icon="calendar-event" :title="__('pages/evenements.empty')" />
                                @else
                                    <x-empty-state icon="calendar-event" :title="__('pages/evenements.empty_category')">
                                        <x-slot:action>
                                            <a class="btn btn-primary-soft" href="{{ route('evenements.index') }}">{{ __('pages/evenements.show_all') }}</a>
                                        </x-slot:action>
                                    </x-empty-state>
                                @endif
                            </div>
                        @else
                            <ul class="row g-4 list-unstyled mb-0" data-xm-items>
                                @foreach ($events as $event)
                                    @include('public.evenements.card', ['event' => $event])
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    @if ($nextUrl)
                        <div class="xm-load-more" data-xm-more>
                            <a class="btn btn-primary-soft px-4" href="{{ $nextUrl }}" rel="nofollow">{{ __('pages/evenements.load_more') }}</a>
                        </div>
                    @endif
                </div>

                @guest
                    <aside class="col-lg-4 vstack gap-4" aria-label="{{ __('pages/evenements.join_title') }}">
                        @include('public.evenements.join')
                    </aside>
                @endguest
            </div>
        </div>
    </div>
</x-layouts.public>
