import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:intl/intl.dart';
import 'package:xomiton/core/sync/sync_providers.dart';
import 'package:xomiton/core/sync/sync_queue.dart';
import 'package:xomiton/features/pair/application/collect_logic.dart';
import 'package:xomiton/features/pair/data/pair_models.dart';

import '../support/fakes.dart';
import '../support/harness.dart';
import '../support/pair_harness.dart';

final S = loadStrings();

const _lists = CollectLists(
  zones: [
    CollectZone(1, 'Littoral', [IdLabel(3, 'Cotonou')]),
    CollectZone(2, 'Atlantique', [IdLabel(5, 'Abomey-Calavi'), IdLabel(6, 'Ouidah')]),
  ],
  populations: [IdLabel(2, 'Femmes cis')],
  violenceTypes: [IdLabel(4, 'Violence physique')],
);

ValidationRules rules([Map<String, dynamic>? body]) => ValidationRules.fromBody(body ?? rulesBody());

CollectValidator validator({ValidationRules? r, DateTime? today}) =>
    CollectValidator(rules: r ?? rules(), lists: _lists, today: today ?? DateTime(2026, 10, 29));

CollectForm full({String date = '2026-10-01', int? commune = 3, String notes = ''}) => CollectForm(
  kind: 'VBG',
  occurredOn: date,
  zoneId: 1,
  communeId: commune,
  populationId: 2,
  sex: 'femme',
  ageRange: '18-24',
  violenceTypeId: 4,
  notes: notes,
);

String isoDay(DateTime d) => DateFormat('yyyy-MM-dd').format(d);

Map<String, FakeHandler> collectRoutes({Map<String, FakeHandler> extra = const {}}) => pairRoutes(extra: extra);

/// Brouillon de la collecte, déjà au dernier écran (vérification).
void draftAtReview(TestEnv env, {String? date}) {
  env.db.peerData.put(
    'draft:collecte',
    jsonEncode({
      ...full(date: date ?? isoDay(DateTime.now().subtract(const Duration(days: 3)))).toDraft(),
      'step': 4,
    }),
  );
}

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('règles de la collecte (les mêmes que le serveur)', () {
    test('champs obligatoires par étape', () {
      final v = validator();
      final e0 = v.validate(const CollectForm(), steps: {CollectStep.dossier});
      expect(e0.keys, unorderedEquals(['kind', 'occurred_on']));
      expect(v.validate(const CollectForm(), steps: {CollectStep.lieu}).keys, ['zone_id']);
      expect(v.validate(const CollectForm(), steps: {CollectStep.personne}).keys, unorderedEquals(['population_id', 'sex', 'age_range']));
      expect(v.validate(const CollectForm(), steps: {CollectStep.faits}).keys, ['violence_type_id']);
      expect(v.validate(full()), isEmpty);
    });

    test('date : format, jour existant, pas dans le futur (règle active) ou futur permis (règle inactive)', () {
      final v = validator();
      expect(v.validate(full(date: '2026-10-30'))['occurred_on']!.key, 'peer_collecte_errors_date_future');
      expect(v.validate(full(date: '2026-10-29')), isEmpty, reason: 'aujourd’hui est permis');
      expect(v.validate(full(date: '29/10/2026'))['occurred_on']!.key, 'peer_collecte_errors_date_invalid');
      expect(v.validate(full(date: '2026-02-31'))['occurred_on']!.key, 'peer_collecte_errors_date_invalid');
      final off = rules(rulesBody(rules: [{'key': 'required'}]));
      expect(validator(r: off).validate(full(date: '2026-12-31')), isEmpty, reason: 'date_not_future désactivée');
    });

    test('commune ↔ département (commune_in_zone), commune facultative', () {
      final v = validator();
      expect(v.validate(full(commune: 5))['commune_id']!.key, 'peer_collecte_errors_commune_in_zone');
      expect(v.validate(full(commune: null)), isEmpty);
      expect(v.validate(full(commune: 999))['commune_id']!.key, 'peer_collecte_errors_invalid');
      final off = rules(rulesBody(rules: [{'key': 'required'}]));
      expect(validator(r: off).validate(full(commune: 5)), isEmpty, reason: 'règle désactivée : la commune doit seulement exister');
    });

    test('notes : vides ou au moins N caractères quand min_len_20 est active', () {
      final v = validator();
      expect(v.validate(full(notes: 'trop court'))['notes']!.args['min'], 20);
      expect(v.validate(full(notes: '')), isEmpty);
      expect(v.validate(full(notes: 'Résumé non identifiant de la situation.')), isEmpty);
      final off = rules(rulesBody(rules: [{'key': 'required'}]));
      expect(validator(r: off).validate(full(notes: 'court')), isEmpty);
    });

    test('tranche d’âge hors des bornes servies, orientation hors liste, valeurs de listes inconnues', () {
      final v = validator();
      expect(v.validate(full().copyWith(ageRange: '50+'))['age_range']!.key, 'peer_collecte_errors_invalid');
      expect(v.validate(full().copyWith(referral: 'Inconnu'))['referral']!.key, 'peer_collecte_errors_invalid');
      expect(v.validate(full().copyWith(sex: 'x'))['sex']!.key, 'peer_collecte_errors_invalid');
      expect(v.validate(full().copyWith(kind: 'ABC'))['kind']!.key, 'peer_collecte_errors_invalid');
      expect(v.validate(full().copyWith(zoneId: 77))['zone_id']!.key, 'peer_collecte_errors_invalid');
      expect(v.validate(full().copyWith(populationId: 77))['population_id']!.key, 'peer_collecte_errors_invalid');
    });

    test('avertissements non bloquants : date trop ancienne et doublon suspect (champs du catalogue)', () {
      final v = validator();
      final old = v.warnings(full(date: '2019-01-01'), const []);
      expect(old.single.key, 'cases_warnings_date_old');
      expect(old.single.args['years'], 5);
      expect(v.validate(full(date: '2019-01-01')), isEmpty, reason: 'l’avertissement ne bloque jamais');
      expect(v.warnings(full(), const []), isEmpty);

      final known = [
        {'code': 'VBG-0003', 'status': 'ouvert', 'occurred_on': '2026-10-01', 'commune_id': 3, 'population_id': 2, 'age_range': '18-24'},
        {'code': 'VBG-0004', 'status': 'archive', 'occurred_on': '2026-10-01', 'commune_id': 3, 'population_id': 2, 'age_range': '18-24'},
        {'code': 'VBG-0005', 'status': 'ouvert', 'occurred_on': '2026-10-02', 'commune_id': 3, 'population_id': 2, 'age_range': '18-24'},
      ];
      final dup = v.warnings(full(), known);
      expect(dup.single.key, 'cases_warnings_duplicate_suspected');
      expect(dup.single.args['codes'], 'VBG-0003');
      expect(dup.single.args['count'], 1);
      // Un champ vide ne prouve aucun doublon.
      expect(v.warnings(full(commune: null), known), isEmpty);
      // Champs du catalogue : seulement la date.
      final byDate = rules(rulesBody(rules: [
        {'key': 'duplicates', 'params': {'fields': ['date']}},
      ]));
      expect(validator(r: byDate).warnings(full(), known).single.args['count'], 1);
    });

    test('corps envoyé : champs vides omis ; brouillon sans notes', () {
      final f = full(commune: null, notes: ' Résumé ').copyWith(referral: '');
      final p = f.toPayload();
      expect(p.containsKey('commune_id'), isFalse);
      expect(p.containsKey('referral'), isFalse);
      expect(p['notes'], 'Résumé');
      expect(p['zone_id'], 1);
      expect(f.toDraft().containsKey('notes'), isFalse);
    });
  });

  group('parcours de collecte', () {
    Future<void> tapKey(WidgetTester tester, String key) async {
      final f = find.byKey(ValueKey(key));
      await tester.ensureVisible(f);
      await tester.pumpAndSettle();
      await tester.tap(f);
      await tester.pumpAndSettle();
    }

    Future<void> next(WidgetTester tester) async {
      await tester.ensureVisible(find.byKey(const ValueKey('col-next')));
      await tester.tap(find.byKey(const ValueKey('col-next')));
      await tester.pumpAndSettle();
    }

    Future<void> fillAll(WidgetTester tester, {String notes = ''}) async {
      await tapKey(tester, 'col-kind-VBG');
      await tapKey(tester, 'col-date');
      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();
      await next(tester);
      await pickDropdown(tester, 'col-zone', 'Littoral');
      await pickDropdown(tester, 'col-commune', 'Cotonou');
      await next(tester);
      await pickDropdown(tester, 'col-population', 'Femmes cis');
      await pickDropdown(tester, 'col-sex', 'Femme');
      await pickDropdown(tester, 'col-age', '18-24 ans');
      await next(tester);
      await pickDropdown(tester, 'col-violence', 'Violence physique');
      if (notes.isNotEmpty) await tester.enterText(find.byKey(const ValueKey('col-notes')), notes);
      await next(tester);
    }

    Map<String, FakeHandler> pushRoutes({int? failWith, Map<String, dynamic> Function(Map item)? result}) => collectRoutes(extra: {
      'POST sync/push': (o) {
        final items = ((o.data as Map)['items'] as List).cast<Map>();
        return jsonBody(200, {
          'data': {
            'items': [
              for (final i in items)
                result?.call(i) ?? {'uuid': i['uuid'], 'result': 'created', 'status': 201, 'reference': 'VBG-0099'},
            ],
          },
        });
      },
    });

    testWidgets('en ligne : cinq étapes, vérification, envoi dans un lot, code attribué par le serveur', (tester) async {
      final env = await openPair(tester, routes: pushRoutes(), goTo_: '/pair/dossiers/collecte');
      expect(find.text('${S.t('mobile_pair_col_step', {'n': 1, 'total': 5})} · ${S.t('mobile_pair_col_step_dossier')}'), findsOneWidget);
      expect(find.textContaining(S.t('peer_collecte_no_identity_title')), findsOneWidget);
      await fillAll(tester, notes: 'Résumé non identifiant de la situation.');
      expect(find.text(S.t('mobile_pair_col_review_title')), findsOneWidget);
      expect(find.text('Cotonou'), findsOneWidget);
      expect(find.text('Femmes cis'), findsOneWidget);
      expect(find.text(S.t('peer_collecte_submit')), findsOneWidget);
      await tester.ensureVisible(find.byKey(const ValueKey('col-submit')));
      await tester.tap(find.byKey(const ValueKey('col-submit')));
      await tester.pumpAndSettle();
      final push = env.api.calls('POST sync/push').single;
      final item = ((push.data as Map)['items'] as List).single as Map;
      expect(item['type'], 'collecte');
      expect(item['op'], 'create');
      final payload = item['payload'] as Map;
      expect(payload['client_uuid'], item['uuid']);
      expect(payload['kind'], 'VBG');
      expect(payload['zone_id'], 1);
      expect(payload['commune_id'], 3);
      expect(payload['population_id'], 2);
      expect(payload['sex'], 'femme');
      expect(payload['age_range'], '18-24');
      expect(payload['violence_type_id'], 4);
      expect(payload['notes'], 'Résumé non identifiant de la situation.');
      expect(find.text(S.t('mobile_pair_col_sent_title')), findsOneWidget);
      expect(find.text('VBG-0099'), findsWidgets);
      expect(find.text(S.t('mobile_sync_state_sent')), findsOneWidget);
      expect(env.db.peerData.get('draft:collecte'), isNull, reason: 'le brouillon est vidé à l’envoi');
    });

    testWidgets('étape incomplète : le message dit combien de champs, on ne passe pas à la suite', (tester) async {
      await openPair(tester, routes: collectRoutes(), goTo_: '/pair/dossiers/collecte');
      await next(tester);
      expect(find.text(S.t('mobile_pair_col_errors', {'count': 2})), findsOneWidget);
      expect(find.text(S.t('peer_collecte_errors_required')), findsWidgets);
      expect(find.text('${S.t('mobile_pair_col_step', {'n': 1, 'total': 5})} · ${S.t('mobile_pair_col_step_dossier')}'), findsOneWidget);
    });

    testWidgets('changer de département vide la commune ; commune d’abord grisée', (tester) async {
      await openPair(tester, routes: collectRoutes(), goTo_: '/pair/dossiers/collecte');
      await tapKey(tester, 'col-kind-VDH');
      await tapKey(tester, 'col-date');
      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();
      await next(tester);
      expect(find.text(S.t('mobile_pair_col_commune_first')), findsOneWidget);
      await pickDropdown(tester, 'col-zone', 'Atlantique');
      await pickDropdown(tester, 'col-commune', 'Ouidah');
      await pickDropdown(tester, 'col-zone', 'Littoral');
      expect(find.text('Ouidah'), findsNothing);
    });

    testWidgets('brouillon par étape : repris à la réouverture (sans les notes), effaçable', (tester) async {
      final env = await openPair(tester, routes: collectRoutes(), goTo_: '/pair/dossiers/collecte');
      await tester.tap(find.byKey(const ValueKey('col-kind-VDH')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('peer_collecte_draft_saved')), findsOneWidget);
      final draft = jsonDecode(env.db.peerData.get('draft:collecte')!) as Map;
      expect(draft['kind'], 'VDH');
      expect(draft.containsKey('notes'), isFalse);
      // On quitte et on revient : même saisie.
      goTo(tester, '/pair/dossiers');
      await tester.pumpAndSettle();
      goTo(tester, '/pair/dossiers/collecte');
      await tester.pumpAndSettle();
      final chip = tester.widget<ChoiceChip>(find.byKey(const ValueKey('col-kind-VDH')));
      expect(chip.selected, isTrue);
      await tester.tap(find.byKey(const ValueKey('col-draft-clear')));
      await tester.pumpAndSettle();
      expect(env.db.peerData.get('draft:collecte'), isNull);
      expect(tester.widget<ChoiceChip>(find.byKey(const ValueKey('col-kind-VDH'))).selected, isFalse);
    });

    testWidgets('avertissement de plausibilité : affiché, jamais bloquant, le dossier est bien envoyé', (tester) async {
      final env = await openPair(
        tester,
        routes: pushRoutes(),
        before: (env) => draftAtReview(env, date: '2019-01-15'),
        goTo_: '/pair/dossiers/collecte',
      );
      expect(find.byKey(const ValueKey('col-warnings')), findsOneWidget);
      expect(find.text(S.t('cases_warnings_date_old', {'years': 5})), findsOneWidget);
      await tester.ensureVisible(find.byKey(const ValueKey('col-submit')));
      await tester.tap(find.byKey(const ValueKey('col-submit')));
      await tester.pumpAndSettle();
      expect(env.api.calls('POST sync/push'), hasLength(1));
      expect(find.text(S.t('mobile_pair_col_sent_title')), findsOneWidget);
    });

    testWidgets('doublon suspect parmi les dossiers de l’appareil signalé avant l’envoi', (tester) async {
      final day = isoDay(DateTime.now().subtract(const Duration(days: 3)));
      await openPair(
        tester,
        routes: pushRoutes(),
        before: (env) {
          draftAtReview(env, date: day);
          env.db.peerData.put('c:cases:7', jsonEncode({'id': 7, 'code': 'VBG-0007', 'status': 'ouvert', 'occurred_on': day, 'commune_id': 3, 'population_id': 2, 'age_range': '18-24'}));
        },
        goTo_: '/pair/dossiers/collecte',
      );
      expect(find.text(S.t('cases_warnings_duplicate_suspected', {'count': 1, 'codes': 'VBG-0007'})), findsOneWidget);
    });

    testWidgets('mode avion : la fiche attend sur l’appareil (« En file »), puis part une seule fois au retour du réseau', (tester) async {
      var online = false;
      final seen = <String, int>{};
      final routes = collectRoutes(extra: {
        'POST sync/push': (o) {
          if (!online) throw offline(o);
          final items = ((o.data as Map)['items'] as List).cast<Map>();
          return jsonBody(200, {
            'data': {
              'items': [
                for (final i in items)
                  {
                    'uuid': i['uuid'],
                    // Le serveur déduplique par uuid : un rejeu rend `duplicate`, jamais un second dossier.
                    'result': (seen[i['uuid']] = (seen[i['uuid']] ?? 0) + 1) == 1 ? 'created' : 'duplicate',
                    'status': 200,
                    'reference': 'VBG-0123',
                  },
              ],
            },
          });
        },
      });
      final env = await openPair(
        tester,
        routes: routes,
        online: false,
        before: (env) => draftAtReview(env),
        goTo_: '/pair/dossiers/collecte',
      );
      expect(find.text(S.t('mobile_pair_col_submit_offline')), findsOneWidget);
      await tester.ensureVisible(find.byKey(const ValueKey('col-submit')));
      await tester.tap(find.byKey(const ValueKey('col-submit')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_pair_col_queued_title')), findsOneWidget);
      expect(find.text(S.t('mobile_pair_col_code_later')), findsOneWidget);
      expect(env.api.calls('POST sync/push'), isEmpty, reason: 'pas de réseau : rien n’est parti');
      final qKeys = env.db.peerData.keys.where((k) => k.startsWith('q:')).toList();
      expect(qKeys, hasLength(1));
      expect(env.db.peerData.get('draft:collecte'), isNull);
      final uuid = qKeys.single.substring(2);
      // Retour du réseau : synchronisation automatique, puis rejeu manuel du même lot.
      online = true;
      final engine = containerOf(tester).read(syncEngineProvider);
      await tester.runAsync(() => engine.sync(force: true));
      final entry = QueueEntry.fromJson(jsonDecode(env.db.peerData.get('q:$uuid')!))!;
      expect(entry.status, QueueStatus.sent);
      expect(entry.resultCode, 'VBG-0123');
      // Rejeu manuel (réponse perdue côté téléphone) : même uuid → pas de doublon.
      entry
        ..status = QueueStatus.pending
        ..batch = 'lot-rejoue';
      await tester.runAsync(() async {
        await containerOf(tester).read(syncQueueProvider).save(entry);
        await engine.sync(force: true);
      });
      expect(seen, {uuid: 2}, reason: 'un seul uuid, jamais un second dossier');
      expect(containerOf(tester).read(syncQueueProvider).find(uuid)!.status, QueueStatus.sent);
    });

    testWidgets('refus du serveur (422) : « Le dossier n’a pas été enregistré », Corriger rouvre la fiche avec l’erreur sur le champ', (tester) async {
      final env = await openPair(
        tester,
        routes: pushRoutes(
          result: (i) => {
            'uuid': i['uuid'],
            'result': 'rejected',
            'status': 422,
            'code': 'validation_failed',
            'errors': {'notes': ['Les notes contiennent au moins 20 caractères, ou restent vides.']},
          },
        ),
        // Règle « notes » désactivée dans la copie locale, active côté serveur : refus au retour.
        before: (env) => draftAtReview(env),
        goTo_: '/pair/dossiers/collecte',
      );
      await tester.ensureVisible(find.byKey(const ValueKey('col-submit')));
      await tester.tap(find.byKey(const ValueKey('col-submit')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('peer_collecte_refused_title')), findsOneWidget);
      expect(find.text('Les notes contiennent au moins 20 caractères, ou restent vides.'), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('col-correct')));
      await tester.pumpAndSettle();
      expect(env.db.peerData.keys.where((k) => k.startsWith('q:')), isEmpty, reason: 'la fiche refusée sort de la file');
      expect(find.text('${S.t('mobile_pair_col_step', {'n': 4, 'total': 5})} · ${S.t('mobile_pair_col_step_faits')}'), findsOneWidget);
      expect(find.text('Les notes contiennent au moins 20 caractères, ou restent vides.'), findsOneWidget);
    });

    testWidgets('aucun référentiel codé en dur : sans les listes du serveur, la collecte le dit', (tester) async {
      await openPair(
        tester,
        online: false,
        routes: collectRoutes(extra: {
          'GET zones': (o) => throw offline(o),
          'GET violence-types': (o) => throw offline(o),
        }),
        goTo_: '/pair/dossiers/collecte',
      );
      expect(find.text(S.t('mobile_pair_col_lists_missing')), findsOneWidget);
      expect(find.byKey(const ValueKey('col-next')), findsNothing);
    });

    testWidgets('modifier depuis la vérification : retour sur l’étape concernée', (tester) async {
      await openPair(tester, routes: collectRoutes(), before: draftAtReview, goTo_: '/pair/dossiers/collecte');
      await tester.ensureVisible(find.byKey(const ValueKey('col-edit-lieu')));
      await tester.tap(find.byKey(const ValueKey('col-edit-lieu')));
      await tester.pumpAndSettle();
      expect(find.text('${S.t('mobile_pair_col_step', {'n': 2, 'total': 5})} · ${S.t('mobile_pair_col_step_lieu')}'), findsOneWidget);
    });

    testWidgets('cibles tactiles de 44 px au moins (puces, boutons)', (tester) async {
      await openPair(tester, routes: collectRoutes(), goTo_: '/pair/dossiers/collecte');
      for (final k in ['col-kind-VBG', 'col-kind-VDH', 'col-next', 'col-date']) {
        expect(tester.getSize(find.byKey(ValueKey(k))).height, greaterThanOrEqualTo(44), reason: k);
      }
    });
  });
}
