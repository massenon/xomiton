import Flutter
import UIKit

class SceneDelegate: FlutterSceneDelegate {
  private var cover: UIView?

  // Masque l'aperçu dans le sélecteur d'applications.
  override func sceneWillResignActive(_ scene: UIScene) {
    if let window = (scene as? UIWindowScene)?.windows.first, cover == nil {
      let v = UIView(frame: window.bounds)
      v.backgroundColor = .white
      v.autoresizingMask = [.flexibleWidth, .flexibleHeight]
      window.addSubview(v)
      cover = v
    }
    super.sceneWillResignActive(scene)
  }

  override func sceneDidBecomeActive(_ scene: UIScene) {
    cover?.removeFromSuperview()
    cover = nil
    super.sceneDidBecomeActive(scene)
  }
}
