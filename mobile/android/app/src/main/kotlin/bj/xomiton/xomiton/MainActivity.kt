package bj.xomiton.xomiton

import android.content.ComponentName
import android.content.pm.PackageManager
import android.view.WindowManager
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    // Mode discret : bascule entre les deux activity-alias du manifeste
    // (icône + nom « Xomiton » / icône + nom « Notes »).
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "bj.xomiton/discreet")
            .setMethodCallHandler { call, result ->
                if (call.method == "setDiscreet") {
                    setDiscreet(call.argument<Boolean>("discreet") == true)
                    result.success(null)
                } else {
                    result.notImplemented()
                }
            }
    }

    private fun setDiscreet(discreet: Boolean) {
        // On active le nouveau point d'entrée avant de désactiver l'ancien :
        // l'application a toujours une icône.
        if (discreet) {
            setAlias("LauncherDiscreet", true)
            setAlias("LauncherDefault", false)
        } else {
            setAlias("LauncherDefault", true)
            setAlias("LauncherDiscreet", false)
        }
    }

    private fun setAlias(name: String, enabled: Boolean) {
        packageManager.setComponentEnabledSetting(
            ComponentName(this, "bj.xomiton.xomiton.$name"),
            if (enabled) PackageManager.COMPONENT_ENABLED_STATE_ENABLED
            else PackageManager.COMPONENT_ENABLED_STATE_DISABLED,
            PackageManager.DONT_KILL_APP,
        )
    }

    // Masque l'aperçu dans le sélecteur d'applications (récits, contacts) sans
    // interdire les captures d'écran quand l'application est au premier plan.
    override fun onPause() {
        window.setFlags(WindowManager.LayoutParams.FLAG_SECURE, WindowManager.LayoutParams.FLAG_SECURE)
        super.onPause()
    }

    override fun onResume() {
        super.onResume()
        window.clearFlags(WindowManager.LayoutParams.FLAG_SECURE)
    }
}
