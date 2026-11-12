---
type: prd
title: "Conversations en BD (bulles de conversation)"
slug: "conversation-en-bd"
status: draft
phase: "01"
created: 2026-11-12
updated: 2026-11-12
generated_by: sdlc-prd
owner: "Équipe Xomiton"
tags: [planning, prd, ccc, bd, bulles, pedagogie, accessibilite]
stakeholders: []
target_date: ""
---

# Conversations en BD (bulles de conversation)

> Spécification produite avec `/product-management:write-spec` (demande du client du 12/11/2026), **révisée le même jour** avec les réponses du client. Rédigée en français comme le reste du projet.
> Source du plan d'exécution : `docs/IMPLEMENTATION_PLAN.md`, tâches **P20-01 à P20-12**, décisions **Q99**.
> Annexes : `design-system/bd/conversations/GRAMMAIRE_DES_BULLES.md` (dessin et rendu des bulles, d'après le tutoriel Mangakoaching fourni par le client) et `design-system/bd/conversations/MODELES.md` (casting et 12 conversations modèles, proposés et validés).

## Décisions du client (12/11/2026)

| Question | Décision |
|---|---|
| Style | **Pas** de dessins simples, de bonshommes bâtons ni de symboles : on garde la **BD illustrée** des BD « violences numériques », avec des **bulles de conversation** |
| Visibilité | **Publiques et indexées**, comme les BD |
| Les 12 modèles | Claude **propose et valide** les brouillons → faits (`MODELES.md`) |
| Personnages | « Oui et non » : on **reprend** les personnages des BD (Afi, Sena, Kossi, Mariam, Tantie Rosine, Nadia) **et** on en ajoute 4 pour les rôles qu'ils ne couvrent pas (Fifamè, adolescente ; Yasmine, en fauteuil roulant ; Ablavi, cheffe de projet ; Joël, sourd) |
| Créations publiées | **Anonymes par défaut** (la personne peut choisir d'afficher son pseudonyme) |
| Créations des membres | **Oui, dès la version 1** |
| Export | **PDF et PNG** |
| Référence pour les bulles | https://www.mangakoaching.com/tutoriels/anatomie-bulle-de-bd/ |

Le code couleur des émotions de la première version de la spécification est **abandonné** : l'émotion passe par le dessin du visage et par la **forme de la bulle** (cri hérissé, peur tremblée, pensée en nuage…), comme dans le tutoriel.

## Context

Xomiton publie déjà des BD illustrées sur les violences numériques ([[violences-numeriques-contenus-bd]]). Elles racontent une histoire en 4 planches, avec une légende sous chaque image : les personnages n'y **parlent** pas.

La « conversation en BD » met au contraire la **parole** au centre : chaque case montre ce que les personnes **disent** (bulle de parole), **pensent** (bulle de pensée), **crient**, **chuchotent** ou **écrivent** (bulle de message). C'est l'écart entre ce qui est dit et ce qui est pensé qui fait le travail pédagogique : dire « peut-être » en pensant « non », gronder en ayant peur, rester silencieux·se par crainte du groupe. Le format sert aussi à **vulgariser** (double authentification, règles) et à sensibiliser au **handicap** et à la **vie en entreprise**.

## Problem Statement

Les pair·es animateur·rices n'ont pas de support pour **décomposer une interaction** en séance (consentement, pression du groupe, sexisme, handicap, coming out), et les membres n'ont aucun moyen de **raconter une situation vécue** de façon visuelle et sûre. Les textes longs sont peu lus, en particulier par les personnes peu lettrées ou sur téléphone. Sans ce support, les situations d'emprise ou de pression restent abstraites et les séances reposent sur la seule parole de l'animateur·rice.

## Goals

1. **Usage en séance** : 60 % des séances CCC des pair·es utilisent au moins une conversation en BD, 3 mois après la mise en ligne.
2. **Compréhension** : 75 % des participant·es d'une séance déclarent avoir mieux compris la situation (question en fin de séance, mesure agrégée).
3. **Audience publique** : les 12 modèles sont indexés et ensemble lus au moins 2 000 fois par mois, 3 mois après la mise en ligne.
4. **Expression des membres** : 200 conversations créées par des membres dans les 3 premiers mois, dont au moins 30 publiées après modération.
5. **Accessibilité** : 100 % des conversations lisibles au lecteur d'écran (chaque bulle lue avec son personnage et son type) et au clavier.

## Non-Goals

- **Dessin libre** ou import d'images par les membres : risque de contenus non conformes et de photos de personnes réelles ; les membres composent à partir d'une bibliothèque de scènes validées.
- **Génération d'images par IA à la demande des membres** : coût des crédits, risque de contenus non conformes, pas de relecture possible avant affichage.
- **Thérapie clinique** : outil d'éducation et de soutien entre pair·es, pas un soin.
- **Édition à plusieurs en temps réel** : complexité sans besoin avéré en version 1.
- **Langues locales** (fon, yoruba…) : chantier distinct ; le format (texte réel dans les bulles) le rendra possible.

## User Stories

**Visiteur·se (non connecté·e)**
- En tant que visiteur·se, je veux lire une conversation en BD case par case, avec les bulles dans l'image, pour comprendre une situation sans lire un long texte.
- En tant que visiteur·se qui utilise un lecteur d'écran, je veux entendre chaque bulle avec le nom du personnage et sa nature (dit, pense, crie, chuchote, message), pour suivre la conversation comme les autres.
- En tant que visiteur·se, je veux télécharger la conversation en PDF ou en PNG pour la garder ou la partager.
- En tant que visiteur·se, je veux trouver à la fin des liens d'aide (Signaler, SOS, urgences, aide) quand le sujet me concerne.

**Pair·e animateur·rice**
- En tant que pair·e animateur·rice, je veux projeter une conversation en **mode séance** qui affiche d'abord les paroles puis révèle les pensées, pour faire réfléchir le groupe à l'écart entre ce qui est dit et ce qui est pensé.
- En tant que pair·e animateur·rice, je veux les questions d'animation de chaque modèle, pour préparer ma séance.
- En tant que pair·e animateur·rice, je veux imprimer une conversation en PDF A4 avec ses questions, pour animer sans connexion ni projecteur.
- En tant que pair·e animateur·rice, je veux utiliser les conversations hors ligne dans l'app, pour animer là où le réseau manque.

**Membre**
- En tant que membre, je veux créer ma propre conversation en choisissant des scènes illustrées et en ajoutant des bulles, pour raconter une situation vécue ou imaginée.
- En tant que membre, je veux garder ma création privée tant que je le souhaite, pour ne rien exposer sans le vouloir.
- En tant que membre, je veux proposer ma création à la publication, sous mon pseudonyme ou anonymement, pour aider d'autres personnes.
- En tant que membre, je veux exporter ma création en PDF ou en PNG.
- En tant que membre en mode discret, je veux que l'éditeur et mes créations n'apparaissent pas dans un aperçu de partage ni dans un titre d'onglet explicite.

**Équipe d'administration et de modération**
- En tant que modérateur·rice, je veux valider ou refuser une création proposée avant qu'elle soit publique, avec un motif, pour qu'aucun contenu nuisible ne soit indexé.
- En tant qu'administrateur·rice, je veux créer et modifier les conversations modèles (scènes, bulles, questions) dans l'admin, sans développeur.
- En tant que modérateur·rice, je veux qu'une conversation publiée puisse être signalée, pour réagir vite.

**Cas limites**
- Une bulle trop longue → l'éditeur propose de la couper en bulles associées.
- Une création qui contient un mot interdit, un numéro de téléphone ou une adresse → bloquée avant la proposition, avec une explication.
- Une création refusée → la personne voit le motif et peut la corriger ; rien n'est public.
- Une case sans bulle → autorisée (silence, regard) ; une conversation sans aucune bulle → refusée.

## Requirements

### P0 — indispensables (version 1)

**P0-1 · Format d'une conversation**
- 3 à 8 cases illustrées (format 3:2) ; titre, résumé, thème, catégorie, public conseillé, message clé, avertissement de contenu facultatif.
- Bulles des 9 types de la grammaire : `parole`, `pensee`, `cri`, `tremblee`, `chuchote`, `joie`, `message`, `voix_appareil`, `cartouche` ; queue vers le personnage, l'écran ou le bord de la case (hors-champ) ; bulles associées et superposées.
- Au plus 20 mots par bulle et 4 bulles par case (hors cartouche).
- *Critères* : [ ] chaque type a la forme décrite dans la grammaire ; [ ] la queue d'une chaîne associée n'est dessinée que sur la dernière bulle ; [ ] les positions sont en pourcentage et le rendu est identique de 320 px à 1600 px de large.

**P0-2 · Rendu des bulles par la plateforme**
- Les illustrations ne contiennent **aucun texte** ; les bulles sont dessinées en SVG par-dessus, avec du texte réel.
- *Critères* : [ ] le texte des bulles est présent dans le HTML de la page publique (indexable) ; [ ] corriger une bulle ne demande pas de régénérer l'image ; [ ] contraste du texte ≥ 7:1.

**P0-3 · Pages publiques indexées**
- Liste des conversations publiées et page par conversation (adresse lisible), SEO (titre, description, image de partage, JSON-LD), sitemap.
- Lecture case par case (boutons, clavier ← →) et lecture de toutes les cases à la suite sans JavaScript.
- Fin de lecture : message clé, liens d'aide (Signaler, SOS, urgences, aide), autres conversations, lien vers la fiche ou la BD du même thème quand il existe.
- *Critères* : [ ] audit SEO du projet passé ; [ ] une conversation non publiée renvoie une page introuvable neutre ; [ ] mode discret respecté (titre d'onglet neutre, cases repliées) pour une personne connectée.

**P0-4 · Accessibilité**
- Chaque case est une figure avec une description de la scène ; les bulles sont lues dans l'ordre de lecture : « Fifamè pense : … », « Message reçu de … : … ».
- *Critères* : [ ] parcours complet au clavier ; [ ] lecture correcte avec un lecteur d'écran (NVDA ou TalkBack) ; [ ] la forme de la bulle n'est jamais le seul repère.

**P0-5 · Mode séance**
- Projection plein écran, une case à la fois ; affichage d'abord des bulles dites (tous les types sauf `pensee`), puis bouton « Révéler les pensées » ; questions d'animation à la fin.
- *Critères* : [ ] utilisable à la télécommande ou au clavier ; [ ] fonctionne hors ligne dans l'app.

**P0-6 · Créations des membres**
- Éditeur réservé aux membres connecté·es : choix de cases dans une **bibliothèque de scènes** illustrées et validées (cases des modèles et scènes neutres supplémentaires), ajout, déplacement et dimensionnement automatique des bulles d'après leur texte, choix du type et du personnage, orientation de la queue, aperçu.
- Utilisable au clavier et sur téléphone (glisser ou boutons de position).
- Création **privée** par défaut, brouillon enregistré automatiquement.
- Contrôles de la grammaire (§7 de la grammaire), filtre de mots interdits du projet, blocage des numéros de téléphone, adresses e-mail et liens.
- *Critères* : [ ] une création ne quitte jamais l'état privé sans action de la personne ; [ ] l'éditeur ne permet aucun import d'image ; [ ] chaque contrôle refuse avec un message clair.

**P0-7 · Publication et modération des créations**
- « Proposer à la publication » (**anonyme par défaut**, pseudonyme sur choix) → file de modération dans l'admin → publication ou refus motivé ; retrait possible à tout moment par l'auteur·rice.
- Signalement d'une conversation publiée (motifs existants du projet), traité dans `/admin/moderation`.
- *Critères* : [ ] rien n'est public ni indexé avant validation ; [ ] l'auteur·rice est notifié·e de la décision ; [ ] un retrait par l'auteur·rice dépublie immédiatement ; [ ] décisions journalisées.

**P0-8 · Export PDF et PNG**
- PNG : une image par case et une image « bande » de toute la conversation (1080 px de large) pour les réseaux.
- PDF : A4, cases et bulles en vectoriel, titre, message clé, questions d'animation (pour les modèles), mention de la source.
- Disponible pour les conversations publiées (visiteur·ses) et pour ses propres créations (membre).
- *Critères* : [ ] le texte du PDF est sélectionnable ; [ ] PNG ≤ 400 Ko par case ; [ ] l'export d'une création privée n'est possible que par son auteur·rice.

**P0-9 · Administration des modèles**
- Onglet « Conversations en BD » dans `/admin/ccc` : créer, modifier, ordonner, publier ; gestion de la bibliothèque de scènes ; import des 12 modèles depuis les fichiers de données (idempotent, comme l'import des BD).
- *Critères* : [ ] permission dédiée ; [ ] actions journalisées.

**P0-10 · Les 12 modèles**
- Les 12 conversations de `MODELES.md`, illustrées (60 cases), importées et publiées.
- *Critères* : [ ] 60 cases conformes à la bible (aucun texte dans l'image, zone des bulles respectée) ; [ ] WebP ≤ 100 Ko.

### P1 — souhaitables (suivi rapide)

- **Partage** des conversations publiées par les passerelles existantes (Facebook, Telegram) et dans le fil social, avec l'image « bande ».
- **API et app** : lecture des conversations dans l'app Flutter avec téléchargement hors ligne (le mode séance hors ligne est déjà en P0-5).
- **Création depuis l'app** (la version 1 crée sur le web).
- Bibliothèque de scènes enrichie (nouveaux décors : école, marché, transport, hôpital) et poses supplémentaires du casting.
- Statistiques agrégées : lectures, exports, usages en séance.

### P2 — plus tard (à ne pas empêcher)

- Traductions et langues locales (texte réel dans les bulles, structure prête).
- Lecture audio des bulles (voix enregistrées).
- Création à plusieurs et commentaires.
- Personnages supplémentaires choisis par les membres.

## Success Metrics

**Indicateurs avancés (premières semaines)**
- Taux de lecture jusqu'à la dernière case : ≥ 60 % (pages publiques).
- Taux d'exports (PDF ou PNG) par lecture : ≥ 5 %.
- Membres qui ouvrent l'éditeur puis enregistrent une création : ≥ 40 %.
- Délai médian de modération d'une création proposée : < 48 h.

**Indicateurs de fond (3 mois)**
- Séances CCC utilisant une conversation : ≥ 60 % (Goal 1).
- Compréhension déclarée : ≥ 75 % (Goal 2).
- Créations publiées : ≥ 30 (Goal 4).
- Lectures publiques : ≥ 2 000 par mois (Goal 3).

Mesure : compteurs agrégés et anonymes (pas de suivi individuel), comme les autres indicateurs du programme.

## Open Questions

| Question | Qui | Bloquante ? |
|---|---|---|
| Âge minimal pour créer une conversation | association, juridique | non |
| Relecture santé de M11 et relecture de sensibilité de M03, M05, M08 avant publication ? | association | non |

## Timeline Considerations

- **Dépendances** : crédits IA Figma (épuisés jusqu'au 13/11/2026, partagés avec les 15 BD restantes) ; modération existante (`/admin/moderation`) ; filtre de mots interdits existant ; visionneuse `x-comic-viewer` (base de la lecture case par case).
- **Phase 1 (4 à 5 semaines)** : P0-1 à P0-10.
  - Semaine 1 : format, rendu SVG des bulles, import et pages publiques des modèles (images provisoires possibles).
  - Semaines 2 et 3 : éditeur des membres, contrôles, publication et modération.
  - Semaine 4 : export PDF et PNG, mode séance, administration.
  - En parallèle dès que les crédits le permettent : 60 illustrations.
- **Phase 2** : P1 (partage, app, création depuis l'app, statistiques).

## Linked epics

_(regenerated by the librarian — wikilinks to epics for this initiative)_

- [[violences-numeriques-contenus-bd]] — initiative sœur (BD illustrées)
- [[tableau-de-bord-admin]] — modération et administration

## References

- [[_INDEX]] — global index
- `design-system/bd/conversations/GRAMMAIRE_DES_BULLES.md`, `design-system/bd/conversations/MODELES.md`
- `design-system/bd/BIBLE_PERSONNAGES.md` (style et règles non négociables)
- Tutoriel « Bulle de BD : types, usages et tracé », Mangakoaching : https://www.mangakoaching.com/tutoriels/anatomie-bulle-de-bd/
- `design-system/charte-graphique/CHARTE_GRAPHIQUE.md` §5 (typographie), §7 (illustration), §12 (interdits)
- `docs/VOICE_AND_TONE.md`, `docs/IMPLEMENTATION_PLAN.md` (P19, P20)
