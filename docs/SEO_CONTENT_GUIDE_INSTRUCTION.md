---
name: xomiton-seo-content-guide
description: Guide SEO éditorial de Xomiton — titres, métadonnées, slugs, mots-clés, structure Hn, maillage interne, textes alternatifs des images, contenus à ne pas indexer. À charger avant de rédiger ou publier une page publique, une fiche thématique ou une ressource.
---

# Guide SEO des contenus

## 1. Ce qui est indexé
- **Indexé** : accueil, thématiques, ressources, violences, lois, Do Glegbe, aide, urgences, signaler (page d'entrée), événements publics.
- **Non indexé** (`noindex, nofollow`) : tout l'espace connecté, l'administration, les pages de newsletter (confirmation, désinscription), l'OTP, les résultats de recherche interne, les pages de remerciement.
- `robots.txt` bloque `/admin`, `/api`, `/newsletter.php?`, `/storage` ; le sitemap ne liste que les pages indexées.

## 2. Titre (`<title>`)
- Format : « {Sujet précis} – Xomiton », 50 à 60 caractères, mot-clé principal au début.
- Unique par page ; jamais « Accueil » seul.

## 3. Méta description
- 140 à 160 caractères, bénéfice + action (« Découvrez vos droits face aux violences au Bénin et où trouver de l'aide, gratuitement et en toute confidentialité. »).
- Unique par page ; pas de guillemets doubles.

## 4. Slug
- Minuscules, sans accents, mots séparés par des tirets, ≤ 5 mots : `/thematiques/droits-des-femmes-benin`.
- Stable : un changement impose une redirection 301.

## 5. Titres Hn
- Un `h1` contenant le mot-clé principal (formulé naturellement).
- `h2` pour chaque section (questions que se pose la personne : « Que faire en cas de violence ? »).
- `h3` dans les cartes et sous-sections ; aucun niveau sauté.

## 6. Texte optimisé
- 300 mots minimum pour une fiche thématique ; réponse directe dans les 2 premières phrases.
- Mot-clé principal dans le premier paragraphe, variantes et synonymes ensuite ; jamais de bourrage.
- Listes, tableaux et FAQ pour les réponses courtes (extraits enrichis).

## 7. Maillage interne
- Chaque fiche renvoie vers : 2 fiches liées, la ressource principale, la page d'aide adaptée (signaler, urgences, assistant).
- Ancres descriptives (« connaître les recours juridiques »), jamais « cliquez ici ».
- Fil d'Ariane sur toutes les pages internes (avec `BreadcrumbList`).
- Aucune page orpheline : toute page indexée est accessible en ≤ 3 clics depuis l'accueil.

## 8. Images
- `alt` qui décrit ce que l'image apporte (« Quatre jeunes femmes tenant un drapeau arc-en-ciel lors d'un atelier ») ; décoratives : `alt=""`.
- Nom de fichier descriptif (`atelier-affirmation-de-soi-porto-novo.webp`).
- `title` conservé (choix du prototype), `width`/`height` renseignés, WebP/AVIF + repli.
- Image Open Graph 1200 × 630 par page clé.

## 9. Données structurées
`Organization`, `WebSite` + `SearchAction`, `WebPage`, `BreadcrumbList` partout ; `Article` (fiches), `FAQPage` (FAQ), `Event` (événements publics), `CollectionPage` (listes). Détail et grille : [FRONT_END_SEO_PERFECT_AUDIT.md](FRONT_END_SEO_PERFECT_AUDIT.md).

## 10. Contenus sensibles
Aucun témoignage identifiable indexé ; les témoignages publics sont anonymisés et consentis. Pas de mots-clés exploitant la détresse (« victime de viol » en titre racoleur).
