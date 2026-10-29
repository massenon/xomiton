# Xomiton — application mobile (Flutter)

SDK : Flutter stable (Dart ^3.13). `flutter pub get`, `flutter analyze`, `flutter test`
(454 tests, dont `test/integration/` : scénarios de bout en bout contre une API simulée).

Build Android depuis Windows (SDK, émulateur ou téléphone, API locale `10.0.2.2`, APK debug, clé de
signature, AAB, Firebase, dépannage) : [`docs/BUILD_ANDROID_WINDOWS.md`](../docs/BUILD_ANDROID_WINDOWS.md).

## Notifications push (P15-08) : mise en place de Firebase

Le dépôt ne contient AUCUN secret Firebase. Sans configuration, l'application
démarre avec `NoopPushService` : aucune autorisation n'est demandée, rien n'est
enregistré, tout le reste fonctionne. Avec la configuration, `FcmPushService`
est choisi automatiquement au démarrage (`createPushService()` dans
`lib/core/push/fcm_push_service.dart`) : si `Firebase.initializeApp()` échoue, on
retombe sur Noop.

### Étapes

1. Console Firebase : créer le projet, ajouter une application Android
   `bj.xomiton.xomiton` (et iOS si besoin).
2. Télécharger `google-services.json` et le placer dans
   `mobile/android/app/google-services.json`. Il est ignoré par git
   (`.gitignore`) : ne jamais le committer. Le plugin Gradle `google-services`
   ne s'applique que si le fichier existe (`android/app/build.gradle.kts`).
3. iOS (non livré, reste) : `GoogleService-Info.plist` dans `ios/Runner/` (hors
   dépôt), capacités « Push Notifications » et « Background Modes > Remote
   notifications », clé APNs téléversée dans la console Firebase.
4. Côté serveur (hors `mobile/`) : compte de service FCM pour le canal `fcm`
   des notifications (Q7), `FCM_*` dans l'environnement du serveur — jamais dans
   le dépôt.
5. Tester : connexion, accepter l'autorisation (Android 13+), vérifier
   `GET me/push-subscriptions` (un abonnement `fcm`) et `GET me/devices`.

### Contrat avec le serveur

- Message FCM « données seules », priorité haute : `data = {type, id}` et rien
  d'autre (l'application ignore toute autre clé). Pas de bloc `notification`
  (sinon le système l'affiche avec le texte du serveur, sans respecter le
  masquage des aperçus).
- `type` : `message`, `sos`, `alert`, `event`, `group`, `post`, ou autre
  (ouvre `/notifications`). `id` : `[A-Za-z0-9_-]{1,64}`.
- Le texte est choisi par l'application : neutre (« Notification ») quand les
  aperçus sont masqués (valeur par défaut tant que `me/security` n'a pas été lue).
- Canaux Android : `sos_alerts` « SOS et alertes » (importance maximale,
  sonore même si « Sons » est coupé), `community` « Communauté », `comm_silent`
  « Communauté (sans son) » (utilisé quand « Sons » est coupé : le son d'un
  canal Android ne se change plus après sa création).

## Mode discret

Android : deux `activity-alias` (`.LauncherDefault` « Xomiton » et
`.LauncherDiscreet` « Notes » avec l'icône `ic_notes`), basculés par
`MainActivity` (canal `bj.xomiton/discreet`). Le lanceur peut mettre quelques
secondes à se mettre à jour. iOS : le nom ne peut pas changer à l'exécution et
l'icône alternative n'est pas livrée (reste) ; l'écran le dit honnêtement.

## Code PIN

4 chiffres, local à l'appareil (`flutter_secure_storage`), PBKDF2-HMAC-SHA256 avec
sel, comparaison à temps constant, 5 essais (compteur persistant) puis session
fermée et reconnexion mot de passe + OTP. Seul `pin_enabled` est envoyé
(`PATCH me/security`). SOS, urgences et signalement anonyme restent
accessibles sans code.

## Un seul compte, trois espaces (P15-10, P15-11)

`GET me` déclare `roles[]` (cumulés) et `spaces[]` (`member`, `peer`, `admin`) : le
client ne fait qu'**afficher** ; chaque route `pair/*` et `admin/*` est
revérifiée par le serveur (un 403 `forbidden` relit `GET me` et ferme l'espace).
Sans `spaces`, le compte n'a que l'espace Membre. `--dart-define=SPACES=member,pair,admin`
(défaut : les trois) limite les espaces d'un build (variante « membre seul »).

Sécurité renforcée des espaces Pair et Admin :

- **Code PIN obligatoire** pour y entrer (écran `/pin-setup` au premier accès,
  impossible à désactiver ensuite) ; verrou après 60 s en Pair, 30 s en Admin ;
  SOS, urgences et signalement restent sans code.
- **Captures d'écran interdites** (`FLAG_SECURE`, canal `xomiton/secure`) sur
  tout `pair/**` et `admin/**`, et côté Membre sur messages, signalement, suivi,
  SOS et sécurité. Le fil reste capturable. Build de recette :
  `--dart-define=ALLOW_SCREENSHOTS=true`, **ignoré en `release`**. **iOS** n'a pas
  d'équivalent : un recouvrement opaque masque l'écran dès que l'application
  quitte le premier plan (limite connue, à vérifier sur iPhone, MOB-15).
- **Effacement** : déconnexion, 401, 5 codes faux, 403 `device_revoked` (tout est
  purgé, un nouvel identifiant d'appareil sera créé) et plus de 14 jours sans
  contact avec le serveur (données Pair seulement, horloge reculée = expirée).
  L'ordre d'effacement à distance (`wipe` du signal de vie, accusé
  `POST me/devices/{uuid}/wiped`) vide le coffre du pair, ferme la session et
  prévient la personne. Un téléphone éteint ou hors réseau ne le reçoit pas :
  l'expiration locale est le filet de sécurité.
- **Mode discret** activé par défaut au premier accès à l'espace Pair ou Admin
  (la personne est prévenue et peut le désactiver).

## Synchronisation hors ligne (P15-12)

`lib/core/sync/` : `SyncEngine`, `SyncQueue`, `CollectionStore`.

- **Appareil** : `POST me/devices` avant toute synchro (une fois par lancement) ;
  `X-Device-Id` sur chaque requête. 403 `device_required` avec un appareil déjà
  enregistré, ou 403 `device_revoked` : purge totale et retour à la connexion.
- **Envoi** (`sync/push`) : lots de 50 au plus ; `Idempotency-Key` = uuid du lot,
  **posé dans les entrées avant l'envoi** : un rejeu repart avec la même clé et le
  même corps, un nouvel élément ne rejoint jamais un lot déjà posé. Résultat lu
  élément par élément (`created/updated/duplicate` parti, `conflict` à trancher,
  `rejected` « à corriger », 429 et 503 repartent dans un nouveau lot). Les suivis
  et les marques « réalisée » passent par leurs routes dédiées (`client_uuid` =
  uuid de l'entrée). Un suivi d'un dossier encore en file attend son code.
- **Lecture** (`sync/pull`) : curseur, `reset`, `deletes`, `has_more` ; les pages
  sont appliquées en bloc et le curseur est écrit en dernier. Collections du pair :
  `cases`, `case_followups`, `alerts`, `validation_rules` ; communes (coffre
  public, préfixe `sync:`) : `emergency_contacts`, `directory_contacts`,
  `thematiques`.
- **Quand** : à l'ouverture, au retour du réseau, au retour au premier plan, toutes
  les 5 min (signal de vie compris) et sur « Synchroniser maintenant ». Après un
  échec : attente exponentielle avec gigue (5 s à 5 min), une action de la
  personne passe outre. Pas de tâche d'arrière-plan en v1 (plan §8.5).
- **Coffres chiffrés** : coffres Membre (`cache_*`, `session`, `outbox`, `drafts`)
  avec une clé ; coffre du pair (`pair_data`) avec **une clé propre**
  (`hive_encryption_key_peer`). L'effacement du pair supprime la boîte et détruit la
  clé. L'espace Admin ne garde rien. La boîte d'envoi Membre (SOS, signalement,
  message, publication) garde ses routes dédiées : le moteur la vide avant le lot du pair.

## Espace pair (P15-13 à P15-15)

`lib/features/pair/` : accueil (KPI, tâches), alertes routées (liste relue toutes
les 10 s si de garde, 30 s sinon ; accusé, prise en charge, clôture selon `can{…}`
du serveur ; avertissement avant Maps), dossiers (liste, fiche en 5 sections, frise,
suivi, statut), collecte par étapes (règles actives en cache, brouillon sans notes,
avertissements de plausibilité), file de synchronisation et écran de conflit 409
(**le pair tranche**). Les écrans de détail restent dans la barre d'onglets
(SOS et Réseau toujours à portée). Routes : `/pair/accueil`, `/pair/alertes[/:id]`,
`/pair/dossiers[/collecte|/:code[/suivi]]`, `/pair/accueil/synchro[/conflit/:id]`.

Ne pas lancer `dart format` sur tout `lib/` : le formateur « tall » de Dart 3.13
réécrit les fichiers existants (le style du dépôt est antérieur) ; formater
uniquement les fichiers neufs.

Listes de la collecte : `GET pair/collect-options` (copie chiffrée, repli sur
`zones` / `violence-types`). Fiche du dossier : `data.sheet` de
`GET pair/cases/{code}` (cinq sections, `filled`). Garde : `meta.on_duty` de
`GET pair/alerts` et `peer.on_duty` du tableau de bord.

## Espace admin d'astreinte (P15-17, P15-18, P15-24)

`lib/features/admin/` : API `admin/*` (docs/API.md §23), **rien n'est persisté**
(pas de boîte Hive, pas de cache : une lecture est toujours faite au réseau).

- **Écrans** : tableau d'astreinte `/admin/astreinte` (SOS actifs, compte à
  rebours d'escalade, indicateurs, relecture 10 s / 30 s), alertes
  `/admin/alertes[/:id]` (filtres, accusé, prise en charge, statut, note,
  réattribution, clôture, position **arrondie**, avertissement avant Maps),
  « Gérer » `/admin/gerer` → inscriptions `/admin/inscriptions`, rôles et
  invitations `/admin/roles`, modération `/admin/moderation[/:id]`, maintenance
  `/admin/maintenance`, notifications `/admin/notifications`.
- **`step-up`** : les actions à fort impact (réattribution, refus d'une
  inscription ou d'une demande, approbation d'une demande de rôle, invitation et
  révocation, don et retrait du rôle pair, maintenance) redemandent le mot de
  passe (`POST admin/step-up`) ; le jeton obtenu est à usage unique et part en
  `X-Step-Up-Token` ; le mot de passe n'est jamais envoyé à l'action ni gardé.
- **Écritures** : `Idempotency-Key` posée automatiquement sur tout
  `POST/PUT/DELETE admin/*` (hors `step-up`), rejeu avec la même clé.
- **Le rôle admin n'est jamais attribuable depuis le mobile** : `AdminApi` n'a
  aucune méthode qui prenne un nom de rôle (seulement `grantPeerRole` /
  `revokePeerRole`) ; un compte d'équipe n'a aucun bouton de rôle.
- **Hors ligne** : bandeau « appelez directement la personne », boutons
  désactivés, aucune requête d'écriture.
- Messages privés : jamais proposés à la modération (filtrés aussi côté client).

## Push d'équipe (P15-19)

`lib/core/push/` : charge `{type, id}` seulement. Types : `alert_routed`
(→ `/pair/alertes/:id`), `duty_sos` et `duty_alert` (→ `/admin/alertes/:id`),
`peer_pending` (→ `/admin/inscriptions`), `content_reported` (→
`/admin/moderation`), silencieux `sync` (→ cycle de synchronisation) et `rules`
(→ relecture des règles ; reçu application fermée, la demande est notée dans les
préférences et traitée au prochain démarrage). Canal Android **« Astreinte »**
(`duty`, importance maximale) pour l'astreinte, « Communauté » pour le reste.
Ouverture : l'espace est activé puis l'écran empilé, **après le code PIN**, et
seulement si le serveur a déclaré l'espace au compte. Aperçus masqués : titre
« Xomiton », corps neutre (jamais SOS, lieu ni motif). iOS : APNs à configurer
(compte Apple).

## Finalisation de l'espace membre (P15-23)

- **Mode maintenance (RG-14)** : `lib/core/maintenance/` + `features/shell/presentation/maintenance_page.dart`.
  Un 503 `maintenance` (corps : `maintenance_message`, `ends_at`, `overdue`, `timezone`) active l'écran plein
  `/maintenance` ; `GET status` est sondé toutes les 60 s et au bouton « Vérifier maintenant ». Restent
  ouverts : SOS, signalement, suivi, urgences, connexion (l'équipe garde tout l'accès, jamais de jeton
  révoqué, la boîte d'envoi est conservée).
- **Erreurs** : `errorMessage` (`core/ui/xm_widgets.dart`) donne un texte clair par statut (401, 403, 404,
  413, 422, 429, 503 maintenance, réseau) ; le message français du serveur prime quand il existe.
- **Hors ligne** : tout compte connecté tire `sync/pull` (urgences, annuaire public, thématiques) ;
  `GET urgences`, les thématiques et leur fiche se replient sur ces lignes sans réseau ni copie d'API.
- **Newsletter** : `/newsletter` (Paramètres) — abonnement et centres d'intérêt ; pas de fréquence (le schéma
  n'en a pas, `docs/API.md` §3).
- Aucune page d'attente : `/soon/:page` n'existe plus.

## Tests d'intégration (P15-21)

`test/integration/e2e_test.dart` fait tourner l'application réelle (routeur, gardes d'espace, code PIN,
file de synchronisation, push) contre `test/support/sim_server.dart` : un serveur simulé à **état unique**
derrière un adaptateur Dio (coupure réseau, maintenance, droit retiré, effacement, conflit 409 y sont des
interrupteurs). Treize scénarios : connexion membre, pair + membre, admin + membre ; retrait d'un droit ;
collecte et statut hors ligne puis conflit 409 tranché par le pair ; accusé par un pair puis un admin ; push
après le code PIN ; effacement à distance ; expiration de 14 jours ; maintenance. Ce ne sont pas des
`integration_test` sur émulateur (recette sur appareil : `docs/CHECKLIST_RECETTE.md`, MOB-01 à MOB-37).

## Release Android (P15-22)

Configuration prête, **jamais construite à ce jour** (aucun SDK Android dans l'environnement de rédaction).

- `android/app/build.gradle.kts` : `applicationId` `bj.xomiton.xomiton`, `versionName` / `versionCode` lus de
  `pubspec.yaml` (`version: 1.0.0+1` ; garder `kAppVersion` identique), signature par `android/key.properties`
  (**jamais commité**, modèle `android/key.properties.example`, repli sur la clé de debug avec avertissement),
  R8 (`isMinifyEnabled`, `isShrinkResources`, `proguard-rules.pro`).
- Commande de publication : `flutter build appbundle --release --obfuscate --split-debug-info=build/symbols
  --dart-define=API_BASE_URL=https://…/api/v1` (garder `build/symbols` et `mapping.txt` hors dépôt).
- Icônes (classique, adaptative, monochrome, Play 512) et rosace de démarrage : `python3 tool/android_icons.py`
  (Pillow seul) depuis `design-system/logo/icone-appli-bleu.svg`. Écran de démarrage natif clair, sombre et
  Android 12.
- Sécurité : `FLAG_SECURE` (`MainActivity.kt`), HTTPS seul (`res/xml/network_security_config.xml` ;
  `src/debug/res/xml/` autorise `http` vers `10.0.2.2`, `localhost`, `127.0.0.1` en debug seulement), permissions
  minimales (localisation précise pour le SOS, notifications ; le reste retiré explicitement).
- `google-services.json` : attendu dans `android/app/`, **ignoré par git** (voir « Notifications push »).
- CI `.github/workflows/mobile.yml` : analyse, tests, APK debug, APK release arm64 et budget de taille
  (`tool/size_budget.json`, `tool/check_size.py`).
