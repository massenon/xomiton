{{--
    Fiche publique `/thematiques/{slug}` (P4-02, ThematiquesShowController ; prototype_social/thematique.php l.36-110).
    Ordre (FRONT_END_SECTION_ORDER_INSTRUCTION.md) : fil d'Ariane → image → catégorie + durée → h1 → résumé →
    avertissement → sections en accordéon → FAQ ; colonne latérale : contacts publics + « J’ai besoin d’aide »,
    ressource principale, thématiques liées. Grille col-lg-8 / col-lg-4 (gabarit public, sans sidenav membre : Q18).
    Couverture = image LCP préchargée. Variables : $thematique, $category, $breadcrumb, $sections, $contacts, $related,
    $primaryResource, $resourceSize, $faqItems, $showAssistant, $seo.
--}}
<x-layouts.public :seo="$seo" pub-nav="thematiques">
    <x-slot:head>
        <link rel="preload" as="image" href="{{ $thematique->cover_url }}" fetchpriority="high">
    </x-slot:head>

    <div class="container py-4 py-sm-5">
        <div class="row g-4">
            <div class="col-lg-8 vstack gap-4">
                <x-breadcrumb :items="$breadcrumb" />
                @include('public.thematiques.show.article')
            </div>
            <aside class="col-lg-4" aria-label="{{ __('pages/thematiques.aside_label') }}">
                <div class="row g-4">
                    <div class="col-sm-6 col-lg-12">
                        @include('public.thematiques.show.aide')
                    </div>
                    @if ($primaryResource)
                        <div class="col-sm-6 col-lg-12">
                            @include('public.thematiques.show.ressource')
                        </div>
                    @endif
                    @if ($related->isNotEmpty())
                        <div class="col-sm-6 col-lg-12">
                            @include('public.thematiques.show.liees')
                        </div>
                    @endif
                </div>
            </aside>
        </div>
    </div>
</x-layouts.public>
