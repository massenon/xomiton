import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:xomiton/features/auth/presentation/splash_carousel_page.dart';

import '../support/harness.dart';

/// Les six textes validés par le client le 2026-10-29 (tutoiement), mot pour mot
/// (apostrophes typographiques de l'application).
const _validated = [
  ('Un espace à toi.', 'Ici, tu peux être toi-même. Xomiton t’écoute, sans jugement.'),
  ('Ta parole compte.', 'Tu n’as plus à te taire. Raconte, demande, partage, à ton rythme.'),
  ('En sécurité.', 'Un bouton SOS et un signalement anonyme sont toujours à portée de main.'),
  ('Une communauté qui te comprend.', 'Rencontre des personnes qui vivent la même chose que toi.'),
  ('Des réponses fiables.', 'Ressources, droits, santé : de l’information claire, vérifiée.'),
  ('Ta vie privée d’abord.', 'Tu décides de ce que tu montres. Ton vrai nom n’est jamais demandé.'),
];

void main() {
  final s = loadStrings();

  test('les six textes validés sont intégrés tels quels', () {
    for (var i = 0; i < _validated.length; i++) {
      expect(s.t('mobile_onboarding_slide_${i + 1}_title'), _validated[i].$1);
      expect(s.t('mobile_onboarding_slide_${i + 1}_body'), _validated[i].$2);
      expect(s.t('mobile_onboarding_slide_${i + 1}_alt'), isNotEmpty);
    }
    expect(kSplashSlides.length, 6);
  });

  test('une photo différente par diapositive, présente sur le disque', () {
    final paths = kSplashSlides.map((e) => e.image).toList();
    expect(paths.toSet().length, 6);
    for (final p in paths) {
      expect(File(p).existsSync(), isTrue, reason: p);
    }
  });

  test('budget d’images de l’application : moins de 700 Ko', () {
    var total = 0;
    for (final f in Directory('assets/images').listSync(recursive: true).whereType<File>()) {
      total += f.lengthSync();
    }
    expect(total, lessThan(700 * 1024), reason: '$total octets');
  });

  test('les visuels basse résolution sont en bandeau (jamais agrandis en plein écran)', () {
    final banners = kSplashSlides.where((e) => e.fit == SlideFit.banner).map((e) => e.image);
    expect(banners, containsAll(['assets/images/splash/symbole-venus.webp', 'assets/images/splash/bouche-scotchee.webp']));
  });

  testWidgets('chaque diapositive affiche titre (en-tête accessible) et texte validés', (tester) async {
    final handle = tester.ensureSemantics();
    usePhone(tester);
    addTearDown(tester.view.reset);
    await tester.pumpWidget(await TestEnv(api: FakeApi(), seen: false).app());
    await tester.pumpAndSettle();
    for (var i = 0; i < _validated.length; i++) {
      expect(find.text(_validated[i].$1), findsOneWidget, reason: 'titre ${i + 1}');
      expect(find.text(_validated[i].$2), findsOneWidget, reason: 'texte ${i + 1}');
      expect(
        tester.getSemantics(find.text(_validated[i].$1)).flagsCollection.isHeader,
        isTrue,
      );
      if (i < _validated.length - 1) {
        await tester.tap(find.byKey(const ValueKey('splash-next')));
        await tester.pumpAndSettle();
      }
    }
    // Dernière diapositive : « Créer un compte » et « Se connecter », plus de « Suivant ».
    expect(find.byKey(const ValueKey('splash-signup')), findsOneWidget);
    expect(find.byKey(const ValueKey('splash-login')), findsOneWidget);
    expect(find.byKey(const ValueKey('splash-next')), findsNothing);
    // Cibles d'au moins 44 px.
    for (final k in ['splash-signup', 'splash-login', 'splash-skip']) {
      expect(tester.getSize(find.byKey(ValueKey(k))).height, greaterThanOrEqualTo(44), reason: k);
    }
    handle.dispose();
  });

  testWidgets('petit écran (360 × 640) : titre et texte de la dernière diapositive ne débordent pas', (tester) async {
    tester.view.physicalSize = const Size(360 * 2, 640 * 2);
    tester.view.devicePixelRatio = 2;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(await TestEnv(api: FakeApi(), seen: false).app());
    await tester.pumpAndSettle();
    for (var i = 0; i < 5; i++) {
      await tester.tap(find.byKey(const ValueKey('splash-next')));
      await tester.pumpAndSettle();
    }
    expect(tester.takeException(), isNull);
    final title = tester.getTopLeft(find.text(_validated[5].$1));
    expect(title.dy, greaterThan(80), reason: 'sous la barre du logo');
  });
}
