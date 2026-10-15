---
name: xomiton-final-design-direction
description: Direction de design finale de Xomiton — l'implémentation reprend 100 % du prototype prototype_social (structure, ordre des sections, espacements, conteneurs, typographie, boutons, images, CTA, animations, responsive, HTML, CSS, tunnel). À charger avant de créer ou modifier tout écran web.
---

# Direction de design finale

**Nous gardons 100 % de `prototype_social/` pour l'implémentation.** Le prototype est la maquette de référence : l'implémentation Laravel / Livewire / Vue en reproduit le rendu et le comportement à l'identique, avec les données réelles.

## Ce qui doit rester identique

| # | Élément | Règle vérifiable |
|---|---|---|
| 1 | Structure des pages | Même hiérarchie de blocs (`header` › `main` › sections › `footer`), mêmes repères ARIA |
| 2 | Ordre des sections | Celui de [FRONT_END_SECTION_ORDER_INSTRUCTION.md](FRONT_END_SECTION_ORDER_INSTRUCTION.md), aucune section déplacée |
| 3 | Espacements | Classes utilitaires Bootstrap du prototype (`py-4 py-sm-5`, `g-4`, `mb-3`…) et jetons `--xm-space-*` |
| 4 | Conteneurs | `container` / `container-fluid` et grille (`col-lg-3` · `col-md-8 col-lg-6` · `col-lg-3` pour l'espace membre) |
| 5 | Hiérarchie typographique | Un seul `h1` par page, mêmes niveaux `h2`/`h3`, Montserrat pour les titres, Arial pour le texte |
| 6 | Position des boutons | Mêmes emplacements (en-tête de carte, pied de modale : Annuler à gauche, action à droite) |
| 7 | Placement des images | Mêmes ratios (`xm-ratio-4x3`, `card-img`), mêmes recadrages, `alt` et `title` conservés |
| 8 | Placement des CTA | Mêmes CTA aux mêmes endroits (hero, fin de section, CTA final), libellés de [CTA_LIBRARY.md](CTA_LIBRARY.md) |
| 9 | Animations | Seulement celles du prototype (survol de carte −2/−3 px, pulsation SOS, transitions Bootstrap), désactivées en mouvement réduit |
| 10 | Comportement responsive | Points de rupture et transformations de [FRONT_END_RESPONSIVE.md](FRONT_END_RESPONSIVE.md) |
| 11 | Structure HTML | Mêmes classes du thème Social et classes `xm-*` ; les composants Blade/Vue produisent ce HTML |
| 12 | Mise en page CSS | `style.css` (Social) jamais modifié ; surcharges uniquement dans `tokens.css` → `social-xomiton.css` → `xomiton.css` |
| 13 | Psychologie de conversion | Réassurance avant demande (confidentialité, gratuité, anonymat), une action principale par écran |
| 14 | Tunnel | Accueil → création de compte → OTP → fil ; accueil → signalement anonyme sans compte ; SOS accessible partout |
| 15 | Cohérence | Mêmes cartes, modales CRUD, DataTables, filtres, barres de recherche et KPI dans tous les modules |

## Ce qui peut changer

- Les données fictives deviennent réelles ; les compteurs codés en dur (« 24 posts », « 182 abonné·es ») sont calculés.
- Les accès aux fichiers JSON (`store_all`, `store_append`…) deviennent des requêtes Eloquent.
- Les éléments retirés par décision ne reviennent pas : gestion de stock, passerelles réseaux sociaux (D4), sortie rapide (D8).
- Les écarts au design system relevés pendant l'implémentation sont corrigés **dans le design system d'abord**, puis appliqués partout.

## Procédure de vérification « à l'identique »

1. Ouvrir la page du prototype et la page implémentée côte à côte aux largeurs 360, 768, 1024 et 1440 px.
2. Comparer : ordre des sections, titres, CTA, images, espacements (tolérance 4 px), états de survol et de focus.
3. Faire une capture de chaque largeur et la joindre à la demande de fusion.
4. Tout écart volontaire est justifié dans la demande de fusion et consigné dans `design-system/DECISIONS.md`.
