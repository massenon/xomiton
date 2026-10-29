# Construire et lancer l'application Android depuis Windows (P15-22)

Guide pas à pas pour une personne qui n'a jamais compilé l'application. Il couvre : l'installation de Flutter et d'Android Studio, le lancement sur émulateur ou téléphone, l'APK de test, le paquet de publication (AAB) signé, Firebase et le dépannage.

> **Limite de ce guide.** Il a été écrit et la configuration Android relue sans pouvoir lancer un build Android (l'environnement de rédaction n'a pas de SDK Android). Les étapes de compilation n'y ont donc pas été exécutées de bout en bout : si une commande ou un chemin diffère chez vous, notez-le et corrigez ce document. L'analyse du code et les 454 tests Flutter, eux, sont exécutés (CI : `.github/workflows/mobile.yml`).

Application : identifiant `bj.xomiton.xomiton`, nom affiché « Xomiton », Android 8.0 minimum (`minSdk 26`). Le code est dans `mobile/`. Les versions (`1.0.0+1` dans `mobile/pubspec.yaml`) fixent le `versionName` (1.0.0) et le `versionCode` (1) ; **augmentez le nombre après le `+` à chaque envoi sur Play** (`1.0.0+2`, `+3`…), et mettez `kAppVersion` (`mobile/lib/core/push/push_registrar.dart`) au même numéro de version.

## 1. Ce qu'il faut avoir

| Besoin | Détail |
|---|---|
| Windows 10 ou 11, 64 bits | 16 Go de mémoire conseillés (8 Go minimum), 20 Go libres |
| Virtualisation activée | pour l'émulateur : « Plateforme d'hyperviseur Windows » (Windows Hypervisor Platform) cochée dans « Activer ou désactiver des fonctionnalités Windows », et la virtualisation (VT-x / SVM) active dans le BIOS |
| Un compte Google | Firebase (notifications) et Play Console (publication) |
| Connexion Internet | le premier build télécharge plusieurs Go (Gradle, SDK) ; un téléchargement interrompu se relance |

## 2. Installer les outils

### 2.1 Git

Installer [Git pour Windows](https://git-scm.com/download/win) (options par défaut), puis, dans PowerShell :

```powershell
git config --global core.longpaths true
```

(les chemins longs de Gradle dépassent sinon la limite de Windows).

### 2.2 Flutter

La version suivie par la CI est **Flutter 3.47.8 (canal stable, Dart 3.13)**.

1. Télécharger l'archive Windows de cette version sur <https://docs.flutter.dev/install/archive> et l'extraire dans `C:\src\flutter` (pas dans `Program Files` : droits d'écriture ; pas de chemin avec espace ni accent).
2. Ajouter `C:\src\flutter\bin` à la variable d'environnement **Path** (Paramètres › Système › À propos › Paramètres système avancés › Variables d'environnement › Path de l'utilisateur).
3. Ouvrir un **nouveau** PowerShell et vérifier : `flutter --version`.

### 2.3 Android Studio et SDK Android

1. Installer [Android Studio](https://developer.android.com/studio) (dernière version stable) ; au premier lancement, laisser l'assistant installer le **SDK Android** et l'**émulateur**.
2. Menu **More Actions › SDK Manager** (ou *File › Settings › Languages & Frameworks › Android SDK*) :
   - onglet **SDK Platforms** : cocher **Android 14 (API 34)** ou la plus récente proposée ;
   - onglet **SDK Tools** : cocher **Android SDK Build-Tools**, **Android SDK Command-line Tools (latest)**, **Android SDK Platform-Tools**, **Android Emulator**. Appliquer.
3. Java : Android Studio embarque un JDK 17 ou plus (le projet exige Java 17). Dire à Flutter de l'utiliser :

   ```powershell
   flutter config --android-sdk "C:\Users\VOTRE_NOM\AppData\Local\Android\Sdk"
   flutter config --jdk-dir "C:\Program Files\Android\Android Studio\jbr"
   ```

   (adapter les chemins ; le JDK est dans le dossier `jbr` d'Android Studio).
4. Accepter les licences (répondre `y` à chaque question) :

   ```powershell
   flutter doctor --android-licenses
   ```

5. Vérifier l'ensemble :

   ```powershell
   flutter doctor -v
   ```

   Attendu : coches vertes pour **Flutter**, **Android toolchain** et **Android Studio**. Le « Visual Studio » (applications Windows) et « Chrome » peuvent rester en rouge : ils ne servent pas ici. Tout autre rouge : voir §10.

## 3. Récupérer le code

```powershell
cd C:\dev
git clone https://github.com/<organisation>/xomiton.git
cd xomiton\mobile
flutter pub get
flutter analyze
flutter test
```

`flutter analyze` doit répondre « No issues found » et `flutter test` « All tests passed ». Sinon, ne pas aller plus loin : le problème vient de l'installation, pas de l'application.

## 4. L'API locale (URL `10.0.2.2`)

L'application parle à l'API Laravel (`/api/v1`). En développement, lancer le serveur sur le PC (voir `web/README.md` : `php artisan serve`, qui écoute sur <http://localhost:8000>).

- **Depuis l'émulateur Android**, le PC s'appelle **`10.0.2.2`** : l'adresse par défaut de l'application est donc `http://10.0.2.2:8000/api/v1` (aucune option à passer).
- **Depuis un vrai téléphone en USB**, `10.0.2.2` n'existe pas. Utiliser le « reverse » d'adb (téléphone branché, débogage USB accepté) :

  ```powershell
  adb reverse tcp:8000 tcp:8000
  flutter run --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1
  ```

- Une autre adresse : `--dart-define=API_BASE_URL=https://…/api/v1`.
- **Le trafic non chiffré (`http`) n'est autorisé que dans les builds de debug**, et seulement vers `10.0.2.2`, `localhost` et `127.0.0.1` (`android/app/src/debug/res/xml/network_security_config.xml`). Une version **release** n'accepte que **HTTPS** et refuse de démarrer avec une adresse `http`.

## 5. Lancer l'application (`flutter run`)

### 5.1 Sur un émulateur

1. Android Studio › **Device Manager** › *Create Device* : un « Pixel 6 » ou « Pixel 7 », image système **API 34 « Google Play »**, finir et lancer l'appareil (bouton ▶).
2. Dans PowerShell, dossier `xomiton\mobile` :

   ```powershell
   flutter devices          # l'émulateur doit apparaître
   flutter run
   ```

   Touches utiles pendant l'exécution : `r` (rechargement à chaud), `R` (redémarrage), `q` (quitter).

### 5.2 Sur un téléphone

1. Téléphone : *Paramètres › À propos du téléphone*, toucher 7 fois « Numéro de build » pour activer le mode développeur, puis *Options pour les développeurs › Débogage USB*.
2. Brancher en USB, accepter « Autoriser le débogage USB » sur l'écran du téléphone.
3. `adb devices` doit lister l'appareil (« device », pas « unauthorized »), puis `flutter run`.

### 5.3 Options du build de développement

| Option | Effet |
|---|---|
| `--dart-define=API_BASE_URL=…` | adresse de l'API |
| `--dart-define=SPACES=member,pair,admin` | espaces compilés (défaut : les trois ; `member` seul = variante « membre ») |
| `--dart-define=ALLOW_SCREENSHOTS=true` | autorise les captures d'écran pour la recette ; **ignoré en release** |

## 6. L'APK de test (`flutter build apk --debug`)

```powershell
flutter build apk --debug
```

Fichier produit : `mobile\build\app\outputs\flutter-apk\app-debug.apk`. Pour l'installer sur un téléphone branché : `adb install -r build\app\outputs\flutter-apk\app-debug.apk`, ou envoyer le fichier au téléphone et l'ouvrir (autoriser « sources inconnues »).

Un APK de debug est gros (plus de 100 Mo) et lent : c'est normal, il sert à tester, pas à publier. La taille de la version release est suivie par la CI (§9).

## 7. Signer la version de publication

Google Play n'accepte que des paquets **signés par vous**. La clé n'est **jamais** dans le dépôt (`.gitignore` ignore `key.properties`, `*.jks`, `*.keystore`).

### 7.1 Créer la clé de téléversement (une seule fois)

```powershell
keytool -genkey -v -keystore $env:USERPROFILE\xomiton-upload.jks -storetype JKS -keyalg RSA -keysize 2048 -validity 10000 -alias upload
```

(`keytool` est dans le dossier `jbr\bin` d'Android Studio ; s'il n'est pas trouvé, appeler son chemin complet entre guillemets.) Choisir deux mots de passe solides (magasin et clé) et répondre aux questions d'identité (nom de l'organisation, pays `BJ`).

**Sauvegarder** le fichier `.jks` et les mots de passe dans un coffre de mots de passe, hors du dépôt, avec une copie sur un second support. Perdre la clé de téléversement oblige à passer par le support Google pour la réinitialiser. Activer **Play App Signing** dans la Play Console : Google garde la clé définitive, vous ne gardez que la clé de téléversement.

### 7.2 Renseigner `android/key.properties`

```powershell
cd xomiton\mobile\android
copy key.properties.example key.properties
notepad key.properties
```

Remplir les quatre lignes (`storePassword`, `keyPassword`, `keyAlias=upload`, `storeFile`). `storeFile` est relatif au dossier `android\` ou absolu ; en absolu, **utiliser des barres obliques** : `storeFile=C:/Users/VOTRE_NOM/xomiton-upload.jks`. Sans `key.properties`, un build release est signé avec la clé de debug (un message d'avertissement le dit) et Play le refusera.

## 8. Le paquet de publication (`flutter build appbundle --release`)

```powershell
cd xomiton\mobile
flutter build appbundle --release --obfuscate --split-debug-info=build\symbols --dart-define=API_BASE_URL=https://api.xomiton.example/api/v1
```

(remplacer l'adresse par celle de l'API de production, **en https**).

- Fichier produit : `mobile\build\app\outputs\bundle\release\app-release.aab` : c'est lui qu'on téléverse dans la Play Console (piste de test interne d'abord).
- **Conserver le dossier `build\symbols`** (symboles de débogage de l'obfuscation Dart) et le fichier `build\app\outputs\mapping\release\mapping.txt` (correspondance R8) : sans eux, les rapports de plantage ne se lisent plus. Les ranger hors dépôt, un dossier par version.
- Avant chaque envoi : augmenter le nombre après `+` dans `pubspec.yaml` ; Play refuse un `versionCode` déjà utilisé.
- Un APK de release local pour tester le comportement réel (R8, HTTPS) : `flutter build apk --release --dart-define=API_BASE_URL=https://…`.

Ce qui est configuré dans le projet Android (`mobile/android/`) :

| Élément | Où |
|---|---|
| Identifiant, versions, signature, R8 (`isMinifyEnabled`, `isShrinkResources`) | `app/build.gradle.kts` |
| Règles R8 | `app/proguard-rules.pro` |
| Icône classique, adaptative et monochrome | `app/src/main/res/mipmap-*`, `mipmap-anydpi-v26/ic_launcher.xml` (générées par `python mobile\tool\android_icons.py` depuis `design-system/logo/icone-appli-bleu.svg`, avec Pillow : `pip install pillow`) |
| Icône 512 × 512 de la fiche Play | `app/src/main/ic_launcher-playstore.png` |
| Écran de démarrage natif (clair, sombre, Android 12+) | `res/drawable*/launch_background.xml`, `splash_mark.png`, `values*/styles.xml`, `colors.xml` |
| Captures d'écran interdites (`FLAG_SECURE`) | `MainActivity.kt` (par écran sensible, et dans le sélecteur d'applications) |
| HTTPS seul | `res/xml/network_security_config.xml` (debug : `src/debug/res/xml/`) |
| Permissions | `AndroidManifest.xml` |

## 9. Intégration continue

`.github/workflows/mobile.yml` exécute, à chaque envoi touchant `mobile/` : `flutter analyze`, `flutter test` (dont `test/integration/`), `flutter build apk --debug`, puis un APK release arm64 dont la **taille est comparée au budget** `mobile/tool/size_budget.json` (plafond, et base + 15 %). La CI n'a ni `google-services.json` ni clé de signature. Après le premier passage sur `main`, relever la taille réelle dans le résumé du job et la saisir dans `baseline_release_apk_mb`.

## 10. Notifications : Firebase (`google-services.json`)

Sans Firebase, l'application fonctionne mais **sans notifications push** (`NoopPushService`). Le fichier de configuration est personnel au projet Firebase : **il n'est pas dans le dépôt** (`.gitignore` : `**/google-services.json`).

1. <https://console.firebase.google.com> › *Ajouter un projet* (désactiver Google Analytics si inutile).
2. Dans le projet › *Ajouter une application* › Android : nom du paquet **`bj.xomiton.xomiton`** (exactement), surnom « Xomiton ».
3. Empreinte SHA-1 (recommandée) : `cd mobile\android` puis `.\gradlew signingReport` ; copier le SHA-1 de la variante `debug`, et, une fois la clé de téléversement créée, celui de `release` ; ajouter aussi le SHA-1 de **la clé de signature d'application de Play** (Play Console › *Intégrité de l'application*).
4. Télécharger `google-services.json` et le placer dans **`mobile\android\app\google-services.json`**. Le plugin Gradle `google-services` ne s'applique que si ce fichier existe.
5. iOS (hors Windows, non livré) : voir `mobile/README.md`.
6. Côté serveur (pas dans le dépôt) : compte de service FCM et variables `FCM_*` de l'environnement du serveur (voir `mobile/README.md`, « Notifications push »).
7. Vérifier : se connecter, accepter l'autorisation de notifications (Android 13+), puis `GET me/push-subscriptions` (un abonnement `fcm`) et `GET me/devices`.

## 11. Permissions déclarées

| Permission | Pourquoi | Quand elle est demandée |
|---|---|---|
| `INTERNET`, `ACCESS_NETWORK_STATE` | appeler l'API, détecter le mode hors ligne | jamais (installées d'office) |
| `ACCESS_FINE_LOCATION` (avec `ACCESS_COARSE_LOCATION`) | **joindre la position précise à une alerte SOS** (attente de 8 s au plus) | au moment du SOS seulement ; jamais en arrière-plan ; la personne peut refuser, le SOS part sans position |
| `POST_NOTIFICATIONS` | notifications push (Android 13+) | après la première connexion |
| `VIBRATE` | alerte d'astreinte | jamais |

Photos, caméra, micro, stockage, agenda, démarrage au boot et localisation en arrière-plan sont **retirés explicitement** du manifeste (`tools:node="remove"`) : l'application n'en a pas besoin (pièces jointes par le sélecteur de fichiers du système, ajout à l'agenda par intention). Contrôle avant chaque publication : ouvrir `app-release.aab` dans Android Studio (*Build › Analyze APK*) › `AndroidManifest.xml` fusionné, et vérifier que la liste est celle du tableau.

## 12. Dépannage

| Symptôme | Cause probable et remède |
|---|---|
| `flutter` n'est pas reconnu | `C:\src\flutter\bin` absent du Path, ou PowerShell non rouvert |
| `flutter doctor` : « Unable to locate Android SDK » | `flutter config --android-sdk "…\Android\Sdk"` (chemin du SDK Manager) |
| « cmdline-tools component is missing » | SDK Manager › SDK Tools › *Android SDK Command-line Tools (latest)*, puis `flutter doctor --android-licenses` |
| « Android license status unknown » / licences refusées | `flutter doctor --android-licenses`, répondre `y` partout ; PowerShell **en administrateur** si l'écriture échoue |
| « Unsupported class file major version » / erreur de JDK | Java trop ancien ou trop récent : `flutter config --jdk-dir` vers le `jbr` d'Android Studio (JDK 17 ou plus) ; vérifier avec `flutter doctor -v` |
| Le build se fige ou échoue au téléchargement de Gradle | réseau d'entreprise ou antivirus : relancer ; sinon configurer le proxy dans `%USERPROFILE%\.gradle\gradle.properties` (`systemProp.https.proxyHost=…`, `systemProp.https.proxyPort=…`) |
| « Filename longer than 260 characters » | `git config --global core.longpaths true`, cloner dans un dossier court (`C:\dev`) |
| L'émulateur ne démarre pas ou est très lent | virtualisation désactivée dans le BIOS, ou « Plateforme d'hyperviseur Windows » non cochée ; redémarrer après l'avoir activée |
| `adb devices` : « unauthorized » | débloquer le téléphone, accepter la fenêtre « Autoriser le débogage USB » ; sinon *Révoquer les autorisations de débogage USB*, rebrancher |
| `adb devices` vide | câble « charge seulement » : choisir « Transfert de fichiers » ; pilote USB du fabricant |
| L'application affiche « Connexion impossible » en debug | API non lancée, ou mauvaise adresse : émulateur = `http://10.0.2.2:8000/api/v1` ; téléphone = `adb reverse tcp:8000 tcp:8000` + `127.0.0.1` ; vérifier <http://localhost:8000> dans le navigateur du PC |
| « Cleartext HTTP traffic … not permitted » | normal en release (HTTPS seul) ; en debug, l'adresse n'est pas `10.0.2.2`, `localhost` ou `127.0.0.1` : utiliser `adb reverse` ou un tunnel https |
| « API_BASE_URL doit être une URL https en production » | build release lancé avec une adresse `http` : donner une adresse https |
| `android/key.properties absent : le build release sera signé avec la clé de debug` | normal sans clé ; pour Play, créer la clé (§7) |
| « Keystore was tampered with, or password was incorrect » | mot de passe faux dans `key.properties`, ou `storeFile` pointe vers un autre fichier ; en chemin absolu, barres obliques `/` |
| Play : « Vous avez importé un APK ou un App Bundle signé en mode debug » | build sans `key.properties` ; refaire après §7 |
| Play : « Le code de version 1 a déjà été utilisé » | augmenter le nombre après `+` dans `pubspec.yaml` |
| Pas de notifications | pas de `google-services.json` (push désactivé), nom de paquet différent de `bj.xomiton.xomiton`, autorisation refusée sur le téléphone, ou compte de service FCM non configuré côté serveur |
| Erreur de ressources après changement d'icône | relancer `python tool\android_icons.py`, puis `flutter clean` et `flutter pub get` |
| Comportement étrange après une mise à jour de Flutter | `flutter clean`, `flutter pub get`, supprimer `mobile\android\.gradle` |

Si un problème persiste : copier la sortie complète de `flutter doctor -v` et de la commande qui échoue (avec `-v`), sans mot de passe ni contenu de `key.properties`, et la joindre au ticket.
