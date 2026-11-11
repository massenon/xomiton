import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/app/router.dart';
import 'package:xomiton/core/network/app_toast.dart';
import 'package:xomiton/core/platform/discreet_icon.dart';
import 'package:xomiton/core/platform/legal_links.dart';
import 'package:xomiton/core/platform/platform_services.dart';
import 'package:xomiton/core/platform/platform_ui.dart';
import 'package:xomiton/core/providers/core_providers.dart';
import 'package:xomiton/core/security/screen_security.dart';

import '../support/fakes.dart';
import '../support/harness.dart';
import '../support/pair_harness.dart';

final S = loadStrings();

class FakeCapture implements ScreenCaptureSource {
  final StreamController<ScreenCaptureEvent> controller = StreamController<ScreenCaptureEvent>.broadcast();
  bool captured = false;
  @override
  Stream<ScreenCaptureEvent> get events => controller.stream;
  @override
  Future<bool> isCaptured() async => captured;
}

class FakeLauncher implements LinkLauncher {
  final List<Uri> opened = [];
  @override
  Future<bool> open(Uri uri) async {
    opened.add(uri);
    return true;
  }
}

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('iOS : adaptations pures (plan iOS §3, HIG)', () {
    test('5 onglets au plus sur iOS : l’onglet « autre espace » est retiré, pas sur Android', () {
      expect(kIosMaxTabs, 5);
      expect(showOtherSpaceTab(TargetPlatform.iOS), isFalse);
      expect(showOtherSpaceTab(TargetPlatform.android), isTrue);
      expect(isIosPlatform(TargetPlatform.iOS), isTrue);
      expect(isIosPlatform(TargetPlatform.android), isFalse);
    });

    test('texte d’aide du mode discret : iOS dit que le nom reste, Android garde « Notes », sinon indisponible', () {
      expect(discreetHelpKey(supported: true, ios: true), 'mobile_security_discreet_ios_help');
      expect(discreetHelpKey(supported: true, ios: false), 'mobile_security_discreet_help');
      expect(discreetHelpKey(supported: false, ios: false), 'mobile_security_discreet_unsupported_help');
      expect(S.t('mobile_security_discreet_ios_help'), contains('Xomiton'));
      expect(S.t('mobile_security_discreet_ios_help'), isNot(contains('Notes')), reason: 'l’icône iOS n’imite pas « Notes »');
    });

    test('liens légaux : adresses HTTPS du site, politique, conditions et contact', () {
      expect(kLegalLinks.map((l) => l.uri.toString()), [
        'https://xomiton.app/politique-de-confidentialite',
        'https://xomiton.app/conditions-utilisation',
        'https://xomiton.app/mentions-legales',
      ]);
      for (final l in kLegalLinks) {
        expect(isAllowedExternalUri(l.uri), isTrue, reason: l.key);
        expect(S.t(l.labelKey), isNot(l.labelKey), reason: 'libellé manquant : ${l.labelKey}');
      }
    });
  });

  group('canal de capture d’écran (natif iOS → Dart)', () {
    test('capture d’écran et enregistrement arrivent comme événements, hors iOS rien n’est écouté', () async {
      TestWidgetsFlutterBinding.ensureInitialized();
      const channel = MethodChannel('test.xomiton/capture');
      final capture = ChannelScreenCapture(enabled: true, channel: channel);
      final events = <ScreenCaptureEvent>[];
      final sub = capture.events.listen(events.add);
      Future<void> send(String method, [Object? args]) =>
          TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.handlePlatformMessage(
            channel.name,
            channel.codec.encodeMethodCall(MethodCall(method, args)),
            (_) {},
          );
      await send('screenshot');
      await send('captureChanged', {'captured': true});
      await send('captureChanged', {'captured': false});
      await Future<void>.delayed(Duration.zero);
      expect(events[0], isA<ScreenshotTaken>());
      expect((events[1] as CaptureChanged).captured, isTrue);
      expect((events[2] as CaptureChanged).captured, isFalse);
      await sub.cancel();

      final off = ChannelScreenCapture(enabled: false, channel: const MethodChannel('test.xomiton/capture-off'));
      expect(await off.isCaptured(), isFalse);
    });
  });

  group('informations légales dans Paramètres', () {
    testWidgets('trois liens (politique, conditions, contact) ouvrent les adresses HTTPS du site', (tester) async {
      final launcher = FakeLauncher();
      final api = FakeApi({
        'GET me': (_) => jsonBody(200, {'data': userJson()}),
        'GET me/settings': (_) => jsonBody(200, {
              'data': {'theme': 'auto', 'locale': 'fr', 'language': 'fr', 'sounds': true, 'sounds_enabled': true, 'notifications': true},
            }),
      });
      final env = TestEnv(api: api, token: 'tok', cachedUser: userJson(), extra: [linkLauncherProvider.overrideWithValue(launcher)]);
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await settle(tester);
      goTo(tester, '/parametres');
      await settle(tester, 10);
      for (final l in kLegalLinks) {
        final tile = find.byKey(Key('legal-${l.key}'));
        await tester.ensureVisible(tile);
        await tester.pump();
        await tester.tap(tile);
        await tester.pump();
      }
      expect(launcher.opened.map((u) => u.toString()), [
        'https://xomiton.app/politique-de-confidentialite',
        'https://xomiton.app/conditions-utilisation',
        'https://xomiton.app/mentions-legales',
      ]);
      await disposeApp(tester);
    });
  });

  group('barre Membre sur iPhone', () {
    testWidgets('compte à deux espaces : 5 onglets sur iOS, aucun onglet « autre espace » ; le profil garde « Changer d’espace »', (tester) async {
      debugDefaultTargetPlatformOverride = TargetPlatform.iOS;
      try {
        await openPair(tester, routes: pairRoutes(), goTo_: '/pair/dossiers');
        await tester.tap(find.byKey(const ValueKey('tab-peer-network')));
        await tester.pumpAndSettle();
        expect(currentPath(tester), '/feed');
        expect(find.byKey(const ValueKey('tab-other-space')), findsNothing);
        expect(tester.widget<NavigationBar>(find.byType(NavigationBar)).destinations, hasLength(5));
        goTo(tester, '/profile');
        await tester.pumpAndSettle();
        expect(find.byKey(const ValueKey('my-spaces-switch')), findsOneWidget);
        await disposeApp(tester);
      } finally {
        debugDefaultTargetPlatformOverride = null;
      }
    });

    testWidgets('même compte sur Android : 6 onglets, comme avant', (tester) async {
      await openPair(tester, routes: pairRoutes(), goTo_: '/pair/dossiers');
      await tester.tap(find.byKey(const ValueKey('tab-peer-network')));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('tab-other-space')), findsOneWidget);
      expect(tester.widget<NavigationBar>(find.byType(NavigationBar)).destinations, hasLength(6));
      await disposeApp(tester);
    });
  });

  group('recouvrement et détection de capture (iOS)', () {
    testWidgets('enregistrement d’écran sur un écran sensible : recouvert ; fin : visible ; écran non sensible : jamais', (tester) async {
      final capture = FakeCapture();
      await openPair(tester, routes: pairRoutes(), overrides: [screenCaptureProvider.overrideWithValue(capture)]);
      expect(find.byKey(const ValueKey('privacy-cover')), findsNothing);
      capture.controller.add(const CaptureChanged(true));
      await tester.pump();
      await tester.pump();
      expect(find.byKey(const ValueKey('privacy-cover')), findsOneWidget);
      capture.controller.add(const CaptureChanged(false));
      await tester.pump();
      await tester.pump();
      expect(find.byKey(const ValueKey('privacy-cover')), findsNothing);
      goTo(tester, '/feed');
      await tester.pumpAndSettle();
      capture.controller.add(const CaptureChanged(true));
      await tester.pump();
      await tester.pump();
      expect(find.byKey(const ValueKey('privacy-cover')), findsNothing, reason: 'le fil reste capturable');
      await disposeApp(tester);
    });

    testWidgets('capture d’écran sur un écran sensible : message d’alerte ; sur le fil : rien', (tester) async {
      final capture = FakeCapture();
      await openPair(tester, routes: pairRoutes(), overrides: [screenCaptureProvider.overrideWithValue(capture)]);
      final container = ProviderScope.containerOf(tester.element(find.byType(Scaffold).first));
      final toasts = <AppToast>[];
      final sub = container.read(toastBusProvider).stream.listen(toasts.add);
      capture.controller.add(const ScreenshotTaken());
      await tester.pump();
      await tester.pump();
      expect(toasts.whereType<ScreenshotToast>(), hasLength(1));
      expect(S.t('mobile_screenshot_notice'), isNot('mobile_screenshot_notice'));
      container.read(routerProvider).go('/feed');
      await tester.pumpAndSettle();
      capture.controller.add(const ScreenshotTaken());
      await tester.pump();
      await tester.pump();
      expect(toasts.whereType<ScreenshotToast>(), hasLength(1), reason: 'pas d’alerte hors écran sensible');
      unawaited(sub.cancel());
      await disposeApp(tester);
    });
  });
}
