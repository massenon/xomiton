import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/core/outbox/outbox.dart';
import 'package:xomiton/core/sync/sync_providers.dart';
import 'package:xomiton/core/sync/sync_queue.dart';
import 'package:xomiton/features/pair/application/collect_controller.dart';

import '../support/fakes.dart';
import '../support/harness.dart';
import '../support/pair_harness.dart';

final S = loadStrings();

void put(TestEnv env, QueueEntry e) => env.db.peerData.put('q:${e.uuid}', jsonEncode(e.toJson()));

QueueEntry entry(
  String uuid,
  QueueKind kind, {
  QueueStatus status = QueueStatus.pending,
  Map<String, dynamic>? payload,
  String? code,
  Map<String, dynamic> summary = const {},
  String? failure,
  Map<String, List<String>> errors = const {},
  List<ConflictRef> conflicts = const [],
  String? resolution,
  DateTime? at,
}) => QueueEntry(
  uuid: uuid,
  kind: kind,
  payload: payload ?? {},
  createdAt: at ?? DateTime.now().subtract(const Duration(minutes: 10)),
  status: status,
  caseCode: code,
  summary: summary,
  failure: failure,
  errors: errors,
  conflicts: conflicts,
  resolution: resolution,
  resultCode: status == QueueStatus.sent ? (code ?? 'VBG-0001') : null,
  sentAt: status == QueueStatus.sent ? DateTime.now() : null,
);

Map<String, dynamic> conflictRow({int id = 42, String field = 'sex', String server = 'homme', String device = 'femme'}) => {
  'id': id,
  'subject': {'type': 'collecte', 'server_id': 1, 'reference': 'VBG-0042'},
  'field': field,
  'status': 'a_resoudre',
  'server_value': server,
  'device_value': device,
  'created_at': '2026-10-29T08:00:00Z',
};

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('file de synchronisation (P10)', () {
    testWidgets('trois sections : conflit, en attente, envoyés ; types, états et code', (tester) async {
      await openPair(
        tester,
        // Le serveur est indisponible : les envois restent en attente.
        routes: pairRoutes(extra: {
          'POST sos': (_) => jsonBody(503, {'message': 'x', 'code': 'retry_later'}),
          'POST sync/push': (_) => jsonBody(503, {'message': 'x', 'code': 'retry_later'}),
          'POST pair/cases/VBG-0007/followups': (_) => jsonBody(503, {'message': 'x', 'code': 'retry_later'}),
        }),
        before: (env) {
          put(env, entry('c1', QueueKind.caseUpdate, status: QueueStatus.conflict, code: 'VBG-0042', summary: {'code': 'VBG-0042', 'field': 'status', 'value': 'clos'}, conflicts: const [ConflictRef('sex', 42)]));
          put(env, entry('p1', QueueKind.caseCreate, payload: {'kind': 'VDH', 'occurred_on': '2026-10-05'}, summary: {'commune': 'Cotonou'}));
          put(env, entry('p2', QueueKind.followupCreate, code: 'VBG-0007', summary: {'code': 'VBG-0007', 'step': 'Appel de suivi'}));
          put(env, entry('s1', QueueKind.caseCreate, status: QueueStatus.sent, code: 'VBG-0099', payload: {'kind': 'VBG', 'occurred_on': '2026-10-01'}));
          put(env, entry('s2', QueueKind.caseUpdate, status: QueueStatus.sent, code: 'VBG-0042', summary: {'code': 'VBG-0042', 'value': 'en_cours'}, resolution: 'appareil'));
          env.db.outbox.put('sos1', jsonEncode(OutboxEntry(uuid: 'sos1', type: OutboxType.sos, payload: {}, createdAt: DateTime.now()).toJson()));
        },
        goTo_: '/pair/accueil/synchro',
      );
      // Écran haut : toutes les lignes sont construites.
      tester.view.physicalSize = const Size(412 * 2.625, 2400 * 2.625);
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_sync_title')), findsOneWidget);
      expect(find.text(S.t('mobile_sync_conflicts')), findsOneWidget);
      expect(find.text(S.t('mobile_sync_pending')), findsNWidgets(4), reason: 'le titre de section et les trois pastilles');
      expect(find.text(S.t('mobile_sync_sent')), findsOneWidget);
      expect(find.text(S.t('mobile_sync_kind_case')), findsWidgets);
      expect(find.text(S.t('mobile_sync_kind_followup')), findsOneWidget);
      expect(find.text(S.t('mobile_sync_kind_sos')), findsOneWidget);
      expect(find.text(S.t('mobile_sync_state_conflict')), findsOneWidget);
      expect(find.text(S.t('mobile_sync_state_resolved')), findsOneWidget);
      expect(find.text('VBG-0099'), findsOneWidget);
      expect(find.text(S.t('peer_status_queue', {'count': 4})), findsOneWidget);
    });

    testWidgets('file vide : « tout est à jour »', (tester) async {
      await openPair(tester, routes: pairRoutes(), goTo_: '/pair/accueil/synchro');
      expect(find.text(S.t('mobile_sync_empty')), findsOneWidget);
      expect(find.textContaining('Dernière synchronisation'), findsOneWidget, reason: 'le passage d’ouverture est déjà fait');
    });

    testWidgets('« Synchroniser maintenant » : désactivé hors ligne, sinon passage complet et bilan', (tester) async {
      await openPair(
        tester,
        online: false,
        routes: pairRoutes(),
        goTo_: '/pair/accueil/synchro',
      );
      // Le bouton n'est actif qu'avec le réseau.
      await tester.pumpAndSettle();
      expect(tester.widget<FilledButton>(find.byKey(const ValueKey('sync-now'))).onPressed, isNull);
      expect(find.text(S.t('mobile_sync_now_off')), findsOneWidget);
    });

    testWidgets('« Synchroniser maintenant » en ligne : envoi de la file puis bilan', (tester) async {
      final env = await openPair(
        tester,
        routes: pairRoutes(extra: {
          'POST sync/push': (o) => jsonBody(200, {
            'data': {
              'items': [
                for (final i in ((o.data as Map)['items'] as List).cast<Map>())
                  {'uuid': i['uuid'], 'result': 'created', 'status': 201, 'reference': 'VBG-0200'},
              ],
            },
          }),
        }),
        goTo_: '/pair/accueil/synchro',
      );
      // Une fiche arrive dans la file après le passage d'ouverture.
      await containerOf(tester).read(syncQueueProvider).save(entry('p1', QueueKind.caseCreate, payload: {'kind': 'VDH', 'occurred_on': '2026-10-05'}));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_sync_state_pending')), findsWidgets);
      await tester.runAsync(() async {
        await tester.tap(find.byKey(const ValueKey('sync-now')));
        await Future<void>.delayed(const Duration(milliseconds: 300));
      });
      await tester.pumpAndSettle();
      expect(env.api.calls('POST sync/push'), hasLength(1));
      expect(find.text(S.t('mobile_sync_done', {'count': 1})), findsOneWidget);
      expect(find.text('VBG-0200'), findsOneWidget);
    });

    testWidgets('fiche refusée : « À corriger » avec l’erreur du serveur, Corriger rouvre le formulaire rempli', (tester) async {
      final env = await openPair(
        tester,
        routes: pairRoutes(),
        before: (env) => put(
          env,
          entry(
            'f1',
            QueueKind.caseCreate,
            status: QueueStatus.failed,
            failure: 'validation_failed',
            errors: {'occurred_on': ['La date ne peut pas être postérieure à aujourd’hui.']},
            payload: {'kind': 'VBG', 'occurred_on': '2999-01-01', 'zone_id': 1, 'population_id': 2, 'sex': 'femme', 'age_range': '18-24', 'violence_type_id': 4},
          ),
        ),
        goTo_: '/pair/accueil/synchro',
      );
      expect(find.text(S.t('mobile_sync_state_failed')), findsOneWidget);
      expect(find.text('La date ne peut pas être postérieure à aujourd’hui.'), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('correct-f1')));
      await tester.pumpAndSettle();
      expect(currentPath(tester), '/pair/dossiers/collecte');
      expect(env.db.peerData.get('q:f1'), isNull);
      expect(find.text('La date ne peut pas être postérieure à aujourd’hui.'), findsOneWidget);
      final form = containerOf(tester).read(collectControllerProvider).form;
      expect(form.kind, 'VBG');
      expect(form.zoneId, 1);
    });

    testWidgets('supprimer un envoi refusé : confirmation d’abord', (tester) async {
      final env = await openPair(
        tester,
        routes: pairRoutes(),
        before: (env) => put(env, entry('f2', QueueKind.followupCreate, status: QueueStatus.failed, failure: 'case_locked', code: 'VBG-0001', summary: {'code': 'VBG-0001', 'step': 'x'})),
        goTo_: '/pair/accueil/synchro',
      );
      expect(find.text(S.t('cases_errors_locked')), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('remove-f2')));
      await tester.pumpAndSettle();
      await tester.tap(find.text(S.t('common_cancel')));
      await tester.pumpAndSettle();
      expect(env.db.peerData.get('q:f2'), isNotNull);
      await tester.tap(find.byKey(const ValueKey('remove-f2')));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, S.t('mobile_sync_remove')));
      await tester.pumpAndSettle();
      expect(env.db.peerData.get('q:f2'), isNull);
    });
  });

  group('conflit 409 tranché par le pair (P11)', () {
    Map<String, FakeHandler> conflictRoutes({Map<String, FakeHandler> extra = const {}, bool pairInConflict = true}) => pairRoutes(extra: {
      'GET sync/conflicts': (_) => jsonBody(200, {'data': [conflictRow()], 'meta': {'open': 1}}),
      ...extra,
    });

    void seed(TestEnv env) => put(
      env,
      entry(
        'c1',
        QueueKind.caseUpdate,
        status: QueueStatus.conflict,
        code: 'VBG-0042',
        payload: {'code': 'VBG-0042', 'changes': {'sex': 'femme'}, 'base': {'sex': 'autre'}},
        conflicts: const [ConflictRef('sex', 42)],
      ),
    );

    testWidgets('écran de choix : explication, champ, valeur de départ, deux valeurs ; rien n’est choisi d’avance', (tester) async {
      await openPair(tester, routes: conflictRoutes(), before: seed, goTo_: '/pair/accueil/synchro/conflit/42');
      expect(find.text(S.t('mobile_conflict_title')), findsWidgets);
      expect(find.text(S.t('mobile_conflict_you')), findsOneWidget);
      expect(find.text('VBG-0042 · ${S.t('peer_collecte_fields_sex')}'), findsOneWidget);
      expect(find.text('Autre / non-binaire'), findsOneWidget, reason: 'valeur de départ (base)');
      await tester.scrollUntilVisible(find.byKey(const ValueKey('conflict-confirm')), 250, scrollable: find.byType(Scrollable).first);
      expect(find.text('Homme'), findsOneWidget, reason: 'valeur du serveur');
      expect(find.text('Femme'), findsOneWidget, reason: 'valeur de l’appareil');
      expect(tester.widget<FilledButton>(find.byKey(const ValueKey('conflict-confirm'))).onPressed, isNull);
      expect(find.text(S.t('mobile_conflict_choose_first')), findsOneWidget);
    });

    testWidgets('choisir « mon appareil » : confirmation, POST resolve, valeur retenue appliquée, entrée « tranchée par toi »', (tester) async {
      final env = await openPair(
        tester,
        routes: conflictRoutes(extra: {
          'POST sync/conflicts/42/resolve': (_) => jsonBody(200, {'data': {'id': 42, 'status': 'resolu', 'resolution': 'appareil'}, 'meta': {'open': 0}}),
        }),
        before: seed,
        goTo_: '/pair/accueil/synchro/conflit/42',
      );
      await tester.scrollUntilVisible(find.byKey(const ValueKey('option-appareil')), 250, scrollable: find.byType(Scrollable).first);
      await tester.ensureVisible(find.byKey(const ValueKey('option-appareil')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('option-appareil')));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byKey(const ValueKey('conflict-confirm')));
      await tester.tap(find.byKey(const ValueKey('conflict-confirm')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_conflict_confirm_title', {'value': 'Femme'})), findsOneWidget);
      expect(find.text(S.t('mobile_conflict_confirm_mine')), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('conflict-yes')));
      await tester.pumpAndSettle();
      final call = env.api.calls('POST sync/conflicts/42/resolve').single;
      expect((call.data as Map)['resolution'], 'appareil');
      expect(find.text(S.t('mobile_conflict_done_title')), findsOneWidget);
      expect(find.text(S.t('mobile_conflict_done_text', {'value': 'Femme'})), findsOneWidget);
      final e = QueueEntry.fromJson(jsonDecode(env.db.peerData.get('q:c1')!))!;
      expect(e.status, QueueStatus.sent);
      expect(e.resolution, 'appareil');
      await tester.tap(find.byKey(const ValueKey('conflict-back')));
      await tester.pumpAndSettle();
      expect(currentPath(tester), '/pair/accueil/synchro');
      expect(find.text(S.t('mobile_sync_state_resolved')), findsOneWidget);
    });

    testWidgets('choisir « valeur du serveur » : la bonne résolution part, texte de confirmation adapté, annuler n’envoie rien', (tester) async {
      final env = await openPair(
        tester,
        routes: conflictRoutes(extra: {
          'POST sync/conflicts/42/resolve': (_) => jsonBody(200, {'data': {'id': 42, 'status': 'resolu'}}),
        }),
        before: seed,
        goTo_: '/pair/accueil/synchro/conflit/42',
      );
      await tester.scrollUntilVisible(find.byKey(const ValueKey('option-serveur')), 250, scrollable: find.byType(Scrollable).first);
      await tester.ensureVisible(find.byKey(const ValueKey('option-serveur')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('option-serveur')));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byKey(const ValueKey('conflict-confirm')));
      await tester.tap(find.byKey(const ValueKey('conflict-confirm')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_conflict_confirm_server')), findsOneWidget);
      await tester.tap(find.text(S.t('common_cancel')));
      await tester.pumpAndSettle();
      expect(env.api.calls('POST sync/conflicts/42/resolve'), isEmpty);
      await tester.tap(find.byKey(const ValueKey('conflict-confirm')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('conflict-yes')));
      await tester.pumpAndSettle();
      expect((env.api.calls('POST sync/conflicts/42/resolve').single.data as Map)['resolution'], 'serveur');
    });

    testWidgets('refus du serveur (ex. dossier verrouillé) : message du serveur, rien n’est marqué résolu', (tester) async {
      final env = await openPair(
        tester,
        routes: conflictRoutes(extra: {
          'POST sync/conflicts/42/resolve': (_) => jsonBody(422, {'message': 'Ce dossier est archivé : le conflit ne peut plus être appliqué.', 'code': 'conflict_subject_missing'}),
        }),
        before: seed,
        goTo_: '/pair/accueil/synchro/conflit/42',
      );
      await tester.scrollUntilVisible(find.byKey(const ValueKey('option-appareil')), 250, scrollable: find.byType(Scrollable).first);
      await tester.ensureVisible(find.byKey(const ValueKey('option-appareil')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('option-appareil')));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byKey(const ValueKey('conflict-confirm')));
      await tester.tap(find.byKey(const ValueKey('conflict-confirm')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('conflict-yes')));
      await tester.pumpAndSettle();
      expect(find.text('Ce dossier est archivé : le conflit ne peut plus être appliqué.'), findsOneWidget);
      expect(QueueEntry.fromJson(jsonDecode(env.db.peerData.get('q:c1')!))!.status, QueueStatus.conflict);
    });

    testWidgets('hors ligne : on ne peut pas confirmer (effet immédiat), le message l’explique', (tester) async {
      await openPair(
        tester,
        online: false,
        routes: conflictRoutes(extra: {'GET sync/conflicts': (o) => throw offline(o)}),
        before: (env) {
          seed(env);
          env.db.peerData.put('conflicts', jsonEncode({'items': [conflictRow()]}));
        },
        goTo_: '/pair/accueil/synchro/conflit/42',
      );
      await tester.scrollUntilVisible(find.byKey(const ValueKey('conflict-confirm')), 250, scrollable: find.byType(Scrollable).first);
      expect(find.text(S.t('mobile_conflict_offline')), findsOneWidget);
      expect(tester.widget<FilledButton>(find.byKey(const ValueKey('conflict-confirm'))).onPressed, isNull);
    });

    testWidgets('conflit déjà résolu ou inconnu : message et retour à la file', (tester) async {
      await openPair(
        tester,
        routes: pairRoutes(), // `GET sync/conflicts` rend une liste vide
        goTo_: '/pair/accueil/synchro/conflit/999',
      );
      expect(find.text(S.t('mobile_conflict_not_found')), findsOneWidget);
    });

    testWidgets('depuis la file : « Résoudre le conflit » ouvre l’écran de choix', (tester) async {
      await openPair(tester, routes: conflictRoutes(), before: seed, goTo_: '/pair/accueil/synchro');
      await tester.tap(find.byKey(const ValueKey('resolve-42')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_conflict_what_h')), findsOneWidget);
    });
  });
}
