import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/core/sync/sync_providers.dart';
import 'package:xomiton/core/sync/sync_queue.dart';

import '../support/fakes.dart';
import '../support/harness.dart';
import '../support/pair_harness.dart';

final S = loadStrings();

Map<String, dynamic> caseJson(
  String code, {
  int id = 1,
  String kind = 'VBG',
  String status = 'ouvert',
  int followups = 0,
  List<Map<String, dynamic>>? fu,
  String? notes,
  bool anonymized = false,
  Map<String, bool>? can,
}) => {
  'id': id,
  'code': code,
  'kind': kind,
  'kind_label': kind == 'VBG' ? 'Violence basée sur le genre' : 'Violation des droits humains',
  'occurred_on': '2026-10-01',
  'zone': {'id': 1, 'name': 'Littoral'},
  'commune': {'id': 3, 'name': 'Cotonou'},
  'population': {'id': 2, 'label': 'Femmes cis'},
  'sex': 'femme',
  'sex_label': 'Femme',
  'age_range': '18-24',
  'age_range_label': '18-24 ans',
  'violence_type': {'id': 4, 'label': 'Violence physique'},
  'referral': 'Centre de santé',
  'status': status,
  'status_label': status == 'ouvert' ? 'Ouvert' : (status == 'clos' ? 'Clos' : 'En cours'),
  'anonymized': anonymized,
  'followups_count': fu?.length ?? followups,
  'notes': ?notes,
  'followups': ?fu,
  'can': {'update': can?['update'] ?? !anonymized, 'add_followup': can?['add_followup'] ?? !anonymized},
  'created_at': '2026-10-02T08:00:00Z',
};

Map<String, dynamic> fuJson(int id, {String step = 'Premier entretien', String outcome = 'planifie', String date = '2026-10-03'}) => {
  'id': id,
  'date': date,
  'step': step,
  'outcome': outcome,
  'outcome_label': outcome == 'realise' ? 'Réalisé' : 'Planifié',
  'next_on': '2026-10-10',
  'overdue': false,
  'comment': 'Écoute et orientation',
  'author': 'Awa_S',
  'by_me': true,
};

Map<String, FakeHandler> casesRoutes({List<Map<String, dynamic>>? list, Map<String, FakeHandler> extra = const {}}) =>
    pairRoutes(extra: {
      'GET pair/cases': (o) {
        final q = (o.queryParameters['q'] as String?)?.toUpperCase();
        final status = o.queryParameters['status'] as String?;
        var rows = list ?? [caseJson('VBG-0042', followups: 2), caseJson('VDH-0007', id: 2, kind: 'VDH', status: 'clos')];
        if (q != null) rows = [for (final r in rows) if ('${r['code']}'.contains(q)) r];
        if (status != null) rows = [for (final r in rows) if (r['status'] == status) r];
        return jsonBody(200, {'data': rows, 'meta': {'current_page': 1, 'last_page': 1, 'total': rows.length}});
      },
      'GET zones': (_) => jsonBody(200, {
        'data': [
          {'id': 1, 'name': 'Littoral', 'communes': [{'id': 3, 'name': 'Cotonou'}]},
        ],
      }, headers: {'ETag': '"z"'}),
      'GET violence-types': (_) => jsonBody(200, {'data': [{'id': 4, 'slug': 'physique', 'label': 'Violence physique'}]}, headers: {'ETag': '"v"'}),
      ...extra,
    });

Future<void> reveal(WidgetTester tester, String key) => revealKey(tester, key);

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('liste (P4)', () {
    testWidgets('dossiers collectés : code, nature, statut, date · commune · violence · étapes ; jamais les notes', (tester) async {
      await openPair(tester, routes: casesRoutes(list: [caseJson('VBG-0042', followups: 2, notes: 'NOTE-SECRETE')]), goTo_: '/pair/dossiers');
      expect(find.text(S.t('peer_cases_heading')), findsOneWidget);
      expect(find.byKey(const ValueKey('case-VBG-0042')), findsOneWidget);
      expect(find.textContaining('Cotonou'), findsOneWidget);
      expect(find.textContaining(S.t('mobile_pair_case_steps', {'count': 2})), findsOneWidget);
      expect(find.text(S.t('peer_cases_results', {'count': 1})), findsOneWidget);
      expect(find.textContaining('NOTE-SECRETE'), findsNothing);
    });

    testWidgets('recherche par code (envoyée au serveur), filtre de statut, réinitialisation', (tester) async {
      final env = await openPair(tester, routes: casesRoutes(), goTo_: '/pair/dossiers');
      expect(find.byKey(const ValueKey('case-VDH-0007')), findsOneWidget);
      await tester.enterText(find.byKey(const ValueKey('cases-search')), 'vbg');
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();
      expect(env.api.calls('GET pair/cases').any((r) => r.queryParameters['q'] == 'vbg'), isTrue);
      expect(find.byKey(const ValueKey('case-VDH-0007')), findsNothing);
      expect(find.byKey(const ValueKey('case-VBG-0042')), findsOneWidget);
      await tester.tap(find.text(S.t('peer_cases_reset')));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('case-VDH-0007')), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('status-clos')));
      await tester.pumpAndSettle();
      expect(env.api.calls('GET pair/cases').last.queryParameters['status'], 'clos');
      expect(find.byKey(const ValueKey('case-VBG-0042')), findsNothing);
    });

    testWidgets('aucun résultat : messages vide et filtré', (tester) async {
      await openPair(tester, routes: casesRoutes(list: []), goTo_: '/pair/dossiers');
      expect(find.text(S.t('peer_cases_empty')), findsOneWidget);
      expect(find.text(S.t('peer_cases_empty_text')), findsOneWidget);
      await tester.enterText(find.byKey(const ValueKey('cases-search')), 'zzz');
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();
      expect(find.text(S.t('peer_cases_empty_filtered')), findsOneWidget);
    });

    testWidgets('hors ligne : lecture depuis la copie chiffrée (suivis comptés, libellés des référentiels)', (tester) async {
      await openPair(
        tester,
        online: false,
        routes: casesRoutes(extra: {'GET pair/cases': (o) => throw offline(o)}),
        before: (env) {
          env.db.peerData.put('c:cases:1', jsonEncode({'id': 1, 'code': 'VBG-0001', 'kind': 'VBG', 'status': 'ouvert', 'commune_id': 3, 'zone_id': 1, 'violence_type_id': 4, 'occurred_on': '2026-10-01', 'created_at': '2026-10-02T08:00:00Z'}));
          env.db.peerData.put('c:cases:2', jsonEncode({'id': 2, 'code': 'VBG-0002', 'kind': 'VBG', 'status': 'archive', 'created_at': '2026-10-01T08:00:00Z'}));
          env.db.peerData.put('c:case_followups:9', jsonEncode({'id': 9, 'case_id': 1, 'date': '2026-10-03', 'step': 'Appel', 'outcome': 'planifie'}));
          env.db.peerData.put('cursor:pair', 'c');
        },
        goTo_: '/pair/dossiers',
      );
      expect(find.byKey(const ValueKey('case-VBG-0001')), findsOneWidget);
      expect(find.byKey(const ValueKey('case-VBG-0002')), findsNothing, reason: 'archivés exclus sauf filtre « Archivé »');
      expect(find.textContaining('Cotonou'), findsOneWidget);
      expect(find.textContaining('Violence physique'), findsOneWidget);
      expect(find.textContaining(S.t('mobile_pair_case_steps', {'count': 1})), findsOneWidget);
      expect(find.text(S.t('mobile_stale_notice')), findsOneWidget);
    });

    testWidgets('un dossier collecté hors ligne apparaît en tête, « En file », sans code', (tester) async {
      await openPair(
        tester,
        routes: casesRoutes(),
        before: (env) => env.db.peerData.put(
          'q:u1',
          jsonEncode(QueueEntry(
            uuid: 'u1',
            kind: QueueKind.caseCreate,
            payload: {'kind': 'VDH', 'occurred_on': '2026-10-05'},
            createdAt: DateTime.now(),
          ).toJson()),
        ),
        goTo_: '/pair/dossiers',
      );
      expect(find.text(S.t('mobile_pair_case_queued_title')), findsOneWidget);
      expect(find.text(S.t('mobile_pair_in_queue')), findsOneWidget);
      expect(find.text(S.t('peer_cases_results', {'count': 3})), findsOneWidget);
    });

    testWidgets('liste identique au web : mêmes filtres de nature et de statut, cibles ≥ 44 px', (tester) async {
      await openPair(tester, routes: casesRoutes(), goTo_: '/pair/dossiers');
      for (final k in ['kind-all', 'kind-VBG', 'kind-VDH', 'status-all', 'status-ouvert', 'status-en_cours', 'status-clos', 'status-archive']) {
        expect(find.byKey(ValueKey(k)), findsOneWidget, reason: k);
        expect(tester.getSize(find.byKey(ValueKey(k))).height, greaterThanOrEqualTo(44), reason: k);
      }
    });
  });

  group('fiche (P5) et suivi (P6)', () {
    Map<String, FakeHandler> caseRoutes(Map<String, dynamic> c, {Map<String, FakeHandler> extra = const {}}) =>
        casesRoutes(extra: {'GET pair/cases/${c['code']}': (_) => jsonBody(200, {'data': c}), ...extra});

    testWidgets('cinq sections, fiche non remplie signalée, frise de suivi', (tester) async {
      final c = caseJson('VBG-0042', status: 'en_cours', notes: 'Résumé non identifiant', fu: [fuJson(1, outcome: 'realise'), fuJson(2, step: 'Appel de suivi', date: '2026-10-05')]);
      await openPair(tester, routes: caseRoutes(c), goTo_: '/pair/dossiers/VBG-0042');
      for (final s in ['identification', 'facts', 'services', 'orientation', 'consent']) {
        expect(find.text(S.t('peer_sheet_sections_$s'), skipOffstage: false), findsOneWidget, reason: s);
      }
      expect(find.text(S.t('peer_sheet_not_filled')), findsOneWidget);
      expect(find.text('Femme'), findsOneWidget);
      expect(find.text('18-24 ans'), findsWidgets);
      expect(find.text('Cotonou (Littoral)'), findsOneWidget);
      expect(find.text('Résumé non identifiant'), findsOneWidget);
      await reveal(tester, 'case-add-followup');
      expect(find.text(S.t('peer_case_timeline')), findsOneWidget);
      expect(find.text('Premier entretien'), findsOneWidget);
      expect(find.text('Appel de suivi'), findsOneWidget);
      expect(find.byType(NavigationBar), findsOneWidget);
    });

    testWidgets('dossier d’un autre pair ou inconnu : 404 neutre, rien n’est révélé', (tester) async {
      await openPair(
        tester,
        routes: casesRoutes(extra: {'GET pair/cases/VBG-9999': (_) => jsonBody(404, {'message': 'Introuvable.', 'code': 'not_found'})}),
        goTo_: '/pair/dossiers/VBG-9999',
      );
      expect(find.text(S.t('mobile_pair_case_gone')), findsOneWidget);
    });

    testWidgets('dossier archivé ou anonymisé : lecture seule, aucun formulaire', (tester) async {
      final c = caseJson('VBG-0042', status: 'archive', anonymized: true, fu: [fuJson(1)]);
      await openPair(tester, routes: caseRoutes(c), goTo_: '/pair/dossiers/VBG-0042');
      expect(find.text(S.t('peer_case_anonymized')), findsOneWidget);
      expect(find.text(S.t('peer_case_locked'), skipOffstage: false), findsOneWidget);
      expect(find.byKey(const ValueKey('case-status')), findsNothing);
      await revealFinder(tester, find.text(S.t('peer_case_timeline')));
      expect(find.byKey(const ValueKey('case-add-followup')), findsNothing);
      expect(find.byKey(const ValueKey('fu-done-1')), findsNothing);
    });

    testWidgets('changer le statut : PATCH immédiat, message du serveur sur transition invalide', (tester) async {
      final c = caseJson('VBG-0042');
      var fail = false;
      final env = await openPair(
        tester,
        routes: caseRoutes(c, extra: {
          'PATCH pair/cases/VBG-0042': (o) => fail
              ? jsonBody(422, {'message': 'Passage de « Ouvert » à « Archivé » impossible pour ce dossier.', 'code': 'invalid_transition'})
              : jsonBody(200, {'data': caseJson('VBG-0042', status: 'en_cours')}),
        }),
        goTo_: '/pair/dossiers/VBG-0042',
      );
      await revealKey(tester, 'case-status');
      await tester.tap(find.byKey(const ValueKey('case-status')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('En cours').last);
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('case-status-submit')));
      await tester.pumpAndSettle();
      expect((env.api.calls('PATCH pair/cases/VBG-0042').single.data as Map)['status'], 'en_cours');
      expect(find.text(S.t('peer_case_status_saved')), findsOneWidget);
      fail = true;
      await tester.pump(const Duration(seconds: 5));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('case-status')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Clos').last);
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('case-status-submit')));
      await tester.pumpAndSettle();
      expect(find.textContaining('impossible pour ce dossier'), findsOneWidget);
    });

    testWidgets('statut hors ligne : la modification part en file avec sa valeur d’origine', (tester) async {
      final c = caseJson('VBG-0042');
      final env = await openPair(
        tester,
        routes: caseRoutes(c, extra: {'PATCH pair/cases/VBG-0042': (o) => throw offline(o)}),
        goTo_: '/pair/dossiers/VBG-0042',
      );
      await revealKey(tester, 'case-status');
      await tester.tap(find.byKey(const ValueKey('case-status')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Clos').last);
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('case-status-submit')));
      await tester.pumpAndSettle();
      final q = env.db.peerData.keys.where((k) => k.startsWith('q:')).toList();
      expect(q, hasLength(1));
      final e = QueueEntry.fromJson(jsonDecode(env.db.peerData.get(q.first)!))!;
      expect(e.kind, QueueKind.caseUpdate);
      expect(e.payload['changes'], {'status': 'clos'});
      expect(e.payload['base'], {'status': 'ouvert'});
      expect(find.text(S.t('mobile_pair_followup_queued')), findsOneWidget);
    });

    testWidgets('marquer une étape comme réalisée : PATCH de l’étape', (tester) async {
      final c = caseJson('VBG-0042', fu: [fuJson(5)]);
      final env = await openPair(
        tester,
        routes: caseRoutes(c, extra: {
          'PATCH pair/cases/VBG-0042/followups/5': (_) => jsonBody(200, {'data': fuJson(5, outcome: 'realise')}),
        }),
        goTo_: '/pair/dossiers/VBG-0042',
      );
      await reveal(tester, 'fu-done-5');
      await tester.tap(find.byKey(const ValueKey('fu-done-5')));
      await tester.pumpAndSettle();
      expect((env.api.calls('PATCH pair/cases/VBG-0042/followups/5').single.data as Map)['outcome'], 'realise');
      expect(find.text(S.t('peer_case_followup_done')), findsOneWidget);
    });

    testWidgets('ajouter un suivi en ligne : validation locale, envoi idempotent, « confirmé »', (tester) async {
      final c = caseJson('VBG-0042');
      final env = await openPair(
        tester,
        routes: caseRoutes(c, extra: {
          'POST sync/push': (_) => jsonBody(200, {'data': {'items': []}}),
          'POST pair/cases/VBG-0042/followups': (o) => jsonBody(201, {'data': {...fuJson(11), 'step': (o.data as Map)['step']}}),
        }),
        goTo_: '/pair/dossiers/VBG-0042/suivi',
      );
      // Erreur : étape manquante, prochaine date avant la date.
      await tester.tap(find.byKey(const ValueKey('fu-submit')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('peer_collecte_errors_required')), findsOneWidget);
      expect(find.text(S.t('mobile_pair_form_errors', {'count': 1})), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('fu-step')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Appel de suivi').last);
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const ValueKey('fu-comment')), 'Rappel fait');
      await tester.tap(find.byKey(const ValueKey('fu-submit')));
      await tester.pumpAndSettle();
      final post = env.api.calls('POST pair/cases/VBG-0042/followups').single;
      final body = post.data as Map;
      expect(body['step'], 'Appel de suivi');
      expect(body['comment'], 'Rappel fait');
      expect(body['outcome'], 'planifie');
      expect(body['client_uuid'], post.headers['Idempotency-Key']);
      expect(find.text(S.t('peer_case_followup_saved')), findsOneWidget);
      expect(currentPath(tester), '/pair/dossiers/VBG-0042');
    });

    testWidgets('suivi hors ligne : gardé en file, « En file » sur la frise, puis confirmé au retour du réseau', (tester) async {
      final c = caseJson('VBG-0042', fu: [fuJson(1)]);
      var offlineNow = true;
      final env = await openPair(
        tester,
        routes: caseRoutes(c, extra: {
          'POST pair/cases/VBG-0042/followups': (o) => offlineNow ? throw offline(o) : jsonBody(201, {'data': fuJson(12)}),
          'GET pair/cases/VBG-0042': (o) => offlineNow ? throw offline(o) : jsonBody(200, {'data': c}),
        }),
        before: (env) {
          env.db.peerData.put('c:cases:1', jsonEncode({'id': 1, 'code': 'VBG-0042', 'kind': 'VBG', 'status': 'ouvert', 'created_at': '2026-10-02T08:00:00Z'}));
          env.db.peerData.put('c:case_followups:1', jsonEncode({'id': 1, 'case_id': 1, 'date': '2026-10-03', 'step': 'Premier entretien', 'outcome': 'planifie'}));
          env.db.peerData.put('cursor:pair', 'c');
        },
        goTo_: '/pair/dossiers/VBG-0042/suivi',
      );
      await tester.tap(find.byKey(const ValueKey('fu-step')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Premier entretien').last);
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('fu-submit')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_pair_followup_queued')), findsOneWidget);
      // La fiche (lue hors ligne) montre l’étape « En file ».
      expect(find.text(S.t('mobile_pair_in_queue')), findsOneWidget);
      final entries = env.db.peerData.keys.where((k) => k.startsWith('q:')).toList();
      expect(entries, hasLength(1));
      // Retour du réseau : le moteur envoie la file avec la MÊME clé.
      offlineNow = false;
      final uuid = entries.first.substring(2);
      final container = containerOf(tester);
      await tester.runAsync(() => container.read(syncEngineProvider).sync(force: true, pushOnly: true));
      await tester.pumpAndSettle();
      final sent = env.api.calls('POST pair/cases/VBG-0042/followups').last;
      expect(sent.headers['Idempotency-Key'], uuid);
      expect((sent.data as Map)['client_uuid'], uuid);
      expect(find.text(S.t('mobile_pair_in_queue')), findsNothing);
    });

    testWidgets('refus du serveur (422) : les erreurs sont sur les champs, rien n’est perdu', (tester) async {
      final c = caseJson('VBG-0042');
      await openPair(
        tester,
        routes: caseRoutes(c, extra: {
          'POST sync/push': (_) => jsonBody(200, {'data': {'items': []}}),
          'POST pair/cases/VBG-0042/followups': (_) => jsonBody(422, {
            'message': 'x',
            'code': 'validation_failed',
            'errors': {'date': ['La date ne peut pas être postérieure à aujourd’hui.']},
          }),
        }),
        goTo_: '/pair/dossiers/VBG-0042/suivi',
      );
      await tester.tap(find.byKey(const ValueKey('fu-step')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Premier entretien').last);
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('fu-submit')));
      await tester.pumpAndSettle();
      expect(find.text('La date ne peut pas être postérieure à aujourd’hui.'), findsOneWidget);
      expect(currentPath(tester), '/pair/dossiers/VBG-0042/suivi');
    });
  });

  group('compte et pull', () {
    testWidgets('après anonymisation côté serveur, le prochain pull retire le dossier du cache', (tester) async {
      var pulled = false;
      var anonymized = false;
      final env = await openPair(
        tester,
        online: false,
        routes: casesRoutes(extra: {
          'GET pair/cases': (o) => throw offline(o),
          'GET sync/pull': (o) {
            pulled = anonymized;
            return jsonBody(200, {
              'data': {'cases': {'reset': false, 'upserts': [], 'deletes': anonymized ? [1] : [], 'has_more': false}},
              'meta': {'cursor': 'c2', 'has_more': false},
            });
          },
        }),
        before: (env) {
          env.db.peerData.put('c:cases:1', jsonEncode({'id': 1, 'code': 'VBG-0001', 'kind': 'VBG', 'status': 'ouvert', 'created_at': '2026-10-02T08:00:00Z'}));
          env.db.peerData.put('cursor:pair', 'c1');
        },
        goTo_: '/pair/dossiers',
      );
      expect(find.byKey(const ValueKey('case-VBG-0001')), findsOneWidget);
      anonymized = true; // le serveur anonymise le dossier : il part des collections du pair
      await tester.runAsync(() => containerOf(tester).read(syncEngineProvider).sync(force: true));
      expect(pulled, isTrue);
      expect(env.db.peerData.get('c:cases:1'), isNull);
    });
  });
}
