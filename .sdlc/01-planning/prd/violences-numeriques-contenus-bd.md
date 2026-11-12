---
type: prd
title: "Contenus éducatifs et BD sur les violences numériques"
slug: "violences-numeriques-contenus-bd"
status: draft
phase: "01"
created: 2026-11-12
updated: 2026-11-12
generated_by: sdlc-prd
owner: "Équipe Xomiton"
tags: [planning, prd, contenus, bd, violences-numeriques, ccc]
stakeholders: []
target_date: ""
---

# Contenus éducatifs et BD sur les violences numériques

> _(Initiative PRD — décrit le QUOI et le POUR QUI ; le COMMENT relève des specs.)_
> Rédigé en français comme toute la documentation du projet (le vault demande l'anglais : écart signalé au client).
> Source de vérité du plan d'exécution : `docs/IMPLEMENTATION_PLAN.md`, tâches **P19-01 à P19-12**.

## Context

Le client demande (12/11/2026) de **créer et publier** des contenus éducatifs et des bandes dessinées sur **24 formes de violences numériques**, en respectant le design system et la charte graphique de Xomiton, avec une chaîne de production : analyse du contenu → modélisation des personnages → scénario → génération d'images « premium » → couverture, pour l'enseignement (séances des pair·es animateur·rices) et le partage sur les réseaux sociaux.

**Existant (état des lieux du 12/11/2026)** :
- Les contenus éducatifs vivent dans `Thematique` (sections, `content_warning`, couverture, SEO), `Resource` (types fiche, guide, vidéo, audio, PDF, article, lien), `Faq` et `Category` ; gérés dans `/admin/contenu`, publiés sur `/thematiques` et `/ressources` (SEO, JSON-LD, OG 1200 × 630), semés depuis `web/database/seeders/data/content/`.
- Les BD sont des `CccTool` de type `bd` (planches `{image, caption, alt}`), avec CRUD complet dans `/admin/ccc`, lues sur `/ccc` (membres et pair·es), par l'API `GET /api/v1/ccc` et dans l'app (lecture hors ligne). Les 3 BD semées réutilisent **une seule photo** du prototype sur toutes leurs planches : ce ne sont pas de vraies BD.
- **Aucune page publique de BD** (`/bd-ssr`, `x-comic-viewer` prévus mais non faits ; pas de colonne `is_public`).
- Violences numériques : seulement le type de violence `numerique` (« Violence en ligne »), une boîte à images et la BD bd2. **Rien** sur la sextorsion, le revenge porn, les deepfakes ni le doxing.
- Partage : passerelles (`OutboundPost`, kind `bd`) vers Facebook, Telegram (API) et manuel assisté pour les autres ; pas de format de partage par planche.
- **Outillage** : aucun skill de BD ni générateur d'images dans le dépôt ou le catalogue du compte. Options disponibles : génération d'images par le connecteur Figma de la session, ou des extensions tierces (non installées), ou un·e illustrateur·rice. **Décision client** (Q98).

## Problem

- **Pour qui :** filles, femmes et personnes LBTQ+ au Bénin (membres et visiteur·ses), pair·es animateur·rices qui animent des séances, équipe de communication.
- **Quoi :** les violences numériques (harcèlement, sextorsion, diffusion d'images intimes, deepfakes, surveillance) sont mal connues, souvent tues, et les personnes ne savent pas quoi faire ni à qui s'adresser. La plateforme n'a pas de contenu pour les reconnaître et réagir, ni de support visuel pour les séances et les réseaux sociaux.
- **Impact :** signalements tardifs, preuves perdues, isolement ; séances CCC sans support adapté.

## Hypotheses

- **H1 :** une BD courte (4 à 6 planches) suivie d'une fiche « reconnaître · réagir · se faire aider » est mieux comprise et plus partagée qu'un texte seul — _validé par_ le taux de lecture jusqu'à la dernière planche et le nombre de partages par sujet.
- **H2 :** relier chaque contenu au bouton « Signaler », à l'annuaire et au SOS augmente les demandes d'aide sur les violences en ligne — _validé par_ les signalements de type `numerique` après mise en ligne (suivi agrégé, anonyme).
- **H3 :** une chaîne de production assistée (scénario + images) produit des visuels conformes à la charte avec une relecture humaine légère — _validé par_ le taux de visuels acceptés à la première relecture sur le pilote.

## Scope

### In scope

**A. Les 24 sujets (liste du client)** — harcèlement en ligne ; cyberharcèlement / pollupostage ; messages non sollicités ; sextorsion ; revenge porn ; surveillance / suivi non consenti ; traque via GPS ou médias sociaux ; contrôle des appareils ; piratage ; forcer le partage de mots de passe ; usurpation d'identité ; vol d'identité ; doxing / distribution malveillante ; diffamation en ligne ; manipulation psychologique ; deepfakes / manipulation d'images par IA ; sollicitation sexuelle via technologies ; arnaques sentimentales / financières ; forums ou groupes de haine ; trolling et raids numériques ; refus de services ou accès bloqués ; photos privées partagées ; intelligence artificielle. Recoupements à trancher (Q98) : « photos privées partagées » ≈ « revenge porn » ; « intelligence artificielle » ≈ « deepfakes » ; « traque GPS » ≈ « surveillance ».

**B. Trois types de contenu par sujet** (interprétation de « trois types de contenu », à valider) :
1. **BD** : 4 à 6 planches + couverture, personnages récurrents, scénario qui finit sur l'aide (Signaler, annuaire, SOS, Xomiton) → `CccTool` `bd`.
2. **Fiche de connaissances** : définition, signes, que faire tout de suite (garder les preuves, ne pas payer, bloquer, signaler), où trouver de l'aide, ce que dit la loi au Bénin (à fournir et valider par le conseil juridique) → `Thematique` (sections) ou `Resource` `fiche`, dans une nouvelle catégorie « Violences numériques ».
3. **Visuel de partage** : carré 1080 × 1080 pour les réseaux et image OG 1200 × 630, sans texte incrusté illisible, avec lien vers la fiche → passerelles existantes.

**C. Chaîne de production (par sujet)** : analyse du sujet (sources, messages clés) → **bible des personnages** (casting récurrent, divers en peau, morphologie, expression de genre ; personnes actrices, jamais en position de victime) → scénario et découpage → génération des images (style illustration plate de la charte, palette, pas de photoréalisme) → couverture → **relecture humaine obligatoire** (association + conseil juridique) → texte alternatif de chaque planche → publication.

**D. Évolutions de la plateforme** :
- Page **publique** des BD (indexable, `x-comic-viewer`, visionneuse accessible) avec un drapeau `is_public` sur `ccc_tools` (Q19, prévu dans P8-12).
- Catégorie et page carrefour « Violences numériques » reliant fiches, BD, signalement, annuaire et SOS.
- Lien BD ↔ fiche (une BD renvoie à sa fiche et inversement).
- Format de partage par planche et visuel carré dans les passerelles.
- Avertissement de contenu (`content_warning`) et compatibilité **mode discret** (aperçus de partage neutres).

### Out of scope

- Tout visuel explicite : nudité, scène de violence, détresse, bâillon, main sur la bouche, arme (charte §7 et §12). La sextorsion et la diffusion d'images intimes sont représentées par des écrans, icônes et réactions, jamais par l'image intime.
- Toute image IA **présentée comme une vraie personne** ou ressemblant à une personne réelle.
- Le mot « victime » dans l'interface publique (VOICE_AND_TONE : « personne », « survivant·e »).
- Le quiz (aucune source de questions validée).
- La traduction en langues locales (fon, yoruba…) : chantier distinct.

## KPIs

| KPI | Baseline | Target | Timeline |
|---|---|---|---|
| Sujets publiés (BD + fiche + visuel) | 0 / 24 | 24 / 24 | M3 |
| Lecture d'une BD jusqu'à la dernière planche | à mesurer au pilote | ≥ 60 % | 3 mois après M2 |
| Partages des visuels par les passerelles | 0 | 1 par sujet et par mois | 3 mois après M3 |
| Visuels acceptés à la première relecture | à mesurer au pilote | ≥ 80 % | fin du pilote (M1) |

_Cibles proposées, à valider par le client._

## Risks

| Risk | Probability | Impact | Mitigation |
|---|---|---|---|
| Visuel qui retraumatise ou sexualise (sujets intimes) | medium | high | Règles de la charte dans chaque consigne d'image, relecture par l'association, pas d'image intime même suggérée |
| Information juridique fausse sur le droit béninois | medium | high | Relecture par le conseil juridique avant publication, date de mise à jour visible |
| Images générées hors charte ou incohérentes d'une planche à l'autre | high | medium | Bible des personnages, pilote de 3 sujets, relecture et retouches |
| Droits d'utilisation des images générées | medium | medium | Choisir un outil dont les conditions permettent l'usage commercial et le partage ; tracer outil et date dans `MANIFEST.json` |
| Poids des images sur réseaux 2G/3G | medium | medium | WebP 78–82, ≤ 100 Ko par planche (charte §7.1), variantes 320 / 1024 / 1600 existantes |
| Volume (environ 100 à 150 planches) | high | medium | Livraison par lots, pilote d'abord |

## Schedule

- **Kickoff:** 2026-11-12 (PRD en brouillon)
- **Milestone M1:** à planifier — **pilote** : bible des personnages + 3 sujets (proposés : harcèlement en ligne, sextorsion, photos privées partagées) + page publique des BD et page carrefour (P19-01 à P19-06)
- **Milestone M2:** à planifier — les sujets restants, par lots de 5 (P19-07 à P19-09)
- **Milestone M3:** à planifier — visuels de partage, passerelles, séances CCC (P19-10 à P19-12)
- **GA:** après validation du dernier lot par l'association et le conseil juridique

## Stakeholders

- **Sponsor:** à désigner (association porteuse de Xomiton)
- **Product Owner:** Équipe Xomiton
- **Tech Lead:** à désigner
- **Design Lead:** à désigner (garant·e de la charte)
- **Consulted stakeholders:** pair·es animateur·rices, conseil juridique, personnes concernées (relecture de sensibilité), équipe de communication

## Décisions à prendre par le client (Q98)

1. **Outil de génération d'images** : connecteur Figma de la session, une extension tierce (clé et coût à prévoir), ou un·e illustrateur·rice ? Conditions d'utilisation compatibles avec le partage public.
2. Les **trois types de contenu** sont-ils bien BD, fiche de connaissances et visuel de partage ?
3. Fusionner les sujets qui se recoupent (24 → environ 21) ?
4. Les 3 sujets du **pilote**.
5. Les BD sur ces sujets sont-elles **publiques** (indexées) ou réservées aux membres et pair·es ?
6. Qui fournit et valide les **références juridiques** béninoises ?

## Suggested next steps — Frontend UX Gate Sequence

Ce PRD comporte une surface utilisateur (page publique des BD, page carrefour, visionneuse). Avant les specs, enchaîner les portes UX :

| Gate | Skill | Output |
|------|-------|--------|
| 1 — UX Research + Product Design | `/sdlc-kit:ux new <title>` | `ux-criteria.md`, `user-flows.md`, `wireframes/` |

## Linked epics

_(regenerated by the librarian — wikilinks to epics for this initiative)_

- [[tableau-de-bord-admin]] — initiative sœur (administration)

## References

- [[product]] — product vision
- [[_INDEX]] — global index
- `design-system/charte-graphique/CHARTE_GRAPHIQUE.md` §3 (palette), §5 (typographies), §7 (illustration et photo), §12 (interdits)
- `docs/VOICE_AND_TONE.md`, `docs/SEO_CONTENT_GUIDE_INSTRUCTION.md`, `design-system/DECISIONS.md` (images IA)
- `docs/IMPLEMENTATION_PLAN.md` — P8-12 (page publique BD, Q19), P12-03, P12-04, P14 (passerelles), P19 (ce chantier)
