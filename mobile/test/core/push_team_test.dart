import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/app/router.dart';
import 'package:xomiton/core/providers/core_providers.dart';
import 'package:xomiton/features/admin/application/admin_providers.dart';
import 'package:xomiton/core/push/push_registrar.dart';
import 'package:xomiton/core/push/push_service.dart';

import '../support/admin_harness.dart';
import '../support/fakes.dart';
import '../support/harness.dart';
import '../support/pair_harness.dart' show pairRoutes, peerUser;

final S = loadStrings();

String location(WidgetTester tester) {
  final c = ProviderScope.containerOf(tester.element(find.byType(MaterialApp)));
  return c.read(routerProvider).routerDelegate.state.matchedLocation;
}

Future<void> pin(WidgetTester tester, String code) async {
  for (final d in code.split('')) {
    await tester.tap(find.byKey(Key('pin-key-$d')));
    await tester.pump();
  }
  await tester.pumpAndSettle();
}

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('types d’équipe (API §24)', () {
    test('ouvertures profondes', () {
      String r(String t, [String? id]) => PushPayload(t, id).route;
      expect(r('alert_routed', '01JALERT'), '/pair/alertes/01JALERT');
      expect(r('alert_routed'), '/pair/alertes');
      expect(r('duty_sos', '01JALERT'), '/admin/alertes/01JALERT');
      expect(r('duty_alert', '01JALERT'), '/admin/alertes/01JALERT');
      expect(r('peer_pending', '42'), '/admin/inscriptions'); // l'identifiant n'a aucun effet
      expect(r('content_reported', '9'), '/admin/moderation');
      expect(r('type_inconnu', '1'), '/notifications');
    });

    test('canal « Astreinte » (duty) pour l’astreinte, communauté pour le reste ; muet jamais pour l’équipe', () {
      expect(PushChannels.duty, 'duty');
      for (final t in ['alert_routed', 'duty_sos', 'duty_alert']) {
        expect(PushPayload(t, 'x').channel(sounds: false), 'duty', reason: t);
        expect(PushPayload(t, 'x').isUrgent, isTrue);
      }
      expect(const PushPayload('peer_pending', null).channel(sounds: false), PushChannels.community);
      expect(const PushPayload('content_reported', null).channel(sounds: true), PushChannels.community);
      expect(const PushPayload('message', '1').channel(sounds: false), PushChannels.communitySilent);
      expect(const PushPayload('sos', null).channel(sounds: false), PushChannels.sos);
    });

    test('sync et rules sont silencieux', () {
      expect(const PushPayload('sync', null).isSilent, isTrue);
      expect(const PushPayload('rules', null).isSilent, isTrue);
      expect(const PushPayload('duty_sos', 'x').isSilent, isFalse);
    });

    test('charge utile : seulement {type, id}, jamais de récit, de position ni de nom', () {
      final p = PushPayload.fromData({
        'type': 'duty_sos',
        'id': '01JALERT',
        'category': 'sos',
        'url': '/admin/alertes/01JALERT',
        'body': 'Elle est enfermée à Ouidah',
        'position': '6.36,2.08',
        'author': 'Awa',
      })!;
      expect(p.toJson(), {'type': 'duty_sos', 'id': '01JALERT'});
    });

    test('textes : tous définis ; aperçus masqués = « Xomiton » et corps neutre, sans SOS ni lieu', () {
      for (final t in ['alert_routed', 'duty_sos', 'duty_alert', 'peer_pending', 'content_reported']) {
        final p = PushPayload(t, '1');
        for (final masked in [true, false]) {
          expect(S.has(p.titleKey(masked: masked)), isTrue, reason: '$t title masked=$masked');
          expect(S.has(p.bodyKey(masked: masked)), isTrue, reason: '$t body masked=$masked');
        }
        expect(S.t(p.titleKey(masked: true)), 'Xomiton');
        final body = S.t(p.bodyKey(masked: true));
        expect(body, isNot(matches(RegExp('SOS|VBG|admin|pair', caseSensitive: false))));
      }
      expect(S.t(const PushPayload('duty_sos', 'x').bodyKey(masked: true)), 'Alerte urgente');
      expect(S.t(const PushPayload('duty_alert', 'x').bodyKey(masked: true)), 'Nouvelle alerte');
      expect(S.t(const PushPayload('peer_pending', 'x').bodyKey(masked: true)), 'Nouvelle demande');
      expect(S.t(const PushPayload('content_reported', 'x').bodyKey(masked: true)), 'Signalement à modérer');
    });
  });

  group('ouverture profonde avec changement d’espace', () {
    testWidgets('admin : duty_sos ouvre l’alerte, espace Admin activé', (tester) async {
      final push = FakePush();
      await openAdmin(tester, overrides: [pushServiceProvider.overrideWithValue(push)]);
      push.opened.add(const PushPayload('duty_sos', 'a1'));
      await settle(tester, 10);
      expect(location(tester), '/admin/alertes/a1');
    });

    testWidgets('admin : peer_pending ouvre la liste des inscriptions, content_reported la modération', (tester) async {
      final push = FakePush();
      await openAdmin(tester, overrides: [pushServiceProvider.overrideWithValue(push)]);
      push.opened.add(const PushPayload('peer_pending', '5'));
      await settle(tester, 10);
      expect(location(tester), '/admin/inscriptions');
      push.opened.add(const PushPayload('content_reported', '9'));
      await settle(tester, 10);
      expect(location(tester), '/admin/moderation');
    });

    testWidgets('membre sans espace Admin : aucune ouverture, retour à l’accueil Membre', (tester) async {
      final push = FakePush();
      final env = TestEnv(
        api: FakeApi({'GET me': (_) => jsonBody(200, {'data': userJson()}), ...okPushRoutes()}),
        token: 'tok',
        cachedUser: userJson(),
        extra: [pushServiceProvider.overrideWithValue(push)],
      );
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await settle(tester, 10);
      push.opened.add(const PushPayload('duty_sos', 'a1'));
      await settle(tester, 10);
      expect(location(tester), '/home');
      push.opened.add(const PushPayload('alert_routed', 'a1'));
      await settle(tester, 10);
      expect(location(tester), '/home');
    });

    testWidgets('application verrouillée : l’écran ne s’ouvre qu’après le code PIN', (tester) async {
      final push = FakePush(initial: const PushPayload('duty_sos', 'a1'));
      final env = TestEnv(
        api: FakeApi(adminRoutes()),
        token: 'tok',
        cachedUser: adminUser(),
        pin: kAdminPin,
        initialPrefs: {'active_space': 'admin', 'discreet_default_applied': true},
        extra: [adminPollEnabledProvider.overrideWithValue(false), pushServiceProvider.overrideWithValue(push)],
      );
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('pin-key-1')), findsOneWidget);
      expect(location(tester), isNot('/admin/alertes/a1'));
      await pin(tester, kAdminPin);
      await settle(tester, 6);
      expect(location(tester), '/admin/alertes/a1');
    });

    testWidgets('pair : alert_routed ouvre l’alerte dans l’espace pair', (tester) async {
      final push = FakePush();
      final env = TestEnv(
        api: FakeApi(pairRoutes()),
        token: 'tok',
        cachedUser: peerUser(),
        pin: '4821',
        initialPrefs: {'active_space': 'member', 'discreet_default_applied': true},
        extra: [pushServiceProvider.overrideWithValue(push)],
      );
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await tester.pumpAndSettle();
      await pin(tester, '4821');
      push.opened.add(const PushPayload('alert_routed', 'a1'));
      await settle(tester, 10);
      expect(location(tester), '/pair/alertes/a1');
    });
  });

  group('messages silencieux', () {
    Future<(TestEnv, FakePush)> peerApp(WidgetTester tester, {Map<String, Object> prefs = const {}}) async {
      final push = FakePush();
      final env = TestEnv(
        api: FakeApi(pairRoutes()),
        token: 'tok',
        cachedUser: peerUser(),
        pin: '4821',
        initialPrefs: {'active_space': 'peer', 'discreet_default_applied': true, ...prefs},
        extra: [pushServiceProvider.overrideWithValue(push)],
      );
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await tester.pumpAndSettle();
      await pin(tester, '4821');
      return (env, push);
    }

    testWidgets('sync : un cycle de synchronisation est lancé, rien n’est affiché ni ouvert', (tester) async {
      final (env, push) = await peerApp(tester);
      final before = env.api.calls('GET sync/pull').length;
      final where = location(tester);
      push.silent.add(const PushPayload('sync', null));
      await settle(tester, 10);
      expect(env.api.calls('GET sync/pull').length, greaterThan(before));
      expect(location(tester), where);
    });

    testWidgets('rules : les règles de validation sont relues', (tester) async {
      final (env, push) = await peerApp(tester);
      final before = env.api.calls('GET pair/validation-rules').length;
      push.silent.add(const PushPayload('rules', null));
      await settle(tester, 10);
      expect(env.api.calls('GET pair/validation-rules').length, greaterThan(before));
    });

    testWidgets('reçu application fermée : la demande notée est traitée au démarrage, puis effacée', (tester) async {
      final (env, _) = await peerApp(tester, prefs: {kPrefPendingRules: true});
      await settle(tester, 10);
      expect(env.api.calls('GET pair/validation-rules'), isNotEmpty);
      final c = ProviderScope.containerOf(tester.element(find.byType(MaterialApp)));
      expect(c.read(sharedPreferencesProvider).getBool(kPrefPendingRules), isNull);
    });

    testWidgets('membre sans espace pair : un push silencieux ne déclenche aucun appel pair', (tester) async {
      final push = FakePush();
      final api = FakeApi({'GET me': (_) => jsonBody(200, {'data': userJson()}), ...okPushRoutes()});
      final env = TestEnv(api: api, token: 'tok', cachedUser: userJson(), extra: [pushServiceProvider.overrideWithValue(push)]);
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await settle(tester, 10);
      push.silent.add(const PushPayload('sync', null));
      push.silent.add(const PushPayload('rules', null));
      await settle(tester, 10);
      expect(api.calls('GET sync/pull'), isEmpty);
      expect(api.calls('GET pair/validation-rules'), isEmpty);
    });
  });
}

Map<String, FakeHandler> okPushRoutes() => {
  'POST me/devices': (_) => jsonBody(201, {'data': {}}),
  'POST me/push-subscriptions': (_) => jsonBody(201, {'data': {}}),
  'GET dashboard': (_) => jsonBody(200, {
    'data': {
      'tiles': [],
      'counters': {'notifications': 0, 'messages': 0},
      'profile': {'completion': 100, 'complete': true},
      'featured': {'total': 0, 'items': []},
      'next_event': null,
    },
  }),
};
