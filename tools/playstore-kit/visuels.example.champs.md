# Champs de `visuels.example.json`

`visuels.example.json` est un modèle de configuration (JSON strict, donc sans commentaires). Ce fichier explique chaque champ. Copiez le modèle en `visuels.json`, remplacez les valeurs, supprimez ce que vous n'utilisez pas : **tous les champs sauf `application`, `feature_graphic` et `captures` ont une valeur par défaut**. Pour un projet qui fonctionne tout de suite, partir de `example/visuels.json` (ou de `--init`).

Règles générales :

- Tous les chemins sont **relatifs au fichier de configuration**.
- Une couleur est une valeur hexadécimale (`#RRGGBB` ou `#RGB`), un nom de `theme.palette`, ou un jeton d'un fichier CSS (`theme.tokens_css`).
- Les textes visibles sont rangés **par langue** (`textes.fr`, `textes.en`…). Une chaîne vide marque un gabarit à remplir : le kit refuse de générer cette langue tant qu'il en reste.
- Une capture peut surcharger `source` et `barre_etat` ; les valeurs absentes viennent du bloc global.
- Le modèle contient `"nombre": 2` captures pour rester court ; Google en accepte de 2 à 8 (le kit contrôle que le nombre déclaré dans `contraintes.captures.nombre` est atteint).

## Source d'écrans « images » (captures déjà faites)

Remplacer le bloc `source` par :

```json
"source": { "type": "images" },
"barre_etat": { "mode": "recouvrir", "heure": "9:41" }
```

et donner à chaque capture l'image à utiliser :

```json
{ "id": "01-accueil", "source": { "image": "captures/01.png" }, "textes": { "…": "…" } }
```

## Tous les champs

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
