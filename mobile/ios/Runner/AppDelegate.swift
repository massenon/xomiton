// NON COMPILÉ, NON VÉRIFIÉ : écrit sans Mac (lots IOS-05 et IOS-08 de docs/PLAN_IOS.md).
// À relire en premier au premier build (docs/IOS_PREMIER_BUILD_SUR_MAC.md).
import Flutter
import UIKit
import UserNotifications

@main
@objc class AppDelegate: FlutterAppDelegate, FlutterImplicitEngineDelegate {
  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    // Réinstallation : le Keychain survit à la désinstallation, pas UserDefaults. Premier lancement
    // après une (ré)installation = aucune ancienne session, aucun ancien secret (IOS-08).
    purgeKeychainOnFirstLaunch()
    // Les données locales ne partent pas dans les sauvegardes iCloud / iTunes.
    excludeFromBackup()

    // Les notifications (FCM → APNs) sont traitées par firebase_messaging et flutter_local_notifications
    // (plugins Flutter). Firebase lui-même est initialisé côté Dart (Firebase.initializeApp lit
    // GoogleService-Info.plist, fourni hors dépôt) : aucun FirebaseApp.configure() ici. Le délégué ci-dessous
    // est celui que documente flutter_local_notifications ; FlutterAppDelegate l'implémente déjà.
    UNUserNotificationCenter.current().delegate = self as UNUserNotificationCenterDelegate

    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }

  func didInitializeImplicitFlutterEngine(_ engineBridge: FlutterImplicitEngineBridge) {
    GeneratedPluginRegistrant.register(with: engineBridge.pluginRegistry)
    // Plugin natif propre à Xomiton (hors GeneratedPluginRegistrant : ce n'est pas un paquet pub).
    if let registrar = engineBridge.pluginRegistry.registrar(forPlugin: "XomitonSecurityPlugin") {
      XomitonSecurityPlugin.register(with: registrar)
    }
  }

  // Jeton APNs : transmis aux plugins par FlutterAppDelegate (firebase_messaging le donne à Firebase,
  // qui échange contre le jeton FCM envoyé au serveur). Ne rien journaliser du jeton.
  override func application(
    _ application: UIApplication,
    didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data
  ) {
    super.application(application, didRegisterForRemoteNotificationsWithDeviceToken: deviceToken)
  }

  override func application(
    _ application: UIApplication,
    didFailToRegisterForRemoteNotificationsWithError error: Error
  ) {
    // Simulateur, profil sans capacité Push, réseau : le push reste désactivé, l'application fonctionne.
    super.application(application, didFailToRegisterForRemoteNotificationsWithError: error)
  }

  // MARK: - Hygiène des données locales

  private func purgeKeychainOnFirstLaunch() {
    let defaults = UserDefaults.standard
    let key = "app.xomiton.firstLaunchDone"
    if defaults.bool(forKey: key) { return }
    // Mot de passe générique : classe utilisée par flutter_secure_storage (jeton, clés Hive, PIN haché).
    SecItemDelete([kSecClass as String: kSecClassGenericPassword] as CFDictionary)
    defaults.set(true, forKey: key)
  }

  private func excludeFromBackup() {
    let manager = FileManager.default
    let directories: [FileManager.SearchPathDirectory] = [.documentDirectory, .applicationSupportDirectory]
    for directory in directories {
      guard var url = manager.urls(for: directory, in: .userDomainMask).first else { continue }
      var values = URLResourceValues()
      values.isExcludedFromBackup = true
      try? url.setResourceValues(values)
    }
  }
}
