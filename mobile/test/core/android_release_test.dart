import 'dart:io';
import 'dart:typed_data';

import 'package:flutter_test/flutter_test.dart';
import 'package:xomiton/core/push/push_registrar.dart';

/// Configuration de release Android (P15-22) : fichiers lus tels quels, aucun
/// build Android n'est possible dans ce test. Garde-fous contre une régression
/// silencieuse (clé commitée, trafic en clair, permission ajoutée, icône absente).
String read(String path) => File(path).readAsStringSync();

({int w, int h}) pngSize(String path) {
  final b = File(path).readAsBytesSync();
  expect(b.sublist(1, 4), [0x50, 0x4E, 0x47], reason: '$path n’est pas un PNG');
  final d = ByteData.sublistView(Uint8List.fromList(b.sublist(16, 24)));
  return (w: d.getUint32(0), h: d.getUint32(4));
}

void main() {
  const main = 'android/app/src/main';
  final manifest = read('$main/AndroidManifest.xml');
  final gradle = read('android/app/build.gradle.kts');

  group('identité et versions', () {
    test('applicationId, nom affiché et versions lues de pubspec.yaml', () {
      expect(gradle, contains('applicationId = "bj.xomiton.xomiton"'));
      expect(gradle, contains('versionCode = flutter.versionCode'));
      expect(gradle, contains('versionName = flutter.versionName'));
      expect(read('$main/res/values/strings.xml'), contains('<string name="app_name">Xomiton</string>'));
      expect(manifest, contains('android:label="@string/app_name"'));
    });

    test('la version envoyée au serveur (kAppVersion) est celle de pubspec.yaml', () {
      final v = RegExp(r'^version:\s*([\d.]+)\+(\d+)', multiLine: true).firstMatch(read('pubspec.yaml'))!;
      expect(kAppVersion, v.group(1));
      expect(int.parse(v.group(2)!), greaterThanOrEqualTo(1));
    });
  });

  group('signature et réduction du code', () {
    test('key.properties lu, jamais commité ; exemple fourni', () {
      expect(gradle, contains('rootProject.file("key.properties")'));
      expect(gradle, contains('signingConfigs.getByName("release")'));
      expect(read('android/.gitignore'), contains('key.properties'));
      expect(read('.gitignore'), contains('android/key.properties'));
      expect(read('.gitignore'), contains('*.jks'));
      final example = read('android/key.properties.example');
      for (final k in ['storePassword', 'keyPassword', 'keyAlias', 'storeFile']) {
        expect(example, contains('$k='));
      }
      expect(example, isNot(contains('BEGIN')), reason: 'aucun secret dans l’exemple');
      expect(File('android/key.properties').existsSync(), isFalse, reason: 'jamais de clé dans le dépôt');
    });

    test('R8 : minification, réduction des ressources et règles', () {
      expect(gradle, contains('isMinifyEnabled = true'));
      expect(gradle, contains('isShrinkResources = true'));
      expect(gradle, contains('"proguard-rules.pro"'));
      expect(File('android/app/proguard-rules.pro').existsSync(), isTrue);
    });

    test('google-services.json attendu mais ignoré par git', () {
      expect(read('.gitignore'), contains('**/google-services.json'));
      expect(gradle, contains('file("google-services.json").exists()'));
      expect(File('android/app/google-services.json').existsSync(), isFalse);
      expect(read('../docs/BUILD_ANDROID_WINDOWS.md'), contains('google-services.json'));
    });
  });

  group('permissions minimales et réseau', () {
    test('seules les permissions utiles sont déclarées', () {
      final declared = RegExp(r'<uses-permission android:name="android\.permission\.(\w+)"\s*/>').allMatches(manifest).map((m) => m.group(1)!).toSet();
      expect(declared, {'INTERNET', 'ACCESS_NETWORK_STATE', 'ACCESS_COARSE_LOCATION', 'ACCESS_FINE_LOCATION', 'POST_NOTIFICATIONS', 'VIBRATE'});
      // Retirées explicitement de la fusion des manifestes des plugins.
      for (final p in ['CAMERA', 'RECORD_AUDIO', 'READ_EXTERNAL_STORAGE', 'READ_MEDIA_IMAGES', 'READ_CALENDAR', 'ACCESS_BACKGROUND_LOCATION']) {
        expect(manifest, contains('android.permission.$p" tools:node="remove"'));
      }
    });

    test('HTTPS seul : configuration réseau de release, exception de développement limitée au debug', () {
      expect(manifest, contains('android:usesCleartextTraffic="false"'));
      expect(manifest, contains('android:networkSecurityConfig="@xml/network_security_config"'));
      final release = read('$main/res/xml/network_security_config.xml');
      expect(release, contains('cleartextTrafficPermitted="false"'));
      expect(release, isNot(contains('true')));
      expect(release, isNot(contains('src="user"')));
      final debug = read('android/app/src/debug/res/xml/network_security_config.xml');
      expect(debug, contains('10.0.2.2'));
      expect(RegExp(r'<domain [^>]*>([^<]+)</domain>').allMatches(debug).map((m) => m.group(1)).toSet(), {'10.0.2.2', 'localhost', '127.0.0.1'});
    });

    test('captures d’écran : FLAG_SECURE par écran et dans le sélecteur d’applications', () {
      final kt = read('$main/kotlin/bj/xomiton/xomiton/MainActivity.kt');
      expect(kt, contains('FLAG_SECURE'));
      expect(kt, contains('override fun onPause'));
      expect(kt, contains('"xomiton/secure"'));
    });
  });

  group('icônes et écran de démarrage', () {
    test('icône classique aux cinq tailles et premier plan adaptatif (108 dp)', () {
      const dp = {'mdpi': 1.0, 'hdpi': 1.5, 'xhdpi': 2.0, 'xxhdpi': 3.0, 'xxxhdpi': 4.0};
      dp.forEach((d, k) {
        final legacy = pngSize('$main/res/mipmap-$d/ic_launcher.png');
        expect((legacy.w, legacy.h), ((48 * k).round(), (48 * k).round()), reason: 'ic_launcher $d');
        final fg = pngSize('$main/res/mipmap-$d/ic_launcher_foreground.png');
        expect((fg.w, fg.h), ((108 * k).round(), (108 * k).round()), reason: 'foreground $d');
        for (final dir in ['drawable-$d', 'drawable-night-$d']) {
          final s = pngSize('$main/res/$dir/splash_mark.png');
          expect(s.w, greaterThan(0), reason: dir);
        }
      });
      final store = pngSize('$main/ic_launcher-playstore.png');
      expect((store.w, store.h), (512, 512));
    });

    test('icône adaptative avec monochrome ; mode discret conservé ; écran de démarrage clair, sombre et Android 12', () {
      final adaptive = read('$main/res/mipmap-anydpi-v26/ic_launcher.xml');
      for (final t in ['<background', '<foreground', '<monochrome']) {
        expect(adaptive, contains(t));
      }
      expect(File('$main/res/drawable/ic_notes.xml').existsSync(), isTrue);
      for (final f in ['drawable/launch_background.xml', 'drawable-v21/launch_background.xml']) {
        expect(read('$main/res/$f'), contains('@drawable/splash_mark'));
      }
      expect(read('$main/res/values/colors.xml'), contains('splash_background'));
      expect(read('$main/res/values-night/colors.xml'), contains('splash_background'));
      for (final f in ['values-v31', 'values-night-v31']) {
        expect(read('$main/res/$f/styles.xml'), contains('windowSplashScreenAnimatedIcon'));
      }
    });

    test('le script de génération des icônes part du SVG de la charte', () {
      final script = read('tool/android_icons.py');
      expect(script, contains('icone-appli-bleu.svg'));
      expect(File('../design-system/logo/icone-appli-bleu.svg').existsSync(), isTrue);
    });
  });

  group('intégration continue et budget de taille', () {
    test('mobile.yml : analyse, tests, APK debug, budget de taille', () {
      final y = read('../.github/workflows/mobile.yml');
      for (final s in ['flutter analyze', 'flutter test', 'flutter build apk --debug', 'check_size.py debug', 'check_size.py release']) {
        expect(y, contains(s));
      }
      expect(File('tool/size_budget.json').existsSync(), isTrue);
    });
  });
}
