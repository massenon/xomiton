---
type: prd
title: "Conversation en BD (bulles, bonshommes et codes couleur)"
slug: "conversation-en-bd"
status: draft
phase: "01"
created: 2026-11-12
updated: 2026-11-12
generated_by: sdlc-prd
owner: "Équipe Xomiton"
tags: [planning, prd, ccc, bd, pedagogie, accessibilite]
stakeholders: []
target_date: ""
---

# Conversation en BD (bulles, bonshommes et codes couleur)

> Spécification produite avec `/product-management:write-spec` (demande du client du 12/11/2026), rangée dans le vault comme les autres PRD. Rédigée en français comme le reste du projet.
> Inspiration : les « Comic Strip Conversations » (Carol Gray), outil thérapeutique et pédagogique qui dessine une interaction avec des **bonshommes simples**, des **bulles** (ce qui est dit / ce qui est pensé) et un **code couleur** des émotions, pour rendre visibles les intentions et les ressentis.

## Context

Xomiton propose déjà des BD illustrées sur les violences numériques (8 publiées, 15 en production : [[violences-numeriques-contenus-bd]]) et des outils CCC pour les séances des pair·es animateur·rices (manuel, boîtes à images, BD, mode animation, lecture hors ligne).

Ces BD sont **narratives et figées** : elles racontent une histoire, mais ne permettent pas de **décomposer une conversation** (« qu'a-t-elle dit ? qu'a-t-elle pensé ? qu'a-t-elle ressenti ? »), ni de la **construire avec le groupe** pendant une séance. Or c'est là que se jouent l'emprise, la manipulation, le consentement, le chantage : ce qui est dit n'est pas ce qui est pensé ni ressenti.

La « conversation en BD » répond à ce besoin avec un format volontairement **simple** (bonshommes, bulles, symboles, couleurs), rapide à dessiner, compréhensible par tout le monde y compris les personnes peu lettrées, et utilisable aussi pour vulgariser d'autres sujets (droits, santé, handicap, vie en entreprise).

## Problem Statement

Les pair·es animateur·rices et les membres ont du mal à **mettre des mots et des images sur ce qui se passe dans une interaction difficile** : une pression déguisée en affection, un message qui menace sans le dire, un « oui » qui veut dire « non ». Aujourd'hui, la plateforme n'offre aucun outil pour **dessiner ensemble** une conversation et faire apparaître les pensées et émotions cachées. Sans cela, les séances restent verbales, les messages-clés passent mal auprès des publics peu lettrés ou en situation de handicap, et les membres repartent sans support réutilisable.

## Goals

1. **Séances** : au moins **60 % des séances CCC** saisies par les pair·es utilisent une conversation en BD dans les 3 mois suivant la mise en ligne (mesure : séances avec une conversation ouverte en mode animation).
2. **Compréhension** : au moins **75 % des participant·es** disent avoir mieux compris la différence entre « ce qui est dit » et « ce qui est ressenti » (question unique en fin de séance).
3. **Bibliothèque** : **12 conversations modèles** publiées au lancement, une par catégorie de violences numériques et sur 3 thèmes de vulgarisation (consentement, droits, santé).
4. **Accessibilité** : 100 % des conversations lisibles **sans la couleur seule** et par lecteur d'écran (contrôle automatique dans les tests).
5. **Appropriation** (phase 2) : au moins **200 conversations créées par des membres** dans les 6 mois, sans incident de sécurité (aucune donnée identifiante publiée).

## Non-Goals

- **Pas d'éditeur de dessin libre** (pinceau, formes à main levée) : la force de l'outil est sa simplicité ; un éditeur complet coûte cher et nuit à l'accessibilité.
- **Pas de génération d'images par IA** dans l'outil : les bonshommes et symboles sont une bibliothèque fixe, sobre et sans visages réalistes (risque de ressemblance avec des personnes réelles, coût des crédits).
- **Pas d'usage thérapeutique clinique** : Xomiton n'est pas un service de soin ; l'outil est pédagogique et d'écoute, avec orientation vers les professionnel·les de l'annuaire.
- **Pas de collaboration en temps réel à plusieurs appareils** en v1 : en séance, un seul écran (projeté ou partagé) suffit.
- **Pas de traduction en langues locales** en v1 (fon, yoruba…) : chantier distinct, mais le format très visuel limite le besoin de texte.

## User Stories

**Pair·e animateur·rice (en séance, souvent hors ligne)**
- En tant que pair·e animateur·rice, je veux ouvrir une conversation modèle en plein écran et la dérouler case par case pour faire réagir le groupe à chaque étape.
- En tant que pair·e animateur·rice, je veux construire une conversation **avec le groupe** (choisir les personnages, écrire ce qui est dit et pensé, choisir l'émotion) pour partir de leurs propres situations.
- En tant que pair·e animateur·rice, je veux **masquer puis révéler** les bulles de pensée et les émotions pour faire deviner au groupe ce que ressent chaque personnage.
- En tant que pair·e animateur·rice, je veux utiliser l'outil **sans réseau** et retrouver mes conversations sur l'appareil.
- En tant que pair·e animateur·rice, je veux imprimer ou exporter une conversation en image pour la laisser au groupe.

**Membre**
- En tant que membre, je veux lire des conversations modèles sur un sujet (sextorsion, emprise…) pour reconnaître une situation que je vis.
- En tant que membre (phase 2), je veux dessiner une conversation que j'ai vécue, **en privé**, pour mettre des mots sur ce que j'ai ressenti et la montrer, si je le choisis, à une personne de l'annuaire ou à un·e pair·e.

**Équipe éditoriale (administration)**
- En tant qu'administrateur·rice, je veux créer, modifier, publier ou masquer des conversations modèles et les ranger par catégorie et par thème.
- En tant qu'administrateur·rice, je veux relier une conversation à une fiche de connaissances et à une BD existante.

**Personne malvoyante ou utilisant un lecteur d'écran**
- En tant que personne aveugle, je veux que chaque case soit lue dans l'ordre (« Afi dit… ; Afi pense… ; Afi ressent : peur ») pour suivre la conversation sans voir les images.

**Cas limites**
- Conversation vide, case sans bulle, texte trop long, émotion non choisie : messages clairs, rien ne se perd.
- Une conversation créée par un membre contient un nom, un numéro ou une adresse : avertissement avant tout partage.

## Requirements

### Must-Have (P0) — v1 « lire, animer, créer en séance »

1. **Format d'une conversation** : 1 à 8 cases ; par case, 1 à 4 personnages (bonshommes bâtons de la bibliothèque, distingués par une couleur de vêtement et une initiale, jamais un visage réaliste) ; pour chaque personnage, une **bulle « dit »** (bord plein) et/ou une **bulle « pense »** (bord en nuage), ≤ 140 caractères chacune ; une **émotion** au plus par personnage et par case ; jusqu'à 3 **symboles** par case (stop, cœur, point d'interrogation, téléphone, cadenas, alerte, écoute).
   - *Critère* : Étant donné une case avec deux personnages, quand j'ajoute une bulle « pense » à l'un, alors elle s'affiche en nuage et elle est annoncée « pense » au lecteur d'écran.
2. **Code couleur des émotions accessible** : 6 émotions (joie, tristesse, peur, colère, gêne/honte, calme/confiance), chacune = **couleur des jetons du design system + icône + mot**. Jamais la couleur seule ; contraste AA ; légende toujours visible.
   - *Critère* : en niveaux de gris, toutes les émotions restent distinguables (test visuel automatisé sur les tokens).
3. **Lecteur et mode animation** (web Vue + app Flutter) : case par case, plein écran, clavier ← → et balayage, **bouton « révéler les pensées et émotions »** (masquées au départ en mode animation), compteur annoncé, impression de la conversation entière.
4. **Éditeur simple** (web + app) : ajouter / réordonner / supprimer des cases (boutons « Monter », « Descendre ») ; choisir personnages, bulles, émotion, symboles dans des listes ; **aperçu immédiat** ; entièrement utilisable au clavier et au lecteur d'écran ; brouillon enregistré sur l'appareil.
5. **Bibliothèque de conversations modèles** gérée dans l'admin (`/admin/ccc`, nouvel onglet « Conversations en BD ») : créer, modifier, publier / masquer, catégorie (les 6 catégories des violences numériques + thèmes de vulgarisation), lien vers une fiche et une BD ; journal d'audit ; permission `ccc_tools.manage`.
6. **Lecture publique et membres** : les conversations publiées apparaissent dans `/ccc` (onglet « Conversations ») et sur la fiche liée ; pages publiques indexables comme les BD (Q19, Q98) si l'équipe le décide (question ouverte n° 1).
7. **Hors ligne** : conversations modèles et brouillons du ou de la pair·e lisibles et éditables sans réseau (même mécanisme que la lecture hors ligne des outils CCC, D-MOB-19).
8. **Texte alternatif généré** : pour chaque case, une description lisible construite à partir du contenu (« Case 2 : Kossi dit “…”. Afi pense “…”. Afi ressent : peur. »), utilisée par les lecteurs d'écran et à l'export.
9. **Rendu léger** : dessin en **SVG** composé côté client à partir de la bibliothèque (pas d'image générée), poids négligeable, net à toutes les tailles, export PNG à la demande.

### Nice-to-Have (P1)

- **Création par les membres, en privé** (phase 2) : mes conversations, visibles par moi seul·e, partage volontaire et révocable avec un·e pair·e ou une fiche de l'annuaire ; détection d'éléments identifiants (numéro, adresse, nom complet) avec avertissement avant partage.
- **Modèles de départ** (« emprise », « chantage », « dire non », « demander de l'aide ») pour démarrer plus vite.
- **Questions d'animation** associées à chaque conversation modèle (« Que pense Afi ici ? Que pourrait-elle répondre ? »).
- **Partage réseaux** d'une conversation modèle via les passerelles (P19-11), en visuel carré.

### Future Considerations (P2)

- Collaboration à plusieurs appareils en séance (vote du groupe sur l'émotion).
- Bibliothèque de personnages étendue (âges, handicaps visibles, tenues), toujours sans visages réalistes.
- Versions audio (lecture des bulles) pour les publics non lecteurs.
- Traductions en langues locales.
- Statistiques agrégées d'usage en séance pour le rapport d'activité (sans contenu).

## Success Metrics

**Indicateurs avancés (2 à 6 semaines)**
- Taux d'adoption : part des pair·es actif·ves qui ouvrent au moins une conversation en mode animation — cible **50 %** à 30 jours, ambition 70 %.
- Taux d'achèvement : part des conversations ouvertes en mode animation déroulées jusqu'à la dernière case — cible **70 %**.
- Temps de création d'une conversation de 4 cases dans l'éditeur — cible **< 5 minutes** (test d'usage avec 5 pair·es).
- Taux d'erreur : aucune perte de brouillon signalée.

**Indicateurs retardés (1 à 6 mois)**
- Part des séances CCC utilisant l'outil — cible **60 %** à 3 mois.
- Compréhension déclarée en fin de séance — cible **75 %**.
- Conversations créées par les membres (phase 2) — cible **200** à 6 mois, **0** incident de données identifiantes publiées.

Mesure : événements agrégés côté serveur (ouverture, achèvement, création), jamais le contenu des conversations privées.

## Open Questions

1. **Indexation** : les conversations modèles sont-elles publiques et indexées comme les BD, ou réservées aux membres et pair·es ? — *client* (bloquant pour P0-6)
2. **Code couleur** : les 6 émotions proposées et leurs couleurs conviennent-elles au public béninois (sens culturel des couleurs) ? — *client, pair·es animateur·rices, design* (bloquant pour P0-2)
3. **Personnages** : bonshommes bâtons purs, ou silhouettes très simples reprenant les 6 personnages de la bible des BD (Afi, Sena, Kossi, Mariam, Tantie Rosine, Nadia) pour faire le lien ? — *design, client* (non bloquant)
4. **Création par les membres** (P1) : souhaitée dès la v1 ou en phase 2, vu le risque de données identifiantes ? — *client, juridique* (non bloquant)
5. **Rédaction des 12 modèles** : qui écrit et valide les conversations (équipe Xomiton, pair·es, conseil juridique pour les sujets de droit) ? — *client* (bloquant pour le lancement)
6. **Export** : PNG suffit-il, ou faut-il un PDF A4 imprimable pour les séances ? — *pair·es animateur·rices* (non bloquant)

## Timeline Considerations

- **Dépendances** : lecture hors ligne CCC (D-MOB-19, fait), design system (tokens de couleur), admin CCC (fait), passerelles P19-11 (en cours) pour le partage P1.
- **Phasage proposé** :
  - **Phase 1 (P0)** — format, lecteur et mode animation, éditeur en séance, 12 modèles, admin, hors ligne : environ 3 à 4 semaines de développement après réponse aux questions 1, 2 et 5.
  - **Phase 2 (P1)** — création privée par les membres, partage volontaire, détection d'éléments identifiants : 2 à 3 semaines, après une revue juridique.
  - **Phase 3 (P2)** — collaboration, audio, traductions : à replanifier selon l'usage mesuré.
- Aucune échéance contractuelle connue.

## Linked epics

_(regenerated by the librarian — wikilinks to epics for this initiative)_

- [[violences-numeriques-contenus-bd]] — initiative liée (BD illustrées)
- [[tableau-de-bord-admin]] — initiative sœur

## References

- [[_INDEX]] — global index
- `design-system/bd/BIBLE_PERSONNAGES.md`, `design-system/charte-graphique/CHARTE_GRAPHIQUE.md`, `docs/ACCESSIBILITE.md`, `docs/VOICE_AND_TONE.md`
- `docs/IMPLEMENTATION_PLAN.md` — P8-12 (outils CCC), P19 (contenus et BD), D-MOB-19 (hors ligne)
