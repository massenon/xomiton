import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:xomiton/core/storage/secret_store.dart';
import 'package:xomiton/features/security/application/pin_lock.dart';
import 'package:xomiton/features/security/application/pin_service.dart';

import '../support/fakes.dart';
import '../support/harness.dart';

Future<void> tapPin(WidgetTester tester, String pin) async {
  for (final d in pin.split('')) {
    await tester.tap(find.byKey(Key('pin-key-$d')));
    await tester.pump();
  }
  await settle(tester);
}

final S = loadStrings();

void main() {
  group('PinService', () {
    test('haché avec sel : le code n’apparaît jamais, deux enregistrements diffèrent', () async {
      final store = MemorySecretStore();
      final svc = PinService(store, iterations: 5);
      await svc.set('4821');
      final first = store.data[SecretKeys.pinRecord]!;
      expect(first.contains('4821'), isFalse);
      final rec = jsonDecode(first) as Map<String, dynamic>;
      expect(rec.keys, containsAll(['salt', 'iter', 'hash']));
      expect(rec['iter'], 5);
      await svc.set('4821');
      expect(store.data[SecretKeys.pinRecord], isNot(first), reason: 'sel aléatoire');
      expect((await svc.verify('4821')).ok, isTrue);
      expect((await svc.verify('0000')).ok, isFalse);
    });

    test('format : exactement 4 chiffres', () async {
      final svc = PinService(MemorySecretStore(), iterations: 2);
      expect(PinService.isValid('1234'), isTrue);
      expect(PinService.isValid('123'), isFalse);
      expect(PinService.isValid('12a4'), isFalse);
      expect(() => svc.set('12'), throwsArgumentError);
      await svc.set('1234');
      expect((await svc.verify('12')).ok, isFalse);
    });

    test('comparaison à temps constant (pas de sortie anticipée)', () {
      expect(PinService.constantTimeEquals([1, 2, 3], [1, 2, 3]), isTrue);
      expect(PinService.constantTimeEquals([1, 2, 3], [9, 2, 3]), isFalse);
      expect(PinService.constantTimeEquals([1, 2, 3], [1, 2, 9]), isFalse);
      expect(PinService.constantTimeEquals([1, 2], [1, 2, 3]), isFalse);
    });

    test('5 essais, persistants : le bon code ne passe plus ensuite', () async {
      final store = MemorySecretStore();
      final svc = PinService(store, iterations: 2);
      await svc.set('1234');
      final remaining = <int>[];
      for (var i = 0; i < 5; i++) {
        // Un nouveau service à chaque essai : le compteur survit à un redémarrage.
        final r = await PinService(store, iterations: 2).verify('0000');
        expect(r.ok, isFalse);
        remaining.add(r.remaining);
      }
      expect(remaining, [4, 3, 2, 1, 0]);
      final last = await svc.verify('1234');
      expect(last.ok, isFalse);
      expect(last.exhausted, isTrue);
    });

    test('un essai réussi remet le compteur à zéro', () async {
      final svc = PinService(MemorySecretStore(), iterations: 2);
      await svc.set('1234');
      await svc.verify('0000');
      await svc.verify('0000');
      expect((await svc.verify('1234')).ok, isTrue);
      expect(await svc.attempts(), 0);
    });
  });

  group('verrouillage', () {
    TestEnv env0(FakeApi api) => TestEnv(
      api: api,
      token: 'tok',
      cachedUser: userJson(),
      extra: [pinIterationsProvider.overrideWithValue(2)],
    );

    testWidgets('demandé à l’ouverture ; mauvais code puis bon code', (tester) async {
      final api = FakeApi({'GET me': (_) => jsonBody(200, {'data': userJson()})});
      final env = env0(api);
      await PinService(env.secrets, iterations: 2).set('4821');
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await settle(tester);

      expect(find.byKey(const Key('pin-key-1')), findsOneWidget);
      await tapPin(tester, '0000');
      expect(find.byKey(const Key('pin-wrong')), findsOneWidget);
      expect(find.textContaining('4 essais'), findsOneWidget);
      await tapPin(tester, '4821');
      expect(find.byKey(const Key('pin-key-1')), findsNothing);
      await disposeApp(tester);
    });

    testWidgets('SOS, urgences et signalement restent accessibles sans code', (tester) async {
      final api = FakeApi({'GET me': (_) => jsonBody(200, {'data': userJson()})});
      final env = env0(api);
      await PinService(env.secrets, iterations: 2).set('4821');
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await settle(tester);

      expect(find.byKey(const Key('pin-free-sos')), findsOneWidget);
      expect(find.byKey(const Key('pin-free-emergency')), findsOneWidget);
      expect(find.byKey(const Key('pin-free-report')), findsOneWidget);

      await tester.tap(find.byKey(const Key('pin-free-sos')));
      await settle(tester, 10);
      expect(find.byKey(const Key('pin-key-1')), findsNothing, reason: 'le SOS ne demande pas le code');
      expect(containerOf(tester).read(pinLockProvider).locked, isTrue, reason: 'le verrou reste actif');

      // Quitter les écrans de sécurité : le verrou revient.
      goTo(tester, '/home');
      await settle(tester, 10);
      expect(find.byKey(const Key('pin-key-1')), findsOneWidget);

      await tester.tap(find.byKey(const Key('pin-free-report')));
      await settle(tester, 10);
      expect(find.byKey(const Key('pin-key-1')), findsNothing);
      await disposeApp(tester);
    });

    testWidgets('5 essais ratés : session fermée, stockage privé effacé, reconnexion', (tester) async {
      final api = FakeApi({'GET me': (_) => jsonBody(200, {'data': userJson()})});
      final env = env0(api);
      await PinService(env.secrets, iterations: 2).set('4821');
      await env.secrets.write(SecretKeys.token, 'tok');
      await env.secrets.write(SecretKeys.deviceToken, 'dev');
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await settle(tester);

      for (var i = 0; i < 5; i++) {
        await tapPin(tester, '0000');
        if (i < 4) {
          expect(find.byKey(const Key('pin-wrong')), findsOneWidget);
          // L'écran se remet à zéro après chaque essai.
        }
      }
      await settle(tester, 10);
      expect(find.text(S.t('auth_login_heading')), findsOneWidget);
      expect(env.secrets.data.containsKey(SecretKeys.pinRecord), isFalse);
      expect(env.secrets.data.containsKey(SecretKeys.token), isFalse);
      expect(env.secrets.data.containsKey(SecretKeys.deviceToken), isFalse, reason: 'un code OTP sera redemandé');
      expect(env.db.session.get('user'), isNull);
      await disposeApp(tester);
    });

    testWidgets('retour en avant-plan : verrou seulement après le délai', (tester) async {
      var now = DateTime(2026, 10, 26, 10);
      final api = FakeApi({'GET me': (_) => jsonBody(200, {'data': userJson()})});
      final env = TestEnv(
        api: api,
        token: 'tok',
        cachedUser: userJson(),
        extra: [
          pinIterationsProvider.overrideWithValue(2),
          clockProvider.overrideWithValue(() => now),
        ],
      );
      await PinService(env.secrets, iterations: 2).set('4821');
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await settle(tester);
      await tapPin(tester, '4821');
      final c = containerOf(tester);
      expect(c.read(pinLockProvider).locked, isFalse);

      c.read(pinLockProvider.notifier).onBackground();
      now = now.add(const Duration(seconds: 20));
      c.read(pinLockProvider.notifier).onForeground();
      expect(c.read(pinLockProvider).locked, isFalse, reason: 'délai non atteint');

      c.read(pinLockProvider.notifier).onBackground();
      now = now.add(const Duration(minutes: 5));
      c.read(pinLockProvider.notifier).onForeground();
      await settle(tester);
      expect(c.read(pinLockProvider).locked, isTrue);
      await disposeApp(tester);
    });
  });
}
