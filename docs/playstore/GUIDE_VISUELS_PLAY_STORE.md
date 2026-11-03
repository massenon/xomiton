# Produire les visuels Google Play (image de présentation et captures) : méthode réutilisable

Ce guide explique comment les visuels de Xomiton ont été produits et comment refaire la même chose pour **un autre projet**. Le générateur et sa configuration sont dans ce dossier : [build_playstore.py](build_playstore.py) et [visuels.json](visuels.json). Les visuels produits sont dans `assets/` ; la fiche complète est dans [PUBLICATION_PLAY_STORE.md](PUBLICATION_PLAY_STORE.md).

> Aucun « skill » spécifique à Google Play n'existe ici : les visuels viennent d'un **script versionné**, qui rend la production reproductible et contrôlable. Les skills listés au §5 aident au graphisme et au texte, pas à la conformité, qui se vérifie par code.

## 1. Résultat attendu

| Visuel | Format | Fichier produit |
|---|---|---|
| Image de présentation (feature graphic) | 1024 × 500 px, JPEG ou PNG 24 bits **sans canal alpha** | `assets/feature-graphic-1024x500.png` |
| Captures d'écran téléphone (8) | 1080 × 1920 px (9:16), PNG 24 bits sans alpha | `assets/screenshots/01-….png` à `08-….png` |
| Rapport de contrôles | JSON | `assets/rapport-controles.json` |

## 2. Règles Google Play que le script vérifie

- **Image de présentation** : exactement 1024 × 500, sans alpha ; logo, nom et slogan **au centre** (les bords peuvent être rognés selon les formats) ; couleurs vives, pas de blanc pur, de noir ni de gris foncé dominants ; différente de l'icône ; pas de cadre d'un appareil précis.
- **Captures** : au moins 4 pour être éligible aux recommandations (jusqu'à 8 par type d'appareil), 9:16, 1080 × 1920 au minimum pour ce cas ; les trois premières centrées sur l'interface ; barre d'état propre (batterie pleine, réseau plein, pas d'opérateur ni de notification).
- **Texte** : slogans seulement s'ils sont utiles ; **20 % de l'image au plus** ; police lisible ; contraste AA.
- **Mots interdits** : « meilleur », « n° 1 », « le plus populaire », « nouveau », « gratuit », « remise », « millions de téléchargements » ; aucun appel à l'action (« Télécharger », « Installer », « Jouer », « Essayer »). Aucun badge Google Play ni logo tiers.
- **Texte alternatif** : un par visuel, 140 caractères au plus, sans « image de » ni « photo de ».
- **Localisation** : images et texte dans la langue du marché (Xomiton : français seulement ; les blocs `en` de `visuels.json` sont un gabarit vide, que le script refuse de générer tant qu'ils ne sont pas remplis).

## 3. Comment les visuels sont produits

1. **Capturer l'interface réelle** avec Playwright (Chromium), à 390 × 844 en échelle 3 (soit 1170 × 2532), avec une barre d'état figée à 9:41, batterie pleine, sans opérateur, et des données fictives pseudonymisées (la table `remplacements` de `visuels.json` masque les noms propres).
2. **Composer** chaque visuel en HTML/CSS : fond en dégradé tiré des couleurs de la marque (jetons du design system), titre court en haut, capture au centre, pastilles flottantes légères ; pour l'image de présentation, logo et slogan au centre, deux écrans en bordure.
3. **Exporter** par capture d'écran de la page de composition, puis conversion par Pillow en RVB 24 bits sans alpha.
4. **Configurer tout dans `visuels.json`** (titres, textes alternatifs, source des écrans, couleurs, contraintes) pour régénérer en une commande quand l'interface change.
5. **Vérifier par code** : dimensions, absence d'alpha, poids (moins de 8 Mo, ici moins de 0,6 Mo par fichier), ratio 9:16, part du texte, contraste AA mesuré sur le fond réel, mots interdits, longueur des textes alternatifs, couleurs vives, barre d'état. 130 contrôles passent pour Xomiton.
6. **Regarder chaque image** avant de livrer : c'est ainsi que le placement des pastilles et des titres a été ajusté.

### Commandes

```bash
pip install playwright pillow            # Chromium : variable XM_CHROMIUM si besoin
python3 docs/playstore/build_playstore.py                 # tout produire et vérifier
python3 docs/playstore/build_playstore.py --verifier      # vérifier seulement
python3 docs/playstore/build_playstore.py --seulement 02-sos   # une seule capture
```

Le code de sortie est 1 si un contrôle échoue.

## 4. Reprendre la méthode dans un autre projet

1. **Copier** `build_playstore.py` et `visuels.json` dans le nouveau projet (par exemple `docs/playstore/`).
2. **Adapter le début de `visuels.json`** :

   | Champ | À mettre |
   |---|---|
   | `application` | nom de l'application |
   | `tokens_css` | fichier de variables de couleurs du projet |
   | `police_montserrat` | police de la marque (fichier woff2) |
   | `logo_blanc` | logo blanc en SVG |
   | `prototype` | page HTML ou URL qui contient les écrans à capturer |
   | `capture` | taille de la fenêtre, échelle, heure affichée, état de départ |
   | `remplacements` | noms propres à masquer dans les captures |

3. **Décrire chaque capture** dans `captures_ecrans` : identifiant, fichier de sortie, écran source, fond, pastilles flottantes, titre.
4. **Décrire l'image de présentation** dans `feature_graphic` : fond, halos, écrans en bordure, slogan central.
5. **Lancer** le script, **regarder** les images, **ajuster** le placement dans `visuels.json`, recommencer.

**Si le projet n'a pas de prototype HTML** :
- soit prendre de vraies captures de l'émulateur Android (`adb exec-out screencap -p`, après avoir figé l'heure à 9:41 et la batterie pleine) et les utiliser comme source des écrans ;
- soit construire une page HTML qui reproduit l'interface, puis la capturer avec Playwright à 390 × 844 en échelle 3.

**Pour Xomiton**, les captures actuelles viennent du **prototype mobile**, faute d'application compilée : il faudra les refaire avec de **vraies captures** de l'application dès qu'elle tourne (`build_playstore.py` les recompose en une commande).

## 5. Skills utiles

| Besoin | Skill |
|---|---|
| Composition de l'image de présentation | `anthropic-skills:canvas-design` |
| Cohérence avec la charte (couleurs, polices, logo) | `design:design-system`, `anthropic-skills:brand-guidelines` |
| Titres courts des captures et descriptions de la fiche | `design:ux-copy` |
| Contraste et textes alternatifs | `design:accessibility-review` |
| Prévisualiser ou composer dans une page HTML | `anthropic-skills:web-artifacts-builder` |

Ces skills n'automatisent pas la conformité : elle reste vérifiée par le script.

## 6. Cahier des charges à confier à un agent pour un autre projet

> Produis le dossier de visuels Google Play : une image de présentation 1024 × 500 et 8 captures 1080 × 1920, en PNG 24 bits sans alpha, à partir de la charte du projet. Capture les écrans de l'application avec Playwright, compose-les sur fond de marque (titre court en haut, capture, éléments flottants légers), génère tout par un script reproductible configuré par un fichier JSON, et vérifie automatiquement les règles Google : dimensions, absence d'alpha, texte limité à 20 % de l'image, mots interdits, textes alternatifs de 140 caractères au plus, contraste AA. Regarde chaque image et corrige avant de livrer.

## 7. Points de vigilance propres au public de Xomiton

- **Discrétion** : aucune photo de personne, aucun drapeau envahissant, aucun mot stigmatisant dans le nom visible ni dans la description courte de la fiche ; valeur proposée décrite par l'écoute, la sécurité, la communauté et les ressources.
- **Données** : captures avec données fictives et noms de famille réduits à l'initiale.
- **Espaces par rôle** : l'espace admin n'est pas promu sur la fiche publique ; le pair n'apparaît que comme interlocuteur dans la capture Messages.
- **Droits d'image** : conserver hors dépôt la preuve de licence des images utilisées ailleurs (onboarding, illustrations).
