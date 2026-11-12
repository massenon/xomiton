# Grammaire des bulles — « Conversations en BD » (P20)

Règles de dessin et de rendu des bulles (phylactères) des conversations en BD de Xomiton. Elles s'appliquent aux 12 modèles (`MODELES.md`), aux créations des membres et à l'export PDF / PNG.

Source principale : le tutoriel « Bulle de BD : types, usages et tracé » de Mangakoaching (https://www.mangakoaching.com/tutoriels/anatomie-bulle-de-bd/), fourni par le client le 12/11/2026. Les règles du tutoriel sont adaptées ici au web (accessibilité, texte réel, export).

## 1. Principe : l'image n'a jamais de texte, les bulles sont du vrai texte

- Les illustrations sont produites **sans aucun texte ni bulle** (règle de `BIBLE_PERSONNAGES.md`, conservée).
- Les bulles sont **dessinées par la plateforme** (SVG) par-dessus l'illustration. Leur texte est du texte réel :
  - lisible par les lecteurs d'écran ;
  - indexé par les moteurs de recherche (les conversations sont publiques et indexées, décision du client du 12/11/2026) ;
  - corrigeable sans régénérer l'image ;
  - traduisible plus tard.
- Conséquence pour les consignes d'image : **réserver la zone des bulles**. Le tutoriel insiste : « lors de l'étape du storyboard, il est important de déjà réfléchir à la position des phylactères ». Chaque scène des modèles indique où laisser du fond calme (en général le tiers supérieur).

## 2. Anatomie d'une bulle

| Partie | Rôle | Règle Xomiton |
|---|---|---|
| **Bulle** (phylactère) | Contient la parole ou la pensée et la sépare de celle des autres | Forme horizontale (lecture franco-belge, de gauche à droite) ; fond blanc `#FFFFFF`, contour encre `#1B1630` |
| **Texte** | La voix du personnage | Écrit **avant** de tracer la bulle : la bulle se dimensionne d'après le texte, jamais l'inverse |
| **Marge intérieure** | Espace entre le texte et le contour | Ni collé au contour (difficile à lire), ni trop large (la bulle ne tient plus dans la case) : 0,6 em à l'horizontale, 0,45 em à la verticale |
| **Queue** (appendice) | Indique qui parle | Pointe **vers la bouche** du personnage. Vers le **bord de la case** si la voix vient du hors-champ. Vers l'**écran** pour un message reçu |

## 3. Les types de bulles

Chaque type a une forme qui porte un sens (« contour lisse pour un dialogue, contour dentelé pour un cri, petits ronds pour une pensée »). Le type est aussi annoncé aux lecteurs d'écran : la forme n'est jamais le seul repère.

| Clé | Type | Forme et queue | Texte | Lecteur d'écran |
|---|---|---|---|---|
| `parole` | Parole | Ovale à contour lisse, queue pointue vers la bouche | Taille normale | « Ablavi dit : … » |
| `pensee` | Pensée | Nuage, queue faite de **3 petits ronds** de taille décroissante | Taille normale, *italique* | « Fifamè pense : … » |
| `cri` | Cri, colère | Contour **hérissé de pointes**, plus grand, contour épais ; peut déborder légèrement du bord de la case | Plus grand, **gras** | « Dodji crie : … » |
| `tremblee` | Peur, gêne | Contour **irrégulier** (tremblé) | Plus petit : le personnage n'ose pas élever la voix | « Yasmine dit, mal à l'aise : … » |
| `chuchote` | Chuchotement | Contour en **pointillés** | Plus petit | « Joël chuchote : … » |
| `joie` | Joie, énergie | Forme de nuage **avec une queue de parole** pointue (pas de petits ronds) | Taille normale | « Maman Félicité dit, joyeuse : … » |
| `message` | Message écrit (SMS, messagerie, commentaire) | **Rectangle** à coins arrondis, queue vers l'écran du téléphone ou de l'ordinateur | Taille normale | « Message reçu de … : … » |
| `voix_appareil` | Voix d'un appareil (appel, haut-parleur, micro, vocal) | Rectangle avec de **petites marques d'ondes** sur le contour, queue vers l'appareil | *Italique* | « Voix au téléphone, Edwige : … » |
| `cartouche` | Cartouche (récitatif) | Rectangle **sans queue**, collé au bord haut de la case, fond rose pâle `#FBF7FA` | Taille normale | « Narration : … » |

**Hors-champ** : n'importe quel type peut avoir sa queue pointée vers le bord de la case (`queue: bord-gauche`, `bord-droit`, `bord-haut`, `bord-bas`). Le lecteur d'écran dit « … dit, hors de l'image : … ».

**Idéogrammes** : le tutoriel cite les dessins qui remplacent une grossièreté. Xomiton ne s'en sert **pas** pour des insultes. Une insulte ou une menace n'est jamais écrite ni dessinée (bible, règle non négociable) : on la remplace par un cartouche neutre (« Un message insultant. ») ou par une bulle `message` grisée et floutée, sans texte.

## 4. Enchaîner et combiner les bulles

- **Bulles associées** : un long propos est découpé en 2 ou 3 bulles reliées par un petit col. La queue n'est dessinée que sur **la dernière** bulle de la chaîne. Le lecteur d'écran lit la chaîne comme une seule réplique.
- **Bulles superposées** : une bulle qui chevauche la précédente montre une **interruption** ou deux personnes qui parlent en même temps. Plus de 3 bulles superposées donnent une impression de brouhaha : à réserver à cet effet voulu.
- **Ordre de lecture** : de gauche à droite, puis de haut en bas. La première réplique est en haut à gauche. Placer les personnages pour que la première personne qui parle soit à gauche (à prévoir dans la scène).
- **Une bulle ne cache jamais** un visage, une main qui fait un geste important ni une autre queue.

## 5. Texte

- **Longueur** : au plus **20 mots** (environ 120 caractères) par bulle. Au-delà, couper en bulles associées. Pour une information clé, la mettre seule dans une bulle courte, au besoin avec des points de suspension (« Je voulais te dire… » puis « … je ne suis pas d'accord. »).
- **Taille constante** : une seule taille de base pour toute la conversation (18 px à l'écran pour une case de 1200 px de large, 11 pt dans le PDF). Seuls `cri` (×1,3) et `tremblee` / `chuchote` (×0,85) changent la taille.
- **Police** : celle du texte courant de la charte (§5 : pile `Arial, "Liberation Sans", Helvetica, sans-serif`), **Montserrat 700** pour le `cri` et le cartouche. Le PDF embarque Liberation Sans et Montserrat (licences libres, déjà auto-hébergées pour Montserrat). Pas de police « BD » prise sur un site de polices gratuites sans vérifier sa licence (le tutoriel le rappelle).
- **Mise en valeur** : un ou deux mots en **gras** au plus par bulle, pour l'intonation. Pas de souligné (confondu avec un lien).
- **Langue** : français simple, phrases courtes, écriture inclusive du projet (point médian), jamais le mot « victime » (VOICE_AND_TONE).
- **Contraste** : texte encre `#1B1630` sur blanc (rapport supérieur à 15:1). Le cartouche reste au-dessus de 7:1.

## 6. Données d'une bulle (format commun modèles / créations)

```json
{
  "type": "parole",
  "personnage": "ablavi",
  "texte": "Je n'ai pas fini ma phrase.",
  "emphase": ["pas fini"],
  "position": { "x": 8, "y": 6 },
  "queue": { "vers": "personnage", "x": 22, "y": 48 },
  "associee_a": null,
  "superpose": false
}
```

- `position` et `queue` sont en pourcentage de la case (0 à 100) : le rendu reste juste à toutes les largeurs et dans l'export.
- `queue.vers` vaut `personnage`, `ecran`, `bord-gauche`, `bord-droit`, `bord-haut` ou `bord-bas` ; absent pour `cartouche`.
- `associee_a` : indice de la bulle précédente de la chaîne.

## 7. Contrôles automatiques (éditeur et import)

L'éditeur et l'import des modèles refusent ou signalent :
- une bulle de plus de 20 mots (avertissement, proposition de couper) ;
- une bulle qui sort de la case (sauf `cri`, de 4 % au plus) ;
- deux bulles qui se chevauchent sans `superpose: true` ;
- une queue `personnage` qui pointe hors de la case ;
- plus de 4 bulles dans une case (lisibilité sur téléphone) ;
- un ordre de lecture incohérent (une bulle placée plus haut à gauche mais lue après).
