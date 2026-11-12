---
type: prd
title: "Refonte du tableau de bord d'administration"
slug: "tableau-de-bord-admin"
status: draft
phase: "01"
created: 2026-11-12
updated: 2026-11-12
generated_by: sdlc-prd
owner: "Équipe Xomiton"
tags: [planning, prd, admin, moderation, communaute]
stakeholders: []
target_date: ""
---

# Refonte du tableau de bord d'administration

> _(Initiative PRD — décrit le QUOI et le POUR QUI ; le COMMENT relève des specs.)_
> Rédigé en français comme toute la documentation du projet (le vault demande l'anglais : écart signalé au client).
> Source de vérité du plan d'exécution : `docs/IMPLEMENTATION_PLAN.md`, tâches **P18-01 à P18-14**.

## Context

Le tableau de bord actuel (`/admin`, `App\Livewire\Admin\Dashboard\Index`, `DashboardStats`) a été conçu pour le **pilotage des alertes et des cas** (P10-04) : 7 KPI (nouvelles alertes, SOS actifs, cas ouverts / en cours / clos, comptes, taux de synchronisation), 5 graphiques de cas, dernières alertes, carte des SOS, raccourcis, état de la plateforme ; seuls les nouveaux SOS sont rafraîchis (`wire:poll` 30 s).

La plateforme est aussi devenue un **réseau social d'entraide** (fil, forums, messagerie, groupes, événements, vidéos relues). L'équipe d'administration et de modération n'a pas de vue d'ensemble de la santé de la communauté : les chiffres de forum et de messagerie sont dispersés (`/admin/reseau`), les signalements en attente ne remontent pas sur l'accueil, et plusieurs outils attendus d'un espace d'entraide n'existent pas (badges, annonces, boîte à idées, gestion des forums, proposition de ressources par les membres).

Le client a fourni le 12/11/2026 une liste d'exigences en 5 blocs (indicateurs, modération, membres et rôles, contenu, engagement). Un état des lieux du code a été fait le même jour : il est résumé dans **§ Scope** (colonne « existant »).

## Problem

- **Qui :** les administrateur·rices et modérateur·rices (rôles `admin`, `superadmin`, `moderateur`).
- **Quoi :** sans indicateurs communautaires ni file priorisée, un signalement grave (harcèlement, menace, contenu intime) peut attendre derrière des signalements bénins ; l'équipe ne voit pas si la communauté grandit, s'essouffle ou si l'entraide fonctionne (questions sans réponse).
- **Impact :** risque pour la sécurité des membres (public vulnérable : filles, femmes, personnes LBTQ+ au Bénin), perte de confiance, charge de modération mal répartie.

## Hypotheses

- **H1 :** une file de signalements triée par **score de priorité** (gravité du motif, nombre de personnes qui signalent, nature du contenu, récidive) réduit le délai de traitement des signalements graves — _validé par_ la médiane « signalement → première décision » sur les motifs graves, avant / après.
- **H2 :** afficher les KPI communautaires sur l'accueil (actifs, nouveaux, rétention, échanges, questions résolues) permet à l'équipe de repérer un essoufflement et d'agir (événement, annonce) — _validé par_ l'usage des raccourcis « Annonce » / « Événement » depuis le tableau de bord et l'évolution de la rétention à 30 jours.
- **H3 :** la reconnaissance (badges) et la « réponse validée » dans les forums augmentent l'entraide — _validé par_ le taux de sujets résolus et le nombre de réponses par sujet sur 3 mois.

## Scope

### In scope

Légende de l'existant (état des lieux du 12/11/2026) : ✅ existe · ◐ partiel · ✗ absent.

**Bloc 1 — Indicateurs (accueil `/admin`)**
- ◐ Membres : total (existe en comptage par statut) **+ nouveaux du jour (✗) + rétention à 30 jours (✗)** — définition proposée : part des comptes inscrits il y a 30 à 60 jours actifs (`last_seen_at`) dans les 30 derniers jours.
- ✗ Activité des échanges : nouveaux sujets, réponses de forum, messages privés échangés (**comptages seulement, jamais de contenu**), sur la journée et 7 jours.
- ◐ Signalements en attente (existe dans `/admin/moderation`, absent de l'accueil) avec la part « priorité haute ».
- ✗ Taux de résolution : demande la notion de **réponse validée** (✗ : `ForumTopic` n'a ni `solved` ni `accepted_reply_id`) → fonctionnalité forum : l'auteur·rice (ou la modération) marque une réponse comme validée.
- ◐ Temps réel : rafraîchissement périodique des KPI (aujourd'hui seulement les SOS), sans recharger la page.

**Bloc 2 — Modération et sécurité (`/admin/moderation`)**
- ✅ File des signalements (publication, commentaire, message, utilisateur, sujet, réponse).
- ✗ **Tri par urgence** : score de priorité + badge de priorité + tri par défaut.
- ◐ Aperçu du contexte : ajouter le fil (publication parente, sujet) et l'historique de modération de l'auteur·rice ; les messages privés restent à lecture journalisée du seul message signalé (confidentialité).
- ✅ Mots interdits · ◐ spam (motifs de liens) → ajouter une **détection de spam** (fréquence, doublons) · ◐ quarantaine (masquage automatique existant ; jamais pour les messages 1-à-1).
- ◐ Actions rapides : ajouter **« Approuver »** distinct d'« Ignorer », **suspension temporaire avec date de fin**, **bannissement** (statut absent aujourd'hui).
- ✅ Journal d'audit → ajouter le **filtre par modérateur·rice**.

**Bloc 3 — Membres et rôles (`/admin/utilisateurs`)**
- ◐ Filtres : ajouter plage de dates d'inscription, statut « banni », niveau d'activité.
- ◐ Rôles : existants `membre`, `pair` (pair animateur·rice), `moderateur`, `admin`, `superadmin`. **« Membre vérifié » et « bénévole écoutant·e » n'existent pas** → décision client (Q97).
- ✗ **Badges** (manuels et automatiques) : « Conseiller bienveillant », « Membre actif », « Pilier de la communauté » — règles automatiques à définir, retrait possible, affichage sur le profil si la personne l'accepte.

**Bloc 4 — Contenu et thématiques**
- ✅ Catégories (`/admin/contenu`).
- ✗ **Gestion des forums** (créer, réordonner, archiver ; aujourd'hui seulement par seeder).
- ◐ Épingler : `is_pinned` / `is_locked` existent sur les sujets mais **sans action admin** ; ajouter l'épinglage de guides et de ressources d'urgence.
- ✗ **Proposition de ressources par les membres + file de validation** avant publication.

**Bloc 5 — Engagement et communication**
- ✗ **Annonce globale** (bannière web + app, période, public, fermeture par la personne ; distincte du bandeau de maintenance).
- ◐ Événements : ajouter **calendrier admin**, types (groupe de parole, atelier, rencontre) et **file d'approbation** optionnelle.
- ✗ **Boîte à suggestions** (formulaire membre, catégories, statut, réponse de l'équipe).

### Out of scope

- Toute lecture du **contenu** des messages privés hors du message signalé (confidentialité, Q-décisions existantes).
- Les indicateurs du programme (`/admin/indicateurs`, rapports bailleurs) : déjà couverts par P11.
- Un moteur d'anti-spam externe (Akismet, etc.) : heuristiques internes seulement.
- La refonte visuelle globale de l'admin (même design system, mêmes composants).

## KPIs

| KPI | Baseline | Target | Timeline |
|---|---|---|---|
| Médiane « signalement grave → première décision » | à mesurer à la mise en ligne (non mesurée aujourd'hui) | < 4 h | 3 mois après M2 |
| Signalements en attente > 48 h | à mesurer | 0 | 3 mois après M2 |
| Taux de sujets de forum résolus (réponse validée) | 0 (notion absente) | ≥ 40 % | 3 mois après M4 |
| Rétention à 30 jours | à mesurer dès M1 | +10 points | 6 mois après M5 |

_Cibles proposées, à valider par le client._

## Risks

| Risk | Probability | Impact | Mitigation |
|---|---|---|---|
| Les KPI de messagerie sont perçus comme une surveillance | medium | high | Comptages agrégés seulement, aucun contenu, mention dans la politique de confidentialité |
| Badges et classements créent compétition ou exposition (public vulnérable) | medium | medium | Badges sur consentement, jamais de classement public, retrait par la personne |
| Bannissement et suspension mal utilisés | low | high | Motif obligatoire, journal d'audit, contestation possible, double contrôle pour le bannissement |
| Requêtes KPI lentes sur hébergement mutualisé (cPanel) | medium | medium | Agrégats mis en cache 60 s, index, pas de comptage en direct sur gros volumes |
| Périmètre très large (5 blocs) | high | medium | Livraison en 5 jalons indépendants, chacun utile seul |

## Schedule

- **Kickoff:** 2026-11-12 (PRD en brouillon)
- **Milestone M1:** à planifier — Bloc 1 Indicateurs (P18-01 à P18-03)
- **Milestone M2:** à planifier — Bloc 2 Modération (P18-04 à P18-07)
- **Milestone M3:** à planifier — Bloc 3 Membres, rôles, badges (P18-08 à P18-09)
- **Milestone M4:** à planifier — Bloc 4 Forums, épinglage, ressources proposées (P18-10 à P18-12)
- **Milestone M5:** à planifier — Bloc 5 Annonces, événements, suggestions (P18-13 à P18-14)
- **GA:** après recette de M5

## Stakeholders

- **Sponsor:** à désigner (association porteuse de Xomiton)
- **Product Owner:** Équipe Xomiton
- **Tech Lead:** à désigner
- **Design Lead:** à désigner
- **Consulted stakeholders:** équipe de modération, pair·es animateur·rices, conseil juridique (bannissement, données)

## Décisions à prendre par le client (Q97)

1. « Bénévole écoutant·e » : nouveau rôle, ou rôle `pair` (pair animateur·rice) existant ?
2. « Membre vérifié » : qu'est-ce qui est vérifié (identité ? ancienneté ?) et par qui ?
3. Bannissement : nouveau statut définitif, ou suspension sans date de fin (déjà possible) ?
4. Badges : liste, règles automatiques (seuils), visibilité sur le profil.
5. Réponse validée : seulement l'auteur·rice du sujet, ou aussi la modération et les pair·es ?
6. Annonce globale : aussi dans l'app mobile ? aussi pour les visiteur·ses non connecté·es ?

## Linked epics

_(regenerated by the librarian — wikilinks to epics for this initiative)_

- [[violences-numeriques-contenus-bd]] — initiative sœur (contenus éducatifs)

## References

- [[product]] — product vision
- [[_INDEX]] — global index
- `docs/IMPLEMENTATION_PLAN.md` — P10-04 (tableau de bord actuel), P18 (ce chantier)
- `docs/MODULE_SPECIFICATIONS.md` §D1 Pilotage
- `docs/ROLE_ET_PERMISSIONS.md` §1, §3, §4
- `design-system/xomiton/pages/dashboard-admin.md`
