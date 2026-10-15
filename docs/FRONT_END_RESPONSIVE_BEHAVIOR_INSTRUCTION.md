---
name: xomiton-front-end-responsive-behavior
description: Comportements responsives de Xomiton composant par composant (en-tête, offcanvas, sidenav, cartes, tableaux en cartes, KPI en accordéon, modales plein écran, formulaires, barre d'onglets, SOS) selon les points de rupture Bootstrap. À charger avant d'écrire du CSS ou des classes de grille.
---

# Comportements responsives

Points de rupture (Bootstrap 5.3) : **xs** < 576 · **sm** ≥ 576 · **md** ≥ 768 · **lg** ≥ 992 · **xl** ≥ 1200 · **xxl** ≥ 1400. Largeur minimale supportée : 320 px. Gabarits de test : 360, 768, 1024, 1440 px.

| Composant | < 768 px (mobile) | 768–991 px (tablette) | ≥ 992 px (laptop, desktop) |
|---|---|---|---|
| En-tête public | Logo + burger → **offcanvas** (menus en accordéon) | Idem | Menus déroulants, boutons visibles |
| Sidenav membre | Bouton « Menu » → offcanvas gauche | Idem | Colonne `col-lg-3` |
| Colonne droite membre | Sous le contenu, 1 colonne | 2 colonnes (`col-sm-6`) | Colonne `col-lg-3` |
| Hero public | Texte puis image, CTA pleine largeur empilés | Texte puis image | Deux colonnes, hauteur `clamp(520px, 78vh, 780px)` |
| Cartes (grille) | 1 par ligne (2 pour les accès rapides) | 2 par ligne | 3 ou 4 par ligne |
| KPI | **Accordéon** « Indicateurs clés » (fermé, premier KPI en résumé) | 2 par ligne | 4 par ligne (`col-xl-3`) |
| Tableaux de données | **Cartes empilées** (libellé de colonne répété, actions en bas) | Tableau avec défilement horizontal maîtrisé (`.xm-table-wrap`) | Tableau complet |
| Barre de filtres | Recherche pleine largeur, filtres sur 2 colonnes | 3 à 4 colonnes | Une ligne |
| Modales CRUD | **Plein écran** (`modal-fullscreen-sm-down`), pied fixe | `modal-lg` | `modal-lg` / `modal-xl` |
| Éditeur riche + variables | Variables sous l'éditeur | Idem | Variables en colonne droite |
| Accordéons (FAQ, fiches) | Un seul ouvert à la fois | Idem | Idem |
| Pied de page | 1 colonne, puis 2 pour les listes de liens | 2 colonnes | 4 colonnes |
| Barre d'onglets (app) | 5 entrées, SOS au centre | — | — |
| Bouton SOS flottant | Bas droite, 64 px, au-dessus de la barre d'onglets | Bas droite | Bas droite |
| Administration | Barre latérale repliée, recherche globale en icône | Idem | Barre latérale fixe 260 px |

Règles :
- Pas de défilement horizontal de page à aucune largeur.
- Cibles tactiles ≥ 44 × 44 px, espacées d'au moins 8 px.
- Texte ≥ 16 px dans les champs (évite le zoom iOS).
- Les images changent de source (`srcset`) plutôt que de taille CSS seule.
- Aucune information disponible uniquement au survol.
