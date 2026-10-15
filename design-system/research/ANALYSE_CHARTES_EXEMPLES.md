# Analyse des chartes d'exemple (`C:\Projects\xomiton_laravel\chartegraphique`)

Sept PDF ont été extraits (texte) et leurs sommaires lus. Ce document ne retient que ce qui a été **observé** dans ces extraits ; les chartes complètes (visuels, planches) n'ont pas été relues page à page.

| Charte | Ce qu'on y observe (sommaire/texte lu) | Ce qu'on reprend pour Xomiton |
|---|---|---|
| **Human First v3.0** (bien-être, diversité, inclusion) | Une **règle qui prime** sur les autres ; chaque chapitre = sens → spécification → interdits ; **valeurs chiffrées opposables** (contrastes, tailles minimales) ; livrables numériques `tokens.css`, `tailwind.config.js`, `MASTER.md`, `BRAND_GUIDE.md` ; chapitre de **gouvernance** pour les cas non prévus | **Modèle principal** : règle qui prime (« la sécurité avant l'esthétique »), contrastes calculés et opposables, ordre sens/spec/interdits, fichiers `tokens.css` + `MASTER.md`, gouvernance. (On n'a pas repris Tailwind.) |
| **Data Terra 2019** | Sommaire : logotype (déclinaisons, couleurs, typographie, **interdits**, zone de protection, taille minimale) · éléments graphiques (fonds, iconographie, **data visualisation**) · supports ; couleurs en CMJN/RVB/HEXA | Interdits du logo, zone de protection et taille minimale, couleurs en plusieurs espaces (à compléter en CMJN pour l'impression), chapitre data-visualisation |
| **CPNEF audiovisuel 2024** | Logotype et sa signification (pentagone = cinq filières), version avec acronyme développé, utilisation, couleurs, typographies, **traitement des photos**, principe graphique, interdits, outils (papier à lettre, carte, signature e-mail, couverture), exemples (print, en ligne, infographies) | Explication de la **signification du logo** (rosace = personnes réunies), traitement des photos, déclinaisons d'outils (signature e-mail, couverture de rapport) |
| **BlaBlaCar Master Guidelines (light) 2023** | Architecture de marque, **signification du logo** (guillemet = début de conversation), couleur, typographie, photographie, illustration, déclinaison produit « Daily » | Règles d'illustration (style plat inclusif), photographie, cohérence entre marque mère et produits (site, PWA, app) |
| **CFDT 2013** | Identité construite par **entretiens** avec les parties prenantes ; couleur identitaire unique (orange) ; forme ronde = humanité et mouvement ; **signature** simple ; déclinaisons édition et vidéo | Construire/valider la charte avec des membres de l'équipe et de la communauté ; une couleur de marque claire ; envisager une signature (« Soutenir, informer, protéger ») |
| **Instacart 2022** | Cadre « recette du design system » ; « ingrédients » : logo, couleur, typographie, photographie, illustration, iconographie, layout, **motion**, applications | Ordre des chapitres du design system (logo → couleur → typo → photo → illustration → icônes → layout → mouvement → applications) |
| **Olympic Brand Guidelines 2025** | Symbole, couleur, système de logo, typographie (titre, sérif, règles d'alignement, veuves/orphelines), éléments graphiques, illustration, pictogrammes, photographie (**représenter la diversité**), **applications numériques** (grilles, espacement, empilement des composants), infographies, cartes | Règles de typographie détaillées, **représentation de la diversité** en photographie, grilles et espacement numériques, pictogrammes, cartes |

## Enseignements transversaux
1. Les chartes solides **disent pourquoi** avant de dire comment (principe repris dans chaque chapitre).
2. Les **interdits** et la **zone de protection** du logo sont systématiques.
3. La **signification du logo** est expliquée : à rédiger avec le client pour la rosace rouge et le symbole de genre.
4. Les chartes récentes livrent des **fichiers de tokens**, pas seulement un PDF : c'est le choix fait ici (`tokens/`).
5. La **data-visualisation** et les **cartes** ont leur propre chapitre : repris dans `xomiton_implementation/06 §10`.
6. Les écarts avec Xomiton : aucune de ces chartes ne traite la **sécurité d'usagères en danger** (sortie rapide, SOS, anonymat) : ces règles sont propres à Xomiton et inscrites dans la charte §1, §9 et dans `xomiton/pages/signaler-et-sos.md`.

## Méthode ui-ux-pro-max appliquée
- Recherche du système de design (`--design-system`) : le moteur propose un motif de page « Feature-Rich Showcase » et un style « Vibrant & Block-based » ; sa palette (rose/bleu) et ses polices (Space Grotesk/Inter) ne correspondent pas à la marque : **écartées**.
- Retenues : règles d'accessibilité WCAG 2.2 issues de la base UX (authentification accessible, résumé d'erreurs focalisable, focus non masqué par un header collant), règle Laravel (composants Blade anonymes pour les primitives, Alpine pour l'état visuel local, Livewire `lazy` pour les composants lourds), cadrage par curseurs (variance, mouvement, densité) utilisé dans `pages/*.md`.
- Méthode de structuration : fichier MASTER + surcharges par page (`design-system/<projet>/MASTER.md` + `pages/*.md`), architecture de tokens en trois couches (primitif → sémantique → composant).
