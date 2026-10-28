# Simulateur mobile Xomiton — compte membre / pair éducateur·rice

Prototype interactif de l'application mobile Xomiton, avec de vrais parcours utilisateur, dans un châssis **iPhone 14** ou **Pixel 8**. Il applique le design system et la charte graphique Xomiton (`../design-system/`) et sert de référence pour l'implémentation Flutter.

Ouvrir `index.html`. Le fichier s'ouvre aussi en double-clic (`file://`) : les visuels du splash et les logos sont embarqués (data URI) ; seule la police Montserrat (Google Fonts) nécessite le réseau. Sans réseau, les titres s'affichent en Segoe UI ou Arial.

## Ce que contient la page

| Zone | Rôle |
|---|---|
| **Menu des écrans (bouton ☰ fixe)** | Bouton ☰ **fixe en haut à gauche de la page** (48 px, `z-index` 10000, bleu sur blanc), hors du châssis téléphone, présent à l'identique dans `index.html`, `espace-pair.html` et `admin-mobile.html` (partial `src/partials/navmenu.html`). Il ouvre un tiroir « Menu des écrans » : les 30 écrans membre (01 à 30, par module Flutter), l'**Espace pair** (P1 à P11) et l'**Admin mobile** (A1 à A7), avec champ de filtre, écran courant surligné, fermeture par Échap, croix ou clic hors du tiroir. Le tiroir remplace l'ancien rail : même navigation à toutes les largeurs (1280, 1024, 768, 390 px). |
| **Centre** | Châssis iPhone 14 (390 × 844, encoche, zones sûres 47 / 34) ou Pixel 8 (412 × 880, poinçon, zones sûres 32 / 24) avec barre d'état, barre d'accueil et mise à l'échelle automatique. L'application à l'intérieur est **interactive** : formulaires validés, navigation avec retour, barre d'onglets, toasts. Thème **clair** uniquement : aucune bascule de thème dans le simulateur (le thème sombre de l'application suit le réglage du téléphone côté Flutter). |
| **Panneau droit** | Transposition Flutter de l'écran affiché : route `go_router`, page, provider Riverpod, dossier `lib/features/<module>/presentation/`, arbre de widgets, appels `/api/v1`, composants du design system, point de vigilance et état simulé. |

Barre supérieure : choix de l'appareil, du type de compte (le tableau de bord, le profil et les publications changent pour un·e pair éducateur·rice), et « Réinitialiser » (efface l'état enregistré dans le navigateur et revient au splash).

## Liens profonds

Chaque écran a une ancre : `index.html#/dashboard`, `#/login`, `#/sighub`, `#/thematiques` (alias `#/thematiqes`), `#/signalement`, `#/sos`, `#/message`, `#/postpublication`, `#/sondage`… Les écrans à paramètre acceptent un segment de plus : `#/thematique/droits`, `#/post/3`.

## Les 30 écrans

| Module | Écrans |
|---|---|
| `auth` | 01 Splash et carrousel · 02 Connexion · 03 Inscription (4 phases) · 04 Code par e-mail (OTP) · 05 Mot de passe oublié · 06 Nouveau mot de passe |
| `home` | 07 Tableau de bord · 08 Thématiques · 09 Fiche thématique · 10 Ressources · 11 Notifications |
| `sos` | 12 Signaler et suivre (`sighub`) · 13 SOS · 14 Alerte envoyée · 15 Signalement (4 étapes) · 16 Contacts d'urgence |
| `feed` | 17 Fil d'actualités · 18 Publication et commentaires · 19 Créer une publication · 20 Créer un sondage |
| `community` | 21 Messages · 22 Conversation · 23 Groupes · 24 Événements · 25 Assistant |
| `profile` | 26 Mon profil · 27 Profil progressif · 28 Paramètres · 29 Sécurité et confidentialité · 30 Newsletter |

## Parcours d'authentification

`splash` → `login` | `signup` → `otp` → tableau de bord, et `forgot` → `otp` → `reset` → `login`.

- **Splash** (`SplashCarouselPage`, état `onboardingProvider (seen: bool)`) : carrousel **plein écran** de 4 photos (`src/images/splash/*.webp`, WebP 900 px, ≤ 150 Ko) sous un dégradé sombre pour la lisibilité (AA). Barre supérieure avec logo blanc et « Passer » ; titre et sous-titre en tu (écoute sans jugement, SOS, communauté, ressources) ; points cliquables (cibles 44 px) ; « Suivant », puis « Créer un compte » et « Se connecter » sur la dernière diapositive. Balayage, flèches du clavier, texte alternatif sur chaque photo, `prefers-reduced-motion` respecté, aucun défilement automatique. La dernière diapositive vue est mémorisée. Un clic sur « Passer » ou sur une action mémorise `seen = true` : au lancement suivant, redirection vers la connexion. **À valider avant publication** : droits d'image des 4 photos (licence, consentement) et discrétion du visuel (spec `docs/SPEC_PROTOTYPE_MOBILE_V2.md`, §6).
- **OTP par e-mail** (pas par SMS) : couverture universelle, coût nul, aucune dépendance opérateur. Le même écran sert la connexion, l'inscription et la réinitialisation via le paramètre `purpose`. Six cases, collage du code complet, `autocomplete="one-time-code"`, renvoi après 30 s. Démo : n'importe quel code à 6 chiffres est accepté, sauf `000000`.
- **Inscription** en 4 phases : compte (pseudonyme, e-mail, mot de passe avec **jauge de force** et règles affichées), profil (facultatif), sécurité (facultatif), puis **consentements RGPD** en cases distinctes, jamais pré-cochées : conditions d'utilisation et confidentialité obligatoires, données sensibles et newsletter facultatives. Seule la phase 1 est obligatoire (décision D4 : inscription simplifiée). La phase 1 porte la case **« J'ai 15 ans ou plus »** (obligatoire, jamais pré-cochée) : « Continuer » reste désactivé tant qu'elle n'est pas cochée ; « J'ai moins de 15 ans » affiche un refus bienveillant (aucun compte créé, orientation vers une personne de confiance). Même règle que Flutter et le serveur (`age_confirmed`, `xomiton.accounts.min_age`).
- **Comptes de démonstration** (écran Connexion, trois boutons, mêmes identifiants que le site, `docs/GUIDE_RECETTE.md` §5) : Membre `membre@xomiton.bj` / `Membre123!` → tableau de bord ; Pair éducateur·rice `pair@xomiton.bj` / `Pair123!` → `espace-pair.html#/accueil` ; Administrateur·rice `admin@xomiton.bj` / `Admin123!` → `admin-mobile.html#/tableau`. Le code à 6 chiffres reste libre (sauf `000000`). Un mot de passe erroné pour l'un de ces comptes affiche « Identifiants incorrects ».
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
| `index.html` | Simulateur construit, fichier unique (≈ 330 Ko). **Ne pas modifier à la main.** |
| `src/simulateur.html` | Source lisible (HTML, CSS, JavaScript sans dépendance). Les images y sont référencées par `{{IMG:fichier}}`. |
| `src/images/` | Visuels du design system (logos SVG, illustrations JPEG) ; `splash/` : 4 photos WebP du carrousel (copie dans `mobile/assets/images/splash/`). |
| `src/espace-pair.html`, `src/admin-mobile.html` | Sources des deux maquettes ci-dessus (`{{INCLUDE:…}}` du noyau commun, `{{IMG:…}}` des images). |
| `src/partials/kit.css`, `kit.js` | Noyau commun des deux maquettes : jetons, châssis (copie du simulateur), composants, routage par ancre, feuilles, notifications. |
| `build.py` | Reconstruit `index.html`, `espace-pair.html` et `admin-mobile.html` (data URI pour `{{IMG:…}}`, inclusion des fichiers de `src/partials/`) : `python3 build.py`. |

## Maquettes « Espace pair » et « Admin mobile » (P15-09, à valider)

Deux maquettes cliquables complémentaires, avec le même châssis iPhone 14, les mêmes jetons `--xm-*`, Montserrat, thème clair par défaut (le sombre reste accessible par `?theme=dark`). Elles sont atteignables depuis le menu du simulateur. Données fictives et pseudonymisées, rien n'est envoyé nulle part. Elles ne modifient aucun des 30 écrans du simulateur.

| Fichier | Contenu |
|---|---|
| `espace-pair.html` | **Espace pair** (tutoiement) : accueil (KPI, tâches, raccourcis, pastille « de garde »), alertes routées (accuser réception, prendre en charge, clore), mes dossiers (recherche, fiche en 5 sections, frise de suivi, ajouter un suivi), collecte par étapes avec avertissement de plausibilité (état « en file » puis « envoyé »), file de synchronisation avec conflit 409 (valeur serveur ou mon appareil), bandeau hors connexion, SOS toujours accessible dans la barre d'onglets. |
| `admin-mobile.html` | **Admin mobile** (vouvoiement), compagnon d'astreinte et non l'administration complète : bandeau « Alertes SOS actives », liste des alertes (SOS / Signalements / Besoins, statut, prendre en charge), détail (position arrondie, « Ouvrir dans Maps » avec avertissement, personnes prévenues, historique, réattribuer, note interne), validation des pairs, mode maintenance avec confirmation forte, notifications. |
| `captures/` | Captures 390 × 844 (4 par maquette, thème clair et sombre) et captures du simulateur v2 : menu ouvert et fermé (1280 et 390), splash 4 diapositives, inscription (case 15 ans, refus), champ de publication. |

Ouvrir le fichier dans un navigateur (double-clic possible ; seule la police Montserrat demande le réseau). Sur grand écran : index des écrans à gauche, bascule **Réseau** (en ligne ou hors ligne) en haut ; plus de bascule de thème (clair par défaut ; `?theme=dark` reste accepté pour la recette). Sous 480 px de large, l'application occupe tout l'écran, sans châssis. Liens profonds : `espace-pair.html#/alertes`, `#/dossier/VBG-0042`, `#/collecte?etape=5&demo=1`, `#/sync?net=off`, `#/sos` ; `admin-mobile.html#/tableau`, `#/alerte/2041`, `#/pairs`, `#/maintenance`, `#/notifications` ; le paramètre `theme=dark` ou `net=off` s'ajoute après `?` ou `&`.

Parcours à essayer : en hors ligne, enregistrer une collecte (« En file »), puis repasser en ligne : l'envoi part tout seul (« Envoyé », code attribué) ; résoudre le conflit 409 dans la file ; côté admin, activer la maintenance (case à cocher et mot `MAINTENANCE`).

Sources : `web/resources/js/Pages/PeerSpace/*.vue`, `web/lang/fr/peer.php`, `cases.php`, `sync.php`, `admin.php`, `maintenance.php`, `enums/*.php`, `docs/ROLE_ET_PERMISSIONS.md`, `prototype_social/espace-pair.php` et `collecte.php`. Les textes sont regroupés dans l'objet `T` en tête de chaque fichier.

## Conformité au design system

Couleurs, typographie (Montserrat pour les titres, Arial pour le texte), rayons, cibles de 44 px minimum et thème sombre repris de `design-system/tokens/tokens.css` ; rouge réservé au SOS, aux erreurs et à la suppression ; focus visible ; `prefers-reduced-motion` respecté ; libellés visibles sur tous les champs ; aucune icône sans libellé accessible. La sortie rapide n'existe plus (décision D8).
