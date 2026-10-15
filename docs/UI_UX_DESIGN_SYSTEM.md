---
name: xomiton-ui-ux-design-system
description: Design system UI/UX de Xomiton appliqué à l'implémentation — charte graphique, jetons, navigation, composants, tableaux de bord, CRUD en modale, DataTables, filtres, barre de recherche, KPI (accordéon sur mobile), widgets, formulaires, toasts SweetAlert2. À charger pour tout écran, composant ou revue visuelle. Le §4 est la référence de cohérence des écrans d'administration et de gestion.
---

# UI/UX — design system appliqué

Sources : `design-system/xomiton/MASTER.md` (règles), `design-system/charte-graphique/CHARTE_GRAPHIQUE.md` (charte), `design-system/components/COMPOSANTS.md` (composants), `design-system/tokens/` (jetons). Ce document dit **comment les appliquer** dans Laravel, Livewire et Vue.

## 1. Charte graphique (rappel opposable)

| Thème | Règle |
|---|---|
| Couleurs | Bleu `#0433C3` (action, navigation, focus) · Rouge `#E30E0E` (**attention uniquement** : SOS, signaler, critique, suppression) · fond `#FBF7FA` · surface `#FFFFFF` · texte `#1B1630` · atténué `#5B5670` · bordure `#E4DCE6` |
| Sombre | Bleu `#7C97FF`, rouge `#FF6B6B`, fond `#0E1020`, surface `#171A2E`, texte `#EDEAF7` |
| Proportions | 60 % neutres · 30 % bleu · ≤ 10 % rouge ; **bleu sur rouge interdit** (1,91:1) |
| Typographie | Titres **Montserrat** 800/700/600 · texte et sous-titres **Arial** 400/700 · corps 16 px, minimum 14 px · interligne 1,2 (titres) / 1,6 (texte) · mesure ≤ 68 caractères |
| Formes | Rayons 8 px (champs, boutons), 16 px (cartes), pastille (badges, chips) |
| Espacement | Base 4 px : 4, 8, 12, 16, 24, 32, 48, 64, 96 |
| Mouvement | 120 / 200 / 300 ms, `transform` et `opacity` seulement, désactivé si `prefers-reduced-motion` |
| Icônes | Bootstrap Icons, toujours accompagnées d'un libellé visible ou d'un `aria-label` |
| Logo | Variantes du dossier `design-system/logo/` ; jamais recomposé en police ; favicon = `icone-appli-bleu` |

Chargement CSS : `style.css` (thème Social, jamais modifié) → `tokens.css` → `social-xomiton.css` → `components.css` → `xomiton.css`.

## 2. Navigation

| Espace | Navigation |
|---|---|
| Public | En-tête sticky : logo · menus Apprendre / Communauté / Aide & protection · bouton « Signaler » · Se connecter / Créer un compte. < 992 px : burger + **offcanvas**. Pied de page 4 colonnes : marque + SOS · Plateforme · Aide & protection · **Newsletter** |
| Membre / pair (web) | En-tête connecté (recherche, SOS, messages, réglages, notifications, avatar) · colonne gauche `sidenav` (profil + liens groupés Réseau social / Apprendre / Aide & protection / Espace pair) · contenu central · colonne droite (Qui suivre, Événements, Urgence). < 992 px : sidenav en offcanvas « Menu » |
| Mobile (app) | Barre d'onglets 5 entrées : Accueil · Fil · **SOS (centre)** · Messages · Profil |
| Administration | Barre latérale groupée (Pilotage · Gestion des données · Plateforme · Système · Raccourcis), barre supérieure (titre, fil d'Ariane, recherche globale Ctrl K, état en ligne, actions de page, plein écran, notifications, avatar). < 992 px : barre latérale repliée, bouton menu |

Menu d'administration actuel :
- **Pilotage** : Tableau de bord · Alertes & signalements · Indicateurs · Rapports automatisés
- **Gestion des données** : Registres numériques · Fiches VBG / VDH · Suivi des victimes · Annuaire dynamique · Règles de validation
- **Plateforme** : Utilisateurs & rôles · Inscriptions (on/off) · Contenu : thématiques & ressources · **Newsletter** · Outils CCC · Réseau social · Modération · Assistant LLM
- **Système** : Hors connexion & synchro · Mises à jour & optimisation · **Templates e-mails** · Paramètres · Journal d'audit

## 3. Composants

| Composant | Blade (public) | Vue (membre) | Livewire (admin) |
|---|---|---|---|
| Bouton | `<x-button variant size icon loading>` | `<XmButton>` | `<x-button wire:click>` |
| Champ | `<x-field type label hint error>` | `<XmField>` | `<x-field wire:model.blur>` |
| Carte | `<x-card variant>` | `<XmCard>` | `<x-card>` |
| Modale | `<x-modal>` | `<XmModal>` | `<x-admin.modal>` (voir §4) |
| KPI | `<x-stat>` | `<XmStat>` | `<x-admin.kpi>` |
| Tableau | — | — | `<livewire:admin.datatable>` |
| Toast | `<x-toast>` | `useToast()` | SweetAlert2 (`$dispatch('toast')`) |
| SOS | `<x-sos-button>` | `<XmSosButton>` | — |

Le HTML produit est **le même** dans les trois technologies (mêmes classes Bootstrap Social et `xm-*`), copié du prototype.

## 4. Cohérence des écrans de gestion (référence)

Chaque écran de gestion suit **exactement** ce gabarit, dans cet ordre :

```
┌ Barre supérieure : titre de page · fil d'Ariane · [actions de page : + Nouveau…] ┐
├ Rangée de KPI (4 à 5 cartes .xm-stat, couleur = sens)                            │
├ (facultatif) Onglets de sous-module  ?tab=…                                       │
├ Carte « liste »                                                                  │
│   en-tête : titre h2.h6 · [Importer CSV] [Exporter CSV]                          │
│   barre de filtres : recherche · selects · dates · [Filtrer] [Réinitialiser]     │
│   barre d'actions groupées (apparaît à la sélection)                             │
│   tableau .xm-table-wrap  (cartes empilées < 768 px)                             │
│   pied : « Affichage de 1 à 20 sur 57 entrées » · Précédent 1 … 11 Suivant       │
├ Cartes secondaires (graphiques, configuration, journal)                          │
└ Modales : créer/modifier · aperçu · suppression                                   ┘
```

### 4.1 KPI et widgets
- Carte `.xm-stat` + variante couleur (`success`, `warning`, `danger`, `violet`) : libellé `small text-secondary`, valeur `.kpi-value` (Montserrat 800), sous-texte facultatif, icône à droite.
- 4 KPI par rangée en desktop (`col-6 col-xl-3`), 2 en tablette et mobile.
- **Mobile < 768 px : la rangée de KPI devient un accordéon** « Indicateurs clés » fermé par défaut, ouvert sur demande ; le premier KPI reste visible en résumé dans l'en-tête de l'accordéon.
- Un KPI cliquable mène à la liste filtrée correspondante.
- Widgets graphiques : Chart.js, palette catégorielle du design system (`--xm-chart-1..6`), légende en bas, hauteur fixe 280–290 px, titre `h2.h6` dans l'en-tête de carte.

### 4.2 DataTables (Livewire)
- Recherche en direct (`wire:model.live.debounce.300ms`), filtres en selects, plage de dates, tri par colonne (`aria-sort`), pagination serveur (10, 20 ou 50 lignes), compteur « Affichage de x à y sur n entrées ».
- Colonnes : identifiant principal en gras + ligne secondaire `small text-secondary` ; statuts en **badge + texte** ; dates `d/m/Y` ; actions alignées à droite en boutons icônes `btn-sm btn-light` avec `title` et `aria-label`.
- Sélection multiple : case « tout sélectionner », barre d'actions groupées avec compteur, confirmation avant suppression.
- État vide : icône + phrase + action (« Aucun·e abonné·e ne correspond aux filtres. »).
- Chargement : `wire:loading` → squelettes de lignes, pas de spinner plein écran.
- **< 768 px** : chaque ligne devient une carte (libellé de colonne répété), actions en bas de carte.
- Export CSV : UTF-8 avec BOM, séparateur `;`, respecte les filtres actifs ; modèle d'import téléchargeable.

### 4.3 CRUD en modale
- Créer et modifier dans **la même modale** (`modal-lg`, ou `modal-xl modal-fullscreen-lg-down` pour les éditeurs riches) ; titre « Nouveau … » / « Modifier : … ».
- Pied **fixe** : « Annuler » (`btn-light`) à gauche, action principale (`btn-primary`) à droite, libellé précis (« Enregistrer », « Programmer l'envoi »).
- Validation : en direct à la sortie du champ (`wire:model.blur`), message sous le champ (`invalid-feedback`), résumé en haut si plusieurs erreurs, focus sur le premier champ en erreur.
- Succès : fermeture de la modale + toast SweetAlert2 « Contact mis à jour. » + ligne mise à jour sans recharger la page.
- Suppression : modale dédiée rouge, texte qui nomme l'élément, conséquence expliquée ; **confirmation renforcée** (saisie du slug ou du mot « RESTAURER ») pour les éléments critiques (templates système, restauration de sauvegarde).
- Focus piégé, `Échap` ferme, retour du focus au bouton déclencheur.

### 4.4 Barre de recherche et filtres
- Recherche toujours en premier, à gauche, avec icône loupe et texte d'exemple (« Nom ou slug… »).
- Filtres en `form-select-sm`, libellés accessibles (`visually-hidden`), bouton « Filtrer » (`btn-primary-soft`) et lien « Réinitialiser » quand un filtre est actif.
- Les filtres sont dans l'URL (`?q=&st=&tag=`) : partageables et conservés par la pagination et l'export.

### 4.5 Formulaires
- Libellé visible au-dessus, « (facultatif) » pour les champs optionnels, aide sous le champ, hauteur 44 px minimum.
- Groupes de cases et radios dans `fieldset` + `legend`.
- Éditeur riche : **Quill** (auto-hébergé) avec guide de variables cliquables à droite.
- Téléversement : aperçu, poids maximum affiché, refus immédiat si trop lourd.

### 4.6 Toasts et notifications d'interface
- **SweetAlert2** en mode toast (coin supérieur droit, 4,5 s, barre de progression, fermeture au clic), icône selon le type : `success`, `error`, `warning`, `info`.
- Confirmations destructrices : SweetAlert2 modal (`showCancelButton`, bouton de confirmation rouge, focus sur « Annuler »).
- Texte du toast = action au passé avec le même verbe que le bouton (« Publier » → « Publié »).
- Annonce aux lecteurs d'écran : conteneur `aria-live="polite"` (`assertive` pour les erreurs).

### 4.7 Cartes (cohérence)
Une seule famille de cartes : `.card` + `.card-header` (titre `h2.h6`, actions à droite) + `.card-body` + `.card-footer` facultatif. Survol `xm-card-hover` uniquement pour les cartes entièrement cliquables. Même rayon, même ombre, même espacement partout.

## 5. Tableaux de bord

- **Administration** : 7 KPI (nouvelles alertes, SOS actifs, cas ouverts, en cours, clos, utilisateur·rices, taux de synchro), graphiques (cas par mois, type de violence, département, population, sexe × âge), dernières alertes, carte des SOS actifs, raccourcis, état de la plateforme.
- **Membre** : accès rapides (SOS, Signaler, Assistant, Urgences), créer une publication, à la une, filtres, fil.
- **Pair éducateur·rice** : KPI de collecte, tâches, alertes routées, raccourcis CCC.

## 6. États à prévoir pour chaque écran

Chargement (squelettes) · vide (illustration + action) · erreur (message + réessayer) · hors connexion (bandeau + file d'attente) · succès (toast) · lecture seule (bandeau explicatif).
