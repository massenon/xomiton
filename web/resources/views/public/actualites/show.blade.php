{{--
    Fiche publique `/actualites/{slug}` (P4-10, ActualitesShowController ; prototype_social/blog-details.php). Gabarit
    public pour tout le monde (Q18 a), grille col-lg-8 / col-lg-4 sans sidenav. Ordre du prototype : fil d'Ariane
    (Accueil › Actualités › Catégorie › titre, même source que le BreadcrumbList) → article (couverture LCP préchargée,
    badge, h1, méta, corps, avertissement SOS / urgences, retour et partage) ; colonne « À lire aussi ». Registre vous.
    Variables : $article, $category (listée ou null), $others, $minutes, $breadcrumb, $fmt, $seo.
--}}
<x-layouts.public :seo="$seo" pub-nav="blog">
    <x-slot:head>
        <link rel="preload" as="image" href="{{ $article->cover_url }}" fetchpriority="high">
    </x-slot:head>

    <div class="container py-4 py-sm-5">
        <div class="row g-4">
            <div class="col-lg-8 vstack gap-4">
                <x-breadcrumb :items="$breadcrumb" />
                @include('public.actualites.show.article')
            </div>
            @if ($others->isNotEmpty())
                <aside class="col-lg-4" aria-label="{{ __('pages/actualites.aside_label') }}">
                    @include('public.actualites.show.others')
                </aside>
            @endif
        </div>
    </div>
</x-layouts.public>
