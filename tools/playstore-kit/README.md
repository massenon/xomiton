# Kit de visuels Google Play

Un script, un fichier de configuration JSON, et vous obtenez les visuels de votre fiche Google Play, **vérifiés par code** :

- une **image de présentation** 1024 × 500 ;
- jusqu'à 8 **captures d'écran téléphone** 1080 × 1920 (9:16) ;
- tous en **PNG 24 bits sans canal alpha** ;
- un **rapport JSON** de contrôles (`rapport-controles.json`) ; le code de sortie vaut 1 si un contrôle échoue.

Le kit est **autonome** : copiez le dossier `playstore-kit/` dans n'importe quel projet. Rien n'y fait référence à une application, à une police ou à une arborescence particulière ; tout vient de la configuration.

Deux sources d'écrans, au choix :

1. **une page HTML ou une URL** (prototype, application web) capturée avec Playwright : fenêtre, échelle, route, heure figée, éléments masqués, noms propres remplacés ;
2. **des images déjà capturées** (PNG de l'émulateur Android) : le kit les encadre d'un cadre neutre et recouvre la barre d'état par une barre propre.

## Installation

```bash
pip install playwright pillow        # les deux seules dépendances
playwright install chromium          # seulement si aucun Chromium ou Chrome n'est déjà installé
```

Le kit cherche le navigateur dans cet ordre :

1. la variable d'environnement `PW_CHROMIUM` ou `XM_CHROMIUM` (chemin de l'exécutable) ;
2. `PLAYWRIGHT_BROWSERS_PATH`, `/opt/pw-browsers/*`, `~/.cache/ms-playwright`, `~/Library/Caches/ms-playwright`, `%LOCALAPPDATA%\ms-playwright` ;
3. les emplacements usuels de Chromium et de Google Chrome (Linux, macOS, Windows : `Program Files`) ;
4. le Chromium installé par `playwright install chromium`.

Windows : les chemins utilisent `pathlib`, aucune commande propre à Linux n'est appelée ; remplacer `python3` par `python` ou `py`, et `export` par `set` (ou `$env:PW_CHROMIUM=...` sous PowerShell).

## Trois commandes pour démarrer

```bash
python3 build_playstore.py --init mon-projet                      # 1. crée un projet exemple prêt à lancer
# 2. éditer mon-projet/visuels.json (textes, couleurs, source des écrans)
python3 build_playstore.py --config mon-projet/visuels.json       # 3. produit et vérifie
```

Le résultat est dans `mon-projet/sortie/` : `feature-graphic-1024x500.png`, `screenshots/01-….png` à `08-….png`, `rapport-controles.json`. Le dossier `example/` du kit est cet exemple, avec ses images de référence déjà produites dans `example/sortie/` (une petite application fictive, « Grainette », sans aucune donnée réelle).

### Options

| Option | Effet |
|---|---|
| `--config FICHIER` | Fichier de configuration (défaut : `./visuels.json`). |
| `--verifier` | Vérifie les PNG déjà produits sans rien régénérer. |
| `--seulement ID [ID…]` | Ne régénère que ces visuels (`feature-graphic` ou l'`id` d'une capture). |
| `--langue CODE` | Langue des textes (défaut : `langue_defaut`). Les langues autres que la défaut sortent dans `sortie/CODE/`. |
| `--sortie DIR` | Dossier de sortie (remplace le champ `sortie`). |
| `--init DIR` | Crée un projet exemple prêt à lancer dans `DIR` (dossier neuf), puis s'arrête. |

Codes de sortie : 0 tout est conforme ; 1 au moins un contrôle échoue ; 2 configuration ou usage invalide (le message liste tous les problèmes à la fois).

## Ce que le kit produit et contrôle

La composition est faite en HTML/CSS : fond en dégradé, titre court en haut, écran dans un cadre neutre, puces flottantes, halos de couleur ; l'image de présentation montre le logo et le slogan au centre, entre deux écrans. Chaque page est photographiée par Chromium, puis convertie par Pillow en RVB sans alpha.

Contrôles automatiques (chaque résultat figure dans `rapport-controles.json`) :

| Contrôle | Règle vérifiée |
|---|---|
| Dimensions | 1024 × 500 pour l'image de présentation, 1080 × 1920 pour les captures (valeurs configurables) |
| PNG 24 bits sans alpha | profondeur 8, type de couleur RVB, aucun bloc de transparence ni de palette |
| Poids | moins de 15 Mo (image de présentation) et 8 Mo (captures) |
| Ratio 9:16 | exact pour les captures |
| Nombre de captures | égal à `contraintes.captures.nombre` |
| Aire du texte | 20 % de l'image au plus (cadres des puces compris) |
| Contraste AA | 4,5:1 au moins, mesuré au pire pixel du fond réel derrière chaque texte |
| Mots interdits | classements, promotions, appels à l'action, mentions Google (liste française et anglaise par défaut, modifiable) dans slogans, titres, puces, textes alternatifs et fiche |
| Textes alternatifs | 140 caractères au plus, sans « image de », « photo de », « capture d'écran de », « screenshot of »… |
| Couleurs vives | fonds saturés ni noirs ni blancs ; image de présentation sans blanc ni noir dominants |
| Barre d'état | heure figée (9:41 par défaut), signal, Wi-Fi, batterie pleine, aucun opérateur ni notification : mesurée dans la page composée (mode `recouvrir`) ou sur l'élément d'heure de la page capturée (mode `source`) |
| Fiche | longueur du nom (30), de la description courte (80) et longue (4 000), mots interdits (facultatif) |

## Configuration : tous les champs

Le modèle est `visuels.example.json` ; ses champs sont expliqués dans `visuels.example.champs.md` (même tableau). Tous les champs ont un défaut sauf `application`, `feature_graphic` et `captures`.

| Champ | Type | Défaut | Rôle |
|---|---|---|---|
| `application` | texte | obligatoire | Nom de l'application : rapport, et texte central de l'image de présentation si aucun logo n'est fourni. |
| `version` | texte | – | Libre : numéro de version de votre configuration (ignoré par le kit). |
| `langue_defaut` | texte | `fr` | Langue des textes utilisée sans `--langue`. Les autres langues sortent dans `sortie/<langue>/`. |
| `sortie` | chemin | `sortie` | Dossier des PNG et du rapport (relatif au fichier de configuration ; `--sortie` le remplace). |
| `cache` | chemin | `.cache` | Fichiers intermédiaires (captures brutes, pages de composition, mesures). À ignorer dans Git. |
| `theme.palette` | objet | `{}` | Couleurs nommées `nom: "#RRGGBB"`, utilisables partout où une couleur est attendue. |
| `theme.tokens_css` | chemin ou `null` | `null` | Fichier CSS de jetons facultatif : toute variable `--nom: #RRGGBB;` devient une couleur nommée `nom`. |
| `theme.prefixe_jetons` | texte | vide | Préfixe à ajouter pour retrouver un jeton (ex. `brand-` : `blue-500` lit `--brand-blue-500`). |
| `theme.police.famille` | texte | `Segoe UI` | Nom de la police des titres et puces. |
| `theme.police.fichier` | chemin ou `null` | `null` | Fichier de police (`.woff2`, `.woff`, `.ttf`, `.otf`) chargé dans les visuels. Sans fichier : police installée sur la machine. |
| `theme.police.graisses` | texte | `100 900` | Plage de graisses déclarée dans le `@font-face` (police variable). Pour une police statique, mettre sa graisse. |
| `theme.police.repli` | texte | pile sans-serif | Polices de secours CSS. |
| `theme.logo` | chemin ou `null` | `null` | Logo (SVG ou PNG) au centre de l'image de présentation. Sans logo : le nom de l'application en texte. |
| `theme.fond` | objet | bleu foncé | Dégradé par défaut `{de, vers, angle}` des visuels (couleurs, angle en degrés). |
| `theme.couleurs.texte` | couleur | `#FFFFFF` | Couleur des titres et du slogan. |
| `theme.couleurs.puce_fond` / `puce_texte` | couleur | blanc / gris foncé | Fond et texte des puces flottantes. |
| `theme.couleurs.puce_icone_fond` / `puce_icone` | couleur | bleu / blanc | Pastille ronde et trait de l'icône d'une puce. |
| `theme.couleurs.cadre_appareil` | couleur | gris très foncé | Cadre neutre autour des écrans (sans appareil précis). |
| `theme.icones` | objet | `{}` | Icônes de puces supplémentaires `nom: "<path d=…/>"` (trait, viewBox 24). Icônes fournies : shield, lock, pin, chat, bot, book, users, eyeoff, wifioff, check, heart, star, bell, calendar, drop, leaf, sun, home, user, search. |
| `source.type` | `html` ou `images` | `html` | D'où viennent les écrans : page capturée par Playwright, ou images déjà capturées. |
| `source.url` | URL ou chemin | – | (html) Page à capturer : `https://…`, `file://…` ou fichier HTML relatif à la configuration. |
| `source.viewport` | `[largeur, hauteur]` | `[390, 844]` | (html) Taille de la fenêtre, en pixels CSS. |
| `source.echelle` | nombre | `3` | (html) Facteur d'échelle de l'écran (3 : 1170 × 2532 pour 390 × 844). |
| `source.locale` | texte | `fr-FR` | (html) Langue du navigateur. |
| `source.attente_ms` | entier | `700` | (html) Attente après chargement, avant capture. |
| `source.attendre_selecteur` | sélecteur ou `null` | `null` | (html) Attend qu'un élément existe avant de capturer. |
| `source.selecteur_ecran` | sélecteur ou `null` | `null` | (html) Capture seulement cet élément (ex. `#screen`) ; sinon toute la fenêtre. |
| `source.stockage_local` | objet | `{}` | (html) Clés écrites dans `localStorage` avant le chargement (valeurs non textuelles : sérialisées en JSON). Sert à ouvrir l'application déjà connectée, thème clair, etc. |
| `source.selecteur_heure` | sélecteur ou `null` | `null` | (html) Élément qui affiche l'heure : son texte est remplacé par `barre_etat.heure` et les `setInterval` de la page sont neutralisés. |
| `source.masquer` | liste de sélecteurs | `[]` | (html) Éléments retirés de la capture (`display:none`) : bandeaux, notifications, bulles d'aide. |
| `source.css` / `source.js` | texte | vide | (html) CSS injecté et JavaScript exécuté avant la capture. |
| `source.remplacements` | objet | `{}` | (html) Textes remplacés dans la page (noms propres réels → pseudonymes). |
| `source.police_dans_page` | booléen | `false` | (html) Injecte aussi la police du thème dans la page capturée. |
| `source.route` | texte | vide | (par capture, html) Ajoutée à l'URL : `#/ecran`, `?page=2`… |
| `source.image` | chemin | – | (par capture, images) Image déjà capturée (PNG de l'émulateur). |
| `barre_etat.mode` | `auto`, `source`, `recouvrir`, `aucune` | `auto` | `recouvrir` : barre propre superposée (obligatoire avec des images). `source` : barre de la page, heure figée. `aucune` : pas de barre. `auto` : `recouvrir` pour des images, `source` si `selecteur_heure` est défini, sinon `aucune`. Surchargeable par capture. |
| `barre_etat.heure` | texte | `9:41` | Heure affichée et contrôlée. |
| `barre_etat.hauteur_pct` | nombre | `3.4` | (recouvrir) Hauteur de la barre, en % de la hauteur de l'image. |
| `barre_etat.couleur` | couleur ou `auto` | `auto` | (recouvrir) Fond de la barre ; `auto` : couleur mesurée juste sous la barre d'origine. |
| `barre_etat.icones` | `auto`, `clair`, `sombre` | `auto` | (recouvrir) Couleur de l'heure et des icônes. |
| `mise_en_page.ecran_largeur` | entier | `730` | Largeur de l'écran dans la capture 1080 × 1920 (la hauteur suit le rapport de l'image). |
| `mise_en_page.cadre_y` | entier | `365` | Position verticale du cadre ; un cadre plus haut que la page est rogné en bas (voulu). |
| `mise_en_page.biseau`, `rayon_cadre`, `rayon_ecran` | entiers | 16, 92, 76 | Épaisseur du cadre et arrondis. |
| `mise_en_page.titre_haut`, `titre_taille` | entiers | 100, 76 | Position et corps du titre. |
| `mise_en_page.trou_camera` | booléen | `false` | Dessine un trou de caméra (à éviter : pas d'appareil précis). |
| `mise_en_page.feature_telephone_largeur` | entier | `215` | Largeur des deux écrans de l'image de présentation. |
| `contraintes.feature_graphic` | objet | 1024 × 500, 15 Mo | `largeur`, `hauteur`, `poids_max_mo` attendus. |
| `contraintes.captures` | objet | 1080 × 1920, 8, 8 Mo, 20 % | `largeur`, `hauteur`, `nombre` (2 à 8), `poids_max_mo`, `texte_max_pct`. |
| `contraintes.alt_max` | entier | `140` | Longueur maximale d'un texte alternatif. |
| `contraintes.contraste_min` | nombre | `4.5` | Contraste minimal (WCAG AA, texte normal). |
| `contraintes.couleurs_vives` | objet | voir exemple | Seuils des contrôles de couleurs vives (saturation et valeur des fonds, blanc, noir, saturation moyenne de l'image de présentation). |
| `contraintes.mots_interdits` | liste | français + anglais | Remplace la liste par défaut (classements, promotions, appels à l'action, mentions Google). |
| `contraintes.mots_interdits_ajout` | liste | `[]` | Mots à interdire en plus de la liste. |
| `contraintes.mots_interdits_regex` | liste | n° 1, millions de téléchargements… | Remplace les expressions régulières par défaut. |
| `feature_graphic.fichier` | texte | `feature-graphic-1024x500.png` | Nom du fichier produit. |
| `feature_graphic.fond` | objet | `theme.fond` | Dégradé propre à l'image de présentation. |
| `feature_graphic.halos` | liste | deux halos déduits du fond | Halos `{couleur, x, y, rayon, opacite}` (x et y en %, rayon en px, opacité de 0 à 1). |
| `feature_graphic.ecrans` | objet | deux premières captures | `{gauche, droite}` : identifiants des captures montrées sur les côtés. |
| `feature_graphic.textes.<langue>` | objet | obligatoire | `slogan` (texte visible, central) et `alt` (texte alternatif). Chaîne vide = gabarit à remplir. |
| `captures[].id` | texte | obligatoire | Identifiant unique (lettres, chiffres, tirets), utilisé par `--seulement`. |
| `captures[].fichier` | texte | `<id>.png` | Nom du PNG produit dans `sortie/screenshots/`. |
| `captures[].source` | objet | – | Surcharge de `source` pour cette capture (`route`, `image`, `css`, `masquer`…). |
| `captures[].barre_etat` | objet | – | Surcharge de `barre_etat` pour cette capture. |
| `captures[].fond` | objet | `theme.fond` | Dégradé de fond de la capture. |
| `captures[].puces[]` | liste | `[]` | Puces flottantes `{icone, cote, y, texte_ref}` : `cote` = `gauche` ou `droite`, `y` en pixels (0 à 1920), `texte_ref` = clé dans `textes.<langue>.puces`. |
| `captures[].textes.<langue>` | objet | obligatoire | `titre` (court), `puces` (`{clé: texte}`) et `alt` (texte alternatif). |
| `fiche.fichier` | chemin ou `null` | `null` | Fichier Markdown dont des blocs `<!--DEBUT:nom--> … <!--FIN:nom-->` sont contrôlés (longueur, mots interdits). |
| `fiche.textes` | objet | `{}` | Textes de la fiche contrôlés directement (`nom`, `court`, `long`). |
| `fiche.limites` | objet | 30, 80, 4000 | Longueurs maximales par clé (nom, description courte, description longue). |

## Règles Google Play vérifiées

Règles appliquées par les contrôles (relevées dans l'aide de la Play Console au moment de l'écriture ; voir « Limites » ci-dessous) :

- **Image de présentation** : exactement 1024 × 500 px, JPEG ou PNG 24 bits **sans alpha** ; logo, nom et slogan **au centre** (les bords peuvent être rognés selon les écrans) ; couleurs vives, pas de blanc pur, de noir ni de gris foncé dominants ; différente de l'icône ; pas de cadre d'un appareil précis.
- **Captures téléphone** : 2 à 8 par appareil, au moins 4 pour être éligible aux recommandations ; 9:16, côtés de 320 à 3 840 px ; PNG ou JPEG 24 bits sans alpha ; les premières centrées sur l'interface ; barre d'état propre (batterie pleine, réseau plein, pas d'opérateur ni de notification).
- **Texte dans les visuels** : seulement s'il est utile, 20 % de l'image au plus, lisible, contraste AA.
- **Mots et appels interdits** : « meilleur », « n° 1 », « le plus populaire », « nouveau », « gratuit », « remise », « millions de téléchargements » ; aucun appel à l'action (« Télécharger », « Installer », « Essayer »). Aucun badge Google Play ni logo tiers.
- **Texte alternatif** : un par visuel, 140 caractères au plus, qui décrit ce qu'on voit sans « image de » ni « photo de ».
- **Localisation** : images et textes dans la langue du marché ; un jeu de visuels par langue (`--langue`).

## Source « HTML » : capturer un prototype ou une application web

```json
"source": {
  "type": "html",
  "url": "mon-appli/index.html",
  "viewport": [390, 844], "echelle": 3,
  "selecteur_heure": "#horloge",
  "masquer": [".toast", ".bandeau-cookies"],
  "stockage_local": { "session": { "connecte": true } },
  "remplacements": { "Jean Dupont": "Camille" }
}
```

et, par capture, `"source": { "route": "#/accueil" }` (ajoutée à l'URL : `#/ecran`, `?page=2`, `/profil`). Les écrans sont photographiés à 390 × 844 en échelle 3 (1170 × 2532). Penser à : ouvrir l'application déjà connectée (`stockage_local`), neutraliser les notifications et bulles (`masquer`), figer l'heure (`selecteur_heure`), remplacer les noms réels par des pseudonymes (`remplacements`). Utiliser des données fictives : ne jamais publier de données personnelles réelles.

## Cas sans prototype HTML : captures de l'émulateur

1. Lancer l'application dans un émulateur Android (ou un vrai téléphone), avec des données fictives.
2. **Figer l'heure et la batterie** avant de capturer, ce qui évite d'avoir à tout recouvrir, même si le kit le fait pour vous. Avec adb, sur un émulateur récent : `adb shell settings put global sysui_demo_allowed 1`, puis le mode démonstration de la barre d'état (`adb shell am broadcast -a com.android.systemui.demo -e command clock -e hhmm 0941`, `… command battery -e level 100 -e plugged false`, `… command network -e wifi show -e level 4`, `… command notifications -e visible false`). Sous Windows, les mêmes commandes passent par `adb.exe` dans PowerShell.
3. Capturer : `adb exec-out screencap -p > captures/01.png` (le PNG est écrit sans passer par le téléphone ; sous Windows PowerShell, préférer `cmd /c "adb exec-out screencap -p > captures\01.png"` pour éviter une conversion de fins de ligne).
4. Dans `visuels.json` :

```json
"source": { "type": "images" },
"barre_etat": { "mode": "recouvrir", "heure": "9:41", "hauteur_pct": 3.4 }
```

   et pour chaque capture `"source": { "image": "captures/01.png" }`.
5. `hauteur_pct` est la hauteur de la barre d'état d'origine en pourcentage de l'image (environ 3 à 4 % sur Android) : le kit pose par-dessus une barre propre (heure figée, signal, Wi-Fi, batterie pleine, rien d'autre), de la couleur mesurée juste en dessous. Si le haut de l'écran est une image ou un dégradé, donner la couleur à la main (`"couleur": "#FFFFFF"`). Vérifier le résultat à l'œil.

## Personnaliser le thème

- **Couleurs** : `theme.palette` (noms) ou valeurs hexadécimales directes partout (`theme.fond`, `captures[].fond`, `theme.couleurs.*`, `feature_graphic.halos`).
- **Fichier de jetons CSS facultatif** : `"tokens_css": "tokens.css"` lit toutes les variables `--nom: #RRGGBB;` ; avec `"prefixe_jetons": "brand-"`, le nom `blue-500` retrouve `--brand-blue-500`.
- **Police** : `theme.police.famille` ; fournir `fichier` (`.woff2`, `.ttf`…) pour qu'elle soit embarquée, sinon elle doit être installée sur la machine qui génère (les PNG de référence de `example/` utilisent Inter, installée ici : sur une autre machine le rendu peut différer légèrement).
- **Logo** : `theme.logo`, SVG ou PNG de préférence en couleurs claires sur le fond.
- **Contrastes** : les fonds de capture doivent rester assez sombres pour un texte blanc (4,5:1) et assez saturés pour le contrôle de couleurs vives ; le rapport dit lequel échoue et de combien.
- **Mise en page** : `mise_en_page.*` (largeur et position du cadre, taille du titre) ; position des puces : `y` en pixels et `cote`. Regarder chaque image, puis ajuster : une puce ne doit pas masquer un élément important de l'écran.
- **Mots interdits** : `contraintes.mots_interdits_ajout` pour en ajouter, `contraintes.mots_interdits` pour remplacer la liste.

## Dépannage

| Symptôme | Cause probable et remède |
|---|---|
| « Chromium introuvable ou impossible à lancer » | Définir `PW_CHROMIUM` vers l'exécutable, ou `playwright install chromium`. |
| « Configuration invalide » | Le message liste chaque champ fautif (couleur inconnue, icône inconnue, `texte_ref` sans texte…). |
| « Gabarit non rempli » | Un texte de la langue demandée est vide : le remplir ou ne pas générer cette langue. |
| Écran capturé vide ou à moitié chargé | Augmenter `source.attente_ms`, ou `attendre_selecteur`. Ouvrir la page dans un navigateur : une erreur JavaScript y apparaît. |
| L'heure n'est pas figée | Vérifier `source.selecteur_heure` (le sélecteur doit exister dans la page) ; le rapport affiche l'heure lue. |
| Contraste ou couleurs vives en échec | Assombrir ou saturer les couleurs de fond ; le détail du rapport donne le pire cas mesuré. |
| Aire du texte trop grande | Raccourcir le titre ou les puces, réduire `titre_taille`. |
| Texte coupé ou police différente | Fournir `theme.police.fichier` ; vérifier le nom de la famille. |
| Rapport périmé après `--seulement` | Le rapport reprend les mesures du cache pour les autres visuels ; relancer sans option pour tout régénérer. |

## Icône, tablettes, règles et police

- **Barre d'état** : en mode `recouvrir`, la barre propre recouvre celle de la page ; `barre_etat.selecteur` (ex. `.statusbar`) indique l'élément de barre de la page pour que la hauteur couverte soit mesurée. Le contrôle échoue si un texte de la page est coupé par le bord de la barre propre (au lieu d'être entièrement recouvert) ou si un pixel de la bande diffère de la barre propre.
- **Icône 512** : bloc `icone` (`source` : SVG ou PNG carré, `fichier`, `fond` appliqué si la source est transparente). `--icone` la produit seule ; `--seulement icone` la régénère. Contrôlée : 512 × 512, PNG 32 bits, 1 024 Ko au plus, carré plein (aucun pixel transparent, pas de coins arrondis, pas d'ombre), motif dans la zone de sécurité, contraste motif/fond. Fournir un carré plein : Google applique son propre masque.
- **Captures de tablette** : bloc `tablettes` (`appareils` 7 et/ou 10, `orientations` portrait et/ou paysage, `source`, `barre_etat`, `captures` : 4 à 8 par jeu). Sorties dans `sortie/tablettes/<7|10>-pouces-<portrait|paysage>/`. `--seulement tablettes` les régénère seules.
- **Règles Google** : valeurs numériques dans `google_rules.json`, avec la date de dernière relecture humaine ; le kit ne contacte jamais Google. Au-delà de `validite_jours` (180), un avertissement est affiché (jamais un échec). `--regles` liste les règles codées et leurs sources ; elles sont aussi inscrites dans le rapport.
- **Police** : Inter (licence SIL OFL, `fonts/OFL.txt`) est embarquée dans `fonts/` en `.woff2` ; `theme.police.fichiers` (liste `{fichier, graisse}`) déclare plusieurs graisses statiques. Les visuels ne dépendent donc plus d'une police installée.

## Limites honnêtes

- Les contrôles couvrent **les règles connues au moment de l'écriture** de ce kit. Google modifie ses règles : **relire les règles Google Play (aide de la Play Console, « Ajouter des aperçus et des ressources pour présenter votre appli ») avant chaque publication.** Un rapport entièrement vert ne garantit pas l'acceptation de la fiche.
- Le kit **ne juge pas** le contenu : rien ne dit qu'un titre est juste, qu'une image est de bon goût ni que des données sont bien fictives. Il ne détecte pas un visage, une marque tierce ou un texte qui ressemble à une promesse sans employer un mot de la liste.
- Le contraste est mesuré sur le texte du titre, du slogan et des puces, pas sur le contenu de l'écran capturé.
- Le kit ne produit **pas de vidéo**. Il produit en revanche, en option, l'**icône 512** et les **captures de tablette** (voir « Icône, tablettes, règles et police » ci-dessus).
- Les captures téléphone sont toutes en portrait 1080 × 1920 : d'autres formats demandent de changer `contraintes.captures` **et** le gabarit (les tablettes, elles, acceptent portrait 9:16 et paysage 16:9).
- Les icônes de puces sont des traits simples ; ajouter les siennes par `theme.icones`.

## Skills utiles pour la partie créative

Pour la création (titres, cohérence de marque, composition), ces skills de l'assistant aident ; **aucun n'automatise la conformité Google Play**, qui reste vérifiée par le script :

| Besoin | Skill |
|---|---|
| Composition de l'image de présentation | `anthropic-skills:canvas-design` |
| Charte : couleurs, polices, logo | `design:design-system`, `anthropic-skills:brand-guidelines` |
| Titres courts, puces, descriptions de la fiche | `design:ux-copy` |
| Contraste, textes alternatifs, lisibilité | `design:accessibility-review` |

## Tests

```bash
pip install pytest
python3 -m pytest tests -q
```

Sans réseau. Les tests de bout en bout (exemple complet, source « images ») lancent Chromium et sont ignorés s'il est introuvable.

## Contenu du dossier

| Élément | Rôle |
|---|---|
| `build_playstore.py` | Le générateur et ses contrôles. |
| `visuels.example.json`, `visuels.example.champs.md` | Modèle de configuration et explication des champs. |
| `example/` | Mini-exemple complet : application fictive (`app/index.html`), logo, configuration et résultats de référence (`sortie/`). |
| `tests/test_kit.py` | Tests pytest. |
