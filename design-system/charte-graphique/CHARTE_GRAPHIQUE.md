# Charte graphique Xomiton — v1.1

Version 1.1 · 28 octobre 2026 (1.0 : 8 octobre 2026 ; 1.1 : ajout du §7.1 Photographie et illustrations, écrans d'ouverture) · Référentiel de l'identité visuelle de Xomiton, *espace safe d'apprentissage et d'appui holistique* pour filles, femmes et personnes LBTQ+.
Modèle de structure : la charte *Human First* (règle prioritaire, valeurs chiffrées opposables, interdits, gouvernance). Voir `../research/ANALYSE_CHARTES_EXEMPLES.md`.

## Préambule

**À qui s'adresse ce document.** À toute personne qui produit un support au nom de Xomiton : équipe, graphiste, développeur·se, agence, imprimeur.
**Pourquoi des règles.** Xomiton accompagne des personnes en situation de vulnérabilité. Une identité qui varie d'un support à l'autre abîme la confiance avant même qu'on ait lu une ligne. Et une interface confuse, alarmante ou illisible peut coûter du temps à quelqu'un qui en manque. Ces règles sont donc des conditions de **sécurité et d'accessibilité**, pas des préférences esthétiques.

> **La règle qui prime sur toutes les autres : la sécurité de la personne passe avant l'esthétique.**
> Sur les écrans de signalement et de SOS : sobriété, lisibilité maximale, aucun décor arc-en-ciel, aucune animation non nécessaire, aucun texte alarmiste.

Les valeurs chiffrées (rapports de contraste, tailles minimales, durées) sont **opposables** : elles ont été calculées, pas estimées (script `palette.py` de recette, tableau §4).

## 1. Principes de marque

| Principe | Ce que ça veut dire en design |
|---|---|
| **Chaleureux** | fond blanc cassé rosé, illustrations plates et inclusives, coins arrondis |
| **Sûr** | peu d'éléments par écran, hiérarchie nette, bleu stable pour agir, rouge réservé à l'attention |
| **Clair** | titres Montserrat marqués, texte Arial lisible, phrases courtes (voir `xomiton_implementation/11`) |
| **Inclusif** | personnages divers, aucun stéréotype, écriture inclusive homogène, accessibilité WCAG 2.2 AA |
| **Discret** | Titre d'onglet neutre possible, aucune donnée sensible à l'écran de verrouillage |

## 2. Logo

**Description** (fichiers `images/logo.png`, 240×66 px) : mot « xomiton » en bleu profond, premier « o » remplacé par une **rosace rouge à quatre formes en tourbillon** (personnes réunies), second « o » remplacé par un **symbole de genre rouge** (cercle, flèche et croix réunis). Le logo est un dessin : il n'est jamais recomposé en police.

**Livrables réalisés** (dossier `design-system/logo/`, générés par `build_logo.py` et `render_png.py`) : SVG vectoriels et PNG haute définition à fond transparent, soit 14 fichiers SVG et 28 PNG : logo horizontal (couleur, sombre, monochrome encre, bleu et blanc) en **6000 px de large**, logo vertical en 4000 px, symboles (rosace, symbole de genre) en 3000 px, icônes d'application (bleu, blanc, sombre) en 2048 px, avec une version 1200 px pour le web. **Réserve** : le dessin vectoriel est une reconstitution à partir du seul fichier disponible (`logo.png`, 240 × 66 px) ; il est fidèle à la silhouette mais n'est pas le fichier source de la personne qui a conçu le logo, qui reste à obtenir pour une impression de grand format.

**Variantes** (rappel) :
| Variante | Usage | Couleurs |
|---|---|---|
| Horizontale couleur (SVG + PNG) | Fond clair — usage par défaut | Bleu `#0433C3`, rouge `#E30E0E` |
| Horizontale sombre | Fond sombre (thème sombre, bandeaux bleus) | Lettres `#FFFFFF`, rosace et symboles `#FF6B6B` ; le fichier actuel `night-logo.png` est **identique** au clair, donc à refaire (le bleu `#0433C3` sur `#0E1020` ne donne que 2,04:1) |
| Monochrome (noir, blanc) | Tampons, fax, impression 1 couleur | Une seule teinte |
| Icône carrée (SVG, 192 et 512 px) | Favicon, PWA, app mobile, avatars | Rosace seule sur fond blanc ou bleu |
| Verticale | Réseaux sociaux, kakémono | Comme horizontale |

**Zone de protection** : vide minimal égal à la hauteur du « o » rosace (≈ 1/3 de la hauteur du logo) sur les quatre côtés.
**Taille minimale** : 96 px de large à l'écran (logo horizontal), 20 mm en impression ; icône seule : 24 px.
**Fonds autorisés** : blanc, `--xm-bg`, `--xm-surface`, bleu `#0433C3` (version sombre/blanche), photo uniquement avec un voile garantissant 4,5:1.
**Interdits** : déformer, pivoter, recolorer hors variantes, ajouter ombre/contour/dégradé, placer sur fond agité, poser le bleu sur le rouge (1,91:1), séparer rosace et mot, remplacer la rosace par un autre symbole.

## 3. Couleurs

### 3.1 Couleurs de marque
| Rôle | Nom | Hex | RVB | Usage |
|---|---|---|---|---|
| **Primaire** | Bleu Xomiton | **`#0433C3`** | 4, 51, 195 | Actions, liens, navigation active, focus, titres de marque |
| **Secondaire** | Rouge Xomiton | **`#E30E0E`** | 227, 14, 14 | **Attention** : SOS, signaler, urgence critique, suppression |

Échelles dérivées (50→900) : voir `tokens/design-tokens.json`. Variantes pour fond sombre : bleu `#7C97FF`, rouge `#FF6B6B`.

### 3.2 Neutres et fonctions
Fond `#FBF7FA` (blanc cassé rosé, hérité des illustrations) · surface `#FFFFFF` · bordure `#E4DCE6` · texte `#1B1630` (encre indigo) · texte atténué `#5B5670`. Sombre : fond `#0E1020`, surface `#171A2E`, texte `#EDEAF7`.
Fonctions : succès `#0B7A4B` · alerte `#F0A800` (texte foncé dessus) · information `#1864AB` · accents (catégories, jamais l'action) violet `#6F2C91`, rose `#C2255C`, orange `#B84B00`.

### 3.3 Proportions (règle 60-30-10)
60 % neutres (fonds, surfaces, texte) · 30 % bleu (actions, navigation, titres de marque) · **10 % maximum** de rouge, partout où il apparaît. Un écran avec plus de deux éléments rouges est un défaut de conception (le rouge perd son sens).

### 3.4 Sens du rouge
Le rouge est **la couleur de l'attention**. Il signifie toujours « important/risqué/urgent » : SOS, « Signaler un abus », statut critique, action destructive. **Jamais décoratif**, jamais pour une simple information, jamais seul (toujours accompagné d'une icône et d'un texte).

### 3.5 Arc-en-ciel
Les couleurs des illustrations (drapeaux, rubans) restent **décoratives** : héros, bandeaux éditoriaux, états vides. Elles ne portent aucune information fonctionnelle et sont **absentes** des écrans de signalement, SOS, cas et tableau de bord.

## 4. Contrastes opposables (rapports calculés)

| Paire (texte sur fond) | Rapport | Verdict |
|---|---|---|
| `#0433C3` sur blanc `#FFFFFF` | **9,26:1** | AA et AAA texte normal |
| `#0433C3` sur `#FBF7FA` | 8,68:1 | AAA |
| Blanc sur `#0433C3` (bouton primaire) | 9,26:1 | AAA |
| `#E30E0E` sur blanc | **4,84:1** | AA texte normal (limite basse : pas de texte < 14 px en rouge) |
| `#E30E0E` sur `#FBF7FA` | 4,54:1 | AA (très proche du seuil) |
| Blanc sur `#E30E0E` (bouton secondaire) | 4,84:1 | AA |
| Blanc sur `#C10C0C` (survol) | 6,32:1 | AA/AAA |
| `#0433C3` sur `#E30E0E` | **1,91:1** | **Interdit** (ni texte bleu sur rouge, ni l'inverse) |
| Texte `#1B1630` sur `#FBF7FA` | 16,43:1 | AAA |
| Texte atténué `#5B5670` sur `#FBF7FA` | 6,57:1 | AA |
| Texte `#1B1630` sur alerte `#F0A800` | 8,56:1 | AAA |
| `#0433C3` sur sombre `#0E1020` | 2,04:1 | **Interdit** : utiliser `#7C97FF` |
| `#7C97FF` sur `#0E1020` | 6,94:1 | AA |
| `#FF6B6B` sur `#0E1020` | 6,80:1 | AA |
| Blanc sur vert `#0B7A4B` / violet `#6F2C91` / rose `#C2255C` / info `#1864AB` | 5,39 / 8,59 / 5,66 / 6,09 | AA |
| Anneau de focus `#0433C3` sur blanc | 9,26:1 (≥ 3:1 requis) | OK |

Seuils : texte normal ≥ 4,5:1 · grand texte (≥ 24 px ou 18,7 px gras) et composants d'interface ≥ 3:1. Tout nouveau couple est calculé avant d'entrer dans les tokens.

## 5. Typographie

| Rôle | Police | Graisses | Usage |
|---|---|---|---|
| **Titres** | **Montserrat** | 600, 700, 800 | h1-h6, boutons, navigation, chiffres clés des tableaux de bord |
| **Sous-titres** | **Arial** | 400, 700 | chapeaux, `lead`, légendes de section |
| **Texte courant** | Arial *(hypothèse à confirmer)* | 400, 700 | paragraphes, formulaires, tableaux |

- « Montserrat Classic » est interprétée comme la **famille Montserrat standard** (pas *Alternates*, pas *Subrayada*).
- Montserrat est **auto-hébergée** (WOFF2 variable, sous-ensemble latin, `font-display: swap`, préchargement des graisses 700 et 800) : aucun appel à Google Fonts en production (RGPD, performance).
- Arial est une police système : pile `Arial, "Liberation Sans", Helvetica, sans-serif`. **Sur Android (majorité des utilisateur·rice·s), Arial est remplacée par Roboto ou Liberation Sans** : la mise en page doit rester correcte avec ces polices, et le test sur appareil réel est obligatoire.
- Échelle fluide : display 36-56 px · h1 32-44 · h2 26-34 · h3 22-26 · h4 20 · h5 18 · lead 18-21 · texte 16 · petit 14 · légende 13. Interligne titres 1,2, texte 1,6. Largeur de ligne ≤ 68 caractères.
- Montserrat est large : pas de capitales continues, pas de graisse 800 sous 24 px, interlettrage −0,01 em sur les très grands titres seulement.
- Un seul `h1` par page.

## 6. Grille, espacement, formes
Base 4 px : 4, 8, 12, 16, 24, 32, 48, 64, 96 · conteneur Bootstrap (max 1320 px), gouttières 24 px (`g-4`), 48 px (`g-lg-5`) · rayons 8 px (champs, boutons), 16 px (cartes), pastille 999 px · ombres `sm` (cartes), `lg` (modales, survol) · cible tactile ≥ 44 × 44 px, espace ≥ 8 px entre cibles.
Breakpoints : Mobile < 768, Tablet 768-991, Laptop 992-1199, Desktop ≥ 1200 (`xl`).

## 7. Iconographie, illustration, photographie
- **Icônes** : Bootstrap Icons uniquement, trait cohérent, jamais d'émoji comme icône ; toute icône informative a un libellé ou un `aria-label`, toute icône décorative `aria-hidden="true"`.
- **Illustrations** (`images/hero-*.jpg`, `group-chat.svg`) : style plat, personnages divers (couleurs de peau, morphologies, expressions), drapeaux et rubans arc-en-ciel, fonds rose pâle ; à fournir en SVG/AVIF/WebP, avec `alt` descriptif.
- **Photographie** : seulement avec consentement écrit et libre de droits ; **jamais** de photographie identifiable de personnes ayant subi des violences ni de mise en scène de violence ; cadrage chaleureux, lumière naturelle ; voile garantissant 4,5:1 sous tout texte.
- **Cartes et données** : voir `xomiton_implementation/06 §10` (palette catégorielle daltonien-safe, étiquettes directes, cellules < 5 masquées).

### 7.1 Photographie et illustrations, écrans d'ouverture
Visuels fournis par le client et rangés dans `assets/` (voir `assets/MANIFEST.json`) : 4 illustrations (`assets/illustrations/`), 4 photographies de splash portrait (`assets/splash/`) et 2 références à ne pas publier (`assets/splash/references-a-eviter/`). **Droits d'image : licence d'usage commercial confirmée par le client le 2026-10-28, preuve archivée hors dépôt.**

| Règle | Valeur |
|---|---|
| **Cadrage** | Portrait **9:16** pour le splash, regard dans le tiers supérieur et texte dans le tiers inférieur ; paysage 3:2 ou 16:9 pour bandeaux et cartes, sujet gardé d'un côté pour laisser une zone calme. Ne jamais couper un visage ni une main. Largeur minimale 675 px (écran), 2400 px à produire pour l'impression. |
| **Traitement** | Couleurs naturelles chaudes, retouche limitée à ±10 % (exposition, contraste), pas de filtre ni de recoloration, pas de lissage de peau. WebP qualité 78-82, sans EXIF, 100 Ko au plus par image (splash : 900 px de large au plus). |
| **Dégradé de lisibilité** | Sous tout texte sur photographie : dégradé encre (`rgb(8 10 30)`) de 88 % en bas, **au moins 62 % d'opacité jusqu'à 45 % de la hauteur** (sous tout le texte), puis 0 % à 72 % : même sur une photographie blanche, le texte blanc garde plus de **4,5:1** (5,6:1 calculé) ; le logo et « Passer » se posent sur une pastille ou une zone calme. |
| **Ton** | Joie calme, solidarité, dignité, regard vers l'avant ; personnes actrices, jamais en position de victime. Un seul visuel dominant par écran. |
| **À éviter** | Texte incrusté (surtout en anglais : non traduisible, non lu par les lecteurs d'écran) ; nudité ; bâillons, scotch, mains sur la bouche, mise en scène de violence ou de détresse ; arc-en-ciel décoratif sur les écrans de signalement, de SOS et de dossier ; logos ou pastilles de plateformes de banque d'images ; image générée par IA présentée comme une vraie personne. |
| **Accessibilité** | `alt` obligatoire et descriptif (sujet, geste, lieu), `alt=""` seulement si l'image est purement décorative ; contraste du texte par-dessus mesuré, pas estimé ; aucun défilement automatique ; `prefers-reduced-motion` respecté ; aucune information portée par l'image seule. |
| **Mode discret** | L'écran d'ouverture devient neutre : logo sur fond uni, sans photographie ni drapeau. Le mode discret se propose dès la première ouverture ; il remplace aussi les visuels des notifications et de l'aperçu de partage. |
| **Consentement** | Aucune photographie de personne identifiable pour un contenu sensible (violence, santé, signalement, SOS) sans consentement écrit, libre et retirable ; ces visuels illustrent l'esprit de la communauté, jamais une membre ou une victime réelle. |

Statut des visuels : `splash-paillettes-drapeau`, `splash-couple-etreinte-drapeau`, `splash-regard-lumiere-chaude`, `splash-lumiere-doree-drapeau` sont des **alternatives** au carrousel actuel (quatre diapositives, 247 Ko ; plafond 700 Ko) ; `design-system-3` et `splash-3` sont signalées « Ai » (générées par IA) ; `splash-4` (texte incrusté et nudité) et `splash-5` (bouches scotchées) sont écartées de l'onboarding. Les pastilles de la plateforme source ont été retirées par recadrage du bas, sans retouche.

## 8. Mouvement et son
Durées : 120 ms (retours), 200 ms (transitions), 300 ms (panneaux). `prefers-reduced-motion` : toute animation supprimée. Aucun clignotement. Le son n'existe que pour l'**alerte sonore par cloche** (SOS et cas critiques destinés aux agent·e·s concerné·e·s), jamais en lecture automatique pour le public, toujours doublé d'un signal visuel et d'une vibration.

## 9. Sécurité visuelle
- **SOS** : cercle rouge de 64 px, libellé « SOS », maintien 2 s avec anneau de progression, annulation possible pendant 5 s ; alternative sans maintien : double appui puis confirmation.
- Option **mode discret** : nom et icône neutres de l'application installée, titre d'onglet générique, pas de notification explicite sur écran verrouillé.
- Aucune couleur seule ne porte l'information (urgence = couleur + icône + mot).

## 10. Applications
| Support | Règles |
|---|---|
| **Site et PWA** | `tokens.css` + `social-xomiton.css` ; header sticky à effet verre (existant conservé) ; hero fluide ; thème clair/sombre/auto |
| **Aperçu de partage (OG/Twitter)** | 1200 × 630 px, logo en haut à gauche, titre Montserrat 800, fond neutre ou illustration, < 300 Ko |
| **E-mails** | Largeur 600 px, bouton primaire bleu, texte Arial 16 px, aucun contenu sensible dans l'objet |
| **Rapports PDF/Word/Excel** | Couverture avec logo, titres Montserrat, texte Arial, graphiques palette catégorielle, tableau de données sous chaque graphique, pied de page « Confidentiel » |
| **Application mobile** | Mêmes tokens (`design-tokens.json`), icône carrée, barre d'onglets inférieure avec SOS au centre |
| **Réseaux sociaux** | Visuels carrés 1080 px, logo vertical ou icône, texte ≥ 4,5:1, `alt` systématique |
| **Impression** | Pantone/CMJN à définir par l'imprimeur à partir de `#0433C3` et `#E30E0E` (valeurs d'impression à valider sur épreuve) |

## 11. Accessibilité (WCAG 2.2 AA minimum)
Contrastes §4 · focus visible 3 px décalé de 2 px sur tout élément interactif · focus jamais masqué par le header (`scroll-padding-top`) · cibles ≥ 44 px · pas de test cognitif à l'authentification (mot de passe collable, gestionnaire de mots de passe autorisé) · résumé d'erreurs focalisable avec liens vers les champs · aucune information transmise par la couleur seule · `lang`, titres hiérarchisés, repères ARIA · tests clavier et lecteur d'écran (NVDA, VoiceOver, TalkBack).

## 12. Interdits
Bleu sur rouge · rouge décoratif · plus de deux éléments rouges par écran · arc-en-ciel sur les écrans de signalement/SOS/cas · logo déformé ou recoloré · texte sur image sans voile · police autre que Montserrat/Arial · Font Awesome et Bootstrap Icons mélangés · émojis comme icônes · animations sans équivalent « mouvement réduit » · photos de victimes · hexadécimal en dur dans un composant (utiliser `--xm-*`) · écrire « victime » dans l'interface publique (préférer « personne qui a vécu des violences »).

## 13. Gouvernance
- **Propriétaire de la charte** : à désigner (direction Xomiton). **Relecture** : design + accessibilité + (pour tout écran de crise) une personne de l'équipe psychosociale.
- **Versionnage** : numérotation `MAJEUR.MINEUR` ; toute modification de token passe par une demande dans le dépôt (revue de contraste obligatoire), met à jour `design-tokens.json`, `tokens.css`, l'aperçu `/preview` et cette charte.
- **Cas non prévu** : ne pas improviser ; ouvrir une demande avec capture, usage et justification ; décision documentée sous `docs/adr`.
- **Recette** : chaque nouveau composant est ajouté à l'aperçu vivant avec ses états (défaut, survol, focus, actif, désactivé, chargement, erreur) avant d'être utilisé.

## 14. Fichiers du système
| Fichier | Rôle |
|---|---|
| `tokens/design-tokens.json` | Source structurée (3 couches) — réutilisée par Flutter |
| `tokens/tokens.css` | Variables CSS `--xm-*`, thèmes clair et sombre |
| `tokens/social-xomiton.css` | Surcharge du thème Social (Bootstrap 5.3.3 compilé) |
| `xomiton/MASTER.md` | Spécification condensée pour le développement |
| `xomiton/pages/*.md` | Surcharges par page (accueil, signaler, SOS, tableau de bord, authentification) |
| `components/COMPOSANTS.md` | Spécification de chaque composant `x-*` |
| `preview/index.html` | Aperçu vivant (à ouvrir dans un navigateur) |
| `DECISIONS.md` | Décisions D2, D4, D5, D6, D7 et leurs conséquences |
