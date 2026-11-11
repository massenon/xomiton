{{-- Section 5 — Ressources #ressources (prototype index.php l.238-264) : les six premières fiches thématiques publiées (Q19 a : publiques). --}}
<section class="lp-section" id="ressources" aria-labelledby="ressourcesTitre">
    <div class="container">
        <div class="text-center mx-auto mb-5 lp-intro-700">
            <div class="lp-eyebrow mb-2">{{ __('pages/accueil.ressources.eyebrow') }}</div>
            <h2 id="ressourcesTitre" class="lp-title">{{ __('pages/accueil.ressources.title') }}</h2>
            <p class="text-secondary">{{ __('pages/accueil.ressources.text') }}</p>
        </div>
        @if ($thematiques->isEmpty())
            <x-empty-state icon="journal-text" :title="__('pages/accueil.ressources.empty')">
                <x-slot:action>
                    <x-button variant="primary-soft" :href="route('thematiques.index')">{{ __('buttons.discovery.explore_themes') }}</x-button>
                </x-slot:action>
            </x-empty-state>
        @else
            <ul class="row g-4 list-unstyled mb-0">
                @foreach ($thematiques as $thematique)
                    @php($category = $thematique->category)
                    <li class="col-md-6 col-lg-4">
                        <a class="lp-card lp-th d-block bg-body text-reset overflow-hidden h-100" href="{{ route('thematiques.show', $thematique) }}">
                            <x-img class="w-100" :src="$thematique->cover_url" :alt="__('pages/accueil.ressources.cover_alt', ['title' => $thematique->title])" :title="$thematique->title" width="640" height="427" sizes="(min-width: 992px) 380px, (min-width: 768px) 50vw, 100vw" />
                            <div class="p-3">
                                <x-badge :color="$category?->color ?: 'primary'" soft :icon="$category?->icon ? \Illuminate\Support\Str::after($category->icon, 'bi-') : 'grid'" class="mb-2">{{ $category?->label ?? __('pages/accueil.ressources.default_category') }}</x-badge>
                                <h3 class="h6 mb-1">{{ $thematique->title }}</h3>
                                <p class="small text-secondary mb-2">{{ $thematique->summary }}</p>
                                <span class="small fw-bold text-primary d-inline-flex align-items-center"><i class="bi bi-arrow-right me-1" aria-hidden="true"></i>{{ __('buttons.discovery.read_more') }}</span>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="text-center mt-4">
            <x-button variant="primary-soft" :href="route('actualites.index')">{{ __('buttons.discovery.read_news') }} <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></x-button>
        </div>
    </div>
</section>
