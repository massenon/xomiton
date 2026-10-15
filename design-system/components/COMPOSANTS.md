# Composants Xomiton `x-*` — spécification

Chaque composant : composant Blade anonyme (`resources/views/components/<nom>.blade.php`), props typées, variantes, **états** (défaut · survol · focus · actif · désactivé · chargement · erreur), accessibilité, tests de rendu. Style = classes Bootstrap + `.xm-*`, couleurs = tokens uniquement.
Gabarit de spécification (skill *design-system*) : Propriété × état.

## Boutons `<x-button>`
| Propriété | Défaut | Survol | Actif | Désactivé | Focus |
|---|---|---|---|---|---|
| Primaire — fond | `--xm-primary` | `--xm-primary-hover` | `--xm-primary-active` | `--xm-primary` à 60 % | anneau 3 px `--xm-focus-ring`, décalage 2 px |
| Primaire — texte | `--xm-on-primary` | idem | idem | idem | — |
| Secondaire (rouge) — fond | `--xm-secondary` | `--xm-secondary-hover` | `--xm-secondary-active` | 60 % | idem |
Variantes : `primary`, `secondary` (attention), `outline`, `ghost`, `light`, `icon` (libellé `aria-label` obligatoire) · tailles `sm` 36 px, `md` 44 px, `lg` 52 px · état chargement : spinner + libellé « Envoi… », bouton désactivé, `aria-busy`. Un seul bouton primaire par zone. Le rouge n'est **jamais** un style générique.

## Champs `<x-field>`
Types : texte, e-mail, téléphone, mot de passe (œil afficher/masquer, collage autorisé), date, liste (Choices), fichier (Dropzone), interrupteur, cases/radios (groupe avec `fieldset`/`legend`), texte long avec compteur.
Règles : libellé visible au-dessus ; « (facultatif) » plutôt qu'astérisque seul ; aide sous le champ ; erreur sous le champ liée par `aria-describedby`, texte rouge + icône ; validation à la sortie du champ puis à l'envoi ; **résumé d'erreurs** `role="alert"` en haut du formulaire, focalisé après un envoi raté, avec un lien vers chaque champ ; `autocomplete` correct ; `inputmode` pour mobile ; hauteur 44 px.

## Cartes `<x-card>`
Variantes : `content`, `resource`, `news`, `event`, `contact`, `stat`. Structure : étiquette (badge) → titre `h3` → description → action. Entièrement cliquable seulement si une seule action (lien étiré `stretched-link`). Survol : −2 px + ombre `lg` (désactivé en mouvement réduit). Remplace les doublons `partials/*-card` et `components/*-card`.

## Badges `<x-badge>` et urgence `<x-urgency>`
`<x-urgency level="critical">` → `.xm-urgency--critical` : **icône + mot + couleur** (Faible · Moyenne · Élevée · Critique). Statuts de cas : `new` bleu · `assigned` violet · `in_progress` info · `pending_victim_response` alerte · `resolved` vert · `closed` neutre · `unresolved` rouge ; toujours un libellé en toutes lettres.

## Modale `<x-modal>`
Types : `confirm`, `form`, `delete`, `fullscreen` (mobile). `role="dialog"`, `aria-modal`, `aria-labelledby`, focus piégé (Alpine Focus), retour du focus au déclencheur, fermeture `Échap`. **Pied fixe** (Annuler à gauche / action à droite), contenu défilant. Fermeture bloquée avec confirmation si le formulaire est modifié. **Monté en permanence**, ouvert par Alpine (jamais `@if`). Modale de suppression : titre « Supprimer <objet> ? », conséquences listées, saisie du mot de confirmation pour les objets critiques, bouton rouge « Supprimer définitivement » ; les cas, alertes et preuves s'archivent, ils ne se suppriment pas.

## Navigation
- **Header public** sticky à effet verre au scroll ; burger + **offcanvas** < 992 px ; liens : Ressources · Actualités · Événements · Annuaire · bouton « Signaler un abus » · S'inscrire / Se connecter · bascule de thème. `aria-current="page"`.
- **Barre d'onglets mobile** (connecté) : 5 entrées maximum (Accueil · Ressources · **SOS** · Messages · Profil).
- **Fil d'Ariane** `<x-breadcrumb>` + JSON-LD (même source).
- **Onglets** `<x-tabs>` : pattern ARIA, onglet actif mémorisé dans l'URL (`?tab=`).
- **Accordéon** `<x-accordion>` : bouton `aria-expanded`, un seul ouvert par défaut pour la FAQ.

## Tableau de données `<x-datatable>`
Tri, filtres, recherche, pagination, colonnes masquables, actions groupées, export, **état vide illustré**, sélection avec compteur. < 768 px : chaque ligne devient une carte (libellé de colonne répété). En-têtes `scope`, `caption` ou `aria-label`, tri annoncé (`aria-sort`). Page de 25 lignes par défaut.

## KPI `<x-stat>` et graphiques `<x-chart>`
KPI : valeur Montserrat 800, libellé, tendance (flèche + texte « +12 % »), lien « voir le détail ». Graphiques : Chart.js, palette catégorielle (bleu, violet, rose, orange, vert, gris) distinguable en niveaux de gris, étiquettes directes, **tableau de données accessible** sous chaque graphique, cellules < 5 masquées « données masquées ».

## `<x-sos-button>`
64 px, rouge, libellé « SOS » (Montserrat 800). Interaction : maintien 2 s (anneau de progression, `data-holding`) → compte à rebours 5 s **annulable** → envoi. Alternatives accessibles : double appui + confirmation, ou activation clavier (`Espace` maintenu ou `Entrée` puis confirmation). Retour : vibration courte, message « Ton alerte est partie » `aria-live="assertive"`. Position fixe en bas à droite (public) ou au centre de la barre d'onglets (mobile). Jamais masqué par un autre élément flottant.

## `<x-quick-exit>` « Quitter ce site »
Bouton en haut de page (mobile) et dans le header (desktop), `Échap` ×2 déclenche aussi. Action : remplace la page par un site neutre (`location.replace`) et vide les données de session locales non nécessaires.

## `<x-bell>` cloche
Icône `bi-bell` + badge numérique, panneau *Alertes · Cas · Messages · Système*. Son : uniquement SOS/cas critiques pour les destinataires concernés, boucle jusqu'à accusé de réception (SOS), volume réglable, autorisation audio demandée à la première utilisation, **alternative visuelle** (bandeau clignotant désactivé en mouvement réduit → bandeau fixe) et **vibration**. Mise à jour par polling Livewire (voir `DECISIONS.md` D7).

## `<x-toast>`, `<x-empty-state>`, `<x-skeleton>`, `<x-stepper>`, `<x-timeline>`, `<x-avatar>`, `<x-gallery>`, `<x-comic-viewer>`, `<x-player>`
Toast : `aria-live="polite"` (succès/info) ou `assertive` (erreur), 6 s, fermable, pas d'information unique. État vide : illustration + une phrase + une action. Squelettes pour listes et cartes (évite le décalage de mise en page). Stepper : étapes numérotées avec étape courante `aria-current="step"`, sauvegarde à chaque étape. Timeline : chronologie d'un cas (note interne/partagée, changements de statut). Visionneuse de BD : planche par planche, swipe, zoom, plein écran, `alt` par planche. Lecteur : Plyr avec sous-titres.

## Images `<x-img>`
Refuse le rendu sans `alt` ; accepte `title`, `width`, `height` ; `loading="lazy"` sauf image LCP (`fetchpriority="high"`) ; `srcset` AVIF/WebP + repli ; décorative : `alt=""` + `aria-hidden`.

## `<x-seo>`
Voir `xomiton_implementation/07_SEO_ET_DONNEES_STRUCTUREES.md`.

## Catalogue vivant
Chaque composant et chaque état apparaîtra dans `/admin/design-system` (environnement local et staging) ; l'aperçu statique `preview/index.html` en donne un premier état utilisable dès maintenant.
