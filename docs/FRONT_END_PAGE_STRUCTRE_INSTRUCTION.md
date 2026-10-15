---
name: xomiton-front-end-page-structure
description: Structure HTML obligatoire d'une page Xomiton — en-tête de document (title, meta, canonical, Open Graph, Twitter Card, JSON-LD, favicons), repères ARIA, hiérarchie des titres, fil d'Ariane, ordre des scripts. À charger avant de créer ou de relire le gabarit d'une page.
---

# Structure d'une page

## 1. `<head>` (ordre fixe, réf. `partials/title-meta.php` puis `partials/head-css.php`)
1. `<title>` : « {Titre de la page} – Xomiton » (≤ 60 caractères).
2. `meta charset`, `viewport`, `author`, `description` (≤ 160 caractères), `robots` (pages membres : `noindex, nofollow` ; publiques : `index, follow, max-image-preview:large`), `theme-color #0433C3`.
3. `link rel="canonical"` (paramètres conservés : `id`, `cat`, `forum`, `tab`), `manifest`.
4. **Favicons** : `favicon.ico` (16/32/48), SVG `brand/icone-appli-bleu.svg`, PNG 32 et 16, `apple-touch-icon` 180 px.
5. Open Graph (`og:site_name`, `og:locale fr_FR`, `og:type`, `og:title`, `og:description`, `og:url`, `og:image` + `og:image:alt`).
6. Twitter Card `summary_large_image` (titre, description, image, alt).
7. JSON-LD `@graph` : `Organization`, `WebSite` (+ `SearchAction`), `WebPage`, `BreadcrumbList`, + schéma propre à la page (`Article`, `FAQPage`, `Event`, `CollectionPage`).
8. Préchargement des polices (Montserrat 700 et 800), puis CSS dans l'ordre : `style.css` → `tokens.css` → `social-xomiton.css` → `components.css` → `xomiton.css`.

## 2. `<body>`
```html
<a class="xm-skip-link" href="#contenu">Aller au contenu</a>
<header>…</header>                       <!-- banner -->
<nav aria-label="Fil d'Ariane">…</nav>   <!-- pages internes publiques -->
<main id="contenu" tabindex="-1">
  <h1>…</h1>                             <!-- un seul h1 -->
  <section aria-labelledby="…"> <h2 id="…">…</h2> … </section>
</main>
<footer>…</footer>                       <!-- contentinfo -->
<!-- modales globales -->
<script src="bootstrap.bundle.min.js" defer></script>
<script src="functions.js" defer></script>
<script>window.XM = { base, csrf, user }</script>
<script src="xomiton.js" defer></script>
```

## 3. Règles
- **Un seul `h1`** par page ; `h2` par section ; `h3` dans les cartes ; aucun niveau sauté.
- Chaque section a un titre (visible ou `visually-hidden`) relié par `aria-labelledby`.
- Les listes de cartes sont des listes (`ul`/`li` ou `role="list"`) quand l'ordre ou le nombre comptent.
- Images : `alt` descriptif, `title`, `width` et `height`, `loading="lazy"` sauf image LCP (`fetchpriority="high"`).
- Formulaires : `label` lié, `fieldset`/`legend` pour les groupes, `autocomplete` correct.
- Les liens d'action sont des `<a>` ; les actions sur la page sont des `<button type="button">`.
- Langue : `<html lang="fr">` ; passages en fon ou en anglais balisés `lang="fon"` / `lang="en"`.
