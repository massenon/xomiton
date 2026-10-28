# Spec — Prototype mobile v2 : navigation, splash plein écran, âge 15 ans

Statut : implémenté (2026-10-28) ; reste à valider en recette humaine (`docs/CHECKLIST_RECETTE.md` MOB-02, MOB-11 à MOB-13) et à lever le point « droits d'image » du §6. P1 : dernière slide mémorisée et filtre du menu faits ; P2 non fait. Périmètre : `prototype_mobile/` (HTML) et, pour le splash et l'âge, l'application Flutter `mobile/`.

## 1. Problème
Le simulateur mobile (`prototype_mobile/index.html`) liste les écrans dans une colonne fixe : impossible de la replier, les écrans de l'espace pair et de l'admin d'astreinte n'y figurent pas, et une bascule de thème y est intégrée alors que le thème par défaut doit être clair. Le splash d'accueil n'est pas un carrousel plein écran avec photos. Le champ « Créer une publication » est mal proportionné. La règle d'âge minimal (15 ans) n'est pas visible dans le prototype d'inscription.

## 2. Objectifs
1. Le/la client·e parcourt tous les écrans (membre, pair, admin) depuis un menu repliable, en moins de 2 clics.
2. Le splash donne envie de s'inscrire : photos plein écran, 4 slides, aucune friction pour passer.
3. L'âge minimal est identique dans le prototype, l'app Flutter et le serveur (15 ans).
4. Aucun écran existant ne régresse (30 écrans du simulateur).

## 3. Hors périmètre
- Bascule de thème dans le prototype (retirée ; thème clair par défaut, le thème sombre reste géré par le système).
- Nouvelle charte graphique ; vidéo/animation lourde sur le splash.
- Collecte d'une date de naissance (non retenu : minimisation).

## 4. Récits utilisateur
- En tant que client·e, je veux masquer/afficher la liste des écrans pour voir le téléphone seul ou naviguer rapidement.
- En tant que client·e, je veux retrouver les écrans « Espace pair » et « Admin mobile » dans le même menu, par module.
- En tant que nouvelle utilisatrice, je veux voir un carrousel plein écran que je peux passer d'un geste ou avec « Passer ».
- En tant que mineure de moins de 15 ans, je dois être informée que l'inscription m'est refusée.
- En tant que rédactrice, je veux un champ de publication à la bonne taille, sans débordement.

## 5. Exigences

### P0
**R1 — Menu repliable.** Un bouton (☰) affiche/masque la liste des écrans ; état mémorisé (localStorage, avec repli sans erreur). Liste groupée par module (Démarrage, Tableau de bord, SOS & signalement, Fil d'actualités, Communauté, Profil/Paramètres, **Espace pair**, **Admin mobile**), chaque entrée = numéro, titre, ancre (`#/fil`…). Les modules sont eux-mêmes repliables.
- Given le menu ouvert, When je clique ☰, Then la liste se masque et le téléphone se centre ; Then un second clic la restaure.
- Les écrans de `espace-pair.html` et `admin-mobile.html` sont atteignables depuis ce menu (liens ou intégration).
- Au premier chargement : thème clair, aucun sélecteur de thème dans l'interface.

**R2 — Splash carrousel plein écran** (`SplashCarouselPage` Flutter + écran du prototype). Structure : barre supérieure avec « Passer » ; canevas média photo en plein écran (cover) avec dégradé sombre pour la lisibilité ; titre court + sous-titre ; indicateurs en points centrés ; zone d'action « Suivant », puis « Créer un compte » + « Se connecter » sur la dernière slide.
- 4 slides avec les photos fournies (`prototype_mobile/src/images/splash/`, WebP redimensionné, ≤ 150 Ko chacune) ; texte alternatif ; défilement au doigt, points cliquables ; « Passer » mène à l'accueil sans perdre le choix de langue.
- Contraste texte/photo AA (dégradé), cibles ≥ 44 px, `prefers-reduced-motion` respecté.

**R3 — Âge minimal 15 ans** sur l'écran d'inscription du prototype : case obligatoire « J'ai 15 ans ou plus », bouton désactivé tant qu'elle n'est pas cochée, message de refus bienveillant et orienté vers les ressources si l'on déclare moins de 15 ans. Flutter et serveur : déjà appliqués (`age_confirmed`), valeur centrale `xomiton.accounts.min_age`.

**R4 — Champ « Créer une publication ».** Le textarea occupe la largeur utile à côté de l'avatar (flex, `min-width:0`), hauteur minimale 120 px avec croissance automatique, sans dépassement du châssis 390 px.

### P1
- Mémoriser la dernière slide vue ; recherche dans le menu des écrans.

### P2
- Mode « présentation » plein écran du simulateur ; génération du menu depuis un fichier de données unique.

## 6. Risques et points à valider
- **Droits d'image (bloquant avant publication)** : les 4 photos viennent de sources externes (une porte un filigrane d'outil IA, une autre un badge de plateforme). Il faut la licence ou la preuve d'un droit d'usage commercial, et le consentement des personnes si ce sont de vraies photos. À défaut, utiliser des illustrations de la charte.
- **Discrétion** : un splash avec drapeaux arc-en-ciel peut exposer une utilisatrice si un tiers voit son téléphone. À arbitrer avec le mode discret (visuel neutre possible) — décision client.
- Poids : 4 photos plein écran dans l'app (~0,5 Mo au total en WebP) : acceptable ; à confirmer pour le public réseau lent.

## 7. Métriques
- Taux de passage du splash (« Passer » vs inscription) ; abandon à l'inscription au refus d'âge ; temps pour atteindre un écran donné dans le prototype (< 10 s en recette).

## 8. Calendrier
Une itération : prototype (R1, R3, R4) puis splash prototype + Flutter (R2). Recette humaine via `docs/CHECKLIST_RECETTE.md`.
