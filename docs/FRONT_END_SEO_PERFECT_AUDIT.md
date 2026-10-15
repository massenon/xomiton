---
name: xomiton-seo-perfect-audit
description: Grille d'audit SEO page par page de Xomiton — meta title, meta description, slug, H1/H2/H3, texte optimisé, FAQ SEO, Schema.org JSON-LD, Open Graph, Twitter Card, fil d'Ariane, canonical, robots, performance. À charger pour auditer ou valider une page publique avant mise en ligne.
---

# Audit SEO « parfait »

## 1. Grille par page (toutes doivent être ✅ avant mise en ligne)

| # | Critère | Règle | Où le vérifier |
|---|---|---|---|
| 1 | **Meta Title** | 50–60 car., unique, mot-clé au début, « – Xomiton » | `<title>` |
| 2 | **Meta Description** | 140–160 car., unique, bénéfice + action | `meta[name=description]` |
| 3 | **Slug** | Court, sans accents, stable, mot-clé | URL |
| 4 | **H1** | Un seul, mot-clé principal | DOM |
| 5 | **H2** | Une par section, questions de l'utilisateur·rice | DOM |
| 6 | **H3** | Sous-sections et cartes, aucun niveau sauté | DOM |
| 7 | **Texte optimisé** | ≥ 300 mots (fiches), réponse directe en tête, synonymes | Contenu |
| 8 | **FAQ SEO** | 3 à 6 questions réelles, réponses ≤ 60 mots | Section FAQ |
| 9 | **Schema.org / JSON-LD** | `@graph` valide (Organization, WebSite, WebPage, BreadcrumbList + type de page) | Test des résultats enrichis |
| 10 | **Breadcrumb** | Visible + `BreadcrumbList` identique | DOM + JSON-LD |
| 11 | **Open Graph** | `og:title`, `og:description`, `og:url`, `og:image` (1200×630) + `og:image:alt`, `og:locale fr_FR`, `og:type` | Débogueur de partage |
| 12 | **Twitter Card** | `summary_large_image`, titre, description, image, alt | Validateur |
| 13 | **Canonical** | Absolu, sans paramètres de suivi | `link[rel=canonical]` |
| 14 | **Robots** | `index, follow` (public) / `noindex` (connecté, admin, newsletter) | `meta[name=robots]` |
| 15 | **Images** | `alt`, `title`, `width/height`, WebP/AVIF, `loading=lazy` sauf LCP | DOM |
| 16 | **Maillage** | ≥ 3 liens internes descriptifs, aucun lien cassé | Crawl |
| 17 | **Langue** | `<html lang="fr">`, `og:locale` | DOM |
| 18 | **Performance** | LCP < 2,5 s, CLS < 0,1, INP < 200 ms | Lighthouse / CrUX |
| 19 | **Mobile** | Aucun défilement horizontal, cibles 44 px | 360 px |
| 20 | **Accessibilité** | Score Lighthouse ≥ 95, 0 erreur axe critique | Lighthouse, axe |
| 21 | **Favicon et manifeste** | ICO, SVG, PNG 16/32, apple-touch 180, manifeste avec icônes 192/512/maskable | `<head>` |
| 22 | **Sitemap** | Page présente dans `sitemap.xml` avec `lastmod` | Sitemap |

## 2. Modèles JSON-LD

**Fiche thématique (`Article`)**
```json
{ "@type": "Article", "headline": "Tes droits au Bénin", "description": "…", "inLanguage": "fr-BJ",
  "author": { "@id": "https://xomiton.org/#org" }, "publisher": { "@id": "https://xomiton.org/#org" },
  "datePublished": "2026-01-15", "dateModified": "2026-10-15", "image": ["…/og/droits.jpg"],
  "mainEntityOfPage": { "@id": "https://xomiton.org/thematiques/droits-des-femmes-benin#page" } }
```

**FAQ (`FAQPage`)**
```json
{ "@type": "FAQPage", "mainEntity": [ { "@type": "Question", "name": "Mon signalement est-il vraiment anonyme ?",
  "acceptedAnswer": { "@type": "Answer", "text": "Oui. Tu peux signaler sans compte ni nom…" } } ] }
```

**Événement public (`Event`)**
```json
{ "@type": "Event", "name": "Atelier « Dire non sans culpabiliser »", "startDate": "2026-10-17T15:00+01:00",
  "eventAttendanceMode": "https://schema.org/MixedEventAttendanceMode", "location": [{ "@type": "Place", "name": "Porto-Novo" }, { "@type": "VirtualLocation", "url": "…" }],
  "organizer": { "@id": "https://xomiton.org/#org" } }
```
L'adresse exacte d'un lieu sensible n'est jamais publiée.

## 3. Pages et types attendus
| Page | Type Schema.org | FAQ |
|---|---|---|
| Accueil | WebSite + Organization + FAQPage | Oui |
| Thématique | Article | Oui (3–5) |
| Liste des ressources | CollectionPage | Non |
| Aide | FAQPage | Oui |
| Événement public | Event | Non |
| Urgences | WebPage | Non |

## 4. Rapport d'audit
Pour chaque page : URL, tableau des 22 critères (✅ / ❌ + correction), captures Lighthouse mobile, date, auteur·e. Les corrections sont faites dans le composant `<x-seo>` ou dans le contenu, jamais en dur dans une page.
