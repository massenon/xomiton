import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/core/network/idempotency.dart';
import 'package:xomiton/core/platform/platform_services.dart';
import 'package:xomiton/features/admin/data/admin_models.dart';

import '../support/admin_harness.dart';
import '../support/fakes.dart';
import '../support/harness.dart';
import '../support/pair_harness.dart' show currentPath, revealFinder, revealKey;

final S = loadStrings();

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('contrat des écritures admin', () {
    test('Idempotency-Key obligatoire sur POST / PUT / DELETE admin/*, pas sur GET ni step-up', () {
      expect(needsIdempotencyKey('POST', 'admin/alerts/01J/acknowledge'), isTrue);
      expect(needsIdempotencyKey('PUT', 'admin/members/3/role'), isTrue);
      expect(needsIdempotencyKey('DELETE', 'admin/invitations/9'), isTrue);
      expect(needsIdempotencyKey('GET', 'admin/alerts'), isFalse);
      expect(needsIdempotencyKey('POST', 'admin/step-up'), isFalse);
      expect(needsIdempotencyKey('POST', '/api/v1/admin/maintenance/enable'), isTrue);
      expect(needsIdempotencyKey('POST', 'pair/alerts/01J/acknowledge'), isFalse);
    });

    test('compte à rebours d’escalade : 5 min moins l’âge, arrêté par l’accusé', () {
      AdminAlert a(Map<String, dynamic> j) => AdminAlert.fromJson(adminAlertRow('x', age: 2).map((k, v) => MapEntry(k, j[k] ?? v)));
      expect(a({}).escalationMinutesLeft(), 3);
      expect(a({'age_minutes': 9}).escalationMinutesLeft(), 0);
      expect(a({'acknowledged': true}).escalationMinutesLeft(), isNull);
      expect(a({'escalated': true}).escalationMinutesLeft(), isNull);
      expect(a({'type': 'besoin'}).escalationMinutesLeft(), isNull);
    });

    test('modèles tolérants : champs absents ou position nulle', () {
      final d = AdminAlertDetail.fromJson({'id': 'z', 'type': 'sos', 'status': 'nouveau'});
      expect(d.position, isNull);
      expect(d.notified, isEmpty);
      expect(d.can.acknowledge, isFalse);
      final dash = AdminDashboard.fromJson({'alerts': null, 'pending_accounts': 4});
      expect(dash.alerts, isNull);
      expect(dash.pendingPeople, 4);
      expect(dash.pollSeconds, 30);
    });
  });

  group('tableau d’astreinte (A1)', () {
    testWidgets('SOS actifs avec compte à rebours, indicateurs, à traiter, maintenance', (tester) async {
      final env = await openAdmin(tester, routes: adminRoutes(extra: {
        'GET admin/alerts': (o) => jsonBody(200, {
          'data': [adminAlertRow('s1', age: 2)],
          'meta': {'current_page': 1, 'last_page': 1, 'new': 1},
        }),
      }));
      expect(currentPath(tester), '/admin/astreinte');
      expect(find.text(S.t('mobile_admin_dash_title')), findsOneWidget);
      expect(find.text(S.t('mobile_admin_on_call_me')), findsOneWidget);
      expect(find.text(S.t('mobile_admin_sos_banner', {'count': 1})), findsOneWidget);
      expect(find.text(S.t('mobile_admin_escalation_in', {'minutes': 3})), findsOneWidget);
      expect(find.bySemanticsLabel('${S.t('mobile_admin_kpi_new')} : 3'), findsOneWidget);
      expect(find.bySemanticsLabel('${S.t('mobile_admin_kpi_people')} : 3'), findsOneWidget);
      expect(find.text(S.t('mobile_admin_todo_people', {'count': 3})), findsOneWidget);
      expect(find.text(S.t('mobile_admin_todo_moderation', {'count': 3})), findsOneWidget);
      expect(env.api.calls('GET admin/alerts').single.queryParameters['type'], 'sos');
    });

    testWidgets('accuser réception : POST avec clé d’idempotence, escalade arrêtée', (tester) async {
      var acked = false;
      final env = await openAdmin(tester, routes: adminRoutes(extra: {
        'GET admin/alerts': (o) => jsonBody(200, {
          'data': [adminAlertRow('s1', ack: acked)],
          'meta': {'current_page': 1, 'last_page': 1},
        }),
        'POST admin/alerts/s1/acknowledge': (o) {
          acked = true;
          return jsonBody(200, {'data': adminAlertDetail('s1', ack: true, status: 'pris_en_charge')});
        },
      }));
      await tester.tap(find.byKey(const ValueKey('admin-sos-ack-s1')));
      await tester.pumpAndSettle();
      final req = env.api.calls('POST admin/alerts/s1/acknowledge').single;
      expect(req.headers['Idempotency-Key'], isNotEmpty);
      expect(find.text(S.t('mobile_admin_ack_stopped')), findsOneWidget);
      expect(find.byKey(const ValueKey('admin-sos-ack-s1')), findsNothing);
    });

    testWidgets('hors ligne : bandeau + action bloquée avec message clair, aucune requête d’écriture', (tester) async {
      final env = await openAdmin(tester, online: false, routes: adminRoutes(extra: {
        'GET admin/dashboard': (o) => throw offlineError(o),
      }));
      expect(find.text(S.t('mobile_admin_offline_banner')), findsWidgets);
      expect(env.api.calls('POST admin/alerts/s1/acknowledge'), isEmpty);
    });

    testWidgets('hors ligne après lecture : l’accusé est refusé sans appel', (tester) async {
      final env = await openAdmin(tester, online: false, routes: adminRoutes(extra: {
        'GET admin/alerts': (o) => jsonBody(200, {
          'data': [adminAlertRow('s1')],
          'meta': {'current_page': 1, 'last_page': 1},
        }),
      }));
      await revealKey(tester, 'admin-sos-ack-s1');
      await tester.tap(find.byKey(const ValueKey('admin-sos-ack-s1')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_admin_offline_blocked')), findsOneWidget);
      expect(env.api.calls('POST admin/alerts/s1/acknowledge'), isEmpty);
    });

    testWidgets('sans la permission d’un bloc : il n’est pas affiché (null)', (tester) async {
      await openAdmin(tester, routes: adminRoutes(extra: {
        'GET admin/dashboard': (_) => jsonBody(200, {
          'data': {
            'alerts': null,
            'pending_accounts': 1,
            'pending_peer_requests': null,
            'pending_moderation': null,
            'open_sync_conflicts': null,
            'maintenance': {'state': 'inactive', 'active': false},
            'on_call': {'me': false, 'admins': 1, 'peers': 0},
            'poll_seconds': 30,
          },
        }),
      }));
      expect(find.text(S.t('mobile_admin_kpis')), findsNothing);
      expect(find.text(S.t('mobile_admin_todo_people', {'count': 1})), findsOneWidget);
      expect(find.text(S.t('mobile_admin_on_call_me')), findsNothing);
    });

    testWidgets('rien n’est persisté : aucun coffre ne reçoit de donnée admin', (tester) async {
      final env = await openAdmin(tester);
      expect(env.db.peerData.keys.where((k) => k.contains('admin')), isEmpty);
      expect(env.db.privateCache.keys.where((k) => k.contains('admin')), isEmpty);
      expect(env.db.publicCache.keys.where((k) => k.contains('admin')), isEmpty);
      expect(env.db.outbox.keys, isEmpty);
    });
  });

  group('alertes (A2)', () {
    testWidgets('liste : types, statut, lieu, routage, filtre par type envoyé au serveur', (tester) async {
      final env = await openAdmin(tester, goTo_: '/admin/alertes');
      expect(find.text(S.t('mobile_admin_alerts_title')), findsOneWidget);
      expect(find.text(S.t('mobile_admin_alerts_count', {'count': 2})), findsOneWidget);
      expect(find.text('SOS · Abomey-Calavi'), findsOneWidget);
      expect(find.text(S.t('mobile_admin_routed', {'name': 'Point focal Atlantique'})), findsWidgets);
      expect(find.byKey(const ValueKey('admin-ack-a1')), findsOneWidget);
      expect(find.byKey(const ValueKey('admin-ack-a2')), findsNothing);
      await tester.tap(find.byKey(const ValueKey('admin-type-sos')));
      await tester.pumpAndSettle();
      expect(env.api.calls('GET admin/alerts').last.queryParameters['type'], 'sos');
    });

    testWidgets('recherche dans la liste chargée et réinitialisation', (tester) async {
      await openAdmin(tester, goTo_: '/admin/alertes');
      await tester.enterText(find.byKey(const ValueKey('admin-alert-search')), 'cotonou');
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_admin_alerts_count', {'count': 1})), findsOneWidget);
      await tester.enterText(find.byKey(const ValueKey('admin-alert-search')), 'zzz');
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_admin_alerts_empty')), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('admin-alerts-reset')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_admin_alerts_count', {'count': 2})), findsOneWidget);
    });

    testWidgets('droit retiré (403) : message clair, pas d’erreur brute', (tester) async {
      await openAdmin(tester, goTo_: '/admin/alertes', routes: adminRoutes(extra: {
        'GET admin/alerts': (_) => jsonBody(403, {'message': 'Interdit', 'code': 'forbidden'}),
      }));
      expect(find.text(S.t('mobile_admin_forbidden')), findsOneWidget);
    });
  });

  group('détail d’une alerte (A3)', () {
    Map<String, FakeHandler> detailRoutes({Map<String, FakeHandler> extra = const {}, Map<String, dynamic>? detail}) =>
        adminRoutes(extra: {
          'GET admin/alerts/a1': (_) => jsonBody(200, {'data': detail ?? adminAlertDetail('a1')}),
          ...extra,
        });

    testWidgets('position arrondie, personnes prévenues, notes ; jamais de position exacte', (tester) async {
      await openAdmin(tester, routes: detailRoutes(), goTo_: '/admin/alertes/a1');
      await revealFinder(tester, find.textContaining('6,45'));
      expect(find.text('6,45° N, 2,36° E · ${S.t('mobile_admin_gps_rounded')} · ${S.t('mobile_admin_accuracy', {'meters': 35})}'), findsOneWidget);
      await revealKey(tester, 'admin-call-k1');
      expect(find.text('Point focal Atlantique'), findsWidgets);
      expect(find.text('Contact de confiance n° 1'), findsOneWidget);
      expect(find.byKey(const ValueKey('admin-call-k1')), findsOneWidget);
      expect(find.byKey(const ValueKey('admin-call-k2')), findsNothing);
      expect(find.text('Appel tenté.'), findsOneWidget);
    });

    testWidgets('Maps : avertissement d’abord, rien n’est ouvert si on annule', (tester) async {
      final launcher = FakeLauncher();
      await openAdmin(tester,
          routes: detailRoutes(), goTo_: '/admin/alertes/a1', overrides: [linkLauncherProvider.overrideWithValue(launcher)]);
      await revealKey(tester, 'admin-maps');
      await tester.tap(find.byKey(const ValueKey('admin-maps')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_admin_maps_title')), findsOneWidget);
      await tester.tap(find.text(S.t('common_cancel')));
      await tester.pumpAndSettle();
      expect(launcher.opened, isEmpty);
      await tester.tap(find.byKey(const ValueKey('admin-maps')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('admin-confirm-yes')));
      await tester.pumpAndSettle();
      expect(launcher.opened.single.toString(), contains('query=6.45,2.36'));
    });

    testWidgets('sans position : texte « sans position » et pas de bouton Maps', (tester) async {
      await openAdmin(tester,
          routes: detailRoutes(detail: {...adminAlertDetail('a1'), 'position': null}), goTo_: '/admin/alertes/a1');
      await revealFinder(tester, find.text(S.t('mobile_admin_no_gps')));
      expect(find.byKey(const ValueKey('admin-maps')), findsNothing);
      expect(find.text(S.t('mobile_admin_no_gps')), findsOneWidget);
    });

    testWidgets('actions : seuls les boutons permis par can{…} sont proposés', (tester) async {
      await openAdmin(tester,
          routes: detailRoutes(detail: adminAlertDetail('a1', can: {'acknowledge': true, 'take': false, 'close': false, 'reassign': false, 'note': false, 'statuses': []})),
          goTo_: '/admin/alertes/a1');
      await revealKey(tester, 'admin-act-ack');
      expect(find.byKey(const ValueKey('admin-act-ack')), findsOneWidget);
      for (final k in ['take', 'status', 'reassign', 'note', 'close']) {
        expect(find.byKey(ValueKey('admin-act-$k')), findsNothing, reason: k);
      }
    });

    testWidgets('prendre en charge, changer le statut, note interne', (tester) async {
      final env = await openAdmin(tester, goTo_: '/admin/alertes/a1', routes: detailRoutes(extra: {
        'POST admin/alerts/a1/take': (_) => jsonBody(200, {'data': adminAlertDetail('a1', status: 'en_cours', ack: true)}),
        'POST admin/alerts/a1/status': (_) => jsonBody(200, {'data': adminAlertDetail('a1', status: 'traite', ack: true)}),
        'POST admin/alerts/a1/notes': (_) => jsonBody(201, {'data': adminAlertDetail('a1', status: 'en_cours', ack: true)}),
      }));
      await revealKey(tester, 'admin-act-take');
      await tester.tap(find.byKey(const ValueKey('admin-act-take')));
      await tester.pumpAndSettle();
      expect(env.api.calls('POST admin/alerts/a1/take'), hasLength(1));
      await revealKey(tester, 'admin-act-status');
      await tester.tap(find.byKey(const ValueKey('admin-act-status')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('admin-status-opt-traite')));
      await tester.pumpAndSettle();
      expect((env.api.calls('POST admin/alerts/a1/status').single.data as Map)['status'], 'traite');
      await tester.pump(const Duration(seconds: 6));
      await tester.pumpAndSettle();
      await revealKey(tester, 'admin-act-note');
      await tester.tap(find.byKey(const ValueKey('admin-act-note')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('admin-note-add')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_admin_note_empty')), findsOneWidget);
      await tester.pump(const Duration(seconds: 6));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const ValueKey('admin-note-field')), 'Rappel fait.');
      await tester.tap(find.byKey(const ValueKey('admin-note-add')));
      await tester.pumpAndSettle();
      expect((env.api.calls('POST admin/alerts/a1/notes').single.data as Map)['body'], 'Rappel fait.');
    });

    testWidgets('clore : confirmation d’abord', (tester) async {
      final env = await openAdmin(tester, goTo_: '/admin/alertes/a1', routes: detailRoutes(extra: {
        'POST admin/alerts/a1/close': (_) => jsonBody(200, {'data': adminAlertDetail('a1', status: 'clos', ack: true)}),
      }));
      await revealKey(tester, 'admin-act-close');
      await tester.tap(find.byKey(const ValueKey('admin-act-close')));
      await tester.pumpAndSettle();
      await tester.tap(find.text(S.t('common_cancel')));
      await tester.pumpAndSettle();
      expect(env.api.calls('POST admin/alerts/a1/close'), isEmpty);
      await tester.tap(find.byKey(const ValueKey('admin-act-close')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('admin-confirm-yes')));
      await tester.pumpAndSettle();
      expect(env.api.calls('POST admin/alerts/a1/close'), hasLength(1));
    });

    testWidgets('réattribuer : mot de passe ressaisi, jeton à usage unique en en-tête', (tester) async {
      final env = await openAdmin(tester, goTo_: '/admin/alertes/a1', routes: detailRoutes(extra: {
        'GET admin/alerts/a1/contacts': (_) => jsonBody(200, {
          'data': [
            {'id': 7, 'name': 'Juriste Atlantique', 'role': 'juriste', 'zone': 'Atlantique'},
          ],
        }),
        'POST admin/step-up': (o) {
          final pw = (o.data as Map)['password'];
          return pw == 'bon-mot-de-passe'
              ? jsonBody(200, {'data': {'token': 'tok-un-usage', 'expires_in': 300, 'header': 'X-Step-Up-Token'}})
              : jsonBody(422, {'message': 'x', 'errors': {'password': ['Le mot de passe est incorrect.']}});
        },
        'POST admin/alerts/a1/reassign': (_) => jsonBody(200, {'data': adminAlertDetail('a1', ack: true)}),
      }));
      await revealKey(tester, 'admin-act-reassign');
      await tester.tap(find.byKey(const ValueKey('admin-act-reassign')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('admin-contact-7')));
      await tester.pumpAndSettle();
      // Mauvais mot de passe : erreur dans la boîte, aucune réattribution.
      await tester.enterText(find.byKey(const ValueKey('step-up-password')), 'faux');
      await tester.tap(find.byKey(const ValueKey('step-up-confirm')));
      await tester.pumpAndSettle();
      expect(find.text('Le mot de passe est incorrect.'), findsOneWidget);
      expect(env.api.calls('POST admin/alerts/a1/reassign'), isEmpty);
      await tester.enterText(find.byKey(const ValueKey('step-up-password')), 'bon-mot-de-passe');
      await tester.tap(find.byKey(const ValueKey('step-up-confirm')));
      await tester.pumpAndSettle();
      final req = env.api.calls('POST admin/alerts/a1/reassign').single;
      expect(req.headers['X-Step-Up-Token'], 'tok-un-usage');
      expect((req.data as Map)['contact_id'], 7);
      expect(req.headers['Idempotency-Key'], isNotEmpty);
      // Le mot de passe n'est envoyé qu'à step-up, jamais à l'action.
      expect(req.data.toString(), isNot(contains('bon-mot')));
    });

    testWidgets('jeton expiré : message clair (403 step_up_required)', (tester) async {
      await openAdmin(tester, goTo_: '/admin/alertes/a1', routes: detailRoutes(extra: {
        'GET admin/alerts/a1/contacts': (_) => jsonBody(200, {
          'data': [
            {'id': 7, 'name': 'Juriste Atlantique', 'role': 'juriste', 'zone': 'Atlantique'},
          ],
        }),
        'POST admin/step-up': (_) => jsonBody(200, {'data': {'token': 't', 'expires_in': 300}}),
        'POST admin/alerts/a1/reassign': (_) => jsonBody(403, {'message': 'Confirmation requise.', 'code': 'step_up_required'}),
      }));
      await revealKey(tester, 'admin-act-reassign');
      await tester.tap(find.byKey(const ValueKey('admin-act-reassign')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('admin-contact-7')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const ValueKey('step-up-password')), 'x');
      await tester.tap(find.byKey(const ValueKey('step-up-confirm')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_admin_step_up_expired')), findsOneWidget);
    });

    testWidgets('alerte inconnue ou plus accessible : message neutre', (tester) async {
      await openAdmin(tester, goTo_: '/admin/alertes/zzz');
      expect(find.text(S.t('mobile_admin_alert_gone')), findsOneWidget);
    });
  });
}
