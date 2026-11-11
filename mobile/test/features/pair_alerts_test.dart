import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/features/pair/application/pair_providers.dart';

import '../support/fakes.dart';
import '../support/harness.dart';
import '../support/pair_harness.dart';

final S = loadStrings();

Map<String, dynamic> alertDetail(
  String id, {
  String status = 'nouveau',
  bool ack = false,
  Map<String, dynamic>? can,
  Map<String, dynamic>? position,
  String? contact = '+22997000000',
}) => {
  ...alertRow(id, status: status, ack: ack, category: 'Violence physique'),
  'needs': [
    {'slug': 'securite', 'label': 'Mise en sécurité'},
  ],
  'description': 'Elle demande de l’aide.',
  'author': 'Anonyme',
  'anonymous': true,
  'contact': contact,
  'preferred_channel_label': 'Appel',
  'position': ?position,
  'history': [
    {'status': 'nouveau', 'status_label': 'Nouveau', 'at': DateTime.now().toUtc().toIso8601String()},
  ],
  'can': can ?? {'acknowledge': !ack, 'take': true, 'close': false, 'statuses': ['pris_en_charge', 'en_cours']},
};

Future<void> reveal(WidgetTester tester, String key) =>
    tester.scrollUntilVisible(find.byKey(ValueKey(key)), 250, scrollable: find.byType(Scrollable).first);

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('accueil pair (P1)', () {
    testWidgets('chiffres de ses dossiers, tâches du jour, raccourcis, badge de garde', (tester) async {
      await openPair(tester, routes: pairRoutes(peer: peerSection(onDuty: true)));
      expect(currentPath(tester), '/pair/accueil');
      expect(find.text(S.t('peer_home_title')), findsWidgets);
      expect(find.text(S.t('peer_home_greeting', {'name': 'Awa_S'})), findsOneWidget);
      expect(find.text(S.t('mobile_pair_duty')), findsOneWidget);
      expect(find.text('12'), findsOneWidget);
      expect(find.bySemanticsLabel('${S.t('peer_home_kpi_collected')} : 12'), findsOneWidget);
      expect(find.text(S.t('peer_home_tasks_alerts_to_take', {'count': 1})), findsOneWidget);
      expect(find.text(S.t('peer_home_tasks_cases_to_contact', {'count': 2})), findsOneWidget);
      await tester.scrollUntilVisible(find.text(S.t('peer_home_shortcuts_collecte')), 200);
      for (final k in ['collecte', 'cases', 'report', 'directory', 'resources']) {
        expect(find.text(S.t('peer_home_shortcuts_$k')), findsOneWidget);
      }
    });

    testWidgets('sans tâche : textes « aucune … » ; pas de badge de garde', (tester) async {
      await openPair(tester, routes: pairRoutes(peer: peerSection(toTake: 0, toContact: 0)));
      expect(find.text(S.t('mobile_pair_tasks_alerts_none')), findsOneWidget);
      expect(find.text(S.t('mobile_pair_tasks_cases_none')), findsOneWidget);
      expect(find.text(S.t('mobile_pair_duty')), findsNothing);
    });

    testWidgets('hors ligne : chiffres recalculés depuis les dossiers et alertes gardés, avec avis', (tester) async {
      await openPair(
        tester,
        online: false,
        routes: pairRoutes(extra: {'GET dashboard': (o) => throw offline(o)}),
        before: (env) {
          final now = DateTime.now().toUtc().toIso8601String();
          void put(String col, int id, Map<String, dynamic> row) =>
              env.db.peerData.put('c:$col:$id', _json(row));
          put('cases', 1, {'id': 1, 'code': 'VBG-0001', 'status': 'ouvert', 'created_at': now});
          put('cases', 2, {'id': 2, 'code': 'VBG-0002', 'status': 'clos', 'created_at': '2020-01-01T00:00:00Z'});
          put('cases', 3, {'id': 3, 'code': 'VBG-0003', 'status': 'en_cours', 'created_at': now});
          put('alerts', 9, alertRow('a9'));
        },
      );
      expect(find.text(S.t('mobile_pair_home_stale')), findsOneWidget);
      expect(find.bySemanticsLabel('${S.t('peer_home_kpi_collected')} : 3'), findsOneWidget);
      expect(find.bySemanticsLabel('${S.t('peer_home_kpi_this_month')} : 2'), findsOneWidget);
      expect(find.bySemanticsLabel('${S.t('peer_home_kpi_closed')} : 1'), findsOneWidget);
      expect(find.text(S.t('peer_home_tasks_alerts_to_take', {'count': 1})), findsOneWidget);
    });

    testWidgets('pastille de synchro : « À jour » puis éléments en attente ; ouvre la file', (tester) async {
      await openPair(tester);
      expect(find.text('${S.t('peer_status_online')} · ${S.t('mobile_sync_short', {'count': 0})}'), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('sync-chip')));
      await tester.pumpAndSettle();
      expect(currentPath(tester), '/pair/accueil/synchro');
    });
  });

  group('alertes routées (P2)', () {
    Map<String, FakeHandler> alertsRoutes({
      List<Map<String, dynamic>>? rows,
      Map<String, dynamic>? meta,
      Map<String, FakeHandler> extra = const {},
    }) => pairRoutes(extra: {
      'GET pair/alerts': (_) => jsonBody(200, {
        'data': rows ?? [alertRow('a1'), alertRow('a2', type: 'besoin', priority: 'normale', place: 'Cotonou')],
        'meta': {'state': 'open', 'new': 2, ...?meta},
      }),
      ...extra,
    });

    testWidgets('liste : nombre d’alertes ouvertes, types, statuts, lieu, accusé pour les nouvelles', (tester) async {
      await openPair(tester, routes: alertsRoutes(), goTo_: '/pair/alertes');
      expect(find.text(S.t('peer_alerts_title')), findsOneWidget);
      expect(find.text('2 ${S.t('peer_alerts_open_count', {'count': 2})}'), findsOneWidget);
      expect(find.text('SOS · Abomey-Calavi'), findsOneWidget);
      expect(find.text('Besoin · Cotonou'), findsOneWidget);
      expect(find.byKey(const ValueKey('ack-a1')), findsOneWidget);
      expect(find.text(S.t('mobile_pair_alerts_poll', {'seconds': 30})), findsOneWidget);
    });

    testWidgets('de garde (annoncé par le serveur) : texte de garde à 10 s', (tester) async {
      await openPair(tester, routes: alertsRoutes(meta: {'on_duty': true}), goTo_: '/pair/alertes');
      expect(find.text(S.t('peer_alerts_on_duty', {'seconds': 10})), findsOneWidget);
    });

    testWidgets('accuser réception depuis la liste : POST acknowledge, la ligne n’est plus « nouvelle »', (tester) async {
      final routes = alertsRoutes(extra: {
        'POST pair/alerts/a1/acknowledge': (_) => jsonBody(200, {
          'data': alertDetail('a1', status: 'pris_en_charge', ack: true),
          'meta': {'acknowledged_now': true},
        }),
      });
      final env = await openPair(tester, routes: routes, goTo_: '/pair/alertes');
      await tester.tap(find.byKey(const ValueKey('ack-a1')));
      await tester.pumpAndSettle();
      expect(env.api.calls('POST pair/alerts/a1/acknowledge'), hasLength(1));
      expect(find.byKey(const ValueKey('ack-a1')), findsNothing);
      expect(find.byKey(const ValueKey('ack-a2')), findsOneWidget);
      expect(find.text(S.t('peer_alerts_acknowledged')), findsOneWidget);
    });

    testWidgets('liste vide : message', (tester) async {
      await openPair(tester, routes: alertsRoutes(rows: []), goTo_: '/pair/alertes');
      expect(find.text(S.t('peer_alerts_empty')), findsOneWidget);
    });

    testWidgets('hors ligne : la liste vient de la copie locale, avec avis « périmée »', (tester) async {
      await openPair(
        tester,
        online: false,
        routes: alertsRoutes(extra: {'GET pair/alerts': (o) => throw offline(o)}),
        before: (env) {
          env.db.peerData.put('c:alerts:a7', _json(alertRow('a7', place: 'Ouidah')));
          env.db.peerData.put('cursor:pair', 'c');
        },
        goTo_: '/pair/alertes',
      );
      expect(find.text('SOS · Ouidah'), findsOneWidget);
      expect(find.text(S.t('peer_alerts_stale')), findsOneWidget);
    });

    testWidgets('rafraîchissement automatique : 30 s hors garde, nouvelle alerte annoncée', (tester) async {
      var n = 0;
      final routes = alertsRoutes(extra: {
        'GET pair/alerts': (_) {
          n++;
          return jsonBody(200, {
            'data': [alertRow('a1'), if (n > 1) alertRow('a3', place: 'Porto-Novo')],
            'meta': {'new': 1},
          });
        },
      });
      await openPair(
        tester,
        routes: routes,
        overrides: [peerPollEnabledProvider.overrideWithValue(true)],
        goTo_: '/pair/alertes',
      );
      expect(find.text('SOS · Porto-Novo'), findsNothing);
      await tester.pump(const Duration(seconds: 31));
      await tester.pumpAndSettle();
      expect(find.text('SOS · Porto-Novo'), findsOneWidget);
      expect(find.text(S.t('peer_alerts_announce_new', {'count': 1})), findsOneWidget);
    });

    testWidgets('cibles tactiles d’au moins 44 px et libellé d’accessibilité du détail', (tester) async {
      await openPair(tester, routes: alertsRoutes(), goTo_: '/pair/alertes');
      expect(tester.getSize(find.byKey(const ValueKey('ack-a1'))).height, greaterThanOrEqualTo(44));
      expect(tester.getSize(find.byKey(const ValueKey('detail-a1'))).height, greaterThanOrEqualTo(44));
      expect(tester.getSize(find.byKey(const ValueKey('sync-chip'))).height, greaterThanOrEqualTo(44));
      expect(find.bySemanticsLabel(S.t('peer_alerts_detail_for', {'type': 'SOS', 'place': 'Abomey-Calavi'})), findsOneWidget);
    });
  });

  group('détail d’une alerte (P3)', () {
    Map<String, FakeHandler> detailRoutes(Map<String, dynamic> detail, {Map<String, FakeHandler> extra = const {}}) =>
        pairRoutes(extra: {
          'GET pair/alerts': (_) => jsonBody(200, {'data': [], 'meta': {}}),
          'GET pair/alerts/a1': (_) => jsonBody(200, {'data': detail}),
          ...extra,
        });

    testWidgets('champs, contact masqué avant l’accusé, escalade expliquée, boutons selon can{…}', (tester) async {
      await openPair(tester, routes: detailRoutes(alertDetail('a1')), goTo_: '/pair/alertes/a1');
      expect(find.text(S.t('mobile_pair_alert_escalation')), findsOneWidget);
      expect(find.text('Elle demande de l’aide.'), findsOneWidget);
      expect(find.text('Mise en sécurité'), findsOneWidget);
      expect(find.textContaining(S.t('mobile_pair_alert_contact_masked')), findsOneWidget);
      expect(find.textContaining('+22997000000'), findsNothing);
      await reveal(tester, 'detail-take');
      expect(find.byKey(const ValueKey('detail-ack')), findsOneWidget);
      expect(find.byKey(const ValueKey('detail-take')), findsOneWidget);
      expect(find.byKey(const ValueKey('detail-close')), findsNothing, reason: 'close absent de can');
      expect(find.byType(NavigationBar), findsOneWidget, reason: 'le SOS reste accessible');
    });

    testWidgets('après l’accusé : contact visible et bouton d’appel', (tester) async {
      final launcher = FakeLauncher();
      await openPair(
        tester,
        routes: detailRoutes(alertDetail('a1', status: 'pris_en_charge', ack: true, can: {'acknowledge': false, 'take': true, 'close': true, 'statuses': ['en_cours', 'traite', 'clos']})),
        overrides: pairOverrides(launcher: launcher),
        goTo_: '/pair/alertes/a1',
      );
      expect(find.textContaining('+22997000000'), findsOneWidget);
      await reveal(tester, 'alert-call');
      await tester.tap(find.byKey(const ValueKey('alert-call')));
      await tester.pumpAndSettle();
      expect(launcher.opened.single.scheme, 'tel');
      await reveal(tester, 'detail-close');
      expect(find.byKey(const ValueKey('detail-close')), findsOneWidget);
      expect(find.byKey(const ValueKey('detail-treated')), findsOneWidget);
    });

    testWidgets('accuser réception : l’escalade s’arrête (réponse du serveur reflétée)', (tester) async {
      final env = await openPair(
        tester,
        routes: detailRoutes(alertDetail('a1'), extra: {
          'POST pair/alerts/a1/acknowledge': (_) => jsonBody(200, {
            'data': alertDetail('a1', status: 'pris_en_charge', ack: true, can: {'acknowledge': false, 'take': true, 'close': false, 'statuses': ['en_cours']}),
          }),
        }),
        goTo_: '/pair/alertes/a1',
      );
      await reveal(tester, 'detail-ack');
      await tester.tap(find.byKey(const ValueKey('detail-ack')));
      await tester.pumpAndSettle();
      expect(env.api.calls('POST pair/alerts/a1/acknowledge'), hasLength(1));
      expect(find.byKey(const ValueKey('detail-ack')), findsNothing);
      expect(find.text(S.t('mobile_pair_alert_escalation')), findsNothing);
    });

    testWidgets('prendre en charge : statut en_cours ; clore : confirmation puis statut clos', (tester) async {
      final calls = <String>[];
      final env = await openPair(
        tester,
        routes: detailRoutes(
          alertDetail('a1', status: 'pris_en_charge', ack: true, can: {'acknowledge': false, 'take': true, 'close': true, 'statuses': ['en_cours', 'clos']}),
          extra: {
            'POST pair/alerts/a1/status': (o) {
              calls.add((o.data as Map)['status'] as String);
              return jsonBody(200, {
                'data': alertDetail('a1', status: (o.data as Map)['status'] == 'clos' ? 'clos' : 'en_cours', ack: true, can: {'acknowledge': false, 'take': true, 'close': true, 'statuses': ['clos']}),
              });
            },
          },
        ),
        goTo_: '/pair/alertes/a1',
      );
      await reveal(tester, 'detail-take');
      await tester.tap(find.byKey(const ValueKey('detail-take')));
      await tester.pumpAndSettle();
      expect(calls, ['en_cours']);
      await tester.pump(const Duration(seconds: 5)); // le message flottant ne masque plus le bouton
      await tester.pumpAndSettle();
      await reveal(tester, 'detail-close');
      await tester.tap(find.byKey(const ValueKey('detail-close')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('peer_alerts_close_confirm')), findsOneWidget);
      await tester.tap(find.text(S.t('common_cancel')));
      await tester.pumpAndSettle();
      expect(calls, ['en_cours'], reason: 'annulé : rien n’est envoyé');
      await reveal(tester, 'detail-close');
      await tester.tap(find.byKey(const ValueKey('detail-close')));
      await tester.pumpAndSettle();
      await tester.tap(find.text(S.t('peer_alerts_close_yes')));
      await tester.pumpAndSettle();
      expect(calls, ['en_cours', 'clos']);
      expect(env.api.calls('POST pair/alerts/a1/status'), hasLength(2));
    });

    testWidgets('statut invalide : le message du serveur est affiché tel quel', (tester) async {
      await openPair(
        tester,
        routes: detailRoutes(alertDetail('a1'), extra: {
          'POST pair/alerts/a1/status': (_) => jsonBody(422, {'message': 'Passage de « Nouveau » à « Clos » impossible.', 'code': 'invalid_transition'}),
        }),
        goTo_: '/pair/alertes/a1',
      );
      await reveal(tester, 'detail-take');
      await tester.tap(find.byKey(const ValueKey('detail-take')));
      await tester.pumpAndSettle();
      expect(find.text('Passage de « Nouveau » à « Clos » impossible.'), findsOneWidget);
    });

    testWidgets('alerte non routée vers moi (403) : écran « plus disponible », rien n’est révélé', (tester) async {
      await openPair(
        tester,
        routes: pairRoutes(extra: {
          'GET pair/alerts/zz': (_) => jsonBody(403, {'message': 'Interdit.', 'code': 'forbidden'}),
        }),
        goTo_: '/pair/alertes/zz',
      );
      expect(find.text(S.t('mobile_pair_alert_gone')), findsOneWidget);
      expect(find.byKey(const ValueKey('detail-ack')), findsNothing);
    });

    testWidgets('position : avertissement avant d’ouvrir Maps, rien ne part sans accord', (tester) async {
      final launcher = FakeLauncher();
      await openPair(
        tester,
        routes: detailRoutes(alertDetail('a1', position: {'lat': 6.3703, 'lng': 2.3912, 'accuracy': 12})),
        overrides: pairOverrides(launcher: launcher),
        goTo_: '/pair/alertes/a1',
      );
      expect(find.textContaining('6,37° N, 2,39° E'), findsOneWidget);
      await tester.scrollUntilVisible(find.byKey(const ValueKey('alert-maps')), 200);
      await tester.tap(find.byKey(const ValueKey('alert-maps')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_pair_maps_text')), findsOneWidget);
      await tester.tap(find.text(S.t('common_cancel')));
      await tester.pumpAndSettle();
      expect(launcher.opened, isEmpty);
      await tester.tap(find.byKey(const ValueKey('alert-maps')));
      await tester.pumpAndSettle();
      await tester.tap(find.text(S.t('mobile_pair_maps_yes')));
      await tester.pumpAndSettle();
      expect(launcher.opened.single.toString(), contains('6.3703,2.3912'));
    });

    testWidgets('hors ligne : action refusée avec le message, aucune requête', (tester) async {
      final env = await openPair(
        tester,
        online: false,
        routes: detailRoutes(alertDetail('a1')),
        goTo_: '/pair/alertes/a1',
      );
      await reveal(tester, 'detail-ack');
      await tester.tap(find.byKey(const ValueKey('detail-ack')));
      await tester.pumpAndSettle();
      expect(env.api.calls('POST pair/alerts/a1/acknowledge'), isEmpty);
      expect(find.text(S.t('peer_alerts_offline')), findsWidgets);
    });
  });
}

String _json(Object o) => jsonEncode(o);
