# Simulateur mobile Xomiton — compte membre / pair éducateur·rice

Prototype interactif de l'application mobile Xomiton, avec de vrais parcours utilisateur, dans un châssis **iPhone 14** ou **Pixel 8**. Il applique le design system et la charte graphique Xomiton (`../design-system/`) et sert de référence pour l'implémentation Flutter.

Ouvrir `index.html`. Le fichier s'ouvre aussi en double-clic (`file://`) : les visuels du splash et les logos sont embarqués (data URI) ; seule la police Montserrat (Google Fonts) nécessite le réseau. Sans réseau, les titres s'affichent en Segoe UI ou Arial.

## Ce que contient la page

| Zone | Rôle |
|---|---|
| **Rail gauche** | Index des 30 écrans, groupés par module Flutter (`auth`, `home`, `sos`, `feed` (fil d'actualités), `community`, `profile`). Champ de filtre. Clic = saut direct (une session de démonstration est ouverte si l'écran demande d'être connecté·e). Sous 820 px, le rail devient une liste déroulante. |
| **Centre** | Châssis iPhone 14 (390 × 844, encoche, zones sûres 47 / 34) ou Pixel 8 (412 × 880, poinçon, zones sûres 32 / 24) avec barre d'état, barre d'accueil et mise à l'échelle automatique. L'application à l'intérieur est **interactive** : formulaires validés, navigation avec retour, barre d'onglets, toasts, thème clair ou sombre. |
| **Panneau droit** | Transposition Flutter de l'écran affiché : route `go_router`, page, provider Riverpod, dossier `lib/features/<module>/presentation/`, arbre de widgets, appels `/api/v1`, composants du design system, point de vigilance et état simulé. |

Barre supérieure : choix de l'appareil, du type de compte (le tableau de bord, le profil et les publications changent pour un·e pair éducateur·rice), du thème de l'application, et « Réinitialiser » (efface l'état enregistré dans le navigateur et revient au splash).

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

- **Splash** (`SplashCarouselPage`, état `onboardingProvider (seen: bool)`) : carrousel d'authentification du prototype web porté en plein écran mobile, 4 diapositives avec les illustrations Xomiton (`prototype_social/assets/images/xomiton/`). Chaque visuel est traité en **texture** (flou léger, plein cadre) sous un **voile net** et une version nette au centre, pour que le texte superposé reste lisible en portrait quel que soit le format de l'image. Balayage, flèches du clavier, points, « Passer ». Une fois le carrousel terminé, `seen = true` est mémorisé : au lancement suivant, la redirection se fait directement vers la connexion.
- **OTP par e-mail** (pas par SMS) : couverture universelle, coût nul, aucune dépendance opérateur. Le même écran sert la connexion, l'inscription et la réinitialisation via le paramètre `purpose`. Six cases, collage du code complet, `autocomplete="one-time-code"`, renvoi après 30 s. Démo : n'importe quel code à 6 chiffres est accepté, sauf `000000`.
- **Inscription** en 4 phases : compte (pseudonyme, e-mail, mot de passe avec **jauge de force** et règles affichées), profil (facultatif), sécurité (facultatif), puis **consentements RGPD** en cases distinctes, jamais pré-cochées : conditions d'utilisation et confidentialité obligatoires, données sensibles et newsletter facultatives. Seule la phase 1 est obligatoire (décision D4 : inscription simplifiée).
- **Mot de passe oublié** : code par e-mail, réponse volontairement indifférenciée (pas d'énumération de comptes), puis choix d'un nouveau mot de passe.

## Autres parcours à essayer

- **SOS** : maintenir le bouton 2 s (anneau de progression) → compte à rebours de 5 s annulable → « Ton alerte est partie », personnes prévenues, « Je suis en sécurité ». Alternatives : touche Espace maintenue, ou double appui puis confirmation.
- **Signalement** : 4 étapes avec brouillon conservé, récapitulatif, code de suivi à copier, retrouvé ensuite dans « Signaler et suivre ».
- **Fil** : j'aime, vote à un sondage (et retrait du vote), filtres, commentaires, création d'une publication ou d'un sondage (2 à 6 réponses), qui apparaît en tête du fil.
- **Messages** : envoi d'un message et réponse simulée ; aperçus masqués si l'option de sécurité est active.
- **Paramètres** : thème clair ou sombre appliqué immédiatement, langue, déconnexion.

## Fichiers

| Chemin | Rôle |
|---|---|
| `index.html` | Simulateur construit, fichier unique (≈ 330 Ko). **Ne pas modifier à la main.** |
| `src/simulateur.html` | Source lisible (HTML, CSS, JavaScript sans dépendance). Les images y sont référencées par `{{IMG:fichier}}`. |
| `src/images/` | Visuels du splash (JPEG 720 px recompressés) et logos SVG du design system. |
| `build.py` | Reconstruit `index.html` en remplaçant chaque `{{IMG:…}}` par une data URI : `python3 build.py`. |

## Conformité au design system

Couleurs, typographie (Montserrat pour les titres, Arial pour le texte), rayons, cibles de 44 px minimum et thème sombre repris de `design-system/tokens/tokens.css` ; rouge réservé au SOS, aux erreurs et à la suppression ; focus visible ; `prefers-reduced-motion` respecté ; libellés visibles sur tous les champs ; aucune icône sans libellé accessible. La sortie rapide n'existe plus (décision D8).
