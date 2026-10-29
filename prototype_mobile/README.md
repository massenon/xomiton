# Simulateur mobile Xomiton : une seule appli (membre, pair éducateur·rice, admin)

> **Décisions du client du 29/10/2026 intégrées** (voir `../docs/PLAN_MOBILE_APP_UNIQUE.md`, D-MOB-1 à 13) : **une seule application, un seul login** ; l'inscription publique ne crée que des **membres** ; un membre peut recevoir le rôle **pair éducateur·rice** (membre ET pair) ; l'admin est aussi membre. Après connexion, un **sélecteur d'espace** (Membre / Pair / Admin selon les rôles) s'affiche ; un commutateur reste dans l'en-tête et dans le profil. L'espace pair existe dès la v1. **Ces maquettes doivent être validées par le client avant tout code mobile.**

Prototype interactif de l'application mobile Xomiton, avec de vrais parcours utilisateur, dans un châssis **iPhone 14** ou **Pixel 8**. Il applique le design system et la charte graphique Xomiton (`../design-system/`) et sert de référence pour l'implémentation Flutter.

Ouvrir `index.html`. Le fichier s'ouvre aussi en double-clic (`file://`) : les visuels du splash et les logos sont embarqués (data URI) ; seule la police Montserrat (Google Fonts) nécessite le réseau. Sans réseau, les titres s'affichent en Segoe UI ou Arial.

## Ce que contient la page

| Zone | Rôle |
|---|---|
| **Menu des écrans (bouton ☰ fixe)** | Bouton ☰ **fixe en haut à gauche de la page** (48 px, `z-index` 10000, bleu sur blanc), hors du châssis téléphone, présent à l'identique dans `index.html`, `espace-pair.html` et `admin-mobile.html` (partial `src/partials/navmenu.html`). Il ouvre un tiroir « Menu des écrans » : les écrans membre (01 à 31, par module Flutter), l'**Espace pair** (P1 à P12) et l'**Admin mobile** (A1 à A12), avec champ de filtre, écran courant surligné, fermeture par Échap, croix ou clic hors du tiroir. Le tiroir remplace l'ancien rail : même navigation à toutes les largeurs (1280, 1024, 768, 390 px). |
| **Centre** | Châssis iPhone 14 (390 × 844, encoche, zones sûres 47 / 34) ou Pixel 8 (412 × 880, poinçon, zones sûres 32 / 24) avec barre d'état, barre d'accueil et mise à l'échelle automatique. L'application à l'intérieur est **interactive** : formulaires validés, navigation avec retour, barre d'onglets, toasts. Thème **clair** uniquement : aucune bascule de thème dans le simulateur (le thème sombre de l'application suit le réglage du téléphone côté Flutter). |
| **Panneau droit** | Transposition Flutter de l'écran affiché : route `go_router`, page, provider Riverpod, dossier `lib/features/<module>/presentation/`, arbre de widgets, appels `/api/v1`, composants du design system, point de vigilance et état simulé. |

Barre supérieure : choix de l'appareil, du **compte de démonstration** connecté (Membre, Pair + membre, Admin + membre : change les rôles, donc les espaces proposés) et « Réinitialiser » (efface l'état et la session enregistrés dans le navigateur et revient au splash).

## Liens profonds

Chaque écran a une ancre : `index.html#/dashboard`, `#/login`, `#/sighub`, `#/thematiques` (alias `#/thematiqes`), `#/signalement`, `#/sos`, `#/message`, `#/postpublication`, `#/sondage`… Les écrans à paramètre acceptent un segment de plus : `#/thematique/droits`, `#/post/3`.

## Les 31 écrans (01 à 30 inchangés, 31 ajouté)

| Module | Écrans |
|---|---|
| `auth` | 01 Splash et carrousel · 02 Connexion (**unique**, tous les rôles) · 03 Inscription (4 phases, **membre uniquement** ; `#/signup/PAIR-7K4M-Q2` = invitation de pair) · 04 Code par e-mail (OTP) · 05 Mot de passe oublié · 06 Nouveau mot de passe · **31 Choix de l'espace** (après connexion, si plusieurs rôles) |
| `home` | 07 Tableau de bord · 08 Thématiques · 09 Fiche thématique · 10 Ressources · 11 Notifications |
| `sos` | 12 Signaler et suivre (`sighub`) · 13 SOS · 14 Alerte envoyée · 15 Signalement (4 étapes) · 16 Contacts d'urgence |
| `feed` | 17 Fil d'actualités · 18 Publication et commentaires · 19 Créer une publication · 20 Créer un sondage |
| `community` | 21 Messages · 22 Conversation · 23 Groupes · 24 Événements · 25 Assistant |
| `profile` | 26 Mon profil (badges de rôles, carte **Mes espaces**) · 27 Profil progressif · 28 Paramètres · 29 Sécurité et confidentialité · 30 Newsletter |

## Parcours d'authentification

`splash` → `login` | `signup` → `otp` → tableau de bord, et `forgot` → `otp` → `reset` → `login`.

- **Splash** (`SplashCarouselPage`, état `onboardingProvider (seen: bool)`) : carrousel **plein écran** de **6 diapositives** (une photo chacune, `src/images/splash/*.webp`, 382 Ko d’images pour toute l’application) sous un dégradé sombre pour la lisibilité (AA) ; les deux visuels basse résolution (bouche scotchée, symbole de Vénus recadré) s’affichent à la largeur de l’écran puis se fondent dans le fond sombre. Barre supérieure avec logo blanc et « Passer » ; titre et sous-titre en tu, **textes validés par le client le 29/10/2026** (Un espace à toi · Ta parole compte · En sécurité · Une communauté qui te comprend · Des réponses fiables · Ta vie privée d’abord) ; points cliquables (cibles 44 px) ; « Suivant », puis « Créer un compte » et « Se connecter » sur la dernière diapositive. Balayage, flèches du clavier, texte alternatif sur chaque photo, `prefers-reduced-motion` respecté, aucun défilement automatique. La dernière diapositive vue est mémorisée. Un clic sur « Passer » ou sur une action mémorise `seen = true` : au lancement suivant, redirection vers la connexion. **À valider avant publication** : droits d'image des photos (licence confirmée le 28/10, consentement) et discrétion du visuel (spec `docs/SPEC_PROTOTYPE_MOBILE_V2.md`, §6).
- **OTP par e-mail** (pas par SMS) : couverture universelle, coût nul, aucune dépendance opérateur. Le même écran sert la connexion, l'inscription et la réinitialisation via le paramètre `purpose`. Six cases, collage du code complet, `autocomplete="one-time-code"`, renvoi après 30 s. Démo : n'importe quel code à 6 chiffres est accepté, sauf `000000`.
- **Inscription** en 4 phases : compte (pseudonyme, e-mail, mot de passe avec **jauge de force** et règles affichées), profil (facultatif), sécurité (facultatif), puis **consentements RGPD** en cases distinctes, jamais pré-cochées : conditions d'utilisation et confidentialité obligatoires, données sensibles et newsletter facultatives. Seule la phase 1 est obligatoire (décision D4 : inscription simplifiée). La phase 1 porte la case **« J'ai 15 ans ou plus »** (obligatoire, jamais pré-cochée) : « Continuer » reste désactivé tant qu'elle n'est pas cochée ; « J'ai moins de 15 ans » affiche un refus bienveillant (aucun compte créé, orientation vers une personne de confiance). Même règle que Flutter et le serveur (`age_confirmed`, `xomiton.accounts.min_age`).
- **Connexion unique et sélecteur d'espace** (décision du 29/10/2026) : membre, pair et admin utilisent **le même écran** de connexion (aucun choix « type de compte »). Comptes de démonstration (mêmes identifiants que le site, `docs/GUIDE_RECETTE.md` §5) : Membre `membre@xomiton.bj` / `Membre123!` (rôles : membre) ; Pair éducateur·rice `pair@xomiton.bj` / `Pair123!` (membre + pair) ; Administrateur·rice `admin@xomiton.bj` / `Admin123!` (membre + admin). Après le code à 6 chiffres (libre, sauf `000000`), un compte à **plusieurs rôles** arrive sur l'écran 31 « Choix de l'espace » ; un membre simple va directement au tableau de bord. Un mot de passe erroné affiche « Identifiants incorrects ».
- **Changer d'espace** : (1) bouton-pastille d'**en-tête** (« Membre ▾ », « Pair ▾ », « Admin ▾ ») qui ouvre la feuille « Choisir mon espace » ; (2) **onglet visible** de la barre du bas côté membre (« Espace pair » ou « Admin », point violet ; « Espaces » si les deux rôles) et onglet **« Réseau »** (fil, messages…) dans les barres du pair et de l'admin ; (3) **Profil › Mes espaces**. Un membre sans rôle peut « Demander le rôle pair éducateur·rice » (demande validée par l'admin). La session de démonstration est partagée entre les trois pages (`localStorage`, clé `xm-session-v1`, partial `src/partials/espaces.js`) ; en mode recette (menu ☰), ouvrir un écran pair ou admin ajoute le rôle correspondant au compte de démonstration.
- **Inscription** : l'inscription publique ne crée que des **membres** (la phase 2 ne propose plus « pair »). Un lien d'invitation (`#/signup/PAIR-7K4M-Q2`) suit les mêmes écrans, puis l'admin valide le rôle pair (écran « Inscriptions à valider »).
- **Décor** : connexion et inscription portent un ruban arc-en-ciel (six couleurs standard atténuées, charte §3.5) et de petites illustrations en SVG en ligne, `aria-hidden`, statiques (rien à désactiver pour `prefers-reduced-motion`), jamais derrière un champ.
- **Mot de passe oublié** : code par e-mail, réponse volontairement indifférenciée (pas d'énumération de comptes), puis choix d'un nouveau mot de passe.

## Autres parcours à essayer

- **SOS** : maintenir le bouton 2 s (anneau de progression) → compte à rebours de 5 s annulable → « Ton alerte est partie », personnes prévenues, « Je suis en sécurité ». Alternatives : touche Espace maintenue, ou double appui puis confirmation.
- **Signalement** : 4 étapes avec brouillon conservé, récapitulatif, code de suivi à copier, retrouvé ensuite dans « Signaler et suivre ».
- **Fil** : j'aime, vote à un sondage (et retrait du vote), filtres, commentaires, création d'une publication ou d'un sondage (2 à 6 réponses), qui apparaît en tête du fil.
- **Messages** : envoi d'un message et réponse simulée ; aperçus masqués si l'option de sécurité est active.
- **Paramètres** : notifications, sons, langue, déconnexion.

## Fichiers

| Chemin | Rôle |
|---|---|
| `index.html` | Simulateur construit, fichier unique (≈ 680 Ko). **Ne pas modifier à la main.** |
| `src/simulateur.html` | Source lisible (HTML, CSS, JavaScript sans dépendance). Les images y sont référencées par `{{IMG:fichier}}`. |
| `src/images/` | Visuels du design system (logos SVG, illustrations JPEG) ; `splash/` : 6 photos WebP du carrousel (copie dans `mobile/assets/images/splash/`). |
| `src/espace-pair.html`, `src/admin-mobile.html` | Sources des deux maquettes ci-dessus (`{{INCLUDE:…}}` du noyau commun, `{{IMG:…}}` des images). |
| `src/partials/kit.css`, `kit.js` | Noyau commun des deux maquettes : jetons, châssis (copie du simulateur), composants, routage par ancre, feuilles, notifications, bannière push. |
| `src/partials/espaces.js` | Session de démonstration partagée (rôles, espace courant), pastille d'en-tête et feuille « Choisir mon espace » des trois pages. |
| `build.py` | Reconstruit `index.html`, `espace-pair.html` et `admin-mobile.html` (data URI pour `{{IMG:…}}`, inclusion des fichiers de `src/partials/`) : `python3 build.py`. |

## Maquettes « Espace pair » et « Admin mobile » (P15-09, à valider par le client avant tout code mobile)

Deux maquettes cliquables complémentaires (même appli, même compte), avec le même châssis iPhone 14, les mêmes jetons `--xm-*`, Montserrat, thème clair par défaut (le sombre reste accessible par `?theme=dark`). Elles sont atteignables depuis le menu ☰ du simulateur. Données fictives et pseudonymisées, rien n'est envoyé nulle part. Elles ne modifient aucun des 30 écrans d'origine du simulateur. Barre du bas côté pair : Accueil · Alertes · SOS · Dossiers · **Réseau** ; côté admin : Astreinte · Alertes · **Gérer** · Notifs · **Réseau** (le « Réseau » ouvre le fil du réseau social, onglet « Espace pair » / « Admin » pour revenir).

| Fichier | Contenu |
|---|---|
| `espace-pair.html` | **Espace pair** (tutoiement, dès la v1) : accueil (KPI, tâches, raccourcis, pastille « de garde »), alertes routées (accuser réception, prendre en charge, clore, **prévenu·es : admins d'astreinte**), mes dossiers (recherche, fiche en 5 sections, frise de suivi, ajouter un suivi), collecte par étapes avec avertissement de plausibilité (état « en file » puis « envoyé »), file de synchronisation, **conflit 409 tranché par le pair lui-même** (écran de choix « valeur du serveur » ou « mon appareil », explication simple, confirmation, aucune validation par l'équipe), **push SOS reçu** (aperçu), bandeau hors connexion, SOS toujours accessible. |
| `admin-mobile.html` | **Admin mobile** (vouvoiement), périmètre élargi : bandeau « Alertes SOS actives », liste des alertes (SOS / Signalements / Besoins, statut, prendre en charge), détail (**position arrondie**, « Ouvrir dans Maps » avec avertissement, personnes prévenues, historique, réattribuer, note interne), **Inscriptions à valider** (invitations de pairs acceptées, demandes de rôle, inscriptions membres à vérifier), **Rôles et invitations** (inviter un·e pair par lien ou code, révoquer, attribuer ou retirer le rôle pair à un membre, **rechercher un membre**), **Modération** (contenus signalés : masquer, avertir, conserver), menu **Gérer**, mode maintenance avec confirmation forte, notifications, **push SOS reçu** (aperçu), tableau d'astreinte. Le rôle admin ne s'attribue que depuis le site web. |
| `captures/` | Captures 390 × 844 (thème clair et sombre) des trois pages : connexion unique, sélecteur d'espace (feuille et écran), barre à 6 onglets, profil « Mes espaces », inscription (membre uniquement, invitation), espace pair (accueil, alertes, collecte, synchronisation, conflit 409 en 4 temps, push SOS), admin (astreinte, détail, maintenance, inscriptions, gérer, rôles, modération, push SOS), menu ☰ (1280 et 390), et les captures du simulateur v2 (splash 4 diapositives, case 15 ans, champ de publication). |

Ouvrir le fichier dans un navigateur (double-clic possible ; seule la police Montserrat demande le réseau). Sur grand écran : index des écrans à gauche, bascule **Connexion** (en ligne ou hors ligne) en haut ; plus de bascule de thème (clair par défaut ; `?theme=dark` reste accepté pour la recette). Sous 480 px de large, l'application occupe tout l'écran, sans châssis. Liens profonds : `espace-pair.html#/alertes`, `#/dossier/VBG-0042`, `#/collecte?etape=5&demo=1`, `#/sync?net=off`, `#/conflit/q1`, `#/push-sos`, `#/sos` ; `admin-mobile.html#/tableau`, `#/alerte/2041`, `#/inscriptions` (alias `#/pairs`), `#/roles`, `#/moderation`, `#/contenu/m1`, `#/gerer`, `#/maintenance`, `#/notifications`, `#/push-sos` ; le paramètre `theme=dark` ou `net=off` s'ajoute après `?` ou `&`.

Parcours à essayer : se connecter avec le compte admin puis choisir l'espace ; en hors ligne côté pair, enregistrer une collecte (« En file »), repasser en ligne, puis résoudre le conflit 409 (c'est le pair qui choisit) ; côté admin, créer une invitation de pair, valider une inscription, retirer le rôle pair à un membre trouvé par recherche, masquer un contenu signalé, activer la maintenance (case à cocher et mot `MAINTENANCE`).

Sources : `web/resources/js/Pages/PeerSpace/*.vue`, `web/lang/fr/peer.php`, `cases.php`, `sync.php`, `admin.php`, `maintenance.php`, `enums/*.php`, `docs/ROLE_ET_PERMISSIONS.md`, `prototype_social/espace-pair.php` et `collecte.php`. Les textes sont regroupés dans l'objet `T` en tête de chaque fichier.

## Conformité au design system

Couleurs, typographie (Montserrat pour les titres, Arial pour le texte), rayons, cibles de 44 px minimum et thème sombre repris de `design-system/tokens/tokens.css` ; rouge réservé au SOS, aux erreurs et à la suppression ; focus visible ; `prefers-reduced-motion` respecté ; libellés visibles sur tous les champs ; aucune icône sans libellé accessible. La sortie rapide n'existe plus (décision D8).
