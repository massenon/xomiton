{{--
    Balises <head> de référencement, portées de prototype_social/partials/title-meta.php :
    titre, description, canonical, robots, Open Graph, Twitter Card, JSON-LD (Organization, WebSite, WebPage, BreadcrumbList).
    Props : title, description, image (chemin sous assets/images/xomiton/ ou URL), imageAlt, type (website|article),
            index (bool, défaut : vrai pour un·e visiteur·se, faux pour un·e membre connecté·e), breadcrumb ([[libellé, url], …]),
            schema (tableaux JSON-LD supplémentaires), modified (Y-m-d).
--}}
@props([
    'title' => null,
    'description' => null,
    'image' => 'covers/og-default.jpg',
    'imageAlt' => null,
    'type' => 'website',
    'index' => null,
    'breadcrumb' => [],
    'schema' => [],
    'modified' => null,
])
@php
    $site = __('seo.site_name');
    $pageTitle = $title ?? __('seo.default_title');
    $fullTitle = mb_stripos($pageTitle, $site) !== false ? $pageTitle : $pageTitle.' – '.$site;
    $desc = trim(preg_replace('/\s+/', ' ', strip_tags($description ?? __('seo.default_description'))));
    if (mb_strlen($desc) > 160) {
        $desc = rtrim(mb_substr($desc, 0, 157), " ,;:.").'…';
    }
    $root = rtrim(config('app.url'), '/').'/';
    $canonical = request()->url();
    $img = preg_match('#^https?://#', $image) ? $image : $root.'assets/images/xomiton/'.ltrim($image, '/');
    $imgAlt = $imageAlt ?? __('seo.default_image_alt');
    $shouldIndex = $index ?? auth()->guest();
    $robots = $shouldIndex ? 'index, follow, max-image-preview:large' : 'noindex, nofollow';

    $crumbs = [[__('seo.home'), $root]];
    // Sur la racine (accueil), le fil d'Ariane n'a qu'un élément « Accueil » (P4-01).
    $isRoot = rtrim($canonical, '/') === rtrim($root, '/');
    foreach ($breadcrumb ?: ($isRoot ? [] : [[$pageTitle, $canonical]]) as $crumb) {
        $crumbs[] = $crumb;
    }
    $list = [];
    foreach ($crumbs as $i => $crumb) {
        $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $crumb[0], 'item' => $crumb[1]];
    }
    $graph = [
        ['@type' => 'Organization', '@id' => $root.'#org', 'name' => $site, 'url' => $root, 'logo' => $root.'assets/images/xomiton/brand/icone-appli-bleu.svg', 'description' => __('seo.organization_description')],
        ['@type' => 'WebSite', '@id' => $root.'#site', 'url' => $root, 'name' => $site, 'inLanguage' => 'fr-BJ', 'publisher' => ['@id' => $root.'#org'],
            // Recherche interne (SEO_CONTENT_GUIDE_INSTRUCTION.md : WebSite + SearchAction ; prototype title-meta.php).
            'potentialAction' => ['@type' => 'SearchAction', 'target' => route('ressources.index').'?q={search_term_string}', 'query-input' => 'required name=search_term_string']],
        ['@type' => $type === 'article' ? 'Article' : 'WebPage', '@id' => $canonical.'#page', 'url' => $canonical, 'name' => $fullTitle, 'description' => $desc, 'inLanguage' => 'fr-BJ', 'isPartOf' => ['@id' => $root.'#site'], 'primaryImageOfPage' => $img] + ($modified ? ['dateModified' => $modified] : []),
        ['@type' => 'BreadcrumbList', 'itemListElement' => $list],
    ];
    foreach ($schema as $extra) {
        $graph[] = $extra;
    }
    // Construit dans le bloc PHP : « @context » serait sinon pris pour une directive Blade.
    // JSON_HEX_TAG : un contenu éditorial (FAQ, titre) contenant « </script> » ne peut pas refermer la balise.
    $jsonLd = json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
@endphp
<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $desc }}">
<meta name="robots" content="{{ $robots }}">
<link rel="canonical" href="{{ $canonical }}">
<meta property="og:site_name" content="{{ $site }}">
<meta property="og:type" content="{{ $type }}">
<meta property="og:locale" content="fr_FR">
<meta property="og:title" content="{{ $fullTitle }}">
<meta property="og:description" content="{{ $desc }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $img }}">
<meta property="og:image:alt" content="{{ $imgAlt }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $fullTitle }}">
<meta name="twitter:description" content="{{ $desc }}">
<meta name="twitter:image" content="{{ $img }}">
<meta name="twitter:image:alt" content="{{ $imgAlt }}">
<script type="application/ld+json">{!! $jsonLd !!}</script>
