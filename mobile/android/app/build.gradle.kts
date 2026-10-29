import java.util.Properties

plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

// Signature de release : `android/key.properties` (JAMAIS dans le dépôt, voir
// key.properties.example et docs/BUILD_ANDROID_WINDOWS.md). Sans ce fichier, un build
// release est signé avec la clé de debug : bon pour `flutter run --release`, refusé par Play.
val keystoreProperties = Properties()
val keystorePropertiesFile = rootProject.file("key.properties")
val hasReleaseKey = keystorePropertiesFile.exists()
if (hasReleaseKey) {
    keystorePropertiesFile.inputStream().use { keystoreProperties.load(it) }
} else {
    logger.warn("android/key.properties absent : le build release sera signé avec la clé de debug (inutilisable sur Play).")
}

android {
    namespace = "bj.xomiton.xomiton"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        // Identifiant définitif de l'application (Play, Firebase) : ne plus le changer.
        applicationId = "bj.xomiton.xomiton"
        minSdk = 26
        targetSdk = flutter.targetSdkVersion
        // `version: 1.0.0+1` de pubspec.yaml : versionName = 1.0.0, versionCode = 1
        // (à augmenter à chaque envoi sur Play : `+2`, `+3`, …).
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        if (hasReleaseKey) {
            create("release") {
                keyAlias = keystoreProperties["keyAlias"] as String
                keyPassword = keystoreProperties["keyPassword"] as String
                // Chemin relatif à android/ (ou absolu).
                storeFile = rootProject.file(keystoreProperties["storeFile"] as String)
                storePassword = keystoreProperties["storePassword"] as String
            }
        }
    }

    buildTypes {
        release {
            signingConfig = if (hasReleaseKey) signingConfigs.getByName("release") else signingConfigs.getByName("debug")
            // R8 : code réduit et obfusqué, ressources inutiles retirées (règles : proguard-rules.pro).
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}

// Firebase : google-services.json n'est PAS dans le dépôt (voir mobile/README.md).
// Sans lui, le plugin n'est pas appliqué et l'application démarre avec le push désactivé.
if (file("google-services.json").exists()) {
    apply(plugin = "com.google.gms.google-services")
}
