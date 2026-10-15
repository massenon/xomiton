---
name: xomiton-front-end-layout
description: Gabarits de mise en page de Xomiton (public, membre, pair éducateur·rice, authentification, administration, pages sobres SOS / signalement) avec leurs grilles, conteneurs et zones fixes. À charger avant de créer une page ou un layout Blade / Vue / Livewire.
---

# Gabarits de mise en page

Tous les gabarits reprennent exactement ceux de `prototype_social/` ([FINAL_DESIGN_DIRECTION.md](FINAL_DESIGN_DIRECTION.md)).

## 1. Gabarit public — `layouts/public.blade.php`
```
<body>
  lien d'évitement « Aller au contenu »
  bandeau hors connexion (.xm-offline-bar, masqué par défaut)
  <header> public sticky (logo · Apprendre ▾ · Communauté ▾ · Aide & protection ▾ · Signaler · Se connecter · Créer un compte)
  <main id="contenu" tabindex="-1"> sections pleine largeur, contenu en .container </main>
  <footer class="xm-pub-footer"> 4 colonnes : marque + SOS · Plateforme · Aide & protection · Newsletter </footer>
  bouton SOS flottant (si connecté·e)
</body>
```
Réf. : `landing.php`, `partials/public-header.php`, `partials/public-footer.php`.

## 2. Gabarit membre — `layouts/member` (Vue, coquille Blade)
```
header connecté (logo · recherche · SOS · messages · réglages · notifications · avatar)
<main class="container"><div class="row g-4">
  col-lg-3      sidenav (carte profil + liens) — offcanvas < 992 px
  col-md-8 col-lg-6   contenu (vstack gap-4)
  col-lg-3      colonne droite (Qui suivre, Événements, Urgence) — sous le contenu < 992 px
</div></main>
modales globales (créer une publication, sondage, événement)
```
Pages à deux colonnes (profil, paramètres) : `col-lg-3` + `col-lg-9` (`x_pub_open` en mode membre).

## 3. Gabarit pair éducateur·rice
Même coquille que le membre ; la `sidenav` affiche le groupe « Espace pair » (Espace pair, Collecte) ; le tableau de bord pair occupe `col-lg-9`.

## 4. Gabarit authentification
Plein écran, sans en-tête ni pied : `row g-0 min-vh-100` → `col-lg-6` illustration (`xm-auth-side`, masquée < 992 px) + `col-lg-6` formulaire centré (440–520 px). Pas de bouton SOS flottant.

## 5. Gabarit sobre (SOS, signalement, urgences)
Une colonne, 640 px max, fond uni, aucune illustration arc-en-ciel, aucun décor (`design-system/xomiton/pages/signaler-et-sos.md`). En mode visiteur : en-tête public minimal (logo, Urgences, Se connecter, Créer un compte).

## 6. Gabarit administration — `layouts/admin.blade.php` (Livewire)
```
<aside .xm-admin-sidebar>  logo + badge Admin · groupes de menu · raccourcis
<div .xm-admin-main>
  .xm-admin-topbar  titre h1.h5 · fil d'Ariane · recherche globale · état en ligne · file de synchro · actions de page · plein écran · cloche · avatar
  .xm-admin-content  messages flash · contenu de l'écran (gabarit §4 du design system)
  <footer>  « Xomiton – Administration · version · date »
</div>
```
< 992 px : la barre latérale se replie, bouton menu dans la barre supérieure. Impression : barre latérale et barre supérieure masquées.

## 7. Zones fixes et superposition (z-index)
| Élément | z-index | Règle |
|---|---|---|
| Bouton SOS flottant | 1060 | Toujours accessible, au-dessus des modales (charte §10) |
| Modales | 1055 | Focus piégé |
| Offcanvas | 1045 | |
| En-tête sticky | 1030 | |
| Toasts | 1090 | Ne masquent jamais le SOS |

## 8. Conteneurs et espacements
- Sections publiques : `py-4 py-sm-5` (ou `lp-section`), contenu dans `.container`.
- Grilles : `row g-4` (sections), `row g-3` (KPI), `row g-2` (accès rapides).
- Cartes : `card` + `card-body` ; listes verticales : `vstack gap-4`.
