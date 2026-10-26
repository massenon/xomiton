import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/core/platform/platform_services.dart';
import 'package:xomiton/core/providers/core_providers.dart';

import '../support/fakes.dart';
import '../support/harness.dart';
import 'alerts_test.dart' show referentials, urgences;

final S = loadStrings();

Map<String, dynamic> created({String code = 'SIG-A7K2M9P4QX'}) => {
  'data': {
    'id': 5,
    'tracking_code': 'SIGA7K2M9P4QX',
    'tracking_code_display': code,
    'type': 'report',
    'status': 'nouveau',
    'status_label': 'Nouveau',
  },
};

FakeApi apiWith(Map<String, FakeHandler> extra) => FakeApi({
  'GET me': (_) => jsonBody(200, {'data': userJson()}),
  ...referentials,
  ...extra,
});

TestEnv guestEnv(FakeApi api, {bool online = true}) => TestEnv(
  api: api,
  seen: true,
  online: online,
  extra: [
    locationServiceProvider.overrideWithValue(FakeLocation()),
    linkLauncherProvider.overrideWithValue(FakeLauncher()),
    evidencePickerProvider.overrideWithValue(
      FakePicker(const [
        PickedEvidence('preuve.pdf', '/nonexistent/preuve.pdf', 1200),
      ]),
    ),
  ],
);

Future<void> boot(WidgetTester tester, TestEnv env, String route) async {
  usePhone(tester);
  addTearDown(tester.view.reset);
  await tester.pumpWidget(await env.app());
  await settle(tester);
  goTo(tester, route);
  await settle(tester, 8);
}

Future<void> next(WidgetTester tester) async {
  await tester.tap(find.text(S.t('common_next')));
  await settle(tester, 4);
}

/// Remplit les six étapes d'un signalement (type « violence »).
Future<void> fillReport(WidgetTester tester, TestEnv env) async {
  expect(
    find.text(S.t('alerts_report_step', {'current': 1, 'total': 6})),
    findsOneWidget,
  );
  // Étape 1 : type depuis l'API.
  await tester.tap(find.byType(DropdownButtonFormField<String>));
  await settle(tester, 3);
  await tester.tap(find.text('Violence conjugale').last);
  await settle(tester, 3);
  await next(tester);
  // Étape 2 : faits (≥ 20 caractères).
  expect(
    find.text(S.t('alerts_report_step', {'current': 2, 'total': 6})),
    findsOneWidget,
  );
  await tester.tap(find.text(S.t('common_next')));
  await settle(tester, 3);
  expect(
    find.text(
      S.t('alerts_report_fields_description_min', {'min': 20}).isEmpty
          ? ''
          : vRequiredText(),
    ),
    findsOneWidget,
  );
  await tester.enterText(
    find.byType(TextField).first,
    'Mon conjoint me menace depuis des semaines.',
  );
  await next(tester);
  expect(
    env.db.drafts.get('report'),
    isNotNull,
    reason: 'brouillon enregistré à chaque étape',
  );
  // Étape 3 : département obligatoire.
  expect(
    find.text(S.t('alerts_report_step', {'current': 3, 'total': 6})),
    findsOneWidget,
  );
  await tester.tap(find.byType(DropdownButtonFormField<int>).first);
  await settle(tester, 3);
  await tester.tap(find.text('Littoral').last);
  await settle(tester, 3);
  await next(tester);
  // Étape 4 : preuves (facultatif).
  await tester.tap(find.text(S.t('mobile_report_add_files')));
  await settle(tester, 3);
  expect(find.text('preuve.pdf'), findsOneWidget);
  await next(tester);
  // Étape 5 : contact + canal.
  await tester.tap(find.text(S.t('alerts_report_fields_contactable_yes')));
  await settle(tester, 3);
  await tester.enterText(find.byType(TextField).first, '+229 01 97 38 88 47');
  await tester.tap(find.text(S.t('alerts_report_channels_whatsapp')));
  await settle(tester, 3);
  await next(tester);
  // Étape 6 : vérification. Le brouillon ne contient ni contact ni position.
  expect(find.text(S.t('alerts_report_review_title')), findsWidgets);
  final draft = env.db.drafts.get('report')!;
  expect(draft.contains('97 38'), isFalse);
  expect(draft.contains('preuve'), isFalse);
  expect(draft.contains('"lat"'), isFalse);
}

String vRequiredText() => S.t('validation_required', {
  'attribute': S.t('validation_attributes_description'),
});

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('signalement en 6 étapes (sans compte)', () {
    testWidgets(
      'même payload que le web, Idempotency-Key, code de suivi affiché',
      (tester) async {
        final api = apiWith({
          'POST signalements': (_) => jsonBody(201, created()),
        });
        final env = guestEnv(api);
        await boot(tester, env, '/signaler');
        await fillReport(tester, env);

        await tester.tap(find.text(S.t('buttons_help_send_report')));
        await settle(tester, 8);
        final call = api.calls('POST signalements').single;
        expect(
          call.headers['Idempotency-Key'],
          matches(RegExp(r'^[0-9a-f-]{36}$')),
        );
        expect(
          call.headers.containsKey('Authorization'),
          isFalse,
          reason: 'sans compte',
        );
        final b = call.data as Map;
        expect(b['type'], 'report');
        expect(b['category'], 'conjugale');
        expect(b['description'], 'Mon conjoint me menace depuis des semaines.');
        expect(b['zone_id'], 1);
        expect(b['anonymous'], isTrue);
        expect(b['contactable'], isTrue);
        expect(b['contact'], '+229 01 97 38 88 47');
        expect(b['preferred_channel'], 'whatsapp');
        expect(find.byKey(const Key('tracking-code')), findsOneWidget);
        expect(find.text('SIG-A7K2M9P4QX'), findsOneWidget);
        expect(
          env.db.drafts.get('report'),
          isNull,
          reason: 'brouillon effacé après envoi',
        );
        expect(containerOf(tester).read(outboxProvider).list(), isEmpty);
        await disposeApp(tester);
      },
    );

    testWidgets(
      'hors ligne : en attente d’envoi, puis code reçu au rejeu, sans doublon',
      (tester) async {
        var online = false;
        final api = apiWith({
          'POST signalements': (o) {
            if (!online) throw offlineError(o);
            return jsonBody(201, created(code: 'SIG-ZZZ'));
          },
        });
        final env = guestEnv(api, online: false);
        await boot(tester, env, '/signaler');
        await fillReport(tester, env);
        await tester.tap(find.text(S.t('buttons_help_send_report')));
        await settle(tester, 8);

        expect(find.text(S.t('mobile_report_waiting_title')), findsOneWidget);
        expect(
          find.byKey(const Key('tracking-code')),
          findsNothing,
          reason: 'le code est attribué par le serveur',
        );
        final stored = env.db.outbox.keys.toList();
        expect(stored, hasLength(1));
        expect(
          env.db.outbox.get(stored.single)!.contains('"owner":null'),
          isTrue,
        );

        online = true;
        unawaited(containerOf(tester).read(outboxProvider).flush());
        await settle(tester, 8);
        expect(find.text('SIG-ZZZ'), findsWidgets);
        final calls = api.calls('POST signalements');
        expect(calls, hasLength(2));
        expect(
          calls.map((c) => c.headers['Idempotency-Key']).toSet(),
          hasLength(1),
        );
        expect(env.db.outbox.keys, isEmpty);
        await disposeApp(tester);
      },
    );

    testWidgets(
      '« Exprimer un besoin » : besoins obligatoires, canal non exigé si anonyme',
      (tester) async {
        final api = apiWith({
          'POST signalements': (_) => jsonBody(201, created()),
        });
        final env = guestEnv(api);
        await boot(tester, env, '/signaler?type=need');
        await next(tester); // pas de catégorie requise pour un besoin
        await tester.enterText(
          find.byType(TextField).first,
          'Je cherche un hébergement sûr cette semaine.',
        );
        await next(tester);
        await tester.tap(find.text(S.t('common_next')));
        await settle(tester, 3);
        expect(
          find.text(S.t('alerts_report_fields_needs_error')),
          findsOneWidget,
        );
        await tester.tap(find.text('Hébergement'));
        await tester.tap(find.byType(DropdownButtonFormField<int>).first);
        await settle(tester, 3);
        await tester.tap(find.text('Atlantique').last);
        await settle(tester, 3);
        for (var i = 0; i < 3; i++) {
          await next(tester);
        }
        await tester.tap(find.text(S.t('buttons_help_send_report')));
        await settle(tester, 8);
        final b = api.calls('POST signalements').single.data as Map;
        expect(b['type'], 'need');
        expect(b['needs'], ['hebergement']);
        expect(b.containsKey('category'), isFalse);
        expect(b['contactable'], isFalse);
        expect(b.containsKey('contact'), isFalse);
        expect(find.text(S.t('alerts_report_done_need_title')), findsOneWidget);
        await disposeApp(tester);
      },
    );

    testWidgets('brouillon repris à la demande seulement', (tester) async {
      final api = apiWith({});
      final env = guestEnv(api);
      await env.db.drafts.put(
        'report',
        '{"step":1,"type":"report","category":"conjugale","description":"Un récit assez long pour continuer.","needs":[],"zone_id":1,"saved_at":"${DateTime.now().toIso8601String()}"}',
      );
      await boot(tester, env, '/signaler');
      expect(find.text(S.t('alerts_report_draft_found')), findsOneWidget);
      expect(
        find.text(S.t('alerts_report_step', {'current': 1, 'total': 6})),
        findsOneWidget,
      );
      await tester.tap(find.text(S.t('alerts_report_draft_resume')));
      await settle(tester, 3);
      expect(
        find.text(S.t('alerts_report_step', {'current': 2, 'total': 6})),
        findsOneWidget,
      );
      expect(find.text('Un récit assez long pour continuer.'), findsOneWidget);
      await disposeApp(tester);
    });
  });

  group('suivi, mes signalements, urgences', () {
    testWidgets('suivi par code sans compte, 404 neutre', (tester) async {
      final api = apiWith({
        'GET signalements/SIG-A7K2M9P4QX': (_) => jsonBody(200, {
          'data': {
            'tracking_code_display': 'SIG-A7K2M9P4QX',
            'type': 'report',
            'status': 'en_cours',
            'status_label': 'En cours',
            'status_text': 'En cours de traitement.',
            'received_at': '2026-10-20T10:00:00+01:00',
            'acknowledged': true,
            'routed_role': 'juriste',
            'history': [
              {
                'status': 'nouveau',
                'status_label': 'Nouveau',
                'at': '2026-10-20T10:00:00+01:00',
              },
              {
                'status': 'en_cours',
                'status_label': 'En cours',
                'at': '2026-10-21T10:00:00+01:00',
              },
            ],
          },
        }),
      });
      await boot(tester, guestEnv(api), '/suivi');
      await tester.enterText(find.byType(TextField), 'SIG-A7K2M9P4QX');
      await tester.tap(find.text(S.t('alerts_report_tracking_submit')));
      await settle(tester, 6);
      expect(
        find.text(S.t('alerts_report_tracking_status', {'status': 'En cours'})),
        findsOneWidget,
      );
      expect(
        find.text(S.t('alerts_report_tracking_handled_by')),
        findsOneWidget,
      );
      expect(
        api
            .calls('GET signalements/SIG-A7K2M9P4QX')
            .single
            .headers
            .containsKey('Authorization'),
        isFalse,
      );

      await tester.enterText(find.byType(TextField), 'SIG-INCONNU');
      await tester.tap(find.text(S.t('alerts_report_tracking_submit')));
      await settle(tester, 6);
      expect(
        find.text(S.t('alerts_report_tracking_not_found', {'days': 90})),
        findsOneWidget,
      );
      await disposeApp(tester);
    });

    testWidgets('« Mes signalements » : liste du compte', (tester) async {
      final api = apiWith({
        'GET signalements/mine': (_) => jsonBody(200, {
          'data': [
            {
              'tracking_code_display': 'SIG-AAA',
              'type': 'report',
              'status': 'nouveau',
              'status_label': 'Nouveau',
              'created_at': '2026-10-20T10:00:00+01:00',
            },
          ],
          'meta': {'current_page': 1, 'last_page': 1},
        }),
      });
      final env = TestEnv(api: api, token: 'tok', cachedUser: userJson());
      await boot(tester, env, '/signalements');
      expect(find.text('SIG-AAA'), findsOneWidget);
      await disposeApp(tester);
    });

    testWidgets(
      'urgences depuis l’API, puis depuis le cache Hive sans réseau',
      (tester) async {
        var online = true;
        final launcher = FakeLauncher();
        final api = apiWith({
          'GET urgences': (o) {
            if (!online) throw offlineError(o);
            return jsonBody(200, urgences, headers: {'ETag': '"u1"'});
          },
        });
        final env = TestEnv(
          api: api,
          seen: true,
          extra: [linkLauncherProvider.overrideWithValue(launcher)],
        );
        await boot(tester, env, '/urgences');
        expect(find.text('Police secours · 117'), findsOneWidget);
        expect(find.text('Awa K.'), findsOneWidget);

        online = false;
        goTo(tester, '/login');
        await settle(tester, 10);
        goTo(tester, '/urgences');
        await settle(tester, 8);
        expect(find.text('Police secours · 117'), findsOneWidget);
        expect(find.text(S.t('mobile_stale_notice')), findsOneWidget);
        await tester.tap(find.text('Police secours · 117'));
        expect(launcher.opened.single.toString(), 'tel:117');
        await disposeApp(tester);
      },
    );
  });
}
