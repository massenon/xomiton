{{--
    Page `/violences` (P4-04, ViolencesController ; prototype_social/violences.php). Publique et indexable (Q19 a),
    gabarit public pour tout le monde (Q18 a, sans la coquille membre du prototype), registre vous. Ordre du prototype :
    héro (SOS / Signaler / Assistant) → formes de violences (référentiel violence_types) → cycle → que faire / que ne pas
    faire → que dit la loi → auto-évaluation confidentielle (calculée dans le navigateur, rien n'est transmis).
    Image du héro (décorative, fond CSS dans le prototype) = image LCP préchargée. Fil d'Ariane visible Accueil ›
    Comprendre les violences (même liste que le BreadcrumbList du SEO, comme /lois et /do-glegbe).
    Variables : $forms (slug, label, description, icon, signs), $cycle, $questions, $check (total, lowMax, mediumMax, texts), $seo.
--}}
<x-layouts.public :seo="$seo">
    <x-slot:head>
        <link rel="preload" as="image" href="{{ asset('assets/images/xomiton/hero-13.jpg') }}" fetchpriority="high">
    </x-slot:head>

    <div class="container py-4 py-sm-5">
        <div class="vstack gap-4">
            <x-breadcrumb :items="[['label' => __('seo.breadcrumb_home'), 'url' => route('home')], ['label' => __('pages/violences.title')]]" />
            @include('public.violences.hero')
            @if ($forms !== [])
                @include('public.violences.forms')
            @endif
            @include('public.violences.cycle')
            @include('public.violences.actions')
            @include('public.violences.law')
            @include('public.violences.check')
        </div>
    </div>

    @guest
        <x-assistant-offcanvas :fab="false" />
    @endguest
</x-layouts.public>
