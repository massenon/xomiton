# Design system Xomiton — dossier de construction

Version 1.0 · 8 octobre 2026. Charte graphique et design system de Xomiton, à construire avec **Bootstrap 5 (thème Social) + Laravel + Livewire + Alpine**.

## Ouvrir en premier
1. **`Charte-graphique-Xomiton-v1.0.pdf`** : la charte graphique, 125 pages A4 paysage, qui suit le sommaire de `INSTRUCTION-CHARTE-GRAPHIQUE.txt` (14 chapitres : les 11 du sommaire, plus 12 Supports de communication et 13 Data visualisation ajoutés à la demande, puis Contact). PDF balisé (accessible), en français, avec 119 signets.
2. **`preview/index.html`** : aperçu vivant des composants (palette, typographie, boutons, cartes, formulaire, SOS, modale, thème sombre).
3. `DECISIONS.md` : D2, D4 (révisée par D4-bis), D5, D6, D7, D8, D9 actées, avec conséquences (dont hébergement cPanel et sécurité).

## Contenu
| Chemin | Rôle |
|---|---|
| `Charte-graphique-Xomiton-v1.0.pdf` | Charte graphique complète (A4 paysage, 125 pages) |
| `charte-pdf/` | Sources du PDF : `build.py` (assemblage et rendu), `structure.py` (sommaire), `kit.py` (mise en page), `front.py`, `ch01_03.py` à `ch10_12.py` (contenu par chapitre). Régénérer : `python build.py` |
| `logo/` | Logos vectoriels (14 SVG) et PNG haute définition (28 fichiers, jusqu'à 6000 px) ; `build_logo.py` (vectorisation), `render_png.py` (rendu) |
| `blade/` | **Composants Blade `x-*` implémentés** (19 : button, field, error-summary, card, img, urgency, status, alert, modal, breadcrumb+JSON-LD, accordion, tabs, stat, sos-button, toast, empty-state, stepper, avatar, logo), `js/xomiton-ui.js` (Alpine : SOS), `styleguide.blade.php` (catalogue vivant selon le sommaire de la charte), `render-test.php` (rendu avec le vrai Blade de `xomiton/vendor` ; `cd xomiton && php ../design-system/blade/render-test.php`) |
| `tokens/components.css` | Styles des composants absents du thème Social (stepper, avatar, statut violet, modale, `x-cloak`) |
| `fonts/` | Montserrat (WOFF2 variable, latin, latin étendu, vietnamien ; licence SIL OFL) |
| `tokens/design-tokens.json` | Jetons en 3 couches (primitif → sémantique → composant), source pour Flutter |
| `tokens/tokens.css` | Variables `--xm-*`, thèmes clair et sombre, mouvement réduit |
| `tokens/social-xomiton.css` | Surcharge du thème Social (Bootstrap 5.3.3 compilé) |
| `xomiton/MASTER.md` et `xomiton/pages/` | Spécification condensée et surcharges (accueil, signaler et SOS, tableau de bord, authentification) |
| `components/COMPOSANTS.md` | Spécification des composants `x-*` et de leurs états |
| `charte-graphique/CHARTE_GRAPHIQUE.md` | Version texte de la charte (règle qui prime, contrastes, interdits, gouvernance) |
| `research/ANALYSE_CHARTES_EXEMPLES.md` | Ce qui a été tiré des chartes d'exemple et du skill ui-ux-pro-max |

## Réserves à connaître
- **Le dessin vectoriel du logo est une reconstitution** à partir du seul fichier disponible (`images/logo.png`, 240 × 66 px). Il est fidèle à la silhouette mais n'est pas le fichier source du ou de la graphiste : à faire valider ou à remplacer avant une impression grand format. Le `night-logo.png` d'origine était identique au logo clair ; la variante sombre est donc nouvelle.
- Les **photographies et illustrations** montrées dans la charte sont des images de travail du projet : leurs **droits d'usage ne sont pas confirmés**.
- Les **chiffres des infographies sont fictifs** (signalés comme tels sur chaque page).
- Les **valeurs CMJN** sont des conversions mathématiques ; Pantone et CMJN d'impression sont à valider sur épreuve.
- Les pages « Photographie : culture et lieux » et « Schémas pédagogiques » sont des **emplacements à produire** (aucune photographie de lieux béninois ni schéma validé n'existe aujourd'hui).
- Les noms de produits « App », « Ressources », « Annuaire », « Admin » et les formulations de vision et de valeurs sont des **propositions à valider**.

## Intégration dans le projet Laravel (phase 2 du plan)
**État au 8 octobre 2026** : étapes 1 à 5 appliquées à `xomiton/` (jetons, polices auto-hébergées, logos dans `public/assets/images/brand/`, composants `x-*`, Alpine auto-hébergé, Tailwind retiré, layout mis à jour). Sauvegarde des fichiers modifiés : `_sauvegardes/avant-design-system/`. Pages migrées vers `x-card`, `x-resource-category`, `x-faq` (qui s'appuie sur `x-accordion`) et `x-logo` ; doublons `components/*-card` et `partials/*-card` supprimés ; Font Awesome retiré du layout. Reste : revue visuelle dans un navigateur, `x-datatable`, `x-chart`.

1. Copier `tokens/*` vers `xomiton/resources/css/` ; charger après `public/assets/css/style.css`.
2. Auto-héberger Montserrat à partir de `fonts/` ; précharger les graisses 700 et 800.
3. Placer les logos SVG dans `public/assets/images/` ; faire du `icone-appli-*` la source du favicon et des icônes PWA.
4. Supprimer Tailwind (`package.json`, `vite.config.js`, `resources/css/app.css`) et Font Awesome ; garder Bootstrap Icons.
5. Copier `blade/components/*` vers `resources/views/components/`, `blade/js/xomiton-ui.js` vers `resources/js/`, `tokens/components.css` avec les autres CSS ; charger le plugin Alpine Focus (livré avec Livewire 3).
6. Ajouter la route `/admin/design-system` (local et staging) qui rend `blade/styleguide.blade.php`.

## Rapport avec le dossier d'implémentation
Ce dossier **précise et remplace** les §2 (couleurs) et §3 (typographie) de `xomiton_implementation/06_DESIGN_SYSTEM_ET_CHARTE.md`. Le reste de 06 (layouts, modales, conversion Sunshine → Social) reste valable.
