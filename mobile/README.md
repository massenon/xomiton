# Xomiton — application mobile (Flutter)

SDK : Flutter stable (Dart ^3.13). `flutter pub get`, `flutter analyze`, `flutter test`.

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
  L'ordre d'effacement à distance (`device.wipe`, `POST me/devices/{uuid}/wiped`)
  attend le volet serveur de P15-11. Un téléphone éteint ou hors réseau ne le
  reçoit pas : l'expiration locale est le filet de sécurité.
- **Mode discret** activé par défaut au premier accès à l'espace Pair ou Admin
  (la personne est prévenue et peut le désactiver).
