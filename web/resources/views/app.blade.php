{{--
    Racine Inertia de l'espace membre et pair éducateur·rice (P6-02, D9, ADR-30 : aucun rendu côté serveur).
    - Titre : « Notes » si user_settings.neutral_tab_title (props partagées ui.neutral_title, aussi appliqué par app.ts à
      chaque visite), sinon le titre de la page (withViewData('title')) ou « Mon espace ». Attribut `inertia` obligatoire :
      le gestionnaire de tête d'Inertia remplace ce <title> à chaque visite (sans lui, il en ajoute un second et le
      navigateur garde le premier : onglet figé). Robots noindex, nofollow pour tout l'espace connecté (aucune route
      membre dans le sitemap) ; pas de balises Open Graph (partials.head, seo false).
    - Ziggy : seul le groupe « member » de config/ziggy.php est écrit (jamais les routes d'administration ni d'API).
    - #app : même attribut data-page que @inertia (inertia.use_script_element_for_initial_page figé à false), avec le
      squelette x-skeleton dedans jusqu'au montage de Vue (Vue vide le conteneur avant de monter l'application).
--}}
@php
    $neutral = (bool) data_get($page, 'props.ui.neutral_title', false);
    $site = __('seo.site_name');
    $documentTitle = $neutral
        ? __('seo.noindex.neutral')
        : (isset($title) && $title !== '' ? $title.' – '.$site : __('seo.noindex.member'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    @include('partials.head', ['seo' => false])
    <title inertia>{{ $documentTitle }}</title>
    <meta name="robots" content="noindex, nofollow">
    @routes('member', Illuminate\Support\Facades\Vite::cspNonce())
    @vite('resources/js/app.ts')
</head>
<body class="xm-member">
    <div id="app" data-page="{{ json_encode($page) }}"><div class="container py-4" data-xm-shell-skeleton><x-skeleton type="lines" :lines="6" /></div></div>
</body>
</html>
