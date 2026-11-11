{{--
    Fiche publique `/ressources/{slug}` (P4-03, RessourcesShowController). Sans prototype (ressource.php redirigeait vers
    thematique.php) : mise en page de thematique.php l.39-108 en grille publique col-lg-8 / col-lg-4 (Q18, sans sidenav).
    Ordre : fil d'Ariane → article (couverture LCP préchargée, badges, h1, méta, résumé, action principale : télécharger,
    ouvrir le lien ou lire le média, corps / transcription, mention juridique) ; colonne : lecture hors connexion,
    thématique liée, ressources de la même catégorie, « Besoin d’aide ? ». Registre vous.
    Variables : $resource, $category (listée ou null), $thematique, $related, $breadcrumb, $downloadUrl, $streamUrl,
                $offlineFile, $fileSize, $showLegalNotice, $offlineEnabled, $offlineOptions, $fmt, $seo.
--}}
<x-layouts.public :seo="$seo" pub-nav="ressources">
    <x-slot:head>
        <link rel="preload" as="image" href="{{ $resource->cover_url }}" fetchpriority="high">
    </x-slot:head>

    <div class="container py-4 py-sm-5" @if ($offlineEnabled) x-data="xmOfflineLibrary(@js($offlineOptions))" @endif>
        <div class="row g-4">
            <div class="col-lg-8 vstack gap-4">
                <x-breadcrumb :items="$breadcrumb" />
                @include('public.ressources.show.article')
            </div>
            <aside class="col-lg-4" aria-label="{{ __('pages/ressources.aside_label') }}">
                <div class="row g-4">
                    @if ($offlineEnabled)
                        <div class="col-sm-6 col-lg-12">
                            @include('public.ressources.show.hors-ligne')
                        </div>
                    @endif
                    @if ($thematique)
                        <div class="col-sm-6 col-lg-12">
                            @include('public.ressources.show.thematique')
                        </div>
                    @endif
                    @if ($related->isNotEmpty())
                        <div class="col-sm-6 col-lg-12">
                            @include('public.ressources.show.liees')
                        </div>
                    @endif
                    <div class="col-sm-6 col-lg-12">
                        @include('public.ressources.show.aide')
                    </div>
                </div>
            </aside>
        </div>
    </div>
</x-layouts.public>
