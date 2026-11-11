// NON COMPILÉ, NON VÉRIFIÉ : écrit sans Mac (lot IOS-08 de docs/PLAN_IOS.md). À relire en premier
// au premier build (docs/IOS_PREMIER_BUILD_SUR_MAC.md). Ne dépend d'aucun paquet Pod : UIKit et Flutter seulement.
//
// Plugin natif « XomitonSecurity » :
//   1. recouvrement OPAQUE aux couleurs de la marque dès que la scène quitte le premier plan
//      (aperçu du sélecteur d'applications) : remplace le recouvrement blanc de SceneDelegate ;
//   2. détection d'une capture d'écran (userDidTakeScreenshotNotification) et d'un enregistrement /
//      recopie d'écran (UIScreen.isCaptured), signalés à Dart ; pendant un enregistrement, sur un
//      écran sensible, le recouvrement reste affiché (iOS ne sait pas interdire la capture comme
//      FLAG_SECURE sur Android) ;
//   3. icône alternative du mode discret : MÊME contrat que Android (voir MainActivity.kt).
//
// Contrat des canaux (identique à Android pour les deux premiers ; ne pas changer sans changer Dart) :
//   - `app.xomiton/discreet` · `setDiscreet` {discreet: Bool} → nil ; erreur → FlutterError
//     (lib/core/platform/discreet_icon.dart attrape PlatformException).
//   - `xomiton/secure` · `setSecure` {secure: Bool} → nil : écran sensible affiché ou non
//     (lib/core/security/screen_security.dart).
//   - `app.xomiton/capture` (natif → Dart) : `screenshot` (sans argument) et
//     `captureChanged` {captured: Bool} ; Dart peut appeler `isCaptured` → Bool, et
//     `suspendCover` {suspend: Bool} (à appeler autour d'une invite Face ID pour éviter
//     le clignotement du recouvrement, voir plan §4.6).

import Flutter
import UIKit

public final class XomitonSecurityPlugin: NSObject, FlutterPlugin {
  /// Nom de l'icône alternative déclarée dans Info.plist (CFBundleAlternateIcons).
  static let discreetIconName = "DiscreetIcon"

  private let captureChannel: FlutterMethodChannel
  private var secureWanted = false
  private var coverSuspended = false
  private var sceneActive = true
  private var coverWindow: UIWindow?

  public static func register(with registrar: FlutterPluginRegistrar) {
    let messenger = registrar.messenger()
    let instance = XomitonSecurityPlugin(
      captureChannel: FlutterMethodChannel(name: "app.xomiton/capture", binaryMessenger: messenger))

    let secure = FlutterMethodChannel(name: "xomiton/secure", binaryMessenger: messenger)
    secure.setMethodCallHandler { call, result in instance.handleSecure(call, result) }

    let discreet = FlutterMethodChannel(name: "app.xomiton/discreet", binaryMessenger: messenger)
    discreet.setMethodCallHandler { call, result in instance.handleDiscreet(call, result) }

    instance.captureChannel.setMethodCallHandler { call, result in instance.handleCapture(call, result) }
    instance.observe()
  }

  private init(captureChannel: FlutterMethodChannel) {
    self.captureChannel = captureChannel
    super.init()
  }

  // MARK: - Canaux

  private func handleSecure(_ call: FlutterMethodCall, _ result: FlutterResult) {
    guard call.method == "setSecure" else { return result(FlutterMethodNotImplemented) }
    let args = call.arguments as? [String: Any]
    secureWanted = (args?["secure"] as? Bool) == true
    refreshCover()
    result(nil)
  }

  private func handleDiscreet(_ call: FlutterMethodCall, _ result: @escaping FlutterResult) {
    guard call.method == "setDiscreet" else { return result(FlutterMethodNotImplemented) }
    let args = call.arguments as? [String: Any]
    let discreet = (args?["discreet"] as? Bool) == true
    let app = UIApplication.shared
    guard app.supportsAlternateIcons else {
      return result(FlutterError(code: "unsupported", message: "Icône alternative non prise en charge", details: nil))
    }
    let wanted: String? = discreet ? XomitonSecurityPlugin.discreetIconName : nil
    if app.alternateIconName == wanted { return result(nil) }
    // iOS affiche lui-même une alerte « Vous avez changé l'icône de … » : normal, non désactivable.
    // L'appel doit venir du fil principal et de l'application au premier plan.
    app.setAlternateIconName(wanted) { error in
      if let error = error {
        result(FlutterError(code: "icon_failed", message: error.localizedDescription, details: nil))
      } else {
        result(nil)
      }
    }
  }

  private func handleCapture(_ call: FlutterMethodCall, _ result: FlutterResult) {
    switch call.method {
    case "isCaptured":
      result(isCaptured())
    case "suspendCover":
      let args = call.arguments as? [String: Any]
      coverSuspended = (args?["suspend"] as? Bool) == true
      refreshCover()
      result(nil)
    default:
      result(FlutterMethodNotImplemented)
    }
  }

  // MARK: - Observation

  private func observe() {
    let center = NotificationCenter.default
    center.addObserver(self, selector: #selector(sceneWillDeactivate),
                       name: UIScene.willDeactivateNotification, object: nil)
    center.addObserver(self, selector: #selector(sceneDidActivate),
                       name: UIScene.didActivateNotification, object: nil)
    center.addObserver(self, selector: #selector(screenshotTaken),
                       name: UIApplication.userDidTakeScreenshotNotification, object: nil)
    center.addObserver(self, selector: #selector(captureChanged),
                       name: UIScreen.capturedDidChangeNotification, object: nil)
  }

  @objc private func sceneWillDeactivate(_ note: Notification) {
    sceneActive = false
    refreshCover(scene: note.object as? UIWindowScene)
  }

  @objc private func sceneDidActivate(_ note: Notification) {
    sceneActive = true
    refreshCover(scene: note.object as? UIWindowScene)
  }

  @objc private func screenshotTaken() {
    captureChannel.invokeMethod("screenshot", arguments: nil)
  }

  @objc private func captureChanged() {
    captureChannel.invokeMethod("captureChanged", arguments: ["captured": isCaptured()])
    refreshCover()
  }

  private func isCaptured() -> Bool {
    for case let scene as UIWindowScene in UIApplication.shared.connectedScenes where scene.screen.isCaptured {
      return true
    }
    return false
  }

  // MARK: - Recouvrement

  /// Recouvrement visible si : l'application n'est pas active (sélecteur d'applications), ou
  /// un enregistrement est en cours sur un écran sensible. Jamais pendant une invite Face ID
  /// (`suspendCover`).
  private func refreshCover(scene: UIWindowScene? = nil) {
    let shouldShow = !coverSuspended && (!sceneActive || (secureWanted && isCaptured()))
    if shouldShow { showCover(scene: scene) } else { hideCover() }
  }

  private func showCover(scene: UIWindowScene?) {
    if coverWindow != nil { return }
    guard let target = scene ?? UIApplication.shared.connectedScenes.compactMap({ $0 as? UIWindowScene }).first
    else { return }
    let window = UIWindow(windowScene: target)
    window.windowLevel = .alert + 1
    window.rootViewController = CoverViewController()
    window.isHidden = false
    coverWindow = window
  }

  private func hideCover() {
    coverWindow?.isHidden = true
    coverWindow = nil
  }
}

/// Fond de la charte (couleur « LaunchBackground », claire ou sombre) et rosace centrée. Aucune donnée
/// de l'application n'est affichée.
private final class CoverViewController: UIViewController {
  override func viewDidLoad() {
    super.viewDidLoad()
    view.backgroundColor = UIColor(named: "LaunchBackground") ?? .systemBackground
    if let image = UIImage(named: "LaunchMark") {
      let mark = UIImageView(image: image)
      mark.contentMode = .scaleAspectFit
      mark.translatesAutoresizingMaskIntoConstraints = false
      mark.isAccessibilityElement = false
      view.addSubview(mark)
      NSLayoutConstraint.activate([
        mark.centerXAnchor.constraint(equalTo: view.centerXAnchor),
        mark.centerYAnchor.constraint(equalTo: view.centerYAnchor),
        mark.widthAnchor.constraint(equalToConstant: 96),
        mark.heightAnchor.constraint(equalToConstant: 96),
      ])
    }
    view.isAccessibilityElement = false
    view.accessibilityElementsHidden = true
  }
}
