# Produire les visuels Google Play (image de présentation et captures) : méthode réutilisable

Ce guide explique comment les visuels de Xomiton ont été produits et comment refaire la même chose pour **un autre projet**. Le générateur (qui délègue au kit [`tools/playstore-kit/`](../../tools/playstore-kit/README.md)) et sa configuration sont dans ce dossier : [build_playstore.py](build_playstore.py) et [visuels.json](visuels.json). Les visuels produits sont dans `assets/` ; la fiche complète est dans [PUBLICATION_PLAY_STORE.md](PUBLICATION_PLAY_STORE.md).

> **Captures provisoires : à remplacer par de vraies captures de l'application avant soumission à Google, qui exige des captures de l'application elle-même.** Les captures actuelles montrent le **site web** Xomiton (Laravel, thème clair) à largeur mobile 390 px, pas l'application Flutter (non compilable ici). Le vrai logo couleur et le vrai slogan du site (« Espace safe d'apprentissage et d'appui holistique ») sont utilisés ; fond clair teinté ; aucun thème sombre.

> Aucun « skill » spécifique à Google Play n'existe ici : les visuels viennent d'un **script versionné**, qui rend la production reproductible et contrôlable. Les skills listés au §5 aident au graphisme et au texte, pas à la conformité, qui se vérifie par code.

## 1. Résultat attendu

| Visuel | Format | Fichier produit |
|---|---|---|
| Image de présentation (feature graphic) | 1024 × 500 px, JPEG ou PNG 24 bits **sans canal alpha** | `assets/feature-graphic-1024x500.png` |
| Captures d'écran téléphone (8) | 1080 × 1920 px (9:16), PNG 24 bits sans alpha | `assets/screenshots/01-….png` à `08-….png` |
| Icône 512 × 512 | PNG 32 bits, carré plein | `assets/icon-512.png` |
| Rapport de contrôles | JSON | `assets/rapport-controles.json` |

## 2. Règles Google Play que le script vérifie

- **Image de présentation** : exactement 1024 × 500, sans alpha ; logo, nom et slogan **au centre** (les bords peuvent être rognés selon les formats) ; pas de blanc pur, de noir ni de gris foncé dominants (un fond clair teinté de bleu convient) ; différente de l'icône ; pas de cadre d'un appareil précis.
- **Captures** : au moins 4 pour être éligible aux recommandations (jusqu'à 8 par type d'appareil), 9:16, 1080 × 1920 au minimum pour ce cas ; les trois premières centrées sur l'interface ; barre d'état propre (batterie pleine, réseau plein, pas d'opérateur ni de notification).
- **Texte** : slogans seulement s'ils sont utiles ; **20 % de l'image au plus** ; police lisible ; contraste AA.
- **Mots interdits** : « meilleur », « n° 1 », « le plus populaire », « nouveau », « gratuit », « remise », « millions de téléchargements » ; aucun appel à l'action (« Télécharger », « Installer », « Jouer », « Essayer »). Aucun badge Google Play ni logo tiers.
- **Texte alternatif** : un par visuel, 140 caractères au plus, sans « image de » ni « photo de ».
- **Localisation** : images et texte dans la langue du marché (Xomiton : français seulement ; les blocs `en` de `visuels.json` sont un gabarit vide, que le script refuse de générer tant qu'ils ne sont pas remplis).

## 3. Comment les visuels sont produits

1. **Capturer le site réel** (`capturer_site.py`) avec Playwright (Chromium) en `colorScheme: 'light'`, à 390 × 844 en échelle 3 (soit 1170 × 2532), site Laravel lancé en local sur une base temporaire (variables d'environnement, rien dans le dépôt), sans bandeau de débogage ni identifiant de démonstration, puis arrêt du serveur. Les images sont dans `sources/`. Pour l'application, remplacer ces images par des captures d'émulateur (la barre d'état propre se règle par `barre_etat.mode: recouvrir`).
2. **Composer** chaque visuel en HTML/CSS : fond clair en très léger dégradé bleu tiré des jetons du design system (`blue-50`, `blue-100`), titre court en bleu de la charte en haut, capture au centre ; pour l'image de présentation, vrai logo couleur grand à gauche, vrai slogan, une page web et un écran mobile en bordure.
3. **Exporter** par capture d'écran de la page de composition, puis conversion par Pillow en RVB 24 bits sans alpha ; l'icône 512 reste en PNG 32 bits.
4. **Configurer tout dans `visuels.json`** (titres, textes alternatifs, source des écrans, couleurs, contraintes) pour régénérer en une commande quand l'interface change.
5. **Vérifier par code** : dimensions, absence d'alpha, poids (moins de 8 Mo, ici moins de 0,6 Mo par fichier), ratio 9:16, part du texte, contraste AA mesuré sur le fond réel, mots interdits, longueur des textes alternatifs, couleurs vives, barre d'état. 133 contrôles passent pour Xomiton (fiche et icône comprises). Le seuil de « couleurs vives » a été ajusté pour un fond clair teinté : il garde l'interdiction du blanc pur, du noir et du gris foncé (saturation ≥ 0,05, valeur de 0,25 à 0,99), voir PUBLICATION_PLAY_STORE.md §10.
6. **Regarder chaque image** avant de livrer : c'est ainsi que le choix des écrans et leur recadrage ont été ajustés.

### Commandes

```bash
pip install playwright pillow            # Chromium : variable XM_CHROMIUM si besoin
python3 docs/playstore/build_playstore.py                 # tout produire et vérifier
python3 docs/playstore/build_playstore.py --verifier      # vérifier seulement
python3 docs/playstore/build_playstore.py --seulement 03-sos   # une seule capture
python3 docs/playstore/capturer_site.py                   # refaire les captures du site lancé en local
```

Le code de sortie est 1 si un contrôle échoue.

## 4. Reprendre la méthode dans un autre projet

> **Kit générique prêt à copier** : le dossier [`tools/playstore-kit/`](../../tools/playstore-kit/README.md) reprend cette méthode sans aucune référence à Xomiton (configuration JSON unique, source HTML ou captures d'émulateur, mêmes contrôles automatiques, projet exemple, tests). Pour un autre projet, **copier ce dossier** plutôt que `build_playstore.py` : voir [tools/playstore-kit/README.md](../../tools/playstore-kit/README.md). Les étapes ci-dessous restent valables pour reprendre le script de Xomiton tel quel.

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

**Pour Xomiton**, les captures actuelles viennent du **site web** à largeur mobile, faute d'application compilée : elles sont **provisoires** et à refaire avec de **vraies captures de l'application** (émulateur Android) avant soumission à Google (`build_playstore.py` les recompose en une commande).

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

Point de départ conseillé : copier le kit [`tools/playstore-kit/`](../../tools/playstore-kit/README.md) dans le nouveau projet et le citer dans la demande (`--init` crée un projet exemple prêt à lancer).

> Produis le dossier de visuels Google Play : une image de présentation 1024 × 500 et 8 captures 1080 × 1920, en PNG 24 bits sans alpha, à partir de la charte du projet. Capture les écrans de l'application avec Playwright, compose-les sur fond de marque (titre court en haut, capture, éléments flottants légers), génère tout par un script reproductible configuré par un fichier JSON, et vérifie automatiquement les règles Google : dimensions, absence d'alpha, texte limité à 20 % de l'image, mots interdits, textes alternatifs de 140 caractères au plus, contraste AA. Regarde chaque image et corrige avant de livrer.

## 7. Points de vigilance propres au public de Xomiton

- **Discrétion** : aucune photo de personne, aucun drapeau envahissant, aucun mot stigmatisant dans le nom visible ni dans la description courte de la fiche ; valeur proposée par le slogan officiel du site, « Espace safe d'apprentissage et d'appui holistique ».
- **Données** : captures d'un site lancé sur une base de démonstration locale, sans donnée réelle ni identifiant affiché ; pas d'avatar photo (les pages d'espace membre ne sont pas utilisées).
- **Espaces par rôle** : l'espace admin n'est pas promu sur la fiche publique ; aucune capture de l'espace membre ni de l'espace pair n'est utilisée tant que l'application réelle n'est pas capturée.
- **Droits d'image** : conserver hors dépôt la preuve de licence des images utilisées ailleurs (onboarding, illustrations).
