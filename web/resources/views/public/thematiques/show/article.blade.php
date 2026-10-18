{{--
    Article de la fiche (prototype thematique.php l.47-79) : couverture (LCP), badge de catégorie, h1, durée,
    un seul chapeau — celui qui ouvre le corps s'il existe (intro spécifique), sinon le résumé —, avertissement de
    contenu (`content_warning`, lien SOS), corps éditorial et sections `[{title, body}]` (HTML nettoyé à l'écriture :
    App\Casts\PurifiedHtml / PurifiedSections) en accordéon (premier ouvert, sans icônes : non stockées, plan §7),
    mention juridique, FAQ.
--}}
<article class="card overflow-hidden" id="fiche" aria-labelledby="fiche-titre">
    <div class="xm-ratio-16x9">
        <x-img class="xm-img-cover" :src="$thematique->cover_url" :alt="__('pages/thematiques.cover_alt', ['title' => $thematique->title])" width="1200" height="800" priority />
    </div>
    <div class="card-body">
        @if ($category)
            <x-badge :color="$category->color ?: 'primary'" soft :icon="$category->icon ? \Illuminate\Support\Str::after($category->icon, 'bi-') : null" class="mb-2">{{ $category->label }}</x-badge>
        @endif
        <h1 class="h4" id="fiche-titre">{{ $thematique->title }}</h1>
        <p class="small text-secondary"><i class="bi bi-clock me-1" aria-hidden="true"></i>{{ trans_choice('pages/thematiques.reading_minutes', (int) $thematique->reading_minutes) }} · {{ __('pages/thematiques.kicker') }}</p>
        @unless ($thematique->bodyOpensWithLead())
            <p class="lead fs-6">{{ $thematique->summary }}</p>
        @endunless

        @if ($thematique->content_warning)
            <x-alert type="warning" icon="exclamation-triangle" class="mb-3">
                {{ $thematique->content_warning }}
                <a href="{{ route('sos') }}" class="alert-link ms-1">{{ __('pages/thematiques.warning_sos_link') }}</a>
            </x-alert>
        @endif

        @if ($thematique->body)
            <div class="xm-prose xm-th-body">{!! $thematique->body !!}</div>
        @endif

        @if ($sections !== [])
            <x-accordion id="fiche-sections" class="mt-3" level="h2" :open="0" :items="$sections" />
        @endif

        <p class="small text-secondary mt-3 mb-0">{{ __('pages/thematiques.legal_notice') }}</p>

        @if ($faqItems !== [])
            @include('public.thematiques.show.faq')
        @endif
    </div>
</article>
