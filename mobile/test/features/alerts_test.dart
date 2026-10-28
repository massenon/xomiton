import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/core/platform/platform_services.dart';
import 'package:xomiton/core/providers/core_providers.dart';
import 'package:xomiton/features/alerts/presentation/sos_button.dart';

import '../support/fakes.dart';
import '../support/harness.dart';

final S = loadStrings();

Map<String, dynamic> sosData({
  bool cancelled = false,
  bool safe = false,
  String? token = 'tok-secret',
}) => {
  'data': {
    'id': '01SOS',
    'tracking_code': 'XM-1',
    'type': 'sos',
    'status': safe ? 'traite' : 'nouveau',
    'cancelled': cancelled,
    'safe': safe,
    'located': true,
    'acknowledged': false,
    'can_cancel_until': DateTime.now()
        .add(const Duration(seconds: 60))
        .toIso8601String(),
    'notified': [
      {
        'key': 'k1',
        'kind': 'focal',
        'label': 'Marie A.',
        'role': 'Point focal',
        'state': 'sent',
        'state_label': 'Prévenu·e',
        'phone': '+22901000000',
      },
    ],
    'primary_contact': {'label': 'Marie A.', 'phone': '+22901000000'},
    'sos_token': ?token,
  },
  'meta': {'poll_interval_seconds': cancelled || safe ? null : 10},
};

final urgences = {
  'data': {
    'services': [
      {'label': 'Police secours', 'number': '117', 'tel': 'tel:117'},
    ],
    'contacts': [
      {
        'id': 1,
        'name': 'Awa K.',
        'role_label': 'Psychologue',
        'phone': '+22901111111',
        'availability': '8 h – 18 h',
      },
    ],
  },
  'meta': {'count': 1},
};

final referentials = {
  'GET zones': (RequestOptions o) => jsonBody(
    200,
    {
      'data': [
        {
          'id': 1,
          'name': 'Littoral',
          'communes': [
            {'id': 3, 'name': 'Cotonou'},
          ],
        },
        {'id': 2, 'name': 'Atlantique', 'communes': []},
      ],
    },
    headers: {'ETag': '"z1"'},
  ),
  'GET violence-types': (RequestOptions o) => jsonBody(
    200,
    {
      'data': [
        {'slug': 'conjugale', 'label': 'Violence conjugale'},
        {'slug': 'harcelement', 'label': 'Harcèlement'},
      ],
    },
    headers: {'ETag': '"v1"'},
  ),
  'GET needs': (RequestOptions o) => jsonBody(
    200,
    {
      'data': [
        {'slug': 'hebergement', 'label': 'Hébergement'},
        {'slug': 'soutien', 'label': 'Soutien psychologique'},
      ],
    },
    headers: {'ETag': '"n1"'},
  ),
  'GET urgences': (RequestOptions o) =>
      jsonBody(200, urgences, headers: {'ETag': '"u1"'}),
};

TestEnv member(
  FakeApi api, {
  FakeLocation? loc,
  FakeLauncher? launcher,
  bool online = true,
}) => TestEnv(
  api: api,
  token: 'tok',
  cachedUser: userJson(),
  online: online,
  extra: [
    locationServiceProvider.overrideWithValue(loc ?? FakeLocation()),
    linkLauncherProvider.overrideWithValue(launcher ?? FakeLauncher()),
    evidencePickerProvider.overrideWithValue(FakePicker(const [])),
  ],
);

Map<String, dynamic> meUser = userJson();

FakeApi baseApi([Map<String, FakeHandler>? extra]) => FakeApi({
  'GET me': (_) => jsonBody(200, {'data': meUser}),
  'GET dashboard': (_) => jsonBody(404, {'message': 'x', 'code': 'not_found'}),
  ...referentials,
  ...?extra,
});

Future<void> boot(WidgetTester tester, TestEnv env, String route) async {
  usePhone(tester);
  addTearDown(tester.view.reset);
  await tester.pumpWidget(await env.app());
  await settle(tester);
  goTo(tester, route);
  await settle(tester, 8);
}

/// Maintien de 2 s sur le bouton SOS.
Future<void> holdSos(WidgetTester tester) async {
  final g = await tester.startGesture(
    tester.getCenter(find.byType(SosHoldButton)),
  );
  for (var i = 0; i < 5; i++) {
    await tester.pump(const Duration(milliseconds: 500));
  }
  await g.up();
  await tester.pump();
}

Future<void> countdown(WidgetTester tester, [int seconds = 5]) async {
  for (var i = 0; i < seconds; i++) {
    await tester.pump(const Duration(seconds: 1));
  }
  await settle(tester);
}

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('SOS', () {
    testWidgets(
      'maintien de 2 s puis décompte de 5 s annulable : rien ne part',
      (tester) async {
        final api = baseApi();
        await boot(tester, member(api), '/sos');
        expect(find.byType(SosHoldButton), findsOneWidget);

        // Un appui trop court ne déclenche rien.
        final g = await tester.startGesture(
          tester.getCenter(find.byType(SosHoldButton)),
        );
        await tester.pump(const Duration(milliseconds: 800));
        await g.up();
        await tester.pump();
        expect(find.byKey(const Key('sos-countdown')), findsNothing);

        await holdSos(tester);
        expect(find.byKey(const Key('sos-countdown')), findsOneWidget);
        expect(find.text('5'), findsOneWidget);
        await tester.pump(const Duration(seconds: 2));
        expect(find.text('3'), findsOneWidget);

        await tester.tap(find.text(S.t('components_sos_cancel')));
        await settle(tester);
        expect(find.byType(SosHoldButton), findsOneWidget);
        await tester.pump(const Duration(seconds: 10));
        expect(
          api.calls('POST sos'),
          isEmpty,
          reason: 'annulé en local : aucun envoi',
        );
        expect(containerOf(tester).read(outboxProvider).list(), isEmpty);
        await disposeApp(tester);
      },
    );

    testWidgets(
      'alerte envoyée : position, Idempotency-Key, sondage 10 s avec X-Sos-Token, en sécurité',
      (tester) async {
        var safe = false;
        final api = baseApi({
          'POST sos': (_) => jsonBody(201, sosData()),
          'GET sos/01SOS': (_) =>
              jsonBody(200, sosData(token: null, safe: safe)),
          'POST sos/01SOS/safe': (_) {
            safe = true;
            return jsonBody(200, sosData(token: null, safe: true));
          },
        });
        final launcher = FakeLauncher();
        final env = member(api, launcher: launcher);
        await boot(tester, env, '/sos');
        await holdSos(tester);
        await countdown(tester);

        expect(find.text(S.t('alerts_sos_done_title')), findsOneWidget);
        final post = api.calls('POST sos').single;
        expect(
          post.headers['Idempotency-Key'],
          matches(RegExp(r'^[0-9a-f-]{36}$')),
        );
        final body = post.data as Map;
        expect(body['share_location'], isTrue);
        expect(body['lat'], 6.3703);
        expect(body['accuracy'], 12);
        // Contact principal appelable.
        await tester.tap(
          find.text(
            S.t('alerts_sos_done_call_number', {'number': '+22901000000'}),
          ),
        );
        expect(launcher.opened.single.toString(), 'tel:+22901000000');

        // Sondage toutes les 10 s, jeton anonyme en en-tête.
        await tester.pump(const Duration(seconds: 10));
        await settle(tester);
        expect(api.calls('GET sos/01SOS'), hasLength(1));
        expect(
          api.calls('GET sos/01SOS').single.headers['X-Sos-Token'],
          'tok-secret',
        );

        await tapVisible(tester, find.text(S.t('alerts_sos_done_safe')));
        await settle(tester);
        expect(
          api.calls('POST sos/01SOS/safe').single.headers['X-Sos-Token'],
          'tok-secret',
        );
        expect(
          find.text(S.t('alerts_sos_done_safe_confirmed')),
          findsOneWidget,
        );

        // Jamais persisté : ni dans la boîte d'envoi ni dans les caches.
        final db = env.db;
        final stored = [
          db.outbox,
          db.drafts,
          db.privateCache,
          db.publicCache,
          db.session,
        ].expand((b) => b.keys.map(b.get)).join();
        expect(stored.contains('tok-secret'), isFalse);
        expect(containerOf(tester).read(outboxProvider).list(), isEmpty);
        await disposeApp(tester);
      },
    );

    testWidgets('repentir (cancel) après envoi : fausse alerte', (
      tester,
    ) async {
      final api = baseApi({
        'POST sos': (_) => jsonBody(201, sosData()),
        'POST sos/01SOS/cancel': (_) =>
            jsonBody(200, sosData(token: null, cancelled: true)),
      });
      await boot(tester, member(api), '/sos');
      await holdSos(tester);
      await countdown(tester);
      await tapVisible(tester, find.text(S.t('alerts_sos_done_cancel')));
      await settle(tester);
      expect(find.text(S.t('alerts_sos_done_undone_title')), findsOneWidget);
      expect(api.calls('POST sos/01SOS/cancel'), hasLength(1));
      await disposeApp(tester);
    });

    testWidgets(
      'mode avion : file locale, appel possible, un seul SOS au retour du réseau',
      (tester) async {
        var online = false;
        final api = baseApi({
          'POST sos': (o) {
            if (!online) throw offlineError(o);
            return jsonBody(201, sosData());
          },
          'GET sos/01SOS': (_) => jsonBody(200, sosData(token: null)),
        });
        final launcher = FakeLauncher();
        final env = member(api, launcher: launcher, online: false);
        await boot(tester, env, '/sos');
        await holdSos(tester);
        await countdown(tester);

        expect(find.text(S.t('alerts_sos_queued_title')), findsOneWidget);
        expect(find.text(S.t('mobile_sos_queued_saved')), findsOneWidget);
        final queued = env.db.outbox.keys.toList();
        expect(
          queued,
          hasLength(1),
          reason: 'écrit dans le Hive avant tout envoi',
        );
        // Numéro d'urgence du cache, affiché sans réseau.
        await tester.tap(find.text('Police secours · 117'));
        expect(launcher.opened.single.toString(), 'tel:117');

        online = true;
        unawaited(containerOf(tester).read(outboxProvider).flush());
        await settle(tester);
        expect(find.text(S.t('alerts_sos_done_title')), findsOneWidget);
        final calls = api.calls('POST sos');
        expect(calls, hasLength(2));
        expect(calls.map((c) => c.headers['Idempotency-Key']).toSet(), {
          queued.single,
        });
        expect(env.db.outbox.keys, isEmpty);
        unawaited(containerOf(tester).read(outboxProvider).flush());
        await settle(tester);
        expect(
          api.calls('POST sos'),
          hasLength(2),
          reason: 'jamais de doublon',
        );
        await disposeApp(tester);
      },
    );

    testWidgets(
      'sans GPS (Q61) : envoi direct sans position, sans seconde attente',
      (tester) async {
        final api = baseApi({'POST sos': (_) => jsonBody(201, sosData())});
        await boot(tester, member(api, loc: FakeLocation(null)), '/sos');
        await holdSos(tester);
        await countdown(tester);
        await settle(tester);
        expect(find.text(S.t('alerts_sos_manual_title')), findsNothing);
        final body = api.calls('POST sos').single.data as Map;
        expect(body.containsKey('lat'), isFalse);
        expect(find.text(S.t('alerts_sos_done_title')), findsOneWidget);
        await disposeApp(tester);
      },
    );

    testWidgets('préférence sos_share_gps désactivée : pas de localisation', (
      tester,
    ) async {
      final api = baseApi({'POST sos': (_) => jsonBody(201, sosData())});
      final loc = FakeLocation();
      final user = userJson()
        ..['settings'] = {'sos_share_gps': false}
        ..['profile'] = {
          'completion': 43,
          'zone': {'id': 2, 'name': 'Atlantique'},
        };
      meUser = user;
      addTearDown(() => meUser = userJson());
      final env = TestEnv(
        api: api,
        token: 'tok',
        cachedUser: user,
        extra: [
          locationServiceProvider.overrideWithValue(loc),
          linkLauncherProvider.overrideWithValue(FakeLauncher()),
        ],
      );
      await boot(tester, env, '/sos');
      await holdSos(tester);
      await countdown(tester);
      expect(loc.calls, 0);
      final body = api.calls('POST sos').single.data as Map;
      expect(body['share_location'], isFalse);
      expect(body['zone_id'], 2);
      await disposeApp(tester);
    });

    testWidgets(
      'sans compte : /sos mène à l’écran public, urgences depuis l’API',
      (tester) async {
        final api = baseApi({'POST sos': (_) => jsonBody(201, sosData())});
        final env = TestEnv(
          api: api,
          seen: true,
          extra: [
            locationServiceProvider.overrideWithValue(FakeLocation()),
            linkLauncherProvider.overrideWithValue(FakeLauncher()),
          ],
        );
        await boot(tester, env, '/sos');
        expect(find.byType(SosHoldButton), findsOneWidget);
        expect(
          find.text(S.t('alerts_sos_doubt', {'number': '117'})),
          findsOneWidget,
        );
        await holdSos(tester);
        await countdown(tester);
        expect(find.text(S.t('alerts_sos_done_title')), findsOneWidget);
        expect(
          api.calls('POST sos').single.headers.containsKey('Authorization'),
          isFalse,
        );
        await disposeApp(tester);
      },
    );
  });
}
