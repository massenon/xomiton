---
name: xomiton-front-end-responsive
description: Contrat responsive de Xomiton par classe d'appareil (desktop, laptop, tablette, mobile) — burger, menu offcanvas, accordéons, cartes, pied de page, hero — à respecter exactement comme dans le prototype. À charger pour toute vérification visuelle multi-écrans.
---

# Contrat responsive

**Tout doit rester identique au prototype `prototype_social/` à chaque largeur.** Ce document liste ce qu'il faut vérifier.

| Appareil | Plage | Gabarit de test |
|---|---|---|
| Desktop | ≥ 1200 px | 1440 × 900 |
| Laptop | 992–1199 px | 1024 × 768 |
| Tablette | 768–991 px | 768 × 1024 |
| Mobile | < 768 px | 360 × 800 (et 390 × 844) |

## Desktop et laptop
- En-tête complet, menus déroulants, recherche visible.
- Espace membre en 3 colonnes ; administration avec barre latérale fixe.
- Hero en deux colonnes ; sections à 3 ou 4 cartes par ligne ; pied de page en 4 colonnes.

## Tablette
- **Burger** et **menu offcanvas** remplacent les menus (< 992 px).
- Sidenav membre en offcanvas « Menu » ; colonne droite sous le contenu.
- Cartes 2 par ligne ; KPI 2 par ligne ; tableaux défilants dans leur carte.

## Mobile
- Burger + offcanvas ; liens du menu regroupés en **accordéons**.
- **Hero** : titre, texte, CTA empilés pleine largeur, image après le texte.
- **Cartes** : une par ligne (accès rapides 2 par ligne).
- **KPI** : accordéon « Indicateurs clés ».
- **Tableaux** : cartes empilées.
- **Modales** : plein écran, pied fixe.
- **Pied de page** : colonnes empilées, formulaire newsletter pleine largeur, champ 44 px.
- SOS flottant toujours visible.

## Check-list de recette
- [ ] Mêmes sections, même ordre, mêmes CTA qu'au prototype aux 4 largeurs.
- [ ] Burger : ouverture, fermeture (bouton, Échap, clic hors menu), focus piégé dans l'offcanvas.
- [ ] Accordéons : un seul ouvert, `aria-expanded` à jour.
- [ ] Cartes : hauteurs égales par ligne (`h-100`), images au bon ratio.
- [ ] Aucun texte tronqué sans `title` ou mécanisme d'affichage complet.
- [ ] Aucun défilement horizontal.
