---
name: xomiton-module-specifications
description: Spécifications de chaque module Xomiton, écran par écran et onglet par onglet — site public, espace membre (Vue), espace pair éducateur·rice, administration (Livewire), application mobile (Flutter). Donne pour chaque écran la route, les rôles, les blocs, les KPI, les colonnes et filtres de liste, les modales, les règles et les appels API. À charger avant de développer ou modifier un module.
---

# Spécifications des modules

Conventions : **Réf.** = fichier de référence dans `prototype_social/` (web) ou écran de `prototype_mobile/` (mobile). Les rôles renvoient à [ROLE_ET_PERMISSIONS.md](ROLE_ET_PERMISSIONS.md) ; les gabarits d'écran de gestion à [UI_UX_DESIGN_SYSTEM.md](UI_UX_DESIGN_SYSTEM.md) §4.

---

## A. Site public (Blade, indexable)

| Écran | Route | Réf. | Contenu |
|---|---|---|---|
| Accueil | `/` | `landing.php` | Voir [HOME_PAGE_COPY.md](HOME_PAGE_COPY.md) et [FRONT_END_SECTION_ORDER_INSTRUCTION.md](FRONT_END_SECTION_ORDER_INSTRUCTION.md) |
| Thématiques | `/thematiques`, `/thematiques/{slug}` | `thematiques.php`, `thematique.php` | Liste filtrable par catégorie ; fiche avec sections en accordéon, contacts liés, « J'ai besoin d'aide » |
| Ressources | `/ressources`, `/ressources/{slug}` | `ressources.php`, `ressource.php` | Recherche, filtres (type, catégorie), « Charger plus », téléchargement |
| Comprendre les violences | `/violences` | `violences.php` | Catégories de violences, signes, que faire |
| Lois et codes du Bénin | `/lois` | `lois-benin.php` | Textes de loi résumés, liens officiels |
| Do Glegbe | `/do-glegbe` | `do-glegbe.php` | Hygiène menstruelle : fiches, quiz |
| Aide | `/aide`, `/aide/{slug}` | `help.php`, `help-details.php` | FAQ par catégorie, recherche |
| Signaler | `/signaler` | `signalement.php` | Formulaire anonyme en étapes, code de suivi (FR-ALT-01) |
| SOS | `/sos` | `sos.php` | Bouton 96 px, maintien 2 s, compte à rebours 5 s (FR-ALT-03) |
| Contacts d'urgence | `/urgences` | `urgences.php` | Numéros cliquables, plan de sécurité personnel (cases à cocher conservées localement) |
| Newsletter | `/newsletter?a=confirm|unsub` | `newsletter.php` | Confirmation (double opt-in), désinscription en un clic avec motif facultatif |
| Confidentialité et conditions | `/confidentialite` | `privacy-and-terms.php` | Charte, confidentialité, conditions, ancres |
| Connexion, inscription, mot de passe | `/connexion`, `/inscription`, `/mot-de-passe-oublie` | `sign-in.php`, `sign-up.php`, `forgot-password.php` | Voir [LOGIN_INSCRPTION_MULTISTEP_USER_INSTRUCTION.md](LOGIN_INSCRPTION_MULTISTEP_USER_INSTRUCTION.md) |

Pied de page public : marque + bouton SOS · Plateforme · Aide & protection · **Newsletter** (e-mail, consentement, lien confidentialité).

---

## B. Espace membre (Vue 3)

Gabarit 3 colonnes : `sidenav` (col-lg-3) · contenu (col-md-8 col-lg-6) · colonne droite (col-lg-3).

### B1. Fil d'actualités — `/fil` (Réf. `index.php`)
1. Accès rapides (4 cartes) : SOS (bordure rouge), Signaler, Assistant, Urgences.
2. **Créer une publication** (`card card-body`) : avatar + champ « Partagez vos pensées, {prénom}… » ouvrant la modale ; pastilles `nav nav-pills nav-stack small fw-normal` : **Photo · Événement · Sondage · Violences · Signaler**.
3. À la une : carrousel horizontal de thématiques.
4. Filtres : Tout · Témoignages · Questions · Ressources · Événements · **Sondages**.
5. Liste des publications (carte `x_render_post`) + « Charger plus ».
6. Colonne droite : Qui suivre, Événements, carte « En cas d'urgence ».

Modales : **Créer une publication** (texte, type, visibilité, photo, pseudonyme, rappel signalement/SOS) · **Créer un sondage** · **Créer un événement**.

### B2. Sondage (FR-SOC-02)
- **Création** (modale `#modalCreatePoll`) : question (5–200 car., compteur), réponses 2 à 6 (ajout et retrait, numérotées), texte d'accompagnement, image (5 Mo, aperçu), durée (1 j, 3 j, 1 sem., 2 sem., 1 mois, sans fin), visibilité, interrupteurs « plusieurs réponses », « résultats avant le vote », « pseudonyme ».
- **Dans le fil** : question + type + temps restant ; formulaire de vote (radio ou cases) ; après vote : barres de pourcentage, nombre de votes par réponse, réponse en tête en gras, coche sur son choix, total participant·es / votes, « Retirer mon vote » tant que le sondage est ouvert. Sondage terminé : résultats seuls.
- **API** : `POST /api/v1/posts {kind: poll…}`, `POST /api/v1/posts/{id}/poll/vote {choices[]}`, `DELETE …/poll/vote`.

### B3. Publication — `/fil/{id}` (Réf. `post-details.php`)
Carte complète, commentaires paginés, champ de commentaire, menu : enregistrer, signaler, masquer.

### B4. Messages — `/messages` (Réf. `messaging.php`)
Liste des conversations (recherche, non lus) · conversation (bulles, envoi, état hors ligne) · bloquer, signaler. Polling 4 s.

### B5. Groupes, pages, événements, forums
| Écran | Route | Réf. | Points clés |
|---|---|---|---|
| Groupes | `/groupes`, `/groupes/{id}` | `groups.php`, `group-details.php` | Mes groupes / Découvrir, rejoindre (privé = demande) |
| Pages | `/pages`, `/pages/creer` | `pages.php`, `create-page.php` | Pages d'organisations partenaires, suivre |
| Événements | `/evenements`, `/evenements/{id}` | `events.php`, `event-details.php` | Liste, détail, « Je participe », rappel |
| Forums | `/forums`, `/forums/{id}` | `forums.php`, `forum-topic.php` | Sujets, réponses, vues |
| Qui suivre | `/qui-suivre` | `who-to-follow.php` | Suggestions, invitations |

### B6. Profil — `/profil` (Réf. `my-profile*.php`)
Onglets : **Activité** · **À propos** · **Connexions** · **Médias** · **Événements**. En-tête : couverture, avatar, pseudo, zone, statistiques, complétion du profil.

### B7. Paramètres — `/parametres` (Réf. `settings.php`)
Sections : Compte · Apparence (clair, sombre, automatique) · Notifications · **Sécurité** (masquer le contenu sensible des notifications, code PIN) · Confidentialité (export, suppression) · Newsletter. *La préférence « Sortie rapide » est retirée (D8).*

### B8. Autres écrans membre
Notifications (`notifications.php`) · Assistant (`assistant.php`, garde-fous de crise) · Annuaire (`annuaire.php`, contacts publics) · Médiathèque (`albums.php`) · Outils CCC en lecture (`ccc.php`, `boites-a-images.php`, `bd-ssr.php`, `manuel-pair-animateur.php`).

---

## C. Espace pair éducateur·rice (Vue 3)

| Écran | Route | Réf. | Contenu |
|---|---|---|---|
| Tableau de bord pair | `/espace-pair` | `espace-pair.php` | KPI (dossiers collectés, ce mois-ci, en cours, clos) · tâches · raccourcis (Nouvelle collecte, Signalement, Annuaire, Outils CCC, Ressources) · alertes routées vers moi |
| Collecte de données | `/collecte` | `collecte.php` | Formulaire de cas hors ligne (zone → commune en cascade, population, sexe, âge, type, orientation), règles de validation actives, file d'attente visible |
| Outils CCC | `/ccc` | `ccc.php` | Boîtes à images, BD SSR, manuel, lois : mode présentation |

*La section « Consulter le stock » de l'espace pair est retirée avec le module de stock.*

---

## D. Administration (Livewire)

Chaque écran suit le gabarit §4 de [UI_UX_DESIGN_SYSTEM.md](UI_UX_DESIGN_SYSTEM.md). Colonnes, filtres et modales ci-dessous.

### D1. Pilotage
**Tableau de bord** (`admin/index.php`) — KPI : nouvelles alertes, SOS actifs, cas ouverts, cas en cours, cas clos, utilisateur·rices, taux de synchro. Graphiques : cas par mois (12 mois), par type de violence, par département, par population, sexe × tranche d'âge. Dernières alertes (prendre en charge), SOS actifs (carte schématique + liens Maps), raccourcis (Nouveau dossier, Traiter les alertes, Générer un rapport, Suivi des victimes, **Newsletter**, Annuaire, Fiche de référence, Inscriptions), état de la plateforme. *KPI et raccourci « Stock » retirés.*

**Alertes & signalements** (`alertes.php`) — Onglets par type : Toutes · SOS · Dénonciations · Besoins. Colonnes : N°, Type, Alerte (catégorie, description), Zone, GPS, Priorité, Statut, Routé vers. Filtres : mot-clé / n° / commune, statut, priorité, rôle ciblé. Actions : prendre en charge, changer de statut, réattribuer, note interne (« Visible uniquement par l'équipe »), export CSV.

**Indicateurs** (`indicateurs.php`) — IND-01 à IND-16 (IND-09 = taux d'ouverture de la newsletter) : code, libellé, définition, formule, source, fréquence, cible, valeur, tendance. Filtres période et zone. Modale « Nouvel indicateur personnalisé ».

**Rapports automatisés** (`rapports.php`) — Assistant de génération (type, période, dimensions, format PDF / Excel / Word, destinataires), historique des rapports générés.

### D2. Gestion des données
**Registres numériques** (`registres.php`) — Colonnes : Code, Date, Zone, Population, Sexe / âge, Violence, Statut, Pair. Filtres : code / commune / pair, statut, zone, type. Modale « Nouveau dossier » (avertissement : ne jamais saisir de nom ni de coordonnées).

**Fiches VBG / VDH** (`fiches.php`) — Fiche de référence en 5 sections : 1. Identification pseudonymisée · 2. Faits · 3. Services reçus · 4. Orientation (annuaire) · 5. Consentement.

**Suivi des victimes** (`suivi.php`) — Frise de suivi par dossier, « Ajouter une étape », liste de toutes les étapes (date, étape, par, résultat, prochaine échéance).

**Annuaire dynamique** (`annuaire.php`) — KPI : contacts, actifs, points focaux, juristes · psy · gynéco. Liste (contact, rôle, zone, coordonnées, actif, actions), filtres rôle / zone / statut, import / export CSV, modale contact, **aperçu du routage des alertes** (simulateur besoin × zone + tableau département × besoin, badge « repli »).

**Règles de validation** (`validation.php`) — KPI : règles actives, complétude, doublons suspects, score qualité. Catalogue des règles (activer / désactiver, exemples valide / invalide), règles de plausibilité, détection des doublons, rapport de qualité.

### D3. Plateforme
**Utilisateurs & rôles** (`utilisateurs.php`) — KPI : comptes, actifs, en attente, suspendus. Liste filtrable (rôle, statut, zone, recherche), modale utilisateur, validation des comptes en attente (e-mail « compte validé »), **matrice des permissions** ([ROLE_ET_PERMISSIONS.md](ROLE_ET_PERMISSIONS.md) §3).

**Inscriptions (on/off)** (`inscriptions.php`) — KPI : cette semaine, en attente ; options d'inscription (ouvertes, validation manuelle), inscriptions par semaine, demandes en attente, 10 dernières.

**Contenu : thématiques & ressources** (`contenu.php`) — Onglets : **Ressources** · **Thématiques** · **FAQ** · **Catégories**. Modales ressource et question ; publier / brouillon.

**Newsletter** (`newsletter.php`) — Voir §D5.

**Outils CCC** (`ccc.php`) — Onglets : Manuel du pair animateur · Boîtes à images · BD SSR · Lois et codes. KPI : outils numérisés, publiés, masqués, images personnalisées. Remplacer une image.

**Réseau social** (`reseau.php`) — Onglets : Groupes · Pages · Événements · Forums · Publications. KPI par type. Masquer, supprimer, voir.

**Modération** (`moderation.php`) — KPI : à traiter, traités, suspensions, mots surveillés. File de modération, mots interdits, charte de bienveillance.

**Assistant LLM** (`assistant.php`) — Base de connaissances (modale), prompt système, garde-fous et escalade vers un humain, conversations signalées, statistiques.

*Passerelles réseaux sociaux (`passerelles.php`) : module abandonné par la décision D4 — ne pas implémenter ; seul le partage par lien est conservé.*

### D4. Système
**Hors connexion & synchro** (`synchronisation.php`) — KPI : appareils suivis, éléments en attente, conflits, sans synchro > 24 h. Appareils & pairs, résolution des conflits (valeur serveur / appareil), journal, paramètres, cache local.

**Mises à jour & optimisation** (`mises-a-jour.php`) — KPI : version actuelle, versions planifiées, sauvegardes, maintenance. Historique des versions, publier ou planifier une version, fenêtre de maintenance, **Sauvegardes**, optimisation (cache, images, journaux, sauvegarde, index, service worker), pages les plus utilisées, adoption des fonctionnalités.
- **Carte Sauvegardes** : nombre et taille totale, « Sauvegarder maintenant », dernière sauvegarde (date, collections, taille), liste (date, fichiers, taille, badge « avant restauration ») avec actions **Aperçu · Télécharger (ZIP) · Supprimer**.
- **Modale Aperçu** : KPI (collections, enregistrements, taille, collections modifiées depuis), tableau par collection (enregistrements, actuellement, écart, taille, « Voir »), extrait JSON des 5 premiers enregistrements avec champs sensibles masqués, restauration après saisie de « RESTAURER » (RG-13).

**Templates e-mails** (`emails.php`) — Voir §D6.

**Paramètres** (`parametres.php`) — Paramètres généraux (nom, e-mail de contact, fuseau, langues, interrupteurs), sécurité (mot de passe, session, double authentification), identité visuelle, informations système.

**Journal d'audit** (`journal.php`) — KPI : entrées, aujourd'hui, utilisateur·rices distinct·es, résultats du filtre. Filtres, export.

**Recherche globale** (`recherche.php`, Ctrl K) — Résultats groupés : utilisateur·rices, dossiers, alertes, annuaire, thématiques, réseau social, journal.

### D5. Newsletter (`admin/newsletter.php`)
KPI permanents : abonné·es actifs (et en attente), taux d'ouverture, taux de clic, taux de désabonnement, taux de rebond.

| Onglet | Contenu |
|---|---|
| **Statistiques** | Inscriptions / désinscriptions sur 12 mois · abonné·es par statut · performance par campagne (envois, ouverture, clic, désabonnement, rebond) · liens les plus cliqués · centres d'intérêt des actifs |
| **Abonné·es** | Liste (abonné·e, inscription + source, statut Actif / En attente / Désabonné·e / Rebond, tags, intérêts) · recherche · filtres statut, tag, intérêt, dates · actions groupées (actif, désabonner, ajouter un tag, supprimer) · **Importer CSV** (consentement attesté, double opt-in recommandé, tag commun) · **Exporter CSV** (filtres appliqués) · modales ajout / modification / suppression RGPD |
| **Campagnes** | Liste (objet, expéditeur, statut Brouillon / Programmée / Envoi en cours / Envoyée, date, segment, destinataires, ouverture · clic) · actions statistiques, modifier ou voir, annuler la programmation, dupliquer, supprimer · « Traiter la file d'envoi » |

Modale campagne : objet *, nom de l'expéditeur *, e-mail de réponse *, texte d'aperçu, corps (Quill), variables de personnalisation, **segment** (tags, intérêts, inscrit·es depuis / jusqu'au, comptage en direct), **envoi de test**, aperçu, mode d'envoi (brouillon, immédiat avec confirmation, programmé à date et heure). Lien de désinscription unique ajouté automatiquement (RG-11). Envoi par lots de 200 / minute.

### D6. Templates e-mails (`admin/emails.php`)
KPI : templates, templates système, envois (30 j), échecs (30 j).
- **Liste** paginée (10 / page) : Nom (+ catégorie, badge Système, Désactivé), Slug, Objet, Dernière modification (date + auteur), Actions (aperçu, modifier, envoyer un test, dupliquer, supprimer). Recherche par nom ou slug, filtre catégorie.
- **Créer / modifier** (modale XL) : nom, slug unique (généré depuis le nom, verrouillé pour les templates système), objet, catégorie, texte d'aperçu, corps WYSIWYG (Quill) avec mode HTML, actif ; panneau latéral **variables cliquables** (`{{user_name}}`, `{{activation_link}}`, `{{reset_link}}`, `{{otp_code}}`…) insérées au curseur (corps, objet ou texte d'aperçu) ; **prévisualiser** (rendu réel, données fictives, bureau / mobile, avertissement de variables inconnues) ; **envoyer un test** à une adresse via le SMTP configuré avant d'enregistrer.
- **Supprimer** : modale de confirmation ; pour un template système, saisie du slug obligatoire et avertissement des envois qui échoueront (RG-10).
- **Serveur SMTP** : hôte, port, chiffrement, identifiant, mot de passe (inchangé si vide), expéditeur, réponse ; sans hôte : boîte d'envoi locale.
- **Derniers envois** : date, destinataire, template, résultat (SMTP, boîte locale, échec).
- **Service** : `EmailTemplateService::send($slug, $to, $vars)` / prototype `x_mail_send_template()`.

---

## E. Application mobile (Flutter)

30 écrans décrits dans `prototype_mobile/` (panneau « transposition Flutter » de chaque écran) :

| Module | Écrans |
|---|---|
| `auth` | Splash et carrousel (`onboardingProvider.seen`) · Connexion · Inscription 4 phases · OTP e-mail (`purpose`) · Mot de passe oublié · Nouveau mot de passe |
| `home` | Tableau de bord (membre / pair) · Thématiques · Fiche thématique · Ressources · Notifications |
| `sos` | Signaler et suivre · SOS · Alerte envoyée · Signalement 4 étapes · Contacts d'urgence |
| `feed` | Fil · Publication et commentaires · Créer une publication · Créer un sondage |
| `community` | Messages · Conversation · Groupes · Événements · Assistant |
| `profile` | Mon profil · Profil progressif · Paramètres · Sécurité et confidentialité · Newsletter |

---

## F. Modules retirés (ne pas implémenter)

| Module | Décision | Remplacement |
|---|---|---|
| Gestion de stock (admin, KPI, espace pair) | Octobre 2026 | — (IND-09 devient le taux d'ouverture de la newsletter) |
| Passerelles réseaux sociaux | D4 | Boutons de partage par lien |
| Sortie rapide | D8 | Newsletter dans le pied de page ; discrétion par le mode discret |
