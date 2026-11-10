---
title: Premier build iOS sur Mac
description: Procédure pas à pas pour la première personne qui ouvre le projet iOS de Xomiton sur un Mac (versions, flutter doctor, pod install, signature, capacités, Firebase, simulateur, iPhone, archive, TestFlight) et liste des fichiers Swift et plist écrits sans Mac à relire en premier.
---

# Premier build iOS sur Mac

**Avertissement.** Rien de ce qui est sous `mobile/ios/` n'a été compilé ni exécuté : le code iOS a été écrit sans Mac (10 novembre 2026). Il peut contenir des fautes de syntaxe, de nom d'API ou de réglage de projet. Les 454 tests Flutter initiaux et leurs ajouts tournent sous Linux et ne prouvent rien sur iOS. Contexte, décisions D-IOS-1 à 12 et statut des lots : [PLAN_IOS.md](PLAN_IOS.md) (§0.6).

## 1. Versions (à vérifier le jour J)

- macOS et Xcode : prendre l'Xcode exigé par Apple pour les envois à l'App Store à la date du dépôt (à vérifier sur developer.apple.com) ; il impose un macOS minimal.
- Flutter : celui de la CI du plan, **3.47.8** (canal stable), pour reproduire les tests Linux.
- CocoaPods (`sudo gem install cocoapods` ou Homebrew), Ruby du système ou rbenv.
- Cible de déploiement : **iOS 15.0** (projet et `Podfile`, à garder identiques).
- iPhone seul, portrait seul (décisions D-IOS-1 et D-IOS-6).

## 2. Préparation

```bash
cd mobile
flutter doctor -v          # rien de rouge dans « Xcode » et « CocoaPods »
flutter pub get
cd ios && pod install --repo-update && cd ..
```

Le projet utilise déjà le gestionnaire Swift Package de Flutter (`FlutterGeneratedPluginSwiftPackage`) ; les plugins sans paquet Swift (probablement `flutter_secure_storage` 9.x) passent par CocoaPods, d'où le `Podfile` écrit à la main (gabarit Flutter). **Premier point à contrôler** : que `pod install` réussisse et que Flutter ait ajouté les lignes `#include "Pods/..."` dans `ios/Flutter/Debug.xcconfig` et `Release.xcconfig`. Si Flutter régénère le `Podfile`, garder ses lignes. Versionner ensuite `Podfile` et `Podfile.lock` (pas `Pods/`).

Si `pod install` échoue sur un en-tête « non modulaire » : essayer `use_frameworks! :linkage => :static` (voir le commentaire du `Podfile`).

## 3. Ouvrir et signer

1. Ouvrir **`ios/Runner.xcworkspace`** (jamais `Runner.xcodeproj` seul une fois les Pods installés).
2. Cible *Runner* › *Signing & Capabilities* : choisir l'**équipe de développement** (aucune n'est renseignée), laisser « Automatically manage signing », identifiant `app.xomiton.xomiton`.
3. Capacités (le fichier `Runner/Runner.entitlements` est déjà lié ; Xcode doit les montrer sans erreur, et le portail Apple doit les avoir sur l'identifiant) : **Push Notifications**, **Time Sensitive Notifications**, **Associated Domains** (`applinks:xomiton.app`), **Background Modes › Remote notifications**. **Ne pas** activer *Critical Alerts* tant qu'Apple n'a pas accordé le droit (la ligne est commentée dans le fichier).
4. Si Xcode signale que `Runner.entitlements`, `PrivacyInfo.xcprivacy` ou `AlternateIcons` ne sont pas dans la cible : les ajouter (cases « Target Membership »). Ils ont été ajoutés à la main dans `project.pbxproj`.

## 4. Firebase et notifications

- Créer l'app iOS dans Firebase (identifiant `app.xomiton.xomiton`), télécharger **`GoogleService-Info.plist`** et l'ajouter à la cible *Runner* depuis Xcode (**hors dépôt : ne jamais le commiter**).
- Charger la clé APNs (`.p8`) dans Firebase › Paramètres › Cloud Messaging (clé dans le coffre, jamais dans le dépôt).
- Sans ce fichier l'application démarre quand même (push désactivé : `createPushService` renvoie un service neutre).
- Côté serveur : message FCM avec bloc `apns` neutre (annexe C du plan) ; sinon le SOS n'arrivera pas de façon fiable application fermée.

## 5. Lancer

```bash
open -a Simulator
flutter run -d "iPhone 16"           # simulateur
flutter run -d <identifiant de l'iPhone> --release   # appareil (mode développeur activé)
```

Sur simulateur : pas de push réel, pas de Face ID réel. À essayer sur **iPhone réel** : push, icône alternative, recouvrement du sélecteur d'applications, enregistrement d'écran, SOS avec localisation.

## 6. Archive et TestFlight

1. `flutter build ipa --release` (ou *Product › Archive* dans Xcode), puis *Distribute App › App Store Connect*.
2. Joindre le **rapport de confidentialité** (*Xcode Organizer › Archive › Generate Privacy Report*) et le comparer à `Runner/PrivacyInfo.xcprivacy` et aux étiquettes d'App Store Connect.
3. Réponse « chiffrement » : **ne pas** poser `ITSAppUsesNonExemptEncryption` avant l'avis écrit du conseil (D-IOS-8). TODO explicite : tant que la clé manque, App Store Connect posera la question à chaque envoi.
4. TestFlight interne d'abord, puis externe (Beta App Review). Classification d'âge : après l'avis du conseil (D-IOS-5).

## 7. Fichiers à relire en premier (écrits sans Mac, par ordre de risque)

1. **`ios/Runner.xcodeproj/project.pbxproj`** : éditions manuelles (identifiants `A1B2C3D41F0000010…` : `XomitonSecurityPlugin.swift`, `PrivacyInfo.xcprivacy`, `Runner.entitlements`, groupe `AlternateIcons` et ses deux PNG ; `CODE_SIGN_ENTITLEMENTS` dans les trois configurations de *Runner* ; `TARGETED_DEVICE_FAMILY = 1` ; `developmentRegion = fr`). Si Xcode ne l'ouvre pas : annuler ces éditions et ajouter les fichiers par glisser-déposer.
2. **`ios/Runner/XomitonSecurityPlugin.swift`** : compilation (API `UIScene`, `UIWindow(windowScene:)`, `setAlternateIconName`, `UIScreen.capturedDidChangeNotification`), enregistrement dans `AppDelegate`. Contrat des canaux : `app.xomiton/discreet` (`setDiscreet`), `xomiton/secure` (`setSecure`), `app.xomiton/capture` (`screenshot`, `captureChanged`, `isCaptured`, `suspendCover`). À essayer : pas de clignotement du recouvrement ; l'invite Face ID (quand `local_auth` sera ajouté) doit appeler `suspendCover`.
3. **`ios/Runner/AppDelegate.swift`** : `registrar(forPlugin:)` sur le pont de moteur implicite, purge du Keychain au premier lancement (vérifier qu'elle ne supprime pas ce que Firebase recrée mal), exclusion des sauvegardes, délégué de notifications.
4. **`ios/Runner/SceneDelegate.swift`** : volontairement vide (le recouvrement blanc a été déplacé dans le plugin) ; vérifier qu'il n'y a pas de double recouvrement.
5. **`ios/Runner/Info.plist`** : `CFBundleIcons` / `CFBundleAlternateIcons` (`DiscreetIcon`, fichiers `DiscreetIcon@2x.png`/`@3x.png` à la racine du paquet) ; fusion avec l'icône du catalogue (`CFBundleIconName = AppIcon`) ; `UIBackgroundModes`, `FlutterDeepLinkingEnabled`, orientation portrait seule, `NSFaceIDUsageDescription` (dormante tant que Face ID n'est pas codé). Textes d'autorisation en français.
6. **`ios/Runner/Runner.entitlements`** et **`ios/Podfile`** : voir §2 et §3.
7. **`ios/Runner/PrivacyInfo.xcprivacy`** : données collectées déclarées d'après le code (e-mail, pseudonyme, téléphone facultatif, position précise/approximative pour SOS et signalement, contenu, identifiants, diagnostics). **À confirmer** : API « required reason » disque (`E174.1`) et heure de démarrage (`35F9.1`) ; fichiers (`C617.1`) probable ; `UserDefaults` (`CA92.1`) certain. Retirer toute déclaration que le rapport Xcode ne justifie pas, en ajouter si un Pod en réclame.
8. **`ios/Runner/Base.lproj/LaunchScreen.storyboard`** (format écrit à la main) et **`Assets.xcassets`** (`LaunchMark.imageset`, `LaunchBackground.colorset`, `AppIcon.appiconset`) : au besoin, recréer l'écran de lancement dans Xcode avec ces deux ressources. Les icônes viennent de `mobile/tool/ios_icons.py` (relançable).

## 8. Erreurs probables

- `Cannot find 'XomitonSecurityPlugin' in scope` : le fichier n'est pas dans la cible.
- Avertissement ou erreur de profil : capacité absente de l'identifiant (Time Sensitive, Associated Domains).
- Icône alternative sans effet : le nom `DiscreetIcon` doit correspondre exactement à `Info.plist` et aux fichiers ; l'appel exige l'application au premier plan.
- Pas de jeton de push : simulateur, `GoogleService-Info.plist` absent, clé APNs non chargée ou `aps-environment` incohérent.
- Dart : `Platform.isIOS` et `defaultTargetPlatform` sont déjà utilisés ; sur iOS la barre Membre n'a que 5 onglets (l'autre espace est dans le profil, carte « Mes espaces »).
- Blocage d'une personne depuis le fil : **absent** (pas de route d'API) ; à créer côté serveur avant la soumission (règle 1.2).
