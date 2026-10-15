# Surcharge — Accueil (`/`)

> Prime sur MASTER.md pour cette page. Motif : « Feature-Rich Showcase » (recommandation du skill) adapté à l'ordre des sections de la vitrine actuelle, qui **est conservé**.

**Objectif de page** : rassurer, puis amener à deux actions : *s'inscrire* et *signaler un abus*. **Densité** : standard (espacement 16-64 px). **Variance** : moyenne (illustration à droite, texte à gauche). **Mouvement** : subtil (apparition 200 ms, aucune animation de défilement obligatoire).

| # | Section (ordre figé) | Spécificités design |
|---|---|---|
| 1 | Hero | `.xm-hero`, illustration `hero-09.jpg` en `<picture>` (AVIF/WebP), panneau translucide à contraste ≥ 7:1 ; `h1` « Xomiton : votre espace safe d'apprentissage et d'appui holistique » ; CTA primaire « Rejoignez-nous » **et** CTA rouge « Signaler un abus » (un seul rouge) ; citation de témoignage sans note en étoiles (aucun faux score) |
| 2 | « Un soutien à portée de main » | 3 piliers (ressources, communauté, confidentialité) : icône SVG 64 px, `h3`, 2 lignes |
| 3 | « Je me pose des questions » | Colonne texte + bouton noir/primaire ; carte « Je veux témoigner ou signaler » avec mockup mobile ; deux cartes 1/3 |
| 4 | « Renforcez la communauté » | Image ronde + bulles de notification décoratives (`aria-hidden`), texte, CTA primaire |
| 5 | « Nos expert·e·s » | Image + 2 arguments, CTA « Tchatter avec nous » (offcanvas chat) |
| 6 | Appel à l'application | Boutons stores + « Gratuit · 100 % sécurisé » |
| + | FAQ SEO (nouveau) | Accordéon de 5-6 questions, JSON-LD `FAQPage` |

**Contraintes** : image LCP = illustration du hero (préchargée, `fetchpriority="high"`) ; hauteur du hero fluide ; **supprimer** `height: 936px`, `background-size: 100%` et les styles inline ; décor flou (`elements/*.svg`) uniquement ≥ 992 px, `aria-hidden`, `loading="lazy"` ; « Quitter ce site » en haut sur mobile ; SOS flottant présent.
**Interdit sur cette page** : plus de 2 éléments rouges visibles simultanément (CTA signaler + SOS).
