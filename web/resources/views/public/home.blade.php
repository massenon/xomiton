{{--
    Page d'accueil `/` (P4-01, HomeController) : les 11 sections de prototype_social/index.php dans l'ordre figé de
    FRONT_END_SECTION_ORDER_INSTRUCTION.md (Q16) — la 11ᵉ, le pied de page #newsletter, vient du gabarit public.
    Image LCP (05.jpg) préchargée ; aucun style en ligne (accueil.css) ; JSON-LD FAQPage via x-seo.
    Variables : $thematiques, $faqs, $faqItems, $faqSchema.
--}}
<x-layouts.public
    :seo="[
        'title' => __('seo.pages.home.title'),
        'description' => __('seo.pages.home.description'),
        'index' => true,
        'breadcrumb' => [],
        'schema' => array_values(array_filter([$faqSchema])),
    ]"
    pub-nav="accueil"
>
    <x-slot:head>
        <link rel="preload" as="image" type="image/webp" href="{{ asset('assets/images/xomiton/05.webp') }}" fetchpriority="high">
    </x-slot:head>

    @include('public.home.hero')
    @include('public.home.valeurs')
    @include('public.home.questions')
    @include('public.home.reseau')
    @include('public.home.ressources')
    @include('public.home.alerte')
    @include('public.home.experts')
    @include('public.home.do-glegbe')
    @if ($faqs->isNotEmpty())
        @include('public.home.faq')
    @endif
    @include('public.home.cta')

    <x-assistant-offcanvas />
</x-layouts.public>
