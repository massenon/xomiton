---
name: xomiton-plan-mobile-app-unique
description: Plan d'implémentation de l'application mobile Flutter UNIQUE de Xomiton (un seul APK / AAB, un seul login) qui regroupe l'espace membre (finalisation), l'espace pair éducateur·rice (dans la v1) et l'espace admin élargi (astreinte, inscriptions, rôles et invitations, modération essentielle), affichés selon les rôles cumulés du compte. Décisions du client du 29/10/2026 intégrées. Démarche system design (exigences, contraintes, architecture, sécurité, arbitrages), état réel de l'existant, lots P15-10 à P15-24 avec dépendances, estimation, critères d'acceptation, tests, recette et risques. À lire avant tout code dans `mobile/` concernant le pair, l'admin, la synchro ou la sécurité de l'application, et avant toute route `admin/*` de l'API destinée au mobile.
---

# Plan : application mobile unique (membre + pair + admin d'astreinte)

Statut : **le client a tranché le 29/10/2026** (décisions D-MOB-1 à D-MOB-14, §10) ; plan mis à jour le même jour. **Maquettes, arbitrages D-MOB et textes d'onboarding validés par le client le 29/10/2026 : le code mobile a démarré le même jour.** Avancement : onboarding à six diapositives (textes validés) **fait le 2026-10-29** (6 tests) ; **P15-10 fait le 2026-10-29** (54 tests, 217 au total) ; **P15-11, volet mobile, fait le 2026-10-29** (31 tests, 248 au total) ; **P15-12 (34 tests), P15-13 (19), P15-14 (16) et P15-15 (37) faits le 2026-10-29, 354 tests au total, jalon A « Pair bêta » côté mobile hors P15-19 (push) et reste de P15-20** ; la suite est aux lots admin (P15-17, P15-18, P15-24), au push (P15-19) et à la recette sur appareil (voir les lignes « Statut » de §9.2). Il précise et remplace le périmètre de **P15-09** et la décision **Q38** de `IMPLEMENTATION_PLAN.md`. Les maquettes de référence, mises à jour avec les décisions, sont `prototype_mobile/index.html` (31 écrans : connexion unique, choix de l'espace), `prototype_mobile/espace-pair.html` (P1 à P12) et `prototype_mobile/admin-mobile.html` (A1 à A12).

**Ordre de réalisation (décision 9 du client) : 1. le client valide les maquettes (recette MOB-10, voir §12) ; 2. seulement ensuite, code mobile (P15-10 et suivants). Aucun code mobile avant cette validation.** (Validation obtenue le 29/10/2026 : le lot P15-10 a démarré.) Le code serveur indépendant des écrans (rôles cumulés, invitations, API admin) peut avancer en parallèle, il ne dépend pas des maquettes.

Sources lues pour ce plan : `mobile/lib` (code réel), `web/routes/api.php` et `web/routes/admin.php`, `docs/API.md` (§1, §3, §21, §22), `docs/ROLE_ET_PERMISSIONS.md`, `docs/IMPLEMENTATION_PLAN.md` (P15-01 à P15-09, Q38), `docs/SPEC_PROTOTYPE_MOBILE_V2.md`, `docs/CHECKLIST_RECETTE.md` (§10, MOB-01 à MOB-13), `docs/DEPLOIEMENT_CPANEL.md`, `prototype_mobile/README.md`.

## 1. Résumé en dix lignes

1. **Une seule application et un seul login**, trois espaces : *Membre* (tous les comptes), *Pair* (rôle `pair` validé), *Admin* (permission dédiée `admin_mobile.access`, admin / superadmin). Les rôles **se cumulent** : tout compte est membre ; un pair est membre ET pair ; un admin est par défaut membre ET admin. L'inscription publique ne crée que des membres.
2. L'espace affiché dépend de ce que **le serveur** déclare dans `GET me` (rôles, permissions) ; le client ne fait qu'**afficher** : toute route est re-contrôlée par le serveur, une action refusée (403) ne produit jamais d'effet.
3. Un compte cumulant plusieurs rôles voit, **après la connexion**, un **sélecteur d'espace**, puis un commutateur dans l'en-tête et dans le profil ; la barre du bas du mode membre porte un onglet « Espace pair » ou « Admin » bien visible, et les barres du pair et de l'admin portent un onglet « Réseau » (fil, messages). L'espace actif est mémorisé, jamais imposé.
4. Le **SOS, le signalement et les urgences restent accessibles sans compte** et dans tous les espaces (RG-01).
5. L'API pair existe déjà (`pair/*`, `sync/*`, `heartbeat`, `me/devices`, `me/push-subscriptions`) ; l'API **admin mobile n'existe pas** (l'administration est 100 % Livewire) : **groupe `admin/*` à créer** (lot P15-16, le plus gros risque de calendrier côté serveur).
6. L'espace pair est conçu pour le **hors ligne** (NFR-08) : collecte, suivi et file d'envoi chiffrés, synchro idempotente par lots de 50 (`sync/push`, `sync/pull`).
7. L'espace admin est un **compagnon d'astreinte élargi** : alertes, prise en charge, **inscriptions à valider, rôles et invitations (recherche de membre), modération essentielle**, maintenance, notifications. **Aucune donnée sensible n'y est conservée hors ligne.** Il ne remplace pas l'administration web (configuration avancée, rapports, exports, attribution du rôle admin).
8. Sécurité renforcée pour les espaces *Pair* et *Admin* : PIN obligatoire, verrouillage court, `FLAG_SECURE` par écran, effacement à la déconnexion et **à distance**, expiration des données locales hors contact.
9. Estimation (mise à jour le 29/10/2026) : **15 lots, ≈ 115 jours-personne (85 mobile + 30 serveur), ≈ 138 avec 20 % de marge** (avant : 98 et 118). Le mobile est le chemin critique : ≈ 10 semaines à trois personnes (deux Flutter, un·e Laravel), ≈ 17 à 20 semaines avec un·e seul·e Flutter (§9.1). Hors validations humaines (maquettes, Firebase, Play Console, droits d'image).
10. **Décisions du client du 29/10/2026** (tranchées) et points restant à valider : §10.

## 2. Exigences

### 2.1 Fonctionnelles

| Id | Exigence | Espace | Source |
|---|---|---|---|
| FR-M1 | Finaliser l'espace membre : tests d'intégration, recette sur appareil, `sync/pull` urgences / thématiques / annuaire, iOS (push, icône alternative) | Membre | P15-01 à P15-08 |
| FR-P1 | Voir les alertes routées vers moi, accuser réception, prendre en charge, clore (`pair/alerts/*`) | Pair | API §22, ROLE §3 |
| FR-P2 | Consulter **mes** dossiers (jamais ceux d'un autre pair), fiche en 5 sections, frise de suivi, ajouter / modifier un suivi | Pair | API §22 |
| FR-P3 | Collecter un dossier VBG / VDH **hors ligne**, avec les règles de validation actives et l'avertissement de plausibilité ; code attribué par le serveur au rejeu | Pair | NFR-08, RG-06, RG-07, RG-08 |
| FR-P4 | File de synchronisation visible (en attente, envoyé, rejeté, conflit 409) ; **le pair tranche lui-même le conflit 409** (valeur du serveur ou valeur de son appareil), sans validation par l'équipe | Pair | API §21.4, décision 4 |
| FR-P5 | Rester joignable en cas de SOS : bouton SOS toujours présent, notification **push SOS** (admins ET pairs) même app fermée | Pair | FR-ALT-03, décision 6 |
| FR-A1 | Tableau de bord d'astreinte : SOS actifs, nouvelles alertes, critiques ouvertes, inscriptions à valider, contenus signalés, compte à rebours d'escalade (RG-05) | Admin | admin-mobile A1 |
| FR-A2 | Lister / filtrer les alertes (SOS, signalements, besoins), détail, accuser réception, prendre en charge, changer le statut, réattribuer, note interne | Admin | A2, A3 |
| FR-A3 | **Inscriptions à valider** : pairs invités ayant terminé leur inscription, demandes du rôle pair faites par des membres, inscriptions membres à vérifier ; valider ou refuser (RG-03), depuis le tableau de bord web **ou** l'appli mobile | Admin | A4 |
| FR-A4 | Activer / désactiver la maintenance (confirmation forte, garde-fou anti-verrouillage RG-14) | Admin | A5 |
| FR-A5 | Notifications d'astreinte (push + liste), dont le **push SOS** reçu par les admins ET les pairs | Admin | A6, A12 |
| FR-A6 | **Rôles et invitations** : inviter un·e pair par lien ou code (même mécanisme d'inscription, usage unique, durée limitée, révocable) ; attribuer ou retirer le rôle pair à un membre ; **rechercher un membre** | Admin | A8 |
| FR-A7 | **Modération essentielle** : liste des contenus signalés (publications, commentaires), décision masquer / conserver / avertir, journal d'audit ; la suspension de compte reste sur le web | Admin | A9, A10 |
| FR-X1 | Connexion unique ; **sélecteur d'espace après la connexion** pour un compte cumulant plusieurs rôles, commutateur d'en-tête et Profil › Mes espaces, onglet « Espace pair » / « Admin » ; espace par défaut mémorisé | Tous | décisions 1 et 2 |
| FR-X2b | Inscription publique = membre seulement ; inscription par invitation de pair avec les mêmes écrans ; demande du rôle pair depuis le profil | Membre | décision 1 |
| FR-X2 | Mise à jour des règles de validation et des référentiels sans republier l'application | Pair | `validation-rules` (ETag) |

### 2.2 Non fonctionnelles

| Id | Exigence | Cible mesurable |
|---|---|---|
| NFR-R1 | **Réseau faible** (2G / 3G, coupures) : toute écriture passe par une file locale ; aucune perte après arrêt brutal | test : processus tué entre saisie et envoi, entrée retrouvée |
| NFR-R2 | Charges utiles légères : pages de 200 lignes, ETag / 304, compression gzip, aucune image dans les listes pair / admin | liste d'alertes < 15 Ko par page (à mesurer) |
| NFR-R3 | Idempotence de bout en bout : un rejeu ne crée jamais de doublon (ADR-32) | `result = duplicate` traité comme un succès |
| NFR-S1 | Aucune donnée sensible en clair sur l'appareil ni dans une notification | audit du stockage + test de charge utile `{type, id}` |
| NFR-S2 | Le serveur autorise toujours ; le client n'est jamais une frontière de sécurité | test « refusé (403) » par rôle et par route (ROLE §6) |
| NFR-D1 | **Discrétion** : icône / nom neutres, aperçus masqués, pas de capture d'écran sur les écrans sensibles | MOB-08, MOB-15, MOB-16 |
| NFR-P1 | Démarrage à froid < 3 s sur Android 8 d'entrée de gamme ; **poids de l'AAB suivi en CI** (alerte au-delà de +15 % par rapport à la base) | budget de taille en CI (P15-10) |
| NFR-A1 | Accessibilité AA (cibles 44 px, contraste, TalkBack, `reduceMotion`) | `ACCESSIBILITE.md` |
| NFR-O1 | Hébergement cPanel : pas de WebSocket, pas de worker permanent ; polling borné et push FCM | §3 |
| NFR-M1 | Aucun référentiel codé en dur (types de violence, zones, statuts, numéros d'urgence, tranches d'âge) | revue + tests |

## 3. Contraintes

| Contrainte | Conséquence de conception |
|---|---|
| Réseau faible et coûteux au Bénin | Hors ligne d'abord pour le pair ; pour l'admin, **actions en ligne seulement** (une prise en charge « en file » serait trompeuse pendant un SOS) avec message clair et numéro à appeler |
| Pas de WebSocket (cPanel, D7) | Polling **seulement au premier plan** (10 s pour une personne « de garde », 30 s sinon, arrêt 5 min après passage en arrière-plan, retrait progressif en cas d'échecs) + push FCM « données seules » pour réveiller |
| Pas de worker permanent (cron chaque minute) | Les notifications SOS partent **dans la requête** (D7) ; les autres, via `notifications:retry` (jusqu'à ~1 min de retard) : l'admin ne doit jamais compter sur le push seul, d'où le polling au premier plan |
| Entry Processes / CPU limités (`DEPLOIEMENT_CPANEL` §2) | Le polling de N pairs et admins de garde pèse sur le forfait : réponses légères, `Cache-Control`, ETag ; seuil à surveiller en recette (passage VPS = D7) |
| Public sensible (VBG, LBTQ+) | Discrétion, chiffrement local, effacement, minimisation (§7) |
| Appareils d'entrée de gamme, Android 8 (`minSdk 26`), iOS 15 | Pas de dépendance lourde ; pas de service d'arrière-plan permanent |
| Une équipe réduite | Une base de code, un pipeline, un store (arbitrage §8) |
| Jamais de confiance côté client | Chaque garde du routeur est doublée d'un contrôle serveur (§6) |

## 4. État de l'existant (constaté dans le dépôt le 29/10/2026)

### 4.1 Application Flutter (`mobile/lib`)

| Élément | État réel | Remarque |
|---|---|---|
| Socle (P15-01) | fait : Riverpod, go_router, Dio, Hive chiffré, `flutter_secure_storage`, `connectivity_plus`, thème, ARB, CI `mobile.yml`, 151 tests | pas d'`integration_test` |
| Authentification (P15-02) | fait (splash, connexion, inscription 4 phases, OTP, mot de passe oublié) | `AppUser` n'expose que `role` (chaîne) ; `isPeer = role == 'pair'` ; **`roles` et `permissions` du `GET me` ne sont pas modélisés** (ils sont dans `raw`) |
| Accueil, thématiques, ressources, notifications (P15-03) | fait, cache Hive + ETag | `sync/pull` **jamais appelé** côté mobile ; le tableau de bord pair existe côté API mais pas d'écran dédié |
| SOS, signalement (P15-04) | fait : maintien 2 s, compte à rebours, position, `cancel`, `safe`, boîte d'envoi, retry | test de rejeu SOS hors ligne avec redémarrage absent |
| Fil, communauté, profil (P15-05 à P15-07) | fait | PIN local (PBKDF2, 5 essais), mode discret Android, appareils connectés |
| Push FCM (P15-08) | fait côté code : `FcmPushService` / `NoopPushService`, canaux, charge `{type, id}`, `me/push-subscriptions` | `google-services.json` absent (compte Firebase) ; **iOS non fait** (APNs, icône alternative) ; types de push `alert`, `sos` ouvrent des écrans membre |
| Routeur | une seule `StatefulShellRoute` à 5 branches (Accueil, Fil, SOS, Messages, Profil) ; garde = session + onboarding + préfixes publics (`kPublicPrefixes`) ; route `/soon/:page` pour les écrans absents | **aucune notion d'espace ni de rôle dans le routeur** |
| Boîte d'envoi (`core/outbox`) | `OutboxType { sos, report, message, post }` rejouées sur leurs **routes dédiées** avec `Idempotency-Key` = uuid de l'entrée ; SOS prioritaire | **aucun type `collecte` / `followup`** ; `POST sync/push` n'est pas utilisé (seulement listé dans `isIdempotentPath`) |
| Appareil | `POST me/devices` à l'enregistrement du push, `X-Device-Id` ajouté par l'intercepteur | `heartbeat` jamais appelé |
| Sécurité d'écran | `MainActivity.kt` pose `FLAG_SECURE` **seulement dans `onPause`** (masque l'aperçu du sélecteur d'applications) et le retire dans `onResume` | **les captures d'écran restent possibles sur tous les écrans** ; rien par écran sensible |
| Effacement | `LocalDb.clearPrivate()` vide cache privé, session, brouillons ; effacé à la déconnexion | pas d'effacement à distance, pas d'expiration hors contact, pas de réaction à `device_revoked` |
| Features `pair` et `admin` | **inexistantes** (aucun dossier dans `lib/features`) | |

### 4.2 API (`web/routes/api.php`, `docs/API.md`)

| Famille | État | Détail |
|---|---|---|
| `auth/*`, `me/*`, `me/devices`, `me/push-subscriptions`, `me/sessions`, `app/version`, `status` | **disponible** | `auth/login` renvoie 202 `{otp_sent}` ou le jeton ; OTP imposé aux admin / superadmin (Q13 b) |
| `pair/cases`, `pair/cases/{code}`, `pair/cases/{code}/followups`, `pair/alerts`, `pair/alerts/{ulid}`, `…/acknowledge`, `…/status`, `pair/validation-rules` | **disponible** (P9-02), middleware `EnsurePeerSpaceAccess`, scope `visibleTo`, `no-store` | le plan P15-09 parlait de `ack` : la route réelle est **`acknowledge`** |
| `sync/push`, `sync/pull`, `sync/conflicts`, `heartbeat` | **disponible** (P9-06) | appareil enregistré obligatoire (`X-Device-Id`, sinon 403 `device_required` / `device_revoked`) ; collections `emergency_contacts`, `directory_contacts`, `thematiques`, `validation_rules`, `cases`, `case_followups`, `alerts` |
| `dashboard` (variante pair) | disponible (P6-08) | |
| `notifications/*` | disponible | contenu des notifications administration (types `SosBurstDetected`, `SyncConflict`…) **à vérifier** pour un compte admin |
| `admin/*` | **absent** | l'administration est servie par `routes/admin.php` (Livewire, session web), pas par jeton Sanctum |
| Résolution de conflit par le pair | **absente** | le conflit est aujourd'hui « tranché dans Administration › Hors connexion & synchro » (API §21.4) ; **le client a décidé que le pair tranche lui-même** (décision 4) : `POST sync/conflicts/{id}/resolve` devient obligatoire (lot P15-15), règle métier à écrire (historique des deux versions, journal d'audit) |
| Rôles cumulés, invitations de pair, demande du rôle pair, API de modération mobile | **à confirmer avec le travail serveur en cours** (rôles cumulés et invitations ajoutés côté web par d'autres agents ; voir `API.md` et `ROLE_ET_PERMISSIONS.md` quand leurs auteurs les mettent à jour) | exposer dans `GET me` la liste `roles[]` cumulée ; groupe `admin/*` : invitations, membres, modération (§11.2) |
| Effacement à distance | **absent** | existent : révocation de session (`DELETE me/sessions/{id}`), appareil révoqué (`DELETE me/devices/{uuid}` → 403 `device_revoked`), mais aucun drapeau d'effacement |
| Jetons Sanctum | `createToken` sans capacités (`*`) ni expiration propre à l'espace | à restreindre pour l'admin |

### 4.3 Maquettes

| Maquette | Écrans | Écarts constatés avec l'API réelle |
|---|---|---|
| `index.html` (simulateur) | 01 à 30 inchangés ; **02 Connexion unique** (comptes de démo membre / pair+membre / admin+membre) ; 03 Inscription membre seulement (lien d'invitation `#/signup/CODE`) ; **31 Choix de l'espace** ; profil avec « Mes espaces » ; barre du bas à 6 onglets (« Espace pair » / « Admin ») | l'API ne propose pas encore la demande du rôle pair (`POST me/role-requests`, à créer) |
| `espace-pair.html` | P1 Accueil · P2 Alertes routées · P3 Détail alerte SOS · P4 Mes dossiers · P5 Fiche dossier · P6 Ajouter un suivi · P7 SOS · P8-P9 Collecte (étapes, vérification) · P10 File de synchronisation · **P11 Conflit 409 : le pair choisit (serveur ou mon appareil)** · **P12 Push SOS reçu** ; barre du bas : Accueil · Alertes · SOS · Dossiers · **Réseau** | endpoint de résolution de conflit à créer (voir ci-dessus) ; bouton « Prendre en charge » = `POST pair/alerts/{ulid}/status` avec `en_cours` |
| `admin-mobile.html` | A1 Tableau d'astreinte · A2 Liste des alertes · A3 Détail SOS (position arrondie, Maps avec avertissement, personnes prévenues, historique, réattribuer, note) · **A4 Inscriptions à valider** · A5 Maintenance · A6 Notifications · A7 Hors connexion · **A8 Rôles et invitations (recherche de membre)** · **A9 Modération** · **A10 Contenu signalé** · **A11 Menu « Gérer »** · **A12 Push SOS reçu** ; barre du bas : Astreinte · Alertes · **Gérer** · Notifs · **Réseau** | aucune route mobile correspondante (§5.2, §11.2) ; position arrondie à ~1 km (décision 5) |

Constat global : **l'espace membre est fonctionnellement complet mais non recetté sur appareil ; les espaces pair et admin n'existent qu'en maquettes ; l'API pair est prête ; l'API admin mobile est à créer.**

## 5. Architecture

### 5.1 Schéma d'ensemble

```
                      APPLICATION FLUTTER UNIQUE (1 APK / AAB, applicationId bj.xomiton.xomiton)
 ┌───────────────────────────────────────────────────────────────────────────────────────────────┐
 │ app/        router.dart (go_router)  ── redirect : session → onboarding → PIN → garde d'espace │
 │             spaceGuard : /pair/** exige space pair ; /admin/** exige space admin               │
 │             theme · shells : MemberShell (5 onglets) · PeerShell · AdminShell                  │
 ├───────────────────────────────────────────────────────────────────────────────────────────────┤
 │ core/       network (Dio, intercepteurs : Bearer, X-Device-Id, Idempotency-Key, 401/403)       │
 │             auth_spaces (SpaceController, AccessPolicy)  · security (PIN, SecureScreen, wipe)  │
 │             storage (Hive chiffré par espace, secret_store)  · sync (moteur push/pull/conflits)│
 │             outbox (file idempotente)  · push (FCM, canaux)  · l10n  · ui                      │
 ├───────────────┬──────────────────────────────┬────────────────────────────────────────────────┤
 │ ESPACE MEMBRE │ ESPACE PAIR (chargé si rôle) │ ESPACE ADMIN (si permission)                   │
 │ features/     │ features/pair/               │ features/admin/                                │
 │  auth home    │  home alerts cases collect   │  duty alerts registrations roles moderation    │
 │  alerts feed  │  sync (file, conflit tranché │  maintenance notifications                     │
 │               │  par le pair)                │                                                │
 │  community    │  data/ application/ presen-  │  data/ application/ presentation/              │
 │  profile      │  tation/ (même découpage)    │  (aucun cache de données personnelles)         │
 │  security     │                              │                                                │
 └───────┬───────┴──────────────┬───────────────┴──────────────────────┬─────────────────────────┘
         │ HTTPS /api/v1 (JSON, Bearer Sanctum, X-Device-Id)                                       │
         ▼                                                                                          
 ┌───────────────────────────────────────────────────────────────────────────────────────────────┐
 │ SERVEUR LARAVEL (cPanel, cron chaque minute, pas de WebSocket)                                 │
 │  groupe 1 public : auth, zones, violence-types, urgences, signalements, sos, app/version       │
 │  groupe 2 authentifié : me/*, feed, conversations…, pair/*, sync/*, heartbeat   [existant]    │
 │  groupe 3 personnel d'astreinte : admin/*  (auth:sanctum + ability + permission) [À CRÉER]     │
 │  Policies / permissions spatie  ·  journal d'audit  ·  limiteurs  ·  FCM (dans la requête SOS)│
 └───────────────────────────────────────────────────────────────────────────────────────────────┘
        FCM (données seules {type,id}) ◄── réveil ── appareil ── polling 10/30 s au premier plan
```

### 5.2 Modules par fonctionnalité

Même découpage que l'existant (`data/` API et modèles, `application/` providers et contrôleurs Riverpod, `presentation/` pages), un dossier par module ; **aucun module n'importe un autre module d'espace** (`pair` ne connaît pas `admin`), seulement `core/`.

| Module | Contenu | Données locales |
|---|---|---|
| `core/auth_spaces` | `AppUser` enrichi (`roles` **cumulés**, `permissions`), `SpaceController` (liste des espaces calculée, espace actif mémorisé en `SharedPreferences`), écran et feuille « Choisir mon espace », `AccessPolicy.can('alerts.take')` pour **masquer** des boutons | jamais de permission mise en cache au-delà de la session : relue à chaque ouverture et sur 403 |
| `core/security` | `PinService`, `SecureScreen` (widget `FLAG_SECURE`), `WipeService`, `SessionGuard` (verrouillage, 5 essais, expiration hors contact) | clés dans `flutter_secure_storage` |
| `core/sync` | `SyncEngine` : `push` (lots ≤ 50, `Idempotency-Key` = uuid du lot, tri SOS d'abord), `pull` (curseur `since`, `reset`, `upserts`, `deletes`, `has_more`), `conflicts`, `heartbeat` | boîtes Hive `outbox`, `sync_cursor`, collections |
| `features/pair/*` | `home`, `alerts` (liste, détail, accusé, statut), `cases` (liste, fiche, suivi), `collect` (étapes, règles, plausibilité), `sync` (file, conflit) | `pair_cases`, `pair_followups`, `pair_alerts` (résumés), `pair_rules` : Hive chiffré, **boîtes séparées de l'espace membre** |
| `features/admin/*` | `duty` (tableau), `alerts`, `registrations` (inscriptions à valider), `roles` (rôles, invitations, recherche de membre), `moderation` (contenus signalés), `maintenance`, `notifications` | **aucune persistance** de contenu d'alerte, de signalement ni de fiche membre ; dernier tableau agrégé seulement |
| Serveur `admin/*` | contrôleurs `Api\V1\Admin\*` appelant les cas d'usage existants (`AcknowledgeAlert`, `ChangeAlertStatus`, `ReassignAlert`, `AddInternalNote`, `ValidatePeerAccount`/`ReviewRegistration`, gestion des rôles et invitations (cas d'usage serveur en cours), modération des signalements, `EnableMaintenance`/`DisableMaintenance`, `AlertDesk`, `DashboardStats`) | |

### 5.3 Routage et garde de rôle (go_router)

- Préfixes : membre `/home`, `/feed`, `/sos`, `/messages`, `/profile` (existants, inchangés) ; `/spaces` (choix de l'espace après connexion) ; pair `/pair/accueil`, `/pair/alertes`, `/pair/dossiers`, `/pair/collecte`, `/pair/sync`, `/pair/sync/conflit/:id` ; admin `/admin/astreinte`, `/admin/alertes`, `/admin/inscriptions`, `/admin/roles`, `/admin/moderation`, `/admin/gerer`, `/admin/maintenance`, `/admin/notifications` ; inscription par invitation `/signup/:invitation`. Les écrans publics (`kPublicPrefixes`) restent hors espace.
- Ordre du `redirect` : (1) onboarding, (2) session, (2 bis) **première arrivée après connexion : `/spaces` si le compte a plus d'un espace**, (3) **verrou PIN** (sauf routes publiques SOS / urgences / signalement, comme aujourd'hui), (4) **garde d'espace** : `/pair/**` n'est accessible que si `spaces` contient `pair`, `/admin/**` que si `spaces` contient `admin`, sinon redirection vers l'espace par défaut, (5) espace actif ≠ préfixe demandé : bascule d'espace explicite (un lien profond de push change d'espace après contrôle).
- Trois `StatefulShellRoute` (une par espace) ; la barre d'onglets dépend de l'espace : **Membre** = les 5 onglets existants (Accueil · Fil · SOS · Messages · Profil) **plus** un onglet visible « Espace pair » ou « Admin » (« Espaces » si les deux) pour les comptes concernés ; **Pair** = Accueil · Alertes · SOS · Dossiers · **Réseau** (la collecte s'ouvre depuis Dossiers et l'accueil ; **SOS toujours présent**, P7) ; **Admin** = Astreinte · Alertes · Gérer (inscriptions, rôles et invitations, modération, maintenance) · Notifs · **Réseau** (maquettes A1 à A12). L'onglet « Réseau » ramène au fil et aux messages de l'espace membre : le pair et l'admin gardent le réseau social dans leur barre.
- Le **sélecteur d'espace** : écran affiché après la connexion, puis pastille d'en-tête (« Membre ▾ », « Pair ▾ », « Admin ▾ ») ouvrant une feuille modale, et carte « Mes espaces » dans Profil ; visibles seulement si `spaces.length > 1`.
- Test obligatoire : `appRedirect` reçoit désormais `spaces` et couvre membre simple → `/pair/**` refusé, pair → `/admin/**` refusé, admin → les deux selon les droits, retrait de droit en cours de session.

### 5.4 État (Riverpod) et stockage

| Brique | Choix |
|---|---|
| État | Riverpod existant ; `sessionProvider` → `spacesProvider` (dérivé) → `activeSpaceProvider` ; chaque feature expose des `AsyncNotifier` ; `ref.invalidate` sur changement d'espace ou déconnexion |
| Hive chiffré | une **boîte par espace** (`member_*`, `pair_*`), clé AES propre à l'espace, conservée dans `flutter_secure_storage` (Keystore Android / Keychain iOS) ; la déconnexion supprime les boîtes **et** la clé ; l'espace admin n'a pas de boîte de contenu |
| `flutter_secure_storage` | jeton Sanctum, clés Hive, hachage du PIN (jamais envoyé au serveur, Q37), date du dernier contact serveur |
| `SharedPreferences` | `onboarding.seen`, espace actif, préférences non sensibles |
| Cache API | ETag + Hive pour référentiels, thématiques, règles de validation (déjà en place) |

### 5.5 File d'envoi hors ligne et synchronisation idempotente

Principe retenu : **un seul moteur** (`SyncEngine`) pour les écritures du pair ; les écritures du membre (SOS, signalement, message, publication) gardent leurs **routes dédiées** (le SOS ne doit dépendre d'aucun lot) et rejoignent le moteur plus tard seulement si utile.

```
 saisie ──► OutboxEntry {uuid, type, op, payload, created_at, attempts, status}  (Hive chiffré, écrit AVANT l'envoi)
              │  réseau revenu / ouverture / toutes les 5 min au premier plan / push "sync"
              ▼
   SyncEngine.push : tri SOS ► signalement ► collecte ► suivi ► message ; lots ≤ 50
        POST sync/push  Idempotency-Key = uuid du lot (rejoué tel quel tant qu'il est "processing", 202)
              │
   résultat par élément : created|updated|duplicate ► sortie de la file (succès)
                          conflict (409)            ► sortie + ligne "Conflit" visible ; le pair ouvre l'écran de choix (valeur du serveur / mon appareil) et envoie sa décision (sync/conflicts/{id}/resolve)
                          rejected 4xx (validation) ► sortie + état "À corriger" (le pair édite et renvoie, NOUVEAU uuid)
                          429 / 5xx (retry_later)   ► reste en file, repart dans un nouveau lot, attente exponentielle + jitter
              ▼
   SyncEngine.pull : GET sync/pull?since=<cursor>&collections[]=… tant que has_more
        upserts/deletes/reset appliqués dans une transaction Hive ; curseur écrit APRÈS l'application
```

Règles clés (déjà garanties par le serveur, API §21) : élément dédoublonné par uuid (`sync_outbox_receipts`), même uuid avec autre contenu → 422 `uuid_reused`, un élément en erreur n'arrête jamais le lot, SOS jamais limité, 2xx / 409 / 4xx sortent de la file, 429 / 5xx restent. **Code du dossier** attribué par le serveur (`VBG-0001`) : l'écran affiche « En attente d'envoi » sans code puis le code reçu. **Modification de dossier** : le mobile envoie `base` (valeurs vues avant modification) pour permettre la fusion par champ.

### 5.6 Push FCM et notifications SOS

| Sujet | Décision |
|---|---|
| Charge utile | données seules `{type, id}` (contrat P15-08) ; le texte est choisi par l'application (neutre si aperçus masqués) ; **aucun récit, nom ni position** dans la charge |
| Destinataires du push SOS et alertes (décision 6) | **les admins ET les pairs éducateurs** ; **Tranché 2026-10-29 (recommandation retenue, D-MOB-10)** : admins de garde + pairs de la zone, puis escalade nationale à 5 min sans accusé (tous les admins et tous les pairs) |
| Types ajoutés | `alert_routed` (pair : ouvre `/pair/alertes/:id`), `duty_sos` (admin : ouvre `/admin/alertes/:id`), `peer_pending` (admin : inscription ou demande à valider), `content_reported` (admin : contenu signalé), `rules` (pair : rafraîchir `validation-rules`), `sync` (pair : lancer un cycle) ; types inconnus → `/notifications` (existant) |
| Canaux Android | « SOS et alertes » (existant, haute importance) + **« Astreinte »** pour l'admin / pair de garde (haute importance, son d'alarme) ; ne pas demander `USE_FULL_SCREEN_INTENT` (restreint depuis Android 14 : rejet Play probable) |
| Fiabilité | le push n'est **jamais l'unique** chemin : polling au premier plan, liste d'alertes toujours relue à l'ouverture ; escalade serveur RG-05 (5 min sans accusé) indépendante de l'application |
| Réception app fermée | handler d'arrière-plan minimal (pas d'appel réseau obligatoire) qui affiche la notification ; ouverture profonde après PIN |
| iOS | APNs requis ; « Critical Alerts » demande une autorisation d'Apple (délai incertain) : v1 iOS = notification normale haute priorité, limite documentée |

### 5.7 Mise à jour des règles de validation

| Mécanisme | Détail |
|---|---|
| Source | `GET pair/validation-rules` (ETag, 304) et collection `validation_rules` de `sync/pull` ; `meta.options` fournit tranches d'âge, orientations, étapes de suivi, longueur minimale des notes |
| Quand | à l'ouverture de l'espace pair, au retour au premier plan après 1 h, après push `rules`, avant d'ouvrir la collecte si en ligne |
| Appliquer | `RulesRepository` expose une instance immuable ; la collecte valide **localement avec les règles en cache** (mêmes clés que `CaseRules`) |
| Règles périmées | le serveur **revalide toujours** au rejeu (`CaseValidator`) : une collecte faite avec d'anciennes règles peut être rejetée en 422 → état « À corriger » avec les erreurs par champ ; jamais de suppression silencieuse |
| Verrou | si les règles n'ont jamais été téléchargées, la collecte est refusée hors ligne (« télécharge les règles une première fois en ligne ») ; sinon on collecte avec les dernières connues et on affiche leur date |
| Référentiels | zones, communes, types de violence, populations lus par l'API et mis en cache avec ETag (NFR-M1) |

## 6. Modèle de rôles et permissions côté application

### 6.1 Principe : le serveur autorise, le client affiche

1. `GET me` fournit `role`, `roles[]` (**cumulés** : `membre` toujours présent, `pair` et/ou `admin` en plus), `permissions[]`, `status`, `pending_validation` (API §1.5). Le client en déduit les **espaces proposés** et masque les actions non autorisées. C'est de l'**ergonomie**, pas de la sécurité.
2. Chaque route `pair/*` et `admin/*` est protégée par middleware + Policy + scope de données (ROLE §5) : un 403 du serveur est la seule vérité. À réception d'un 403 `forbidden` sur un espace, l'application relit `GET me`, retire l'espace si le droit a disparu et affiche « Cet espace n'est plus disponible pour ce compte ».
3. Rien de ce qui est affiché ne se déduit d'une valeur modifiable côté client (stockage, préférence, URL) : un utilisateur qui force la route `/admin/alertes` voit un écran vide et des 403.

### 6.2 Espaces, rôles et permissions

| Espace | Condition d'affichage (client) | Permissions serveur contrôlées | État |
|---|---|---|---|
| Membre | compte connecté (tous les rôles) ; e-mail confirmé pour écrire | `social.access`, `posts.create`, `comments.create`… | existant |
| Pair | `roles` contient `pair`, `status = actif` (un pair `en_attente` reste en Membre avec bandeau, RG-03) | `peer_space.access` (middleware `EnsurePeerSpaceAccess`), `cases.create`, `cases.update_own`, `alerts.view_routed`, `alerts.take` (routées), `case_followups.*` | existant côté serveur |
| Admin | `permissions` contient `admin_mobile.access` (**nouvelle**, donnée à `admin` et `superadmin`, retirable par le superadmin) | `alerts.view`, `alerts.take`, `alerts.close`, `alerts.manage` (réattribution, note), `users.validate_peers` (inscriptions et demandes), attribution et retrait du rôle pair, invitations, recherche de membre (permissions à confirmer dans `ROLE_ET_PERMISSIONS.md`), modération (`moderation.*`), `platform.maintenance` | à créer (P15-16, P15-24) |
| Rôle admin, suspension de compte, sauvegardes, paramètres, SMTP, journal d'audit, rapports, exports | **absents du mobile v1** | | web seulement |

**Modèle de rôles (décision 1)** : (a) l'inscription publique ne crée que des membres ; (b) le rôle `pair` s'ajoute au rôle `membre` par **invitation** de l'admin (même mécanisme d'inscription, puis validation) ou par attribution à un membre existant, retirable ; un membre peut aussi **demander** le rôle (D-MOB-15) ; (c) un compte admin est par défaut aussi membre (module réseau social + module admin) ; (d) l'attribution du rôle **admin** reste réservée au site web (D-MOB-16, à valider) ; (e) l'admin valide les inscriptions et les demandes depuis le tableau de bord web ou l'appli mobile (mêmes cas d'usage serveur). Cumul de rôles : un·e admin avec un rôle `pair` voit Membre + Pair + Admin ; un·e admin sans rôle `pair` ne voit **pas** l'espace Pair (il ne recevrait aucune alerte routée : `Alert::routedTo`). Un·e pair validé·e qui devient admin garde ses dossiers (scope `visibleTo` par `collected_by`).

### 6.3 Jetons et sessions

| Sujet | Décision |
|---|---|
| Capacités Sanctum | à la connexion, le jeton reçoit des capacités selon les permissions (`member`, `peer`, `admin-mobile`) ; `admin/*` exige `ability:admin-mobile` **et** la permission (défense en profondeur ; la permission reste la source de vérité) |
| Durée | jeton admin court (7 jours glissants, à valider), pair 30 jours, membre selon Q30 |
| Appareil | `X-Device-Id` obligatoire pour `sync/*` et `admin/*` ; appareil révoqué → 403 `device_revoked` → effacement local |
| Étape de confirmation (`step-up`) | actions admin à fort impact (maintenance, refus de pair, réattribution d'un SOS) : ressaisie du mot de passe ou OTP récent (≤ 5 min) via `POST admin/step-up`, jeton court usage unique |
| OTP de connexion | déjà **imposé** aux admin / superadmin (Q13 b) |

## 7. Sécurité

### 7.1 Synthèse des mesures

| Mesure | Membre | Pair | Admin | État / lot |
|---|---|---|---|---|
| PIN 4 chiffres local (haché dans `flutter_secure_storage`, 5 essais) | facultatif | **obligatoire** à l'entrée dans l'espace | **obligatoire** | membre fait ; imposition P15-11 |
| Délai de verrouillage au retour en avant-plan | selon réglage | 60 s | 30 s | P15-11 |
| SOS, urgences, signalement anonyme sans PIN | oui | oui | oui | fait |
| `FLAG_SECURE` (Android) / flou au sélecteur et détection d'enregistrement (iOS) | écrans sensibles | **tous les écrans** | **tous les écrans** | P15-11 (aujourd'hui seulement masquage de l'aperçu à la mise en pause) |
| Effacement à la déconnexion | oui | oui (boîtes + clé de l'espace) | oui | partiel → P15-11 |
| Effacement à distance | – | oui | oui | P15-11 (client) + serveur |
| Expiration des données locales hors contact (14 j par défaut) | – | oui | – | P15-11 |
| Effacement après 5 PIN faux | session fermée (existant) | **purge des données pair** | purge | P15-11 |
| Mode discret (icône / nom neutres, aperçus masqués) | oui (Android fait, iOS : icône seulement) | oui, activé par défaut au 1er accès à l'espace | oui | partiel → P15-11 |
| Journal d'audit serveur des actions mobile (`source = mobile`) | – | existant (`cases.*`) | **à créer** : accusé, statut, réattribution, validation, maintenance | P15-16 |
| Pas de contenu sensible dans les notifications (le push SOS part vers les admins ET les pairs, sans récit, nom ni position) | oui | oui | oui | contrat existant, tests P15-19 |
| Sauvegarde système Android désactivée (`allowBackup=false`, règles `dataExtractionRules`) | à vérifier | | | P15-11 |

### 7.2 Capture d'écran désactivée (FLAG_SECURE)

- Un canal de plateforme `xomiton/secure` (`setSecure(bool)`) et un widget `SecureScreen` posent le drapeau à l'entrée d'une route sensible et le retirent à la sortie ; le drapeau « mise en pause » existant est conservé pour tout le reste.
- Écrans concernés : tout `pair/**` et `admin/**` ; côté membre, messages, conversation, signalement, suivi d'une alerte, sécurité et confidentialité.
- iOS : pas d'équivalent de `FLAG_SECURE` ; flou à `willResignActive`, écran de recouvrement quand `UIScreen.isCaptured`, texte d'aide honnête (limite connue).
- Build de recette : drapeau `--dart-define=ALLOW_SCREENSHOTS=true` refusé en `release` par la CI (les testeurs ont besoin des captures ; la production jamais).
- Effets de bord assumés : enregistrement d'écran et partage d'écran bloqués (utile pour la discrétion, gênant pour le support à distance) ; TalkBack n'est pas affecté.

### 7.3 Effacement (déconnexion, distance, expiration)

| Déclencheur | Action locale | Mise en œuvre |
|---|---|---|
| Déconnexion volontaire | supprimer boîtes de l'espace, clé Hive, jeton, abonnement push (`DELETE me/push-subscriptions`), appareil `DELETE me/devices/{uuid}` si choisi | `WipeService.wipe(scope)` |
| 401 | session fermée, boîtes pair / admin purgées | intercepteur existant, étendu |
| 403 `device_revoked` / `device_required` | purge totale + nouvel UUID d'appareil | intercepteur |
| **Effacement à distance** | l'admin web ou la personne (« Appareils connectés › Effacer à distance ») pose `sync_devices.wipe_requested_at` ; l'appareil le lit au prochain `heartbeat`, `sync/*` ou `GET me` (champ `device.wipe`) puis purge et accuse (`POST me/devices/{uuid}/wiped`) | serveur P15-11 |
| Hors contact > N jours (14 par défaut, `app/version` ou `me/security` fournit la valeur) | purge des boîtes pair ; reconnexion + PIN exigées | horloge locale **et** dernier `server_time` vu (anti-retour d'horloge) |
| 5 PIN faux | session fermée + purge pair / admin | `SessionGuard` |
| Désinstallation | rien ne reste hors du bac à sable ; sauvegarde système désactivée | manifeste |

Limite à documenter : un téléphone **éteint ou sans réseau** ne reçoit pas l'ordre d'effacement ; l'expiration locale et le jeton court sont le filet de sécurité.

### 7.4 Mode discret, icône et nom neutres

Existant : `activity-alias` Android (nom « Notes »), bascule dans `MainActivity.setAlias`. À faire : appliquer le mode discret par défaut dès qu'un compte accède à l'espace pair / admin ; titres de fenêtre et canaux de notification neutres (le nom d'un canal reste visible dans les réglages Android : à tester) ; icône alternative iOS ; aucun libellé « VBG », « SOS » ou « admin » dans les notifications quand les aperçus sont masqués ; l'espace admin ne s'affiche pas dans l'écran d'accueil du mode discret (le sélecteur d'espace est dans le profil).

### 7.5 Données stockées par espace

| Donnée | Membre | Pair | Admin |
|---|---|---|---|
| Récit d'une alerte, contact, position | – | **jamais en cache** (détail chargé à la demande, en mémoire) | **jamais** (mémoire, `no-store`) |
| Dossiers (`cases`), suivis | – | **ses** dossiers, notes chiffrées (Hive), purge à l'expiration | – |
| Règles de validation, référentiels | oui | oui | oui |
| Résumé d'alertes routées | – | oui (sans récit ni contact) | – |
| Tableau d'astreinte (compteurs) | – | – | dernier instantané agrégé, sans identité |

## 8. Arbitrages et alternatives

### 8.1 Une application, deux ou trois

| Option | Pour | Contre | Verdict |
|---|---|---|---|
| **A. Une seule application, espaces par rôle** (demande du client, **tranché le 29/10/2026**) | un pipeline, un store, une mise à jour, un `applicationId` ; un compte cumulant plusieurs rôles passe d'un espace à l'autre sans réinstaller ; code partagé (réseau, sécurité, sync) ; moins de coûts de recette | écrans d'admin **présents dans le binaire** de tous les utilisateurs ; surface d'examen Play plus large ; AAB plus lourd ; une régression du socle touche tous les espaces ; discrétion : l'application d'une militante « contient » un espace admin | **retenue (décision client)** |
| B. Trois applications (membre / pair / admin) | binaires et listes de permissions minimaux ; l'app publique ne contient rien de sensible ; admin diffusable en interne ; mises à jour indépendantes | trois fiches de store, trois revues, trois jeux de comptes de test, trois clés de signature ; un compte cumulant les rôles installe plusieurs applications ; triple maintenance du socle | non retenue en v1 |
| C. Deux applications (publique + « Xomiton Équipe » pour pair et admin) | séparation nette du public ; le binaire public ne contient ni collecte ni admin ; l'app Équipe peut être non répertoriée | une fiche et une revue de plus ; un·e pair garde l'app membre pour le SOS et la communauté | **plan B si la fuite d'écrans ou la revue Play posent problème** |

Mesures qui rendent A réversible : modules `features/pair` et `features/admin` **sans dépendance croisée**, activés par `--dart-define=SPACES=member,pair,admin` (une variante « Équipe » ou « membre seul » devient un simple paramètre de build, sans réécriture) ; un interrupteur serveur `mobile_spaces_enabled` (par espace) pour couper un espace sans republier.

### 8.2 Taille de l'application

- Estimation (à **mesurer** dès P15-10, aucune mesure n'existe aujourd'hui) : l'ajout de 11 écrans pair et 7 écrans admin en Dart compilé représente quelques centaines de Ko à ~1,5 Mo ; aucune dépendance lourde n'est prévue (pas de carte embarquée, pas de PDF, pas de base SQL : Hive suffit). Les assets (photos du splash, polices) pèsent déjà ~0,6 Mo (`mobile/assets` : 576 Ko).
- Garde-fous : budget de taille en CI (`flutter build appbundle --analyze-size`, alerte à +15 %), AAB (livraison par ABI), `--split-debug-info`, pas de bibliothèque de graphiques (compteurs en texte), icônes depuis les polices Material déjà incluses.
- Alternative écartée : *deferred components* de Flutter (modules Play Feature Delivery) pour télécharger pair / admin à la demande ; gain réel faible (le code est petit), complexité et pannes réseau au premier accès (le pair en brousse en a besoin hors ligne), pas d'équivalent iOS.

### 8.3 Révisions Play Store

| Point | Risque | Parade |
|---|---|---|
| Fonctions « cachées » selon le rôle | la politique Play sur les comportements non documentés peut faire échouer la revue si le relecteur ne voit pas les espaces | fournir **un compte de test par rôle** (membre, pair, admin) et la description des espaces dans *App content › App access* ; mentionner les espaces dans la fiche |
| Questionnaire *Data safety* | l'application traite localisation, données personnelles, données de santé / violence : le formulaire doit refléter pair et admin (dossiers, validation des comptes) | mise à jour du formulaire à chaque lot qui ajoute des données ; coordination avec `docs/playstore/` (travail d'un autre agent, non modifié ici) |
| Permissions | localisation en premier plan (SOS), notifications (Android 13) | pas de `USE_FULL_SCREEN_INTENT`, pas de localisation en arrière-plan, pas de `SYSTEM_ALERT_WINDOW` |
| Nom / icône neutres du mode discret | une icône « Notes » peut être lue comme trompeuse | l'alias est choisi par l'utilisateur·rice, documenté dans l'application et la fiche ; point à valider avec le client |
| Cadence de mise à jour | revue de 1 à 7 jours | **une seule version Play** (décision 7) : pistes interne → fermée → production, déploiement progressif (10 % → 100 %) ; les interrupteurs serveur par espace restent utiles pour couper un espace sans republier, plus pour étaler deux versions |
| Fiche Play (décision 8) | tranches d'âge, pays, prix, langue | âge minimal de l'application 15 ans ; **tranches d'âge Play « 16-17 » et « 18+ » seulement** (point de cohérence D-MOB-18, **conservé** : la tranche la plus basse déclarée est supérieure à l'âge minimal de 15 ans ; à valider par le conseil juridique avant publication ; l'application affiche la règle des 15 ans à l'inscription) ; **pas de tarif** (gratuite, sans achat) ; **tous les pays** ; **français uniquement** (aucune fiche ni localisation anglaise : le gabarit « en » de `docs/playstore/` reste vide) |
| Politique de contenu sensible (VBG, LBTQ+) | catégories examinées avec attention | descriptions factuelles ; pas de capture d'écran de contenus réels ; ressources d'aide mentionnées |

### 8.4 Risque de fuite d'écrans admin dans l'APK

| Menace | Réalité | Mesure |
|---|---|---|
| Quelqu'un décompile l'APK et voit les écrans / routes admin | Vrai : un binaire Flutter AOT n'est pas lisible comme du Java, mais les chaînes, les routes et les noms d'API se retrouvent | **ne rien mettre de secret dans le binaire** : pas de clé, pas de jeton, pas de logique d'autorisation ; routes admin protégées par le serveur ; `--obfuscate --split-debug-info` pour la lisibilité seulement |
| Un·e membre force l'ouverture d'un écran admin (outil d'instrumentation) | possible sur un appareil rooté | écran sans données : toutes les requêtes `admin/*` répondent 403 sans permission **et** sans capacité du jeton |
| Les maquettes / libellés révèlent l'existence d'un espace d'astreinte | faible : l'existence d'une équipe d'astreinte n'est pas un secret | pas de données réelles, pas d'adresse interne en dur, libellés génériques |
| Fuite par les journaux / rapports d'erreur | | aucun rapport automatique de contenu ; journaux désactivés en `release` |

Conclusion : le risque est **acceptable** parce que la sécurité repose sur le serveur ; le vrai coût de l'option A est la surface de revue et la discrétion, traités en §8.3 et §7.4. Si le client exige que « le binaire public ne contienne rien », passer à l'option C (§8.1).

### 8.5 Autres arbitrages

| Sujet | Choix | Alternative écartée | Pourquoi |
|---|---|---|---|
| API admin | nouveau groupe `admin/*` par jeton, cas d'usage existants réutilisés | WebView sur `/admin` (Livewire) | session web, CSRF, aucun mode hors ligne ni push, écrans non adaptés, fuite de cookies |
| Synchro pair | `sync/push` / `sync/pull` (déjà livrés) | routes dédiées `pair/cases` seules | lots, curseurs, suppression, conflits déjà faits ; les routes dédiées restent le repli |
| Synchro SOS | route `POST sos` dédiée | SOS dans un lot | le SOS ne dépend d'aucun lot ni quota (RG-01) |
| Actions admin hors ligne | **refusées** hors ligne (message + numéro à appeler) | file d'envoi | une « prise en charge » non transmise fausserait l'escalade pendant un SOS |
| Arrière-plan Android | pas de WorkManager en v1 ; envoi au premier plan, au retour du réseau et à l'ouverture, réveillé par push `sync` | WorkManager périodique | batterie, règles Play sur l'arrière-plan, complexité ; à revisiter si les pairs oublient d'ouvrir l'application |
| Conflit 409 | **le pair tranche lui-même** (décision 4) : écran de choix « valeur du serveur » ou « mon appareil » avec explication simple, effet immédiat, historique conservant les deux versions et le choix ; endpoint `POST sync/conflicts/{id}/resolve` obligatoire (3 j serveur, lot P15-15) | arbitrage par l'administration (API §21.4) | décision du client ; la règle métier web (« Administration › Hors connexion & synchro ») reste disponible pour les conflits que le pair n'a pas tranchés |
| Position dans le détail admin | **arrondie (~1 km) côté serveur pour les admins** (décision 5) ; la route de position précise est abandonnée | précise sur action journalisée ; précise toujours | minimisation ; reste à confirmer que l'aide immédiate n'exige jamais la position exacte (voir D-MOB-4) |
| Ouverture de Maps | avertissement avant de transmettre la position à un service tiers (maquette A3) | carte embarquée | pas de dépendance, pas de fuite silencieuse |

## 9. Plan par lots

**Pré-requis : validation des maquettes par le client (décision 9), puis seulement le code mobile.** Les lots serveur P15-16 et P15-24 (volet S) et les volets S des autres lots peuvent démarrer avant, car ils ne dépendent pas des écrans.

Convention : lots **P15-10 à P15-24** (la suite de P15-01 à P15-09). Identifiants de recette **MOB-14 et suivants** (MOB-01 à MOB-13 existent déjà dans `CHECKLIST_RECETTE.md`). Unités : jours-personne (j.p.), M = mobile, S = serveur. Les estimations supposent des développeur·ses connaissant le dépôt ; marge de 20 % ajoutée en fin de tableau.

### 9.1 Vue d'ensemble

| Lot | Titre | M | S | Dépend de | Jalon |
|---|---|---|---|---|---|
| P15-10 | Socle multi-espaces : rôles cumulés, `SpaceController`, garde du routeur, shells, **écran de choix après connexion, commutateur d'en-tête, onglet « Espace pair » / « Admin » et « Réseau »**, budget de taille | 5 | 0 | P15-01, P15-07 | A, B |
| P15-11 | Sécurité renforcée : PIN imposé, `FLAG_SECURE` par écran, effacement (déconnexion, distance, expiration), mode discret par défaut | 6 | 2 | P15-10 | A, B |
| P15-12 | Moteur de synchro client : `sync/push`, `sync/pull`, conflits, `heartbeat`, boîtes Hive par espace | 8 | 1 | P15-10 | A |
| P15-13 | Pair 1 : accueil, alertes routées (liste, détail, accusé, statut), polling « de garde » | 5 | 0 | P15-10, P15-11 | A |
| P15-14 | Pair 2 : mes dossiers, fiche en 5 sections, suivi | 7 | 0 | P15-12 | A |
| P15-15 | Pair 3 : collecte hors ligne par étapes, plausibilité, file visible, **conflit 409 tranché par le pair (écran de choix, endpoint de résolution)** | 9 | 3 | P15-12, P15-20 | A |
| P15-16 | **API admin mobile** : permission, capacités de jeton, `step-up`, `admin/*`, audit, limiteurs, tests 403 | 0 | 10 | P9-02, P10-05, P12-01, P13-06 | B |
| P15-17 | Admin 1 : tableau d'astreinte, alertes (liste, détail, actions), Maps avec avertissement | 8 | 1 | P15-10, P15-11, P15-16 | B |
| P15-18 | Admin 2 : **inscriptions à valider (pairs invités, demandes de rôle, membres à vérifier)**, maintenance, notifications, menu « Gérer », comportement hors ligne | 6 | 1 | P15-17 | B |
| P15-19 | Push d'astreinte et pair : canal « Astreinte », types `alert_routed`, `duty_sos`, `peer_pending`, `sync`, `rules`, ouverture profonde, iOS | 4 | 3 | P15-13, P15-17, P15-08 | A, B |
| P15-20 | Règles de validation et référentiels : cache, versions, rejets « à corriger » | 3 | 2 | P15-12 | A |
| P15-21 | Tests d'intégration et recette automatisée (rejeu SOS, collecte hors ligne, conflit, gardes de rôle) | 6 | 0 | P15-12 à P15-18 | C |
| P15-22 | Livraison : signature, AAB, obfuscation, budget de taille, Play (accès test, Data safety), iOS | 4 | 1 | P15-21 | C |
| P15-23 | Finalisation de l'espace membre : reste P15-01 à P15-08, `sync/pull` membre, `/soon`, recette sur appareil | 6 | 0 | P15-12 | A |
| P15-24 | **Admin 3 (périmètre élargi)** : rôles et invitations (inviter un·e pair par lien ou code, révoquer, attribuer / retirer le rôle pair), recherche de membre, modération essentielle (contenus signalés), inscription par lien d'invitation et demande du rôle pair côté membre | 8 | 6 | P15-16, P15-18 | B |
| **Total** | | **85** | **30** | | **115 j.p.** (+20 % = **≈ 138** ; avant décisions : 98 et ≈ 118) |

Jalons internes (pistes de test, **pas des versions Play distinctes**) : **A — Pair bêta** (P15-10 à P15-15, P15-19 partiel, P15-20, P15-23) ; **B — Admin bêta** (P15-10, P15-11, P15-16 à P15-19, P15-24) ; **C — Release unique** (P15-21, P15-22). Ordre conseillé : Flutter = P15-10 → 11 → 12 → 13 → 14 → 15 → 20 → 17 → 18 → 24 → 19 → 21 → 22 ; Laravel = P15-16 dès le début, puis P15-24 (volet S) et les volets serveur des autres lots. **Durée (recalculée le 29/10/2026)** : le mobile porte 85 j.p., le serveur 30. Avec **trois personnes (deux Flutter, un·e Laravel)** : ≈ 43 j de mobile par personne, soit **≈ 10 semaines avec la marge de 20 %**. Avec **un·e seul·e Flutter** : 85 j.p. ≈ 17 semaines, ≈ 20 avec la marge. L'ancien chiffre (« 10 à 11 semaines à deux personnes ») ne tenait que si le mobile était partagé entre deux personnes ; il est corrigé. Chemin critique : P15-10 → P15-12 → P15-15 côté mobile, P15-16 → P15-17 → P15-24 côté admin. **Une seule version Play** (décision 7) : les espaces sont livrés ensemble ; les interrupteurs serveur permettent seulement de couper un espace en cas de problème.

### 9.2 Détail, critères d'acceptation et tests

**P15-10 — Socle multi-espaces** (4 j.p. M)

**Statut : fait le 2026-10-29** (54 tests, `flutter analyze` propre, 217 tests au total). Réalisé : `AppUser` (`roles` cumulés, `permissions`, `spaces` lus de `GET me` ; **sans `spaces` : Membre seul, jamais déduit du rôle**), `core/auth_spaces` (`AppSpace`, `spacesProvider` → `activeSpaceProvider` mémorisé en préférences et validé contre le serveur, `chooseSpace`), `appRedirect` avec garde par espace (`/pair/**`, `/admin/**`) et écran `/spaces` après la connexion (`spaceChoicePending`), trois barres d'onglets (Membre inchangée + onglet « Espace pair » / « Admin » / « Espaces » ; Pair : Accueil · Alertes · SOS · Dossiers · Réseau ; Admin : Astreinte · Alertes · Gérer · Notifs · Réseau ; contenus des écrans Pair et Admin = pages d'attente, livrés par P15-13 et suivants), pastille d'en-tête, feuille « Choisir mon espace », carte « Mes espaces », retrait d'un droit (relecture de `GET me` sur 403 `forbidden` d'une route `pair/*` ou `admin/*`, message « Cet espace n'est plus disponible »), `--dart-define=SPACES`, connexion unique (même écran pour tous), inscription membre seulement (case 15 ans, champ code ou lien d'invitation, `/signup/:invitation`, plus de choix « pair » en phase 2), `me/role-requests` (modèle, client, demande depuis le profil, D-MOB-15). Pas d'endpoint `me/spaces` côté serveur : les espaces viennent de `GET me`. **Reste de P15-10** : mesure de taille de l'AAB et budget en CI (nécessite un build Android, non fait ici ; budget d'images contrôlé par test : 382 Ko pour 700 Ko).

| Contenu | Étendre `AppUser` (`roles` cumulés, `permissions`, `deviceWipe`) ; `SpaceController` (`spaces`, `active`, persistance) ; `appRedirect(spaces, …)` ; trois shells ; **écran de choix d'espace après connexion (31 du simulateur), feuille et pastille d'en-tête, carte « Mes espaces », onglets « Espace pair » / « Admin » et « Réseau »** ; `--dart-define=SPACES` ; budget de taille en CI ; découpage `core/` (déplacer ce qui sert aux trois espaces) |
|---|---|
| Acceptation | un compte membre ne voit que Membre et va directement au tableau de bord ; un pair validé voit Membre + Pair et le sélecteur après connexion ; un admin avec `admin_mobile.access` voit Membre + Admin ; retrait d'un droit (relecture de `GET me`) ferme l'espace ; lien profond vers un espace interdit → espace par défaut ; la barre d'onglets membre est inchangée (5 entrées, libellé « SOS ») |
| Tests | tests unitaires de `appRedirect` (toutes combinaisons session × onboarding × espace) ; test widget du sélecteur ; test de non-régression des 151 tests existants ; mesure de taille de référence consignée |

**P15-11 — Sécurité renforcée** (6 M + 2 S)

**Statut (volet mobile) : fait le 2026-10-29** (31 tests ajoutés, 248 au total, `flutter analyze` propre). Réalisé : code PIN obligatoire pour entrer dans Pair et Admin (`/pin-setup`, garde du routeur, jamais désactivable pour ces comptes, état du code illisible = écran masqué), verrou après 60 s (Pair) et 30 s (Admin), `/pair/sos` sans code ; `ScreenSecurityGate`, `SecureScreen` et canal `xomiton/secure` (`MainActivity.kt` : `FLAG_SECURE` par écran sensible, build de recette `ALLOW_SCREENSHOTS` ignoré en release), recouvrement opaque au passage en arrière-plan (substitut iOS) ; boîte `pair_data` vidée à la déconnexion, après 5 codes faux, après 403 `device_revoked` et après 14 jours sans contact (`ContactClock`, horloge reculée = expirée) ; mode discret par défaut au premier accès Pair ou Admin. **Reste de P15-11** : ~~volet serveur~~ (livré par ailleurs : `device.wipe`, `POST me/devices/{uuid}/wiped`, voir `API.md` §3), ~~réaction à `device_required`~~ et ~~boîtes Hive par espace avec clé propre~~ (livrées avec P15-12, ci-dessous), test d'instrumentation Android `FLAG_SECURE`, recette sur appareil (MOB-15, MOB-16, MOB-23), iOS : flou natif.

| Contenu | PIN imposé à l'entrée des espaces pair / admin ; délais de verrouillage par espace ; canal `xomiton/secure` + `SecureScreen` (Android, iOS flou + `isCaptured`) ; `WipeService` ; réaction à 401 / 403 `device_revoked` ; expiration hors contact ; purge après 5 PIN faux ; mode discret proposé au premier accès ; `allowBackup=false`. Serveur : colonne `sync_devices.wipe_requested_at`, champ `wipe` dans `heartbeat` / `GET me`, `POST me/devices/{uuid}/wiped`, action « Effacer à distance » dans *Appareils connectés* (web et `me/sessions`) |
|---|---|
| Acceptation | impossible d'ouvrir `/pair/**` ou `/admin/**` sans PIN ; capture d'écran impossible (Android) sur un écran pair / admin et possible sur le fil ; après « Effacer à distance » le prochain contact vide les boîtes et ramène à la connexion ; plus de 14 jours sans contact : données pair purgées ; 5 PIN faux : session fermée et pair purgé ; SOS et urgences toujours accessibles sans PIN |
| Tests | widget : verrou, déverrouillage, 5 essais ; unitaire : `WipeService` (boîtes et clé supprimées), horloge reculée ; Feature serveur : `device.wipe` visible une fois, accusé idempotent ; test d'instrumentation Android `FLAG_SECURE` par route ; revue manuelle iOS |

**P15-12 — Moteur de synchro client** (8 M + 1 S)

**Statut : fait le 2026-10-29** (34 tests, `flutter analyze` propre). Réalisé : `SyncEngine` (`lib/core/sync/`) : `sync/push` par lots de 50 avec `Idempotency-Key` = uuid du lot **posé dans l'entrée avant l'envoi** (un rejeu part avec la même clé et exactement le même corps ; un nouvel élément ne rejoint jamais un lot déjà posé), 202 `processing` attendu selon `Retry-After`, résultat lu **par élément** (`created/updated/duplicate` = parti, `conflict` = à trancher, `rejected` = « à corriger » avec les erreurs par champ, 429 / 503 `retry_later` = nouveau lot), attente exponentielle avec gigue (5 s à 5 min) entre deux passages automatiques (une action de la personne passe outre) ; `sync/pull` par groupe de collections (pair : `cases`, `case_followups`, `alerts`, `validation_rules` ; communes : `emergency_contacts`, `directory_contacts`, `thematiques`), `has_more`, `reset`, `deletes`, application **en bloc puis curseur en dernier** (un arrêt pendant le tirage ne fait pas avancer le curseur ; un `reset` supprime le curseur avant d'écrire), `cursor_invalid` = tirage complet ; `heartbeat` à l'ouverture, toutes les 5 min et au retour au premier plan (`open_conflicts`, `rules_updated_at`, `wipe`) ; règles de validation (`pair/validation-rules`, ETag, copie chiffrée) ; conflits (`GET sync/conflicts`, `POST sync/conflicts/{id}/resolve`) ; effacement à distance (`wipe` non nul : accusé `POST me/devices/{uuid}/wiped`, coffre vidé, session fermée). **Appareil enregistré avant toute synchro** (`POST me/devices`, une fois par lancement) ; 403 `device_required` sur un appareil déjà enregistré = purge totale (comme `device_revoked`). **Coffre du pair à clé propre** (`SecretKeys.hiveKeyPeer`, distincte de celle des coffres Membre) : l'effacement détruit la boîte et la clé ; une ancienne boîte `pair_data` chiffrée avec la clé commune est recréée vide ; changement de compte = coffre vidé ; l'espace Admin ne persiste rien, donc pas de clé. **Boîte d'envoi Membre inchangée** (SOS, signalement, message, publication gardent leurs routes dédiées, leur coffre et leur clé : rien à migrer, rien de perdu) ; le moteur la vide avant le lot du pair, SOS en tête. La file du pair (`QueueKind` : `caseCreate`, `caseUpdate`, `followupCreate`, `followupDone`) vit dans le coffre du pair. **Reste de P15-12** : synchronisation en arrière-plan (hors périmètre v1, §8.5), collections communes consommées par les écrans Membre (lot P15-23), test d'intégration (P15-21).

| Contenu | `SyncEngine` : lots ≤ 50 (`xomiton.sync.push_batch_size`), `Idempotency-Key` = uuid du lot, gestion `202 processing` + `Retry-After`, lecture par élément (`created/updated/duplicate/conflict/rejected`), attente exponentielle avec jitter ; `pull` avec curseur, `reset`, `deletes`, `has_more`, transaction Hive ; `OutboxType.collecte` et `followup` ; `heartbeat` à l'ouverture et toutes les 5 min au premier plan ; collections (pair : `cases`, `case_followups`, `alerts`, `validation_rules` ; tous : `emergency_contacts`, `directory_contacts`, `thematiques`) ; migration douce de la boîte d'envoi existante (les entrées SOS / signalement / message en cours ne sont pas perdues). Serveur : `heartbeat` renvoie aussi `rules_updated_at` pour déclencher le rafraîchissement des règles |
|---|---|
| Acceptation | élément rejoué (mêmes uuid, même clé) → un seul dossier ; lot partiellement rejeté : les éléments valides partent, l'erreur est affichée sur l'élément fautif ; arrêt brutal pendant `pull` : le curseur n'avance pas, la collection reste cohérente ; une collection `reset: true` remplace l'ancienne ; aucune donnée d'un autre pair après changement de compte |
| Tests | unitaires avec faux Dio (tous les cas de l'API §21.2 : 200/202/409/422/429/503) ; test de reprise après arrêt ; Feature serveur existant (P9-06) réutilisé ; test d'intégration en P15-21 |

**P15-13 — Pair 1 : accueil et alertes routées** (5 M)

**Statut : fait le 2026-10-29** (19 tests). Réalisé : accueil pair (P1) : KPI de `GET dashboard` (section `peer`), tâches du jour, raccourcis, badge « De garde » si le serveur l'annonce, recalcul des chiffres depuis la copie locale hors ligne ; alertes routées (P2, P3) : liste relue toutes les 10 s (de garde) ou 30 s au premier plan, repli sur la copie locale (« périmée »), accusé direct depuis la liste, détail avec boutons conditionnés à `can{…}` du serveur, contact visible après l'accusé, position avec **avertissement avant Maps**, message du serveur affiché tel quel sur `invalid_transition`, alerte non routée (403/404) = écran neutre sans fuite ; les écrans de détail restent dans la barre d'onglets pair (SOS toujours accessible). **Reste** : texte de garde réel (`on_duty` n'est pas exposé par l'API : voir §11.1), accessibilité TalkBack à vérifier sur appareil.

| Contenu | Accueil pair (maquette P1) : KPI du `GET dashboard`, tâches, pastille « de garde » ; liste (`pair/alerts?state=open`, polling 10 s au premier plan si de garde, sinon 30 s) ; détail (P3) avec `can{…}` du serveur ; `POST …/acknowledge` puis `…/status` (`en_cours`, `traite`, `clos`) ; boutons conditionnés à `can` ; position affichée avec avertissement avant Maps ; SOS toujours présent (P7) |
|---|---|
| Acceptation | un pair ne voit que les alertes routées vers lui ; accuser réception arrête l'escalade (vérifié sur `/admin/alertes`) ; statut invalide → message du serveur (`invalid_transition`) ; alerte non routée en lien profond → écran « Plus disponible » sans fuite |
| Tests | widget (liste, détail, états vide / erreur / hors ligne), faux Dio ; scénario serveur : pair A ne peut pas ouvrir l'alerte de pair B (403) ; accessibilité TalkBack |

**P15-14 — Pair 2 : mes dossiers** (7 M)

**Statut : fait le 2026-10-29** (16 tests). Réalisé : liste (P4) : `GET pair/cases` (filtres de nature et de statut, recherche **par code** seulement, jamais les notes), dossiers en file en tête, lecture hors ligne depuis la copie chiffrée (libellés des référentiels mis en cache) ; fiche en 5 sections (P5) tirées des champs de la collecte, frise de suivi, changement de statut (PATCH immédiat, ou modification en file avec la valeur d'origine hors ligne), « Marquer comme réalisée » ; ajout de suivi (P6) : écrit dans la file (uuid = `client_uuid` = `Idempotency-Key`) puis envoyé tout de suite, « En file » sur la frise puis confirmé, erreurs du serveur sur les champs ; dossier d'un autre pair ou inconnu = 404 neutre ; archivé / anonymisé = lecture seule ; après anonymisation, le `pull` retire le dossier du cache. **Écart** : l'API ne sert pas la « fiche de référence » (services reçus, consentement, lieu précis) : les sections 3 et 5 affichent « Pas encore renseigné » (voir §11.1).

| Contenu | Liste (`pair/cases` + cache `pair_cases`), recherche **par code** (le serveur ne cherche que dans le code, ADR-33), fiche en 5 sections (maquette P5), frise de suivi, ajouter / modifier un suivi (P6, `client_uuid`), dossiers clos / archivés, statut, lecture hors ligne depuis le cache chiffré |
|---|---|
| Acceptation | liste identique au web (mêmes filtres) ; suivi ajouté hors ligne apparaît « en attente » puis confirmé ; dossier d'un autre pair introuvable (404 neutre) ; notes jamais dans la liste ; après anonymisation côté serveur, le cache est vidé au prochain `pull` |
| Tests | widget ; test de fusion `pull` (upsert / delete) ; test de non-affichage des notes en liste ; scénario d'un pair qui change de compte |

**P15-15 — Pair 3 : collecte hors ligne et file de synchronisation** (8 M + 3 S)

**Statut : fait le 2026-10-29** (37 tests, dont 20 de collecte, 13 de file et de conflit, 4 de barre d'onglets). Réalisé : collecte en 5 étapes (P8, P9) avec brouillon par étape **dans le coffre du pair, sans les notes** et distinct de la file ; validation locale par les règles actives du catalogue en cache (les mêmes que `CaseRules` : obligatoires, valeurs de listes, `date_not_future`, `age_range`, `commune_in_zone`, `min_len_20`) ; avertissements de plausibilité **non bloquants** avant l'envoi (`date_old` selon `plausibility.max_past_years`, `duplicate_suspected` selon `duplicates.fields` parmi les dossiers de l'appareil) et ceux du serveur après l'enregistrement ; envoi par la file (« En file » puis « Envoyé » avec le code attribué ; rejeu manuel sans doublon, vérifié) ; refus 422 : « Le dossier n'a pas été enregistré », **Corriger** rouvre la fiche avec l'erreur sur le champ ; file de synchronisation visible (P10 : conflits, en attente, envoyés, SOS de la boîte Membre, « Synchroniser maintenant ») ; **écran de conflit 409 (P11)** : valeur du serveur ou mon appareil, confirmation, `POST sync/conflicts/{id}/resolve`, effet immédiat (réseau requis), entrée « Tranchée par toi », dossiers relus ; aucun référentiel codé en dur (départements, communes, types de violence, tranches d'âge, orientations viennent du serveur ou de sa copie). **Reste** : liste des populations (aucune route ne la sert : `GET pair/collect-options` à créer, §11.1), test d'intégration de bout en bout (P15-21), recette sur appareil (MOB-18, MOB-19).

| Contenu | Collecte par étapes (P8, P9) avec brouillon par étape distinct de la boîte d'envoi ; validation locale par les règles en cache ; avertissement de plausibilité (`CaseQuality` côté serveur renvoie `warnings`) ; états « En file », « Envoyé », code attribué, « À corriger » ; file visible (P10) ; **conflit 409 tranché par le pair (P11)** : écran de choix « valeur du serveur » / « mon appareil », explication simple, confirmation, effet immédiat ; **serveur (obligatoire, décision 4)** : `POST sync/conflicts/{id}/resolve`, historique des deux versions, journal d'audit |
|---|---|
| Acceptation | parcours : mode avion → collecte → file → réseau → dossier créé avec son code, sans doublon même après rejeu manuel ; rejet 422 : l'élément reste modifiable avec les erreurs par champ ; conflit visible avec les deux valeurs, **le pair choisit et la valeur retenue est appliquée sans validation par l'équipe** ; aucun référentiel codé en dur |
| Tests | intégration (P15-21) ; widget des étapes ; test de rejeu du même `client_uuid` (`meta.duplicate`) ; test « règles modifiées entre-temps » |

**P15-16 — API admin mobile** (10 S)

| Contenu | Voir §5.2 et §11 (le volet inscriptions, rôles, invitations et modération est au lot P15-24) : permission `admin_mobile.access` (seeder + migration de données), capacités de jeton, middleware `EnsureAdminMobile`, groupe 3 dans `routes/api.php` (mise à jour du test ADR-31), limiteur `admin_api` (par compte), `Cache-Control: no-store`, journal d'audit (`source = mobile`), `POST admin/step-up`, contrôleurs `Api\V1\Admin\*` sur les cas d'usage existants, Resources sans récit complet dans les listes, `docs/API.md` §23, mise à jour de `ROLE_ET_PERMISSIONS.md` §3-4 |
|---|---|
| Acceptation | admin sans `admin_mobile.access` → 403 sur chaque route ; modérateur, pair, membre → 403 ; jeton sans capacité → 403 ; maintenance : pas d'activation par un compte qui s'exclurait (garde-fou P13-06) ; chaque action écrit une ligne d'audit ; position **arrondie** pour les admins (décision 5) |
| Tests | un test « autorisé » et un test « refusé (403) » par rôle et par route (ROLE §6) ; test de non-fuite (liste sans récit, contact ni position) ; test du limiteur ; test du garde-fou maintenance ; Pest/PHPUnit `tests/Feature/Api/Admin/*` |

**P15-17 — Admin 1 : astreinte et alertes** (8 M + 1 S)

| Contenu | Tableau d'astreinte (A1 : bandeau SOS actifs, KPI, compte à rebours d'escalade, maintenance en cours) ; liste filtrée (A2) ; détail (A3) avec personnes prévenues (dont les admins et pairs destinataires du push), historique, position arrondie (~1 km), « Ouvrir dans Maps » précédé de l'avertissement ; accuser réception, prendre en charge, changer le statut, réattribuer (annuaire de la zone d'abord), note interne ; polling 10 s au premier plan ; rien n'est persisté |
|---|---|
| Acceptation | une alerte SOS fictive apparaît en moins de 15 s (polling) ou à la réception du push ; accuser réception stoppe l'escalade ; hors ligne : bandeau + actions désactivées + numéro à appeler ; chaque action est visible dans le journal d'audit web |
| Tests | widget avec faux Dio ; scénario serveur de bout en bout (SOS → admin accuse → escalade arrêtée) ; test de non-persistance (aucune boîte Hive créée) |

**P15-18 — Admin 2 : inscriptions à valider, maintenance, notifications** (6 M + 1 S)

| Contenu | Inscriptions à valider (A4 : pairs invités, demandes de rôle, membres à vérifier ; filtres, fiche minimale, valider / refuser avec motif) ; menu « Gérer » (A11) ; maintenance (A5 : état, message, confirmation forte « MAINTENANCE » + `step-up`, désactivation) ; notifications (A6) ; hors ligne (A7) |
|---|---|
| Acceptation | pair validé → se connecte sans bandeau (CHECKLIST ADM-05) et reçoit le rôle pair en plus du rôle membre ; maintenance activée depuis le mobile bloque la connexion des non-admins mais pas le SOS ni le signalement (RG-14) ; refus sans `step-up` valide |
| Tests | Feature serveur (RG-03, RG-14) ; widget de la confirmation forte ; test d'expiration du `step-up` |

**P15-19 — Push SOS et alertes (admins ET pairs)** (4 M + 3 S)

| Contenu | Canal Android « Astreinte » ; types `alert_routed`, `duty_sos`, `peer_pending`, `content_reported`, `sync`, `rules` ; ouverture profonde avec changement d'espace après PIN ; textes neutres si aperçus masqués ; serveur : **le push SOS et alertes va aux admins ET aux pairs éducateurs** (décision 6 ; admins de garde + pairs de la zone, escalade nationale à 5 min, D-MOB-10 tranché) dans la requête SOS (D7), reprise par `notifications:retry` ; iOS : APNs (humain : compte Apple) |
|---|---|
| Acceptation | notification reçue app fermée ouvre l'écran cible ; charge `{type, id}` seulement (test) ; un admin non de garde n'est pas réveillé la nuit par un signalement non critique ; SOS : push reçu < 30 s sur 4G (mesure de recette) |
| Tests | test de charge utile (aucun champ sensible) ; test de routage des types ; recette sur appareil réel (MOB-17) |

**P15-20 — Règles de validation et référentiels** (3 M + 2 S)

**Statut : en grande partie fait le 2026-10-29** (tests dans `sync_test.dart` et `pair_collect_test.dart`). Réalisé : règles actives et listes fermées lues de `GET pair/validation-rules` (ETag, copie chiffrée, 304 et hors ligne servis par la copie), relues au passage de synchro et quand `heartbeat` annonce un `rules_updated_at` différent ; **collecte bloquée tant qu'aucune version des règles n'est sur l'appareil**, date de la version affichée ; référentiels (départements, communes, types de violence) en cache avec ETag ; état « À corriger » après rejet. **Reste** : réaction aux push `rules` et `sync` (lot P15-19), rafraîchissement aux autres moments du §5.7.

| Contenu | `RulesRepository` (ETag, version, date), rafraîchissement aux moments du §5.7 ; push `rules` envoyé quand l'administration modifie une règle ; référentiels en cache avec ETag ; état « À corriger » après rejet |
|---|---|
| Acceptation | règle activée côté admin → le pair la voit après son prochain contact ; collecte bloquée tant qu'aucune version des règles n'existe ; date des règles affichée |
| Tests | unitaires du cache et de l'ETag ; Feature serveur (envoi du push à la modification d'une règle, verrou `required`) |

**P15-21 — Intégration et recette automatisée** (6 M)

| Contenu | Premier jeu d'`integration_test` (P15-01 reste) : rejeu SOS après redémarrage, collecte hors ligne → sync sans doublon, conflit 409 visible, garde de rôle, effacement à distance, verrouillage PIN ; exécution en CI sur émulateur contre un serveur de test |
|---|---|
| Acceptation | les 6 scénarios passent en CI ; temps de CI < 25 min |
| Tests | ce lot est le test ; critère de sortie du jalon C |

**P15-22 — Livraison** (4 M + 1 S)

| Contenu | Clé de signature (hors dépôt), AAB, `--obfuscate --split-debug-info` (symboles conservés hors dépôt), budget de taille, `google-services.json` (compte Firebase), fiche Play : comptes de test par rôle, Data safety, permissions, texte des espaces ; iOS : `GoogleService-Info.plist`, APNs, icône alternative ; interrupteurs serveur par espace |
|---|---|
| Acceptation | AAB installable ; piste interne validée ; Data safety cohérent avec les données traitées ; quatre profils de test (visiteur, membre, pair, admin) ouvrent leur espace |
| Tests | installation propre + mise à jour sur Android 8, 10, 14 ; revue manuelle des permissions déclarées |

**P15-24 — Admin 3 : rôles et invitations, recherche de membre, modération** (8 M + 6 S)

| Contenu | Mobile : « Rôles et invitations » (A8 : créer une invitation de pair par lien ou code avec département et durée, copier / partager, révoquer, invitations en cours ; recherche de membre ; donner ou retirer le rôle pair avec confirmation) ; « Modération » (A9, A10 : contenus signalés à traiter / traités, décision masquer, conserver ou avertir) ; inscription par lien d'invitation (`/signup/:invitation`, mêmes écrans que l'inscription publique) ; demande du rôle pair depuis le profil. Serveur : routes `admin/invitations`, `admin/members`, `admin/members/{id}/peer-role`, `admin/moderation/reports` (§11.2), invitations à usage unique et durée limitée, journal d'audit, limiteur `admin_api` |
|---|---|
| Acceptation | une invitation créée sur mobile permet une inscription avec les mêmes écrans ; la personne arrive dans « Inscriptions à valider » (web et mobile), puis, validée, voit Membre + Pair ; retirer le rôle pair ferme l'espace pair à la prochaine relecture de `GET me` et conserve le compte membre ; le rôle admin n'est attribuable ni depuis le mobile ni par invitation ; recherche limitée au pseudonyme et au département (aucune donnée d'identité) ; une décision de modération est écrite au journal d'audit ; les messages privés ne sont jamais proposés à la modération mobile |
| Tests | un test « autorisé » et un test « refusé (403) » par rôle et par route ; test d'usage unique et d'expiration de l'invitation ; test de non-fuite (recherche de membre) ; widget des écrans A8 à A10 |

**P15-23 — Finalisation de l'espace membre** (6 M)

| Contenu | Reste de P15-01 à P15-08 : `sync/pull` des collections urgences / thématiques / annuaire, téléchargement signé hors ligne vérifié, remplacement des routes `/soon/:page` par les écrans ou des liens profonds vers le web, recette sur appareil (MOB-02 à MOB-09), iOS (push, icône), fréquence de newsletter (Q70) |
|---|---|
| Acceptation | numéros d'urgence et thématiques disponibles hors ligne après un premier `pull` ; plus aucune tuile « bientôt » sans décision explicite ; MOB-01 à MOB-09 cochés |
| Tests | tests existants + `pull` membre ; recette humaine |

## 10. Décisions du client (29/10/2026) et points restant à valider

**Tranché 2026-10-29** : le client a rendu les décisions suivantes (D-MOB-1 à D-MOB-5, D-MOB-10, D-MOB-11, D-MOB-12, D-MOB-14). Le client a ensuite répondu « ALLONS Y » aux six arbitrages D-MOB-4, 10, 15, 16, 17, 18 : **recommandation de chaque ligne retenue**, appliquée dans le serveur (voir `API.md` §23, `ROLE_ET_PERMISSIONS.md`). Les décisions D-MOB-6 à D-MOB-9 et D-MOB-13, que le client n'a pas évoquées, gardent **la recommandation du plan, retenue par défaut**, à confirmer au passage des maquettes. Le plan reste la source ; `FEUILLE_DE_DECISION.md` (§E) et `DECISIONS_A_VALIDER.md` (§4) reprennent l'état des décisions.

| Réf. | Décision | Statut / décision du client | Effet sur le plan |
|---|---|---|---|
| D-MOB-1 | **Une seule application** (option A du §8.1), un seul login ; inscription publique = membres seulement ; rôle pair ajouté sur invitation ou attribution (membre ET pair) ; l'admin est aussi membre ; l'admin crée un·e pair par invitation et valide inscriptions et demandes depuis le web ou le mobile | **Tranché 2026-10-29** (option A) ; l'option C reste le plan B si la revue Play l'exige | §6 (modèle de rôles), P15-10, P15-24 |
| D-MOB-2 | Espace pair dans la v1 (`FEUILLE_DE_DECISION.md` Q38 recommandait l'inverse : **recommandation corrigée**) | **Tranché 2026-10-29** : espace pair dans la v1 | jalon A maintenu (P15-12 à P15-15, P15-20) |
| D-MOB-3 | Périmètre admin mobile | **Tranché 2026-10-29, élargi** : alertes, inscriptions et pairs, rôles et invitations, maintenance, notifications, modération essentielle (contenus signalés), recherche de membre, astreinte | + P15-24 (8 M + 6 S), P15-18 +1 M ; total 115 j.p. |
| D-MOB-4 | Position du SOS affichée aux admins | **Tranché 2026-10-29** : **arrondie** (~1 km). L'option « exacte sur demande journalisée » est abandonnée. **Tranché 2026-10-29 (recommandation retenue)** : **jamais exacte pour l'admin, même en intervention** ; vérifié par test (aucune route `admin/*` de position, aucune réponse ne porte les coordonnées exactes, `AdminMobileApiTest`) | route `admin/alerts/{ulid}/position` retirée (§11.2) |
| D-MOB-5 | Conflit 409 | **Tranché 2026-10-29** : **le pair tranche lui-même** (valeur du serveur ou mon appareil) | endpoint de résolution obligatoire ; P15-15 +1 M ; règle métier à écrire |
| D-MOB-6 | PIN obligatoire pour pair et admin ; verrouillage 60 s / 30 s | recommandation retenue par défaut | P15-11 |
| D-MOB-7 | Expiration des données pair hors contact : 14 jours | recommandation retenue par défaut, à adapter au terrain | P15-11 |
| D-MOB-8 | Mode discret activé par défaut pour pair et admin | recommandation retenue par défaut | P15-11 |
| D-MOB-9 | Durée de vie du jeton admin (7 jours glissants) et `step-up` pour maintenance / refus d'inscription | recommandation retenue par défaut | P15-16 |
| D-MOB-10 | Qui reçoit le push SOS et alertes | **Tranché 2026-10-29** : **les admins ET les pairs éducateurs**. **Tranché 2026-10-29 (recommandation retenue)** : à la création, **admins DE GARDE + pairs DE LA ZONE** de l'alerte (`xomiton.alerts.peer_recipients = zone`, repli sur tous les pairs si la zone n'en a pas) ; **escalade nationale à 5 minutes sans accusé de réception** (tous les admins et tous les pairs, en plus du point focal national ; durée `alerts.escalation.sos.escalate_after_minutes` / `XOMITON_ESCALATION_MINUTES`) ; l'accusé de n'importe quel destinataire arrête l'escalade (`alerts:escalate`) | P15-19 |
| D-MOB-11 | Mise en ligne | **Tranché 2026-10-29** : **une seule version Play** | §8.3, §9.1 : jalons A et B internes, release unique |
| D-MOB-12 | Maquettes (`index.html`, `espace-pair.html`, `admin-mobile.html`), droits d'image du splash | **Tranché 2026-10-29** : les **maquettes sont validées par le client avant tout code mobile** ; droits d'image du splash : licence confirmée le 28/10 (`design-system/DECISIONS.md`) | aucun lot mobile ne démarre avant la validation (MOB-10) |
| D-MOB-13 | Nom / icône « Notes » du mode discret au regard des règles Play | recommandation retenue par défaut (maintenir, expliquer) | P15-11, P15-22 |
| D-MOB-14 | Fiche Play | **Tranché 2026-10-29** : âge minimal 15 ans, **pas de tarif**, **tous les pays**, tranches d'âge Play « 16-17 » et « 18+ » seulement, **français uniquement** | P15-22 ; `docs/playstore/` (non modifié ici) à aligner par son responsable |
| D-MOB-15 | Un membre peut-il **demander** le rôle pair depuis son profil (en plus de l'invitation et de l'attribution) ? Les maquettes le proposent | **Tranché 2026-10-29 (recommandation retenue)** : **oui**, un membre peut **demander** le rôle pair (motif court facultatif, une demande en cours à la fois, jamais d'auto-attribution) ; l'admin approuve ou refuse avec motif depuis la file web (page Inscriptions) ou l'appli, avec `step-up` | `POST me/role-requests`, carte « Mes espaces » |
| D-MOB-16 | Le rôle **admin** s'attribue-t-il seulement depuis le site web ? Les maquettes l'affichent ainsi | **Tranché 2026-10-29 (recommandation retenue)** : **oui**, le rôle admin (comme modérateur·rice et super-admin) ne s'attribue **que depuis le web, par un·e super-admin** ; l'API mobile répond 403 (rôle ou invitation d'administration), vérifié par test | P15-24 |
| D-MOB-17 | Faut-il valider **toutes** les inscriptions de membres ou seulement les cas signalés (e-mail jetable, etc.) ? Les maquettes montrent des cas signalés ; l'inscription membre actuelle est immédiate après le code e-mail | **Tranché 2026-10-29 (recommandation retenue)** : **seules les inscriptions SIGNALÉES** attendent la validation (e-mail jetable, inscriptions répétées avec la même empreinte en peu de temps) ; le reste est immédiat après confirmation de l'e-mail ; `reg_manual_validation = all` (Q91) reste l'interrupteur « tout valider » | A4, P15-18 |
| D-MOB-18 | Cohérence âge : l'application exige 15 ans, la fiche Play déclare « 16-17 » et « 18+ » | **Tranché 2026-10-29 (recommandation retenue)** : **conservé : application 15 ans, tranches Play 16-17 et 18+, à valider par le conseil juridique avant publication** ; l'application affiche la règle des 15 ans à l'inscription (une personne de 15 ans est dans l'application mais hors des tranches déclarées) | fiche Play, Data safety |

## 11. API : existant et à créer

### 11.1 Espace pair (existant, à brancher)

| Route | État | Lot |
|---|---|---|
| `GET dashboard` (variante pair), `GET me`, `POST me/devices`, `POST/DELETE me/push-subscriptions`, `GET app/version` | existant | P15-10, P15-13 |
| `GET pair/alerts`, `GET pair/alerts/{ulid}`, `POST …/acknowledge`, `POST …/status` | existant | P15-13 |
| `GET/POST pair/cases`, `GET/PATCH pair/cases/{code}`, `GET/POST/PATCH …/followups` | existant | P15-14, P15-15 |
| `GET pair/validation-rules` (ETag) | existant | P15-20 |
| `POST sync/push`, `GET sync/pull`, `GET sync/conflicts`, `POST heartbeat` | existant | P15-12 |
| `heartbeat` : champs `wipe`, `rules_updated_at` | **livré côté serveur** (`API.md` §21.6) ; consommé par le mobile depuis P15-12 | P15-11, P15-12 |
| `POST me/devices/{uuid}/wiped` et action « Effacer à distance » | **livré côté serveur** (`API.md` §3) ; accusé envoyé par le mobile depuis P15-12 | P15-11 |
| `POST sync/conflicts/{id}/resolve` `{resolution: serveur ou appareil}` | **livré côté serveur** (`API.md` §21.5) ; consommé par l'écran de conflit depuis P15-15 | P15-15 |
| `on_duty` (le pair est-il de garde ?) dans `GET dashboard` (section `peer`) ou `meta` de `GET pair/alerts` | **manquant** : le mobile lit `peer.on_duty` / `meta.on_duty` s'ils existent, sinon relit toutes les 30 s et n'affiche pas le texte de garde | P15-13 |
| `GET pair/collect-options` (`CollectOptions::form()` : populations, et si utile natures et sexes) | **manquant** : aucune route ne sert la liste des populations ; la collecte la lit de cette route et se bloque sans elle | P15-15 |
| Fiche de référence du dossier (services reçus, consentement, lieu précis : `CaseSheet`) dans `GET pair/cases/{code}` | **manquant** : sections 3 et 5 de la fiche vides côté mobile | P15-14 |
| `GET me` : liste `roles[]` cumulée ; `POST auth/register` avec code d'invitation ; `POST me/role-requests` (demande du rôle pair, D-MOB-15) | **livré côté serveur** (`me/role-requests` : `API.md` §23.3 bis ; `GET me` et `auth/register` : voir `API.md`) | P15-10, P15-24 |
| Push `rules`, `sync`, `alert_routed` | **à créer** (types de notification) | P15-19, P15-20 |

### 11.2 Espace admin d'astreinte (tout est à créer, sauf l'authentification)

Cas d'usage serveur déjà présents (à réutiliser tels quels) : `AlertDesk`, `AcknowledgeAlert`, `ChangeAlertStatus`, `ReassignAlert`, `AddInternalNote`, `OnCallRoster`, `EscalateAlerts`, `DashboardStats`, `ValidatePeerAccount` / `ReviewRegistration`, `EnableMaintenance`, `DisableMaintenance`, `MaintenanceOverview`.

| Route proposée (groupe 3, `auth:sanctum` + `ability:admin-mobile` + `can:admin_mobile.access`) | Rôle | Cas d'usage réutilisé | Permission fine | État |
|---|---|---|---|---|
| `POST auth/login`, `POST auth/otp/verify`, `POST auth/logout`, `GET me` | connexion avec OTP imposé aux admin | `AuthenticateUser` | – | **existant** |
| `POST me/devices`, `POST me/push-subscriptions`, `GET app/version`, `GET status` | appareil, push, mise à jour minimale, état public | – | – | **existant** |
| `GET notifications` | cloche (types admin à vérifier) | notifications | – | existant, **à vérifier** pour les types admin |
| `POST admin/step-up` | confirmation récente (mot de passe ou OTP) → jeton court | à écrire (mot de passe ou OTP existants) | `admin_mobile.access` | **à créer** |
| `GET admin/duty` | tableau d'astreinte : compteurs, SOS actifs, escalade, de garde, maintenance | `DashboardStats`, `AlertDesk`, `OnCallRoster`, `MaintenanceOverview` | `alerts.view` | **à créer** |
| `GET admin/alerts?type=&status=&q=&page=` | liste (résumés, sans récit complet) | `AlertDesk` | `alerts.view` | **à créer** |
| `GET admin/alerts/{ulid}` | détail : catégorie, besoins, personnes prévenues, historique, position arrondie, contact (existence seulement) | `AlertDesk` | `alerts.view` | **à créer** |
| ~~`GET admin/alerts/{ulid}/position`~~ | **abandonnée** : position arrondie seulement (D-MOB-4, décision 5) | – | – | – |
| `POST admin/alerts/{ulid}/acknowledge` | accuser réception, arrêter l'escalade | `AcknowledgeAlert` | `alerts.take` | **à créer** |
| `POST admin/alerts/{ulid}/status` | `en_cours`, `traite`, `clos` | `ChangeAlertStatus` | `alerts.take` / `alerts.close` | **à créer** |
| `GET admin/directory/contacts?zone=` | candidats à la réattribution | requête d'annuaire | `alerts.manage` | **à créer** |
| `POST admin/alerts/{ulid}/reassign` | réattribuer à une fiche d'annuaire | `ReassignAlert` | `alerts.manage` + `step-up` | **à créer** |
| `POST admin/alerts/{ulid}/notes` | note interne | `AddInternalNote` | `alerts.manage` | **à créer** |
| `GET admin/registrations?kind=` (`invite`, `demande`, `membre`) | inscriptions et demandes à valider (pairs invités, demandes de rôle, membres à vérifier) | `ReviewRegistration` | `users.validate_peers` | **à créer** |
| `POST admin/registrations/{id}/approve` · `POST admin/registrations/{id}/reject` | valider / refuser (RG-03) ; valider une demande ou une invitation de pair ajoute le rôle `pair` | `ValidatePeerAccount` | `users.validate_peers` + `step-up` pour le refus | **à créer** |
| `POST admin/invitations` · `GET admin/invitations` · `DELETE admin/invitations/{code}` | inviter un·e pair par lien ou code (département, durée), lister, révoquer ; usage unique | cas d'usage d'invitation (serveur, en cours) | `users.validate_peers` (à confirmer) | **à créer** |
| `GET admin/members?q=` | recherche de membre (pseudonyme, département ; aucune donnée d'identité) | requête de membres | `users.validate_peers` (à confirmer) | **à créer** |
| `POST admin/members/{id}/peer-role` · `DELETE admin/members/{id}/peer-role` | donner / retirer le rôle pair (le rôle admin n'est pas proposé, D-MOB-16) | gestion des rôles (serveur, en cours) | `users.validate_peers` + `step-up` | **à créer** |
| `GET admin/moderation/reports?state=` (`todo`, `done`) · `GET admin/moderation/reports/{id}` | contenus signalés (publications, commentaires ; jamais de messages privés) | modération existante | `moderation.*` (à confirmer) | **à créer** |
| `POST admin/moderation/reports/{id}/decision` `{action: hide, keep ou warn}` | masquer, conserver, avertir l'auteur·rice | modération existante | `moderation.*` + journal d'audit | **à créer** |
| `GET admin/maintenance` | état, message, fenêtre planifiée | `MaintenanceOverview` | `platform.maintenance` | **à créer** |
| `POST admin/maintenance/enable` · `POST admin/maintenance/disable` | activer (mot de confirmation, garde-fou anti-verrouillage) / désactiver | `EnableMaintenance`, `DisableMaintenance` | `platform.maintenance` + `step-up` | **à créer** |

Règles communes : réponses `Cache-Control: no-store, private` ; enveloppe `{data, meta, links}` et codes d'erreur de l'API §1.2 ; aucune donnée d'identité de l'autrice ou de l'auteur d'une alerte anonyme (RG-01, RG-02) ; recherche `q` limitée au numéro et au code de suivi ; limiteur nommé `admin_api` (ADR-31 : le test existant refuse toute route sans limiteur) ; journal d'audit pour toute écriture.

## 12. Parcours de recette et critères de sortie

### 12.1 Parcours à ajouter à `CHECKLIST_RECETTE.md` (§10 mobile, ids MOB-14 et suivants)

| Id | Parcours | Résultat attendu |
|---|---|---|
| MOB-14 | Connexion avec `membre@`, `pair@`, `admin@xomiton.bj` (appareil neuf), **même écran de connexion pour tous** | `membre@` arrive au tableau de bord ; `pair@` et `admin@` voient le **sélecteur d'espace** (Membre + Pair, Membre + Admin) puis peuvent changer d'espace par l'en-tête, le profil ou l'onglet « Espace pair » / « Admin » ; `pair.attente@` voit le bandeau, pas d'espace Pair |
| MOB-15 | Espace pair : tenter une capture d'écran sur `/pair/dossiers`, sur le fil, sur un écran admin | impossible sur pair et admin, possible sur le fil ; aperçu masqué dans le sélecteur d'applications |
| MOB-16 | Activer le PIN, quitter l'application 2 min, revenir ; saisir 5 PIN faux dans l'espace pair | PIN demandé ; au 5e échec, session fermée, données pair absentes à la reconnexion ; SOS accessible sans PIN |
| MOB-17 | SOS déclenché depuis un compte membre : les **admins ET les pairs** destinataires reçoivent le push (app fermée) | notification < 30 s ; ouverture après PIN sur l'alerte ; charge sans contenu sensible |
| MOB-18 | Pair : mode avion → collecte d'un dossier → file → réseau | dossier créé, code `VBG-…`, aucun doublon après relance manuelle |
| MOB-19 | Deux appareils modifient le même champ d'un dossier hors ligne | conflit visible sur la file ; **le pair choisit** « valeur du serveur » ou « mon appareil » ; la valeur retenue est appliquée tout de suite ; l'historique garde les deux versions et le choix |
| MOB-20 | Admin désactive une règle de validation ; le pair rouvre la collecte | règle appliquée après le contact suivant ; date des règles visible |
| MOB-21 | Admin mobile : accuser réception d'un SOS, changer le statut, réattribuer, ajouter une note | escalade arrêtée ; actions dans le journal d'audit web ; **position arrondie** ; avertissement avant Maps |
| MOB-22 | Admin mobile : valider une inscription de pair ; activer puis couper la maintenance | pair connectable sans bandeau, membre ET pair ; maintenance : SOS et signalement restent possibles ; confirmation forte exigée |
| MOB-23 | « Effacer à distance » depuis les appareils connectés (web) ; ouvrir l'application | boîtes vidées, retour à la connexion ; l'appareil figure comme effacé |
| MOB-24 | Retirer `admin_mobile.access` au compte admin pendant qu'il est connecté | prochaine requête 403, espace Admin retiré, aucune donnée résiduelle |
| MOB-25 | Réseau 2G simulé (latence 1,5 s, 5 % de perte) : liste d'alertes, collecte, synchro | écrans utilisables, aucune perte, aucun doublon |
| MOB-26 | Inscription publique, puis inscription par lien d'invitation de pair | l'inscription publique ne propose que « membre » ; l'invitation suit les mêmes écrans, est à usage unique et expire ; le compte arrive dans « Inscriptions à valider » |
| MOB-27 | Admin mobile : inviter un·e pair, donner puis retirer le rôle pair à un membre trouvé par recherche, traiter un contenu signalé | rôle ajouté ou retiré à la prochaine relecture de `GET me`, compte membre conservé ; décision de modération au journal d'audit ; le rôle admin n'est pas proposé |

### 12.2 Critères de sortie (jalon C)

1. `flutter analyze` et `flutter test` verts ; les 6 scénarios d'intégration (P15-21) verts en CI.
2. Tests serveur « autorisé / refusé (403) » pour **chaque** route `pair/*` et `admin/*` et chaque rôle (ROLE §6) ; un test prouve qu'un pair ne voit pas le dossier d'un autre pair.
3. MOB-01 à MOB-27 cochés sur appareil réel (au moins un Android 8 d'entrée de gamme et un Android récent) ; iOS : parcours MOB-14, 16, 17 si le compte Apple est disponible, sinon limite consignée.
4. Aucun contenu sensible dans les notifications, les journaux ni les captures ; `FLAG_SECURE` vérifié sur tous les écrans pair / admin.
5. Taille de l'AAB mesurée et consignée, sous le budget fixé en P15-10.
6. Fiche Play prête : comptes de test par rôle, Data safety à jour, permissions justifiées.
7. Décisions D-MOB-1 à D-MOB-18 tranchées le 29/10/2026 (D-MOB-4, 10, 15 à 18 : recommandation retenue) et consignées dans `DECISIONS_A_VALIDER.md`.
8. **Maquettes (simulateur, espace pair, admin) validées par le client (MOB-10) avant tout code mobile**, donc avant P15-10.

## 13. Risques et points à revisiter

| Risque | Probabilité / impact | Parade |
|---|---|---|
| L'API admin mobile n'existe pas : P15-16 conditionne tout l'espace admin | haute / haute | démarrer P15-16 en parallèle du socle ; livrer d'abord `duty`, `alerts`, `acknowledge` |
| Écart entre maquettes et API (conflit côté pair, `ack` vs `acknowledge`, position) | moyenne / moyenne | décisions D-MOB-4 et D-MOB-5 avant P15-15 et P15-17 ; maquettes ajustées |
| Fiabilité du push sur Android d'entrée de gamme (économiseurs d'énergie constructeurs) | haute / haute | polling au premier plan, rappel dans les réglages (dispense d'optimisation), escalade serveur indépendante |
| cPanel : polling de plusieurs admins et pairs de garde (Entry Processes) | moyenne / moyenne | cadences bornées, ETag, mesure en recette, plan VPS (D7) |
| Retard des notifications non-SOS (cron à la minute) | certaine / faible | documenté ; seul le SOS part dans la requête |
| Revue Play : fonctions par rôle, données sensibles, mode discret | moyenne / haute | comptes de test, description, piste fermée, option C |
| Fuite de données sur appareil perdu | moyenne / haute | PIN imposé, chiffrement, expiration, effacement à distance, jeton court |
| `FLAG_SECURE` gêne la recette et le support | certaine / faible | build de recette autorisé, jamais en `release` |
| Compte Firebase / Apple non disponibles | haute / moyenne | `NoopPushService` (l'application fonctionne sans push) ; iOS décalé |
| Dérive de périmètre admin : le périmètre a été **élargi le 29/10/2026** (inscriptions, rôles et invitations, modération essentielle, recherche de membre, +14 j.p.) ; risque de glisser vers le web complet (utilisateurs, sauvegardes, suspension de compte) | moyenne / moyenne | périmètre figé par D-MOB-3 élargi ; tout ajout = nouveau lot ; rôle admin et suspension réservés au web |
| Élévation de privilège par l'attribution de rôles depuis un mobile | faible / haute | rôle admin non attribuable depuis le mobile (D-MOB-16), `step-up`, journal d'audit, test 403 par rôle |
| Décision du pair sur un conflit 409 : erreur humaine ou contestation | moyenne / moyenne | explication simple, confirmation, historique des deux versions, journal d'audit |
| Conflits non résolus ou règles périmées | moyenne / moyenne | état « À corriger » explicite, jamais de perte silencieuse |
| Droits d'image du splash (non levés) | moyenne / moyenne | visuel neutre de repli (SPEC_PROTOTYPE_MOBILE_V2 §6) |
| Estimations : aucun lot n'a été chiffré avec un historique de vitesse sur ces modules | moyenne / moyenne | marge de 20 %, revue après P15-12 |

À revisiter : (1) option C après la première revue Play ; (2) WorkManager si les pairs n'ouvrent pas l'application ; (3) bilan des conflits tranchés par les pairs après la bêta ; (4) extension de la modération mobile (suspension de compte) ; (5) APNs « Critical Alerts » ; (6) cadence de polling après mesure sur cPanel ; (7) durées d'expiration (jeton, données locales) après retour du terrain ; (8) `ETAT_DES_LIEUX.md` (non modifié ici) et `docs/playstore/` à aligner par leurs responsables sur les décisions du 29/10/2026 (FEUILLE_DE_DECISION et DECISIONS_A_VALIDER sont alignés).
