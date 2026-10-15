---
name: xomiton-front-end-section-order
description: Ordre exact des sections de chaque page Xomiton (accueil, fil, thématiques, ressources, signalement, SOS, profil, écrans d'administration), repris du prototype. À charger avant de construire ou réorganiser une page ; aucune section ne doit être déplacée sans décision consignée.
---

# Ordre des sections

## 1. Accueil public (`landing.php`)
| # | Section | Ancre | Titre |
|---|---|---|---|
| 1 | Hero | — | « Xomiton : Votre Espace safe d'apprentissage et d'appui holistique » |
| 2 | Valeurs | `#valeurs` | « Un soutien à portée de main » (3 cartes) |
| 3 | Je me pose des questions / Je veux témoigner | `#questions` | « Je me pose des questions » + bloc signalement + 3 services |
| 4 | Réseau social | `#reseau` | « Échangez, partagez, grandissez ensemble » (6 fonctions) |
| 5 | Ressources | `#ressources` | « Apprendre pour mieux se protéger et décider » |
| 6 | Alerte et signalement | `#alerte` | « En danger ? Besoin d'aide ? Alertez en quelques secondes. » |
| 7 | Expert·es | `#assistant` | « Nos Expert.e.s sont prêt.e.s à vous aider » |
| 8 | Do Glegbe | `#fonctionnalites` | Hygiène menstruelle, droit fondamental |
| 9 | FAQ | `#faq` | « Vous vous demandez… » |
| 10 | CTA final | — | « Prête à nous rejoindre ? » |
| 11 | Pied de page | `#newsletter` | Marque + SOS · Plateforme · Aide & protection · **Newsletter** |

## 2. Fil membre (`index.php`)
Titre masqué « Mon fil d'actualité » → messages flash → accès rapides (SOS, Signaler, Assistant, Urgences) → **Créer une publication** (Photo, Événement, Sondage, Violences, Signaler) → À la une (thématiques) → filtres → publications → « Charger plus ». Colonne droite : Qui suivre → Événements → En cas d'urgence.

## 3. Thématique (fiche)
Fil d'Ariane → image → catégorie + durée → `h1` → résumé → avertissement de contenu → sections en accordéon → contacts liés → « J'ai besoin d'aide » → thématiques liées.

## 4. Ressources
`h1` + introduction → recherche et filtres → liste (« Charger plus ») → FAQ de la catégorie → CTA assistant.

## 5. Signalement / SOS (gabarit sobre)
Signalement : encadré anonymat → stepper → une question par écran → récapitulatif → code de suivi.
SOS : consigne → bouton → partage de position → compte à rebours → confirmation et personnes prévenues → appel du contact principal → « Je suis en sécurité ».

## 6. Profil
Couverture → avatar, nom, pseudo, zone → statistiques → complétion → onglets (Activité, À propos, Connexions, Médias, Événements).

## 7. Écran de gestion (administration)
Barre supérieure (titre, actions) → KPI → onglets éventuels → carte liste (en-tête, filtres, actions groupées, tableau, pagination) → cartes secondaires (graphiques, configuration, journal) → modales.

Exemples :
- **Mises à jour** : KPI → [Historique des versions · Publier ou planifier] | [Fenêtre de maintenance · **Sauvegardes**] → Optimisation → Pages les plus utilisées · Adoption.
- **Templates e-mails** : KPI → liste des templates → [Serveur SMTP · Derniers envois].
- **Newsletter** : KPI → onglets Statistiques · Abonné·es · Campagnes.
