{{--
    Carrefour `/violences-numeriques` (P19-03, ViolencesNumeriquesController). Publique et indexable, gabarit public, vous.
    Ordre : fil d'Ariane → h1 + chapeau + réassurance → « Besoin d’aide maintenant ? » (Signaler, SOS, urgences, BD) →
    une section par catégorie (h2) et ses sujets (h3) : publié = lien vers la fiche et la BD ; à venir = texte sobre, sans
    lien → aperçu des BD (h2) avec lien vers /bd. Variables : $categories, $comics, $comicsTotal, $breadcrumb, $seo.
--}}
<x-layouts.public :seo="$seo">
    <div class="container py-4 py-sm-5">
        <div class="vstack gap-4">
            <x-breadcrumb :items="$breadcrumb" />

            <header class="vstack gap-2">
                <h1 class="h3 mb-0">{{ __('pages/violences_numeriques.h1') }}</h1>
                <p class="lead fs-6 mb-0">{{ __('pages/violences_numeriques.lead') }}</p>
                <p class="mb-0 text-secondary"><i class="bi bi-heart text-primary me-1" aria-hidden="true"></i>{{ __('pages/violences_numeriques.reassurance') }}</p>
            </header>

            <section class="card border-primary" aria-labelledby="vn-aide-titre">
                <div class="card-body d-flex flex-column flex-lg-row gap-3 align-items-lg-center">
                    <div class="flex-grow-1">
                        <h2 class="h5 mb-1" id="vn-aide-titre">{{ __('pages/violences_numeriques.actions_title') }}</h2>
                        <p class="small text-secondary mb-0">{{ __('pages/violences_numeriques.actions_text') }}</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-primary" href="{{ route('signaler') }}"><i class="bi bi-megaphone me-1" aria-hidden="true"></i>{{ __('pages/violences_numeriques.action_report') }}</a>
                        <a class="btn btn-primary-soft" href="{{ route('sos') }}"><i class="bi bi-broadcast me-1" aria-hidden="true"></i>{{ __('pages/violences_numeriques.action_sos') }}</a>
                        <a class="btn btn-primary-soft" href="{{ route('urgences') }}"><i class="bi bi-telephone-fill me-1" aria-hidden="true"></i>{{ __('pages/violences_numeriques.action_emergency') }}</a>
                        @if ($comicsTotal > 0)
                            <a class="btn btn-outline-primary" href="{{ route('bd.index') }}"><i class="bi bi-book me-1" aria-hidden="true"></i>{{ __('pages/violences_numeriques.action_comics') }}</a>
                        @endif
                    </div>
                </div>
            </section>

            @forelse ($categories as $category)
                <section class="vstack gap-3" id="{{ $category['slug'] }}" aria-labelledby="vn-cat-{{ $category['slug'] }}">
                    <div class="d-flex flex-wrap align-items-baseline gap-2">
                        <h2 class="h5 mb-0" id="vn-cat-{{ $category['slug'] }}">
                            @if ($category['icon'])<i class="bi bi-{{ $category['icon'] }} text-primary me-2" aria-hidden="true"></i>@endif{{ $category['label'] }}
                        </h2>
                        <span class="small text-secondary">{{ trans_choice('pages/violences_numeriques.topics_count', count($category['topics']), ['count' => count($category['topics'])]) }}</span>
                    </div>
                    <ul class="list-unstyled row g-3 mb-0">
                        @foreach ($category['topics'] as $topic)
                            <li class="col-md-6 col-xl-4">
                                <div @class(['card h-100 xm-vn-topic', 'is-upcoming' => ! $topic['published']])>
                                    <div class="card-body vstack gap-2">
                                        <h3 class="h6 mb-0">
                                            @if ($topic['published'])
                                                <a href="{{ route('thematiques.show', ['slug' => $topic['slug']]) }}">{{ $topic['title'] }}</a>
                                            @else
                                                {{ $topic['title'] }}
                                            @endif
                                        </h3>
                                        <p class="small text-secondary mb-0">{{ $topic['summary'] }}</p>
                                        @if ($topic['published'])
                                            <div class="d-flex flex-wrap gap-2 mt-auto pt-1">
                                                <a class="btn btn-sm btn-primary-soft" href="{{ route('thematiques.show', ['slug' => $topic['slug']]) }}"><i class="bi bi-journal-text me-1" aria-hidden="true"></i>{{ __('pages/violences_numeriques.topic_read') }}<span class="visually-hidden"> : {{ $topic['title'] }}</span></a>
                                                @if ($topic['comic'])
                                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('bd.show', ['key' => $topic['comic']]) }}"><i class="bi bi-book me-1" aria-hidden="true"></i>{{ __('pages/violences_numeriques.topic_comic') }}<span class="visually-hidden"> : {{ $topic['title'] }}</span></a>
                                                @endif
                                            </div>
                                        @else
                                            <p class="small mb-0 mt-auto"><x-badge color="neutral" soft icon="hourglass-split">{{ __('pages/violences_numeriques.topic_upcoming') }}</x-badge> <span class="text-secondary">{{ __('pages/violences_numeriques.topic_upcoming_help') }}</span></p>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @empty
                <x-empty-state :title="__('pages/violences_numeriques.empty')" icon="journal-text" />
            @endforelse

            @if ($comics->isNotEmpty())
                <section class="vstack gap-3" aria-labelledby="vn-bd-titre">
                    <div>
                        <h2 class="h5 mb-1" id="vn-bd-titre">{{ __('pages/violences_numeriques.comics_title') }}</h2>
                        <p class="small text-secondary mb-0">{{ __('pages/violences_numeriques.comics_text') }}</p>
                    </div>
                    <ul class="list-unstyled row g-3 mb-0">
                        @foreach ($comics as $comic)
                            <li class="col-6 col-lg-3">@include('public.bd.card', ['comic' => $comic, 'heading' => 'h3'])</li>
                        @endforeach
                    </ul>
                    <p class="mb-0"><a href="{{ route('bd.index') }}">{{ __('pages/violences_numeriques.comics_link') }}</a></p>
                </section>
            @endif
        </div>
    </div>
</x-layouts.public>
