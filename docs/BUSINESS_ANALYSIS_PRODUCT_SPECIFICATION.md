---
name: xomiton-business-analysis
description: Analyse métier et spécification produit de Xomiton (BRS, PRD, SRS, FRS, NFRS, règles métier numérotées). À charger pour toute question de périmètre, d'exigence fonctionnelle ou non fonctionnelle, de règle métier ou de critère d'acceptation.
---

# Analyse métier et spécification produit

Gabarit : *PRD skill* — problème, objectifs, utilisateur·rices, exigences numérotées et testables, critères d'acceptation, hors périmètre, mesures de succès.

---

## 1. BRS — Business Requirements Specification

### 1.1 Contexte
Au Bénin, les filles, femmes et personnes LBTQ+ subissent des violences basées sur le genre (VBG) et des violations des droits humains (VDH) souvent tues, faute d'espace sûr, d'information fiable et d'orientation vers les bon·nes interlocuteur·rices. Les organisations qui les accompagnent collectent leurs données sur papier ou dans des tableurs dispersés, ce qui rend le suivi et le reporting aux bailleurs difficiles.

### 1.2 Objectifs métier
| Code | Objectif | Indicateur |
|---|---|---|
| OB-1 | Offrir un **espace numérique sûr** d'échange et d'entraide | Membres actif·ves mensuel·les, taux de rétention à 30 jours |
| OB-2 | **Informer** sur les droits, la santé sexuelle et reproductive, les VBG | Fiches lues, temps de lecture, téléchargements hors ligne |
| OB-3 | **Alerter et orienter** rapidement (signalement, SOS) | Délai médian de prise en charge d'une alerte, % d'alertes traitées sous 24 h (IND-10) |
| OB-4 | **Outiller les pair·es éducateur·rices** (collecte, outils CCC, suivi) | Dossiers collectés par pair, % de suivis réalisés (IND-13) |
| OB-5 | **Piloter et rendre compte** (indicateurs, rapports automatisés) | Rapports générés, couverture géographique (IND-16) |
| OB-6 | **Garder le contact** avec la communauté et les partenaires (newsletter) | Taux d'ouverture (IND-09), taux de clic, taux de désabonnement |

### 1.3 Parties prenantes
Association porteuse (équipe de coordination), pair·es éducateur·rices, membres, intervenant·es de l'annuaire (points focaux, juristes, psychologues, gynécologues, travailleur·ses sociaux·les), bailleurs, partenaires associatifs.

### 1.4 Contraintes métier
- Budget d'hébergement limité : **cPanel mutualisé** (D7).
- Public exposé : anonymat possible partout où c'est pertinent, discrétion de l'application.
- Connexion mobile instable : fonctionnement **hors connexion** pour la collecte, le signalement et le SOS.

---

## 2. PRD — Product Requirements Document

### 2.1 Problème
« Je veux pouvoir parler, apprendre et demander de l'aide sans être exposée. » Les solutions existantes (groupes WhatsApp, pages Facebook) ne protègent ni l'identité ni les données, n'orientent pas vers des professionnel·les et ne produisent aucune donnée de suivi.

### 2.2 Personas
| Persona | Besoin principal | Contexte |
|---|---|---|
| **Awa, 22 ans, membre** | Trouver une communauté bienveillante et des informations fiables, sous pseudonyme | Smartphone Android d'entrée de gamme, forfait limité |
| **Chantal, 31 ans, pair éducatrice** | Animer des causeries, collecter des données de cas, suivre les personnes accompagnées | Terrain, zones sans réseau |
| **Me Adjovi, juriste de l'annuaire** | Recevoir les alertes qui la concernent, rappeler la personne | Usage ponctuel, e-mail et téléphone |
| **Hortense, coordinatrice (admin)** | Modérer, valider les comptes, piloter les indicateurs, produire les rapports bailleurs | Ordinateur portable, bureau |
| **Visiteur·se anonyme** | Lire une ressource, signaler sans compte, déclencher un SOS | Navigateur, parfois appareil partagé |

### 2.3 Périmètre fonctionnel (version 1)
1. Site public : accueil, thématiques, ressources, lois, Do Glegbe, aide, signalement anonyme, SOS, contacts d'urgence, newsletter.
2. Espace membre (Vue) : fil d'actualités (publications, photos, sondages), commentaires, messagerie, groupes, pages, événements, forums, qui suivre, profil progressif, notifications, paramètres et sécurité, assistant.
3. Espace pair éducateur·rice : collecte de données hors ligne, alertes routées, outils CCC (boîtes à images, BD, manuel), suivi.
4. Administration (Livewire) : tableau de bord, alertes, indicateurs, rapports, registres, fiches VBG/VDH, suivi des victimes, annuaire, règles de validation, utilisateurs et rôles, inscriptions, contenus, CCC, réseau social, modération, assistant, newsletter, synchronisation, mises à jour et sauvegardes, templates e-mails, paramètres, journal d'audit.
5. Application mobile Flutter : 30 écrans du simulateur `prototype_mobile/`.

### 2.4 Hors périmètre
Passerelles vers les réseaux sociaux (D4) · gestion de stock (retirée) · sortie rapide (D8) · paiement · visioconférence intégrée · géolocalisation continue.

### 2.5 Mesures de succès (6 mois après lancement — cibles proposées, à valider avec l'association)
- 1 500 comptes actifs, 35 % de rétention à 30 jours.
- 80 % des alertes prises en charge sous 24 h ; 100 % des SOS accusés sous 5 min.
- 12 pair·es actif·ves, 60 % des dossiers suivis au moins une fois.
- Taux d'ouverture de la newsletter ≥ 35 %, taux de rebond < 3 %.
- Score Lighthouse ≥ 90 (performance, accessibilité, SEO) sur les pages publiques.

---

## 3. SRS — Software Requirements Specification (vue d'ensemble)

- **Interfaces utilisateur** : web responsive (Bootstrap 5.3.3, thème Social), application Flutter (Android 8+, iOS 15+).
- **Interfaces logicielles** : API REST `/api/v1` (JSON, Sanctum), SMTP, FCM (push), service de supervision (signal de vie du cron).
- **Interfaces de communication** : HTTPS uniquement, polling (pas de WebSocket en mutualisé).
- **Environnements** : local, recette (staging), production ; même code, pilotes configurés par `.env` (ADR-11).
- Détails : [SOFTWARE_ARCHITECTURE.md](SOFTWARE_ARCHITECTURE.md), [DATABASE_ENGINEERING.md](DATABASE_ENGINEERING.md).

---

## 4. FRS — Functional Requirements Specification

Chaque exigence porte un code, une priorité (**M** obligatoire, **S** souhaitée, **C** confort) et un critère d'acceptation.

### 4.1 Authentification et comptes
| Code | Exigence | Prio | Critère d'acceptation |
|---|---|---|---|
| FR-AUTH-01 | Inscription en 4 phases dont seule la première est obligatoire | M | Un compte est créé avec pseudonyme, e-mail, mot de passe et consentements CGU + confidentialité |
| FR-AUTH-02 | Vérification par code à 6 chiffres envoyé **par e-mail** (OTP) pour l'inscription, la connexion et la réinitialisation | M | Un seul écran, paramètre `purpose` ; code valable 10 min, 5 essais, renvoi après 30 s |
| FR-AUTH-03 | Jauge de solidité du mot de passe (texte + couleur), 10 caractères minimum | M | Le bouton reste actif ; l'erreur s'affiche sous le champ si la solidité est inférieure à « Fort » |
| FR-AUTH-04 | Mot de passe oublié sans énumération de comptes | M | Même message et même délai que le compte existe ou non |
| FR-AUTH-05 | Validation manuelle des pair·es éducateur·rices | M | Un compte « pair » reste `en_attente` jusqu'à validation ; e-mail « compte validé » envoyé |
| FR-AUTH-06 | Ouverture et fermeture des inscriptions par l'administration | M | Inscriptions fermées : formulaire remplacé par un message |
| FR-AUTH-07 | Profil progressif facultatif, chaque champ expliqué | S | Barre de complétion, « Pourquoi on demande ça ? » sous chaque groupe |

### 4.2 Réseau social
| Code | Exigence | Prio | Critère |
|---|---|---|---|
| FR-SOC-01 | Publier texte, photo, sous pseudonyme ou non, avec visibilité (communauté, abonné·es, moi) | M | La publication apparaît en tête du fil avec le bon badge |
| FR-SOC-02 | Publier un **sondage** : question, 2 à 6 réponses, texte et image facultatifs, durée, choix multiple, résultats avant vote | M | Vote sans rechargement, barres en %, votes confidentiels, retrait du vote possible avant la fin |
| FR-SOC-03 | J'aime, commentaires, partage par lien (WhatsApp, Telegram, Facebook, copier) | M | Mise à jour optimiste, pas de compte tiers requis |
| FR-SOC-04 | Messagerie privée et de groupe | M | Polling 4 s dans une conversation ouverte ; bloquer et signaler en 2 gestes |
| FR-SOC-05 | Groupes, pages, événements (participation), forums | M | Groupes privés invisibles aux non-membres |
| FR-SOC-06 | Filtre automatique de mots et signalement de contenu | M | Contenu signalé visible dans la file de modération |

### 4.3 Alerte, signalement, SOS
| Code | Exigence | Prio | Critère |
|---|---|---|---|
| FR-ALT-01 | Signalement anonyme sans compte, en 4 étapes, avec code de suivi | M | Le code permet de consulter le statut sans compte |
| FR-ALT-02 | Expression d'un besoin (juridique, psychologique, médical) | M | Routage vers le rôle correspondant de la zone |
| FR-ALT-03 | SOS : maintien 2 s → compte à rebours 5 s annulable → envoi avec position (si accord) | M | Alerte enregistrée et notifications envoyées dans la requête (≤ 3 s par fournisseur) |
| FR-ALT-04 | Routage automatique vers l'annuaire (rôle + zone, puis point focal de zone, puis national) | M | Identique à `route_alert()` du prototype |
| FR-ALT-05 | Escalade sans accusé de réception sous 5 min | M | Vérifiée par cron à ±1 min |
| FR-ALT-06 | Hors connexion : mise en file locale puis envoi au retour du réseau | M | Aucune perte après redémarrage du téléphone |

### 4.4 Données et suivi (pair·es et administration)
| Code | Exigence | Prio | Critère |
|---|---|---|---|
| FR-DAT-01 | Collecte de cas VBG/VDH hors ligne avec règles de validation | M | Champs refusés selon les règles actives (âge, date, commune cohérente…) |
| FR-DAT-02 | Registres numériques, fiches de référence, suivi des victimes | M | Code dossier `VBG-0000`, chronologie de suivi |
| FR-DAT-03 | Indicateurs IND-01 à IND-16 calculés automatiquement | M | Valeur, cible, tendance, filtre période et zone |
| FR-DAT-04 | Rapports automatisés exportables (PDF, Excel, Word) | M | Génération par morceaux compatible 30 s / 128 Mo |
| FR-DAT-05 | Synchronisation et résolution de conflits | M | Conflit affiché avec valeur serveur / appareil et choix tracé |

### 4.5 Communication
| Code | Exigence | Prio | Critère |
|---|---|---|---|
| FR-COM-01 | **Templates e-mails** en base (CRUD, WYSIWYG, variables, aperçu, envoi de test, suppression sécurisée des templates système) | M | `x_mail_send_template(slug, to, vars)` / `TemplateMail` |
| FR-COM-02 | **Newsletter** : abonné·es (statuts, tags, intérêts, import/export CSV), campagnes (WYSIWYG, objet, expéditeur, réponse), test, envoi immédiat ou programmé, statistiques | M | Désinscription unique dans chaque e-mail, double opt-in |
| FR-COM-03 | Notifications en application, e-mail et push | M | Voir [NOTIFICATIONS_INSTRUCTION.md](NOTIFICATIONS_INSTRUCTION.md) |

### 4.6 Plateforme
| Code | Exigence | Prio | Critère |
|---|---|---|---|
| FR-PLT-01 | Mises à jour : versions publiées ou planifiées, maintenance programmée | M | Seule l'administration se connecte en maintenance |
| FR-PLT-02 | **Sauvegardes** : création, aperçu (collections, enregistrements, écart, extrait masqué), téléchargement, restauration confirmée, suppression | M | L'état courant est sauvegardé avant toute restauration |
| FR-PLT-03 | Journal d'audit consultable et filtrable | M | Toute action d'administration y figure |
| FR-PLT-04 | Recherche globale de l'administration (Ctrl K) | S | Utilisateur·rices, dossiers, alertes, annuaire, thématiques, réseau, journal |

---

## 5. NFRS — Non-Functional Requirements

| Code | Catégorie | Exigence mesurable |
|---|---|---|
| NFR-01 | Performance | LCP < 2,5 s et CLS < 0,1 en 4G lente sur les pages publiques ; réponse API p95 < 600 ms |
| NFR-02 | Poids | Page publique < 500 Ko transférés hors images différées ; JS initial < 150 Ko compressé |
| NFR-03 | Disponibilité | 99,5 % mensuel ; signal de vie du cron supervisé (alerte si absent 3 min) |
| NFR-04 | Sécurité | OWASP ASVS niveau 2 ; chiffrement des champs sensibles ; limitation de débit |
| NFR-05 | Confidentialité | RGPD et loi béninoise sur les données personnelles : consentements tracés, export et effacement en libre-service |
| NFR-06 | Accessibilité | WCAG 2.2 AA ; textes ≥ 14 px ; cibles ≥ 44 px |
| NFR-07 | Compatibilité | 2 dernières versions des navigateurs majeurs, Android 8+, iOS 15+ ; largeur minimale 320 px |
| NFR-08 | Hors connexion | Collecte, signalement, SOS et contacts d'urgence utilisables sans réseau |
| NFR-09 | Maintenabilité | Couverture de tests ≥ 70 % sur le domaine ; analyse statique niveau 6 (Larastan) |
| NFR-10 | Exploitabilité | Hébergement mutualisé : aucun processus permanent requis |
| NFR-11 | Internationalisation | Toutes les chaînes dans `lang/` ; formats de date et nombre `fr-BJ` |

---

## 6. Règles métier

| Code | Règle |
|---|---|
| RG-01 | Une personne peut toujours signaler et déclencher un SOS **sans compte**. |
| RG-02 | Le nom réel n'est jamais public ; le pseudonyme est affiché par défaut. |
| RG-03 | Un compte pair éducateur·rice doit être validé par l'administration avant d'accéder à la collecte. |
| RG-04 | Une alerte est routée vers un contact **actif** de l'annuaire : rôle demandé dans la zone, sinon point focal de la zone, sinon point focal national, sinon premier contact actif. |
| RG-05 | Un SOS non accusé sous 5 minutes est escaladé au point focal national. |
| RG-06 | Le code dossier suit le format `VBG-0000` / `VDH-0000`, unique et non réutilisable. |
| RG-07 | Une date de collecte ne peut pas être postérieure à aujourd'hui ; une date de plus de 5 ans déclenche un avertissement. |
| RG-08 | L'âge saisi est compris entre 10 et 99 ans ; la commune doit appartenir au département choisi. |
| RG-09 | Un sondage a 2 à 6 réponses distinctes ; une seule réponse par personne sauf choix multiple ; les votes individuels ne sont jamais exposés. |
| RG-10 | Un template e-mail **système** ne peut être supprimé qu'en saisissant son slug ; son slug n'est pas modifiable. |
| RG-11 | Une inscription à la newsletter est confirmée par double opt-in ; chaque e-mail de campagne contient un lien de désinscription unique ; une adresse en rebond n'est plus ciblée. |
| RG-12 | Une campagne ne cible que des abonné·es **actifs** correspondant au segment (tags, intérêts, dates). |
| RG-13 | Avant toute restauration de sauvegarde, l'état courant est sauvegardé automatiquement. |
| RG-14 | En mode maintenance, seuls les rôles admin et superadmin peuvent se connecter. |
| RG-15 | Toute modification d'un rôle d'administration est réservée au superadmin et journalisée. |
| RG-16 | *(proposée, à valider)* Les contenus signalés trois fois sont masqués en attendant la modération. |
