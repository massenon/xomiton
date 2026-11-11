import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hive/hive.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:xomiton/core/network/api_client.dart';
import 'package:xomiton/core/network/app_toast.dart';
import 'package:xomiton/core/providers/core_providers.dart';
import 'package:xomiton/core/storage/local_db.dart';
import 'package:xomiton/core/storage/secret_store.dart';
import 'package:xomiton/core/storage/token_store.dart';
import 'package:xomiton/core/sync/collection_store.dart';
import 'package:xomiton/core/sync/sync_engine.dart';
import 'package:xomiton/core/sync/sync_providers.dart';
import 'package:xomiton/core/sync/sync_queue.dart';
import 'package:xomiton/features/auth/application/session_controller.dart';

import '../support/fakes.dart';
import '../support/harness.dart';

class Rig {
  Rig(Map<String, FakeHandler> routes, {bool runPair = true, Future<void> Function()? onWipe, this.deviceCalls}) {
    api = FakeApi(routes);
    db = LocalDb.memory();
    queue = SyncQueue(db.peerData);
    pair = CollectionStore(db.peerData);
    common = CollectionStore(db.publicCache, ns: 'sync:');
    dio = buildApiClient(
      tokens: TokenStore(MemorySecretStore(), initial: 't'),
      toasts: ToastBus(),
      onUnauthorized: () async {},
      baseUrl: 'https://api.test/api/v1',
      adapter: api,
      replayWait: (_) async {},
    );
    engine = SyncEngine(
      dio: dio,
      queue: queue,
      pair: pair,
      common: common,
      ensureDevice: () async {
        deviceCalls?.add(1);
        return 'dev-1';
      },
      runPair: runPair,
      runCommon: runPair,
      now: () => now,
      wait: (d) async => waits.add(d),
      random: Random(1),
      onWipe: onWipe,
    );
  }
  late final FakeApi api;
  late final LocalDb db;
  late final SyncQueue queue;
  late final CollectionStore pair;
  late final CollectionStore common;
  late final Dio dio;
  late final SyncEngine engine;
  final List<int>? deviceCalls;
  DateTime now = DateTime.utc(2026, 10, 29, 9);
  final List<Duration> waits = [];

  Future<QueueEntry> addCase(String uuid, {Map<String, dynamic>? payload}) async {
    final e = QueueEntry(
      uuid: uuid,
      kind: QueueKind.caseCreate,
      payload: payload ?? {'kind': 'VBG', 'occurred_on': '2026-10-01', 'zone_id': 1},
      createdAt: now,
    );
    await queue.save(e);
    return e;
  }
}

Map<String, dynamic> pushOk(List<Map<String, dynamic>> items) => {
  'data': {'items': items},
  'meta': {'created': items.length},
};

Map<String, dynamic> emptyPull({String cursor = 'c1'}) => {
  'data': {for (final c in [...kPairCollections, ...kCommonCollections]) c: {'reset': false, 'upserts': [], 'deletes': [], 'has_more': false}},
  'meta': {'cursor': cursor, 'has_more': false},
};

Map<String, FakeHandler> base({Map<String, FakeHandler> extra = const {}}) => {
  'GET sync/pull': (_) => jsonBody(200, emptyPull()),
  'GET pair/validation-rules': (_) => jsonBody(200, {'data': [], 'meta': {'updated_at': 'r1', 'options': {}}}, headers: {'ETag': '"r1"'}),
  'GET sync/conflicts': (_) => jsonBody(200, {'data': [], 'meta': {'open': 0}}),
  ...extra,
};

void main() {
  group('file d’envoi du pair (chiffrée, idempotente)', () {
    test('aller-retour JSON et ordre par ancienneté ; les envoyés sont tronqués', () async {
      final q = SyncQueue(MemoryStringBox(), keepSent: 2);
      final a = QueueEntry(uuid: 'a', kind: QueueKind.caseCreate, payload: {'x': 1}, createdAt: DateTime.utc(2026, 1, 1));
      final b = QueueEntry(uuid: 'b', kind: QueueKind.followupCreate, payload: {}, createdAt: DateTime.utc(2025, 1, 1), caseUuid: 'a');
      await q.save(a);
      await q.save(b);
      expect(q.list().map((e) => e.uuid), ['b', 'a']);
      expect(q.find('b')!.caseUuid, 'a');
      for (var i = 0; i < 4; i++) {
        await q.save(QueueEntry(
          uuid: 's$i',
          kind: QueueKind.caseCreate,
          payload: {},
          createdAt: DateTime.utc(2026, 2, i + 1),
          status: QueueStatus.sent,
          sentAt: DateTime.utc(2026, 2, i + 1),
        ));
      }
      await q.trimSent();
      expect(q.sent().map((e) => e.uuid), unorderedEquals(['s2', 's3']));
      expect(q.open(), hasLength(2));
    });
  });

  group('sync/push', () {
    test('un lot, Idempotency-Key = uuid du lot, résultat par élément ; le code est retenu', () async {
      late Rig rig;
      rig = Rig(base(extra: {
        'POST sync/push': (o) {
          final items = (o.data as Map)['items'] as List;
          return jsonBody(200, pushOk([
            {'uuid': items[0]['uuid'], 'result': 'created', 'status': 201, 'reference': 'VBG-0007', 'warnings': [{'code': 'date_old', 'message': 'x'}]},
            {'uuid': items[1]['uuid'], 'result': 'duplicate', 'status': 200, 'reference': 'VBG-0008'},
          ]));
        },
      }));
      await rig.addCase('u1');
      await rig.addCase('u2');
      final sent = await rig.engine.sync(force: true);
      expect(sent, 2);
      final calls = rig.api.calls('POST sync/push');
      expect(calls, hasLength(1));
      final key = calls.single.headers['Idempotency-Key'] as String;
      expect(key, isNot('u1'));
      expect(isUuidV4(key), isTrue);
      final body = calls.single.data as Map;
      expect((body['items'] as List).map((i) => i['uuid']), ['u1', 'u2']);
      expect((body['items'] as List).first['type'], 'collecte');
      expect((body['items'] as List).first['op'], 'create');
      expect(rig.queue.find('u1')!.status, QueueStatus.sent);
      expect(rig.queue.find('u1')!.resultCode, 'VBG-0007');
      expect(rig.queue.find('u1')!.warnings.single['code'], 'date_old');
      expect(rig.pair.text('code:u1'), 'VBG-0007');
      expect(rig.queue.find('u2')!.status, QueueStatus.sent, reason: 'duplicate = succès, jamais de doublon');
      expect(rig.engine.state.lastSent, 2);
      expect(rig.engine.state.lastSync, isNotNull);
    });

    test('lots de 50 au plus', () async {
      final rig = Rig(base(extra: {
        'POST sync/push': (o) => jsonBody(200, pushOk([
          for (final i in (o.data as Map)['items'] as List) {'uuid': i['uuid'], 'result': 'created', 'status': 201, 'reference': 'VBG-1'},
        ])),
      }));
      for (var i = 0; i < 120; i++) {
        await rig.addCase('u${i.toString().padLeft(3, '0')}');
      }
      expect(await rig.engine.sync(force: true), 120);
      final sizes = rig.api.calls('POST sync/push').map((r) => ((r.data as Map)['items'] as List).length).toList();
      expect(sizes, [50, 50, 20]);
      final keys = rig.api.calls('POST sync/push').map((r) => r.headers['Idempotency-Key']).toSet();
      expect(keys, hasLength(3));
    });

    test('coupure réseau : l’élément reste, le rejeu part avec la MÊME clé et le MÊME corps', () async {
      var offline = true;
      final rig = Rig(base(extra: {
        'POST sync/push': (o) {
          if (offline) throw offlineError(o);
          return jsonBody(200, pushOk([
            for (final i in (o.data as Map)['items'] as List) {'uuid': i['uuid'], 'result': 'created', 'status': 201, 'reference': 'VBG-9'},
          ]));
        },
      }));
      await rig.addCase('u1');
      expect(await rig.engine.sync(force: true), 0);
      expect(rig.engine.state.offline, isTrue);
      expect(rig.queue.find('u1')!.status, QueueStatus.pending);
      final batch = rig.queue.find('u1')!.batch;
      expect(batch, isNotNull);
      offline = false;
      // Un nouvel élément arrive entre-temps : il ne rejoint PAS le lot déjà posé.
      await rig.addCase('u2');
      expect(await rig.engine.sync(force: true), 2);
      final calls = rig.api.calls('POST sync/push');
      expect(calls, hasLength(3));
      expect(calls[1].headers['Idempotency-Key'], batch);
      expect(jsonEncode(calls[1].data), jsonEncode(calls[0].data));
      expect(calls[2].headers['Idempotency-Key'], isNot(batch));
      expect(((calls[2].data as Map)['items'] as List).single['uuid'], 'u2');
    });

    test('202 processing : attente de Retry-After puis rejeu à l’identique', () async {
      var n = 0;
      final rig = Rig(base(extra: {
        'POST sync/push': (o) {
          n++;
          if (n == 1) {
            return jsonBody(202, {'data': {'status': 'processing', 'retry_after': 3}}, headers: {'Retry-After': '3'});
          }
          return jsonBody(200, pushOk([
            {'uuid': 'u1', 'result': 'created', 'status': 201, 'reference': 'VBG-5'},
          ]));
        },
      }));
      await rig.addCase('u1');
      expect(await rig.engine.sync(force: true), 1);
      expect(rig.waits, contains(const Duration(seconds: 3)));
      final calls = rig.api.calls('POST sync/push');
      expect(calls, hasLength(2));
      expect(calls[0].headers['Idempotency-Key'], calls[1].headers['Idempotency-Key']);
    });

    test('lot partiellement rejeté : les éléments valides partent, l’erreur est sur l’élément fautif', () async {
      final rig = Rig(base(extra: {
        'POST sync/push': (_) => jsonBody(200, pushOk([
          {'uuid': 'ok', 'result': 'created', 'status': 201, 'reference': 'VBG-3'},
          {'uuid': 'bad', 'result': 'rejected', 'status': 422, 'code': 'validation_failed', 'errors': {'occurred_on': ['La date ne peut pas être postérieure à aujourd’hui.']}},
          {'uuid': 'slow', 'result': 'rejected', 'status': 429, 'code': 'too_many_requests'},
          {'uuid': 'down', 'result': 'rejected', 'status': 503, 'code': 'retry_later'},
        ])),
      }));
      for (final u in ['ok', 'bad', 'slow', 'down']) {
        await rig.addCase(u);
      }
      expect(await rig.engine.sync(force: true), 1);
      expect(rig.queue.find('ok')!.status, QueueStatus.sent);
      final bad = rig.queue.find('bad')!;
      expect(bad.status, QueueStatus.failed);
      expect(bad.failure, 'validation_failed');
      expect(bad.errors['occurred_on'], isNotEmpty);
      for (final u in ['slow', 'down']) {
        expect(rig.queue.find(u)!.status, QueueStatus.pending, reason: '429 et 503 repartent dans un nouveau lot');
        expect(rig.queue.find(u)!.batch, isNull);
      }
    });

    test('409 : l’élément passe en conflit avec ses références ; « retry » remet un refus en file', () async {
      final rig = Rig(base(extra: {
        'POST sync/push': (_) => jsonBody(200, pushOk([
          {'uuid': 'c', 'result': 'conflict', 'status': 409, 'code': 'conflict', 'conflicts': [{'field': 'sex', 'conflict_id': 42}]},
        ])),
      }));
      await rig.queue.save(QueueEntry(
        uuid: 'c',
        kind: QueueKind.caseUpdate,
        payload: {'code': 'VBG-0001', 'changes': {'sex': 'femme'}, 'base': {'sex': 'homme'}},
        createdAt: rig.now,
      ));
      await rig.engine.sync(force: true);
      final c = rig.queue.find('c')!;
      expect(c.status, QueueStatus.conflict);
      expect(c.conflicts.single.id, 42);
      expect(c.conflicts.single.field, 'sex');
      expect(rig.queue.pending(), isEmpty, reason: 'un conflit ne repart pas tout seul');

      final failed = QueueEntry(uuid: 'f', kind: QueueKind.caseCreate, payload: {}, createdAt: rig.now, status: QueueStatus.failed, failure: 'validation_failed', batch: 'old');
      await rig.queue.save(failed);
      await rig.queue.retry('f');
      expect(rig.queue.find('f')!.status, QueueStatus.pending);
      expect(rig.queue.find('f')!.batch, isNull);
    });

    test('lot refusé en bloc (422) : éléments à corriger, rien n’est perdu', () async {
      final rig = Rig(base(extra: {
        'POST sync/push': (_) => jsonBody(422, {'message': 'x', 'code': 'validation_failed', 'errors': {'items': ['Lot mal formé.']}}),
      }));
      await rig.addCase('u1');
      await rig.engine.sync(force: true);
      expect(rig.queue.find('u1')!.status, QueueStatus.failed);
      expect(rig.queue.find('u1')!.payload['kind'], 'VBG');
    });

    test('idempotency_key_reused : le lot repart sous une clé neuve', () async {
      var n = 0;
      final rig = Rig(base(extra: {
        'POST sync/push': (o) {
          n++;
          if (n == 1) return jsonBody(422, {'message': 'x', 'code': 'idempotency_key_reused'});
          return jsonBody(200, pushOk([{'uuid': 'u1', 'result': 'created', 'status': 201, 'reference': 'VBG-1'}]));
        },
      }));
      await rig.addCase('u1');
      await rig.engine.sync(force: true);
      expect(rig.queue.find('u1')!.status, QueueStatus.pending);
      expect(rig.queue.find('u1')!.batch, isNull);
      await rig.engine.sync(force: true);
      expect(rig.queue.find('u1')!.status, QueueStatus.sent);
      final keys = rig.api.calls('POST sync/push').map((r) => r.headers['Idempotency-Key']).toList();
      expect(keys[0], isNot(keys[1]));
    });
  });

  group('suivis', () {
    test('un suivi d’un dossier encore en file attend son code, puis part avec son uuid comme client_uuid', () async {
      final rig = Rig(base(extra: {
        'POST sync/push': (_) => jsonBody(200, pushOk([{'uuid': 'c1', 'result': 'created', 'status': 201, 'reference': 'VBG-0042'}])),
        'POST pair/cases/VBG-0042/followups': (o) => jsonBody(201, {'data': {'id': 9, 'step': 'Appel de suivi'}}),
      }));
      await rig.addCase('c1');
      await rig.queue.save(QueueEntry(
        uuid: 'f1',
        kind: QueueKind.followupCreate,
        payload: {'date': '2026-10-02', 'step': 'Appel de suivi', 'outcome': 'planifie'},
        createdAt: rig.now.add(const Duration(seconds: 1)),
        caseUuid: 'c1',
      ));
      expect(await rig.engine.sync(force: true), 2);
      final call = rig.api.calls('POST pair/cases/VBG-0042/followups').single;
      expect((call.data as Map)['client_uuid'], 'f1');
      expect(call.headers['Idempotency-Key'], 'f1');
      expect(rig.queue.find('f1')!.status, QueueStatus.sent);
    });

    test('sans code connu le suivi reste en file ; un refus 422 le laisse modifiable', () async {
      final rig = Rig(base(extra: {
        'POST pair/cases/VBG-0001/followups': (_) => jsonBody(422, {'message': 'x', 'code': 'case_locked'}),
        'PATCH pair/cases/VBG-0001/followups/5': (_) => jsonBody(200, {'data': {'id': 5, 'outcome': 'realise'}}),
      }));
      await rig.queue.save(QueueEntry(uuid: 'w', kind: QueueKind.followupCreate, payload: {}, createdAt: rig.now, caseUuid: 'inconnu'));
      await rig.queue.save(QueueEntry(uuid: 'l', kind: QueueKind.followupCreate, payload: {'step': 'x'}, createdAt: rig.now.add(const Duration(seconds: 1)), caseCode: 'VBG-0001'));
      await rig.queue.save(QueueEntry(uuid: 'd', kind: QueueKind.followupDone, payload: {'id': 5, 'outcome': 'realise'}, createdAt: rig.now.add(const Duration(seconds: 2)), caseCode: 'VBG-0001'));
      await rig.engine.sync(force: true);
      expect(rig.queue.find('w')!.status, QueueStatus.pending);
      expect(rig.queue.find('l')!.status, QueueStatus.failed);
      expect(rig.queue.find('l')!.failure, 'case_locked');
      expect(rig.queue.find('d')!.status, QueueStatus.sent);
    });
  });

  group('sync/pull', () {
    Map<String, dynamic> page(Map<String, Map<String, dynamic>> cols, {String cursor = 'c', bool more = false}) => {
      'data': cols,
      'meta': {'cursor': cursor, 'has_more': more},
    };

    test('premier tirage : reset remplace la collection ; deltas ensuite avec le curseur, suppressions comprises', () async {
      var n = 0;
      final rig = Rig(base(extra: {
        'GET sync/pull': (o) {
          final cols = (o.queryParameters['collections'] as List).cast<String>();
          if (!cols.contains('cases')) {
            return jsonBody(200, page({
              'emergency_contacts': {'reset': true, 'upserts': [{'id': 7, 'name': 'Police'}], 'deletes': [], 'has_more': false},
            }, cursor: 'common-1'));
          }
          n++;
          if (n == 1) {
            return jsonBody(200, page({
              'cases': {'reset': true, 'upserts': [{'id': 1, 'code': 'VBG-0001'}, {'id': 2, 'code': 'VBG-0002'}], 'deletes': [], 'has_more': false},
            }, cursor: 'cur-1'));
          }
          return jsonBody(200, page({
            'cases': {'reset': false, 'upserts': [{'id': 2, 'code': 'VBG-0002', 'status': 'clos'}], 'deletes': [1], 'has_more': false},
          }, cursor: 'cur-2'));
        },
      }));
      await rig.pair.put('cases', 99, {'id': 99, 'code': 'ANCIEN'});
      await rig.engine.sync(force: true);
      expect(rig.pair.rows('cases').map((r) => r['id']), unorderedEquals([1, 2]), reason: 'reset : l’ancienne ligne a disparu');
      expect(rig.common.rows('emergency_contacts').single['name'], 'Police');
      await rig.engine.sync(force: true);
      final pulls = rig.api.calls('GET sync/pull');
      expect(pulls.any((r) => r.queryParameters['since'] == 'cur-1'), isTrue);
      expect(rig.pair.rows('cases').single['status'], 'clos');
      expect(rig.pair.row('cases', 1), isNull);
    });

    test('has_more : on rappelle aussitôt avec le nouveau curseur, tout est appliqué à la fin', () async {
      final seen = <String?>[];
      final rig = Rig(base(extra: {
        'GET sync/pull': (o) {
          final since = o.queryParameters['since'] as String?;
          seen.add(since);
          if (since == null) {
            return jsonBody(200, page({'cases': {'reset': true, 'upserts': [{'id': 1}], 'deletes': [], 'has_more': true}}, cursor: 'p1', more: true));
          }
          return jsonBody(200, page({'cases': {'reset': false, 'upserts': [{'id': 2}], 'deletes': [], 'has_more': false}}, cursor: 'p2'));
        },
      }), runPair: true);
      await rig.engine.sync(force: true);
      expect(seen.take(2), [null, 'p1']);
      expect(rig.pair.rows('cases'), hasLength(2));
      expect(rig.pair.text('cursor:pair'), 'p2');
    });

    test('arrêt pendant le tirage : le curseur n’avance pas et la collection reste cohérente', () async {
      var n = 0;
      final rig = Rig(base(extra: {
        'GET sync/pull': (o) {
          n++;
          if (n == 1) return jsonBody(200, page({'cases': {'reset': true, 'upserts': [{'id': 1}], 'deletes': [], 'has_more': false}}, cursor: 'ok'));
          // Deuxième tirage : page 1 puis coupure au milieu.
          final since = o.queryParameters['since'];
          if (since == 'ok') return jsonBody(200, page({'cases': {'reset': false, 'upserts': [{'id': 5}], 'deletes': [1], 'has_more': true}}, cursor: 'half', more: true));
          throw offlineError(o);
        },
      }));
      await rig.engine.sync(force: true);
      expect(rig.pair.text('cursor:pair'), 'ok');
      await rig.engine.sync(force: true);
      expect(rig.pair.text('cursor:pair'), 'ok', reason: 'le curseur reste celui du dernier tirage complet');
      expect(rig.pair.rows('cases').map((r) => r['id']), [1], reason: 'rien n’a été appliqué à moitié');
    });

    test('curseur illisible (422 cursor_invalid) : tirage complet', () async {
      var n = 0;
      final rig = Rig(base(extra: {
        'GET sync/pull': (o) {
          n++;
          if (o.queryParameters['since'] != null) return jsonBody(422, {'message': 'x', 'code': 'cursor_invalid'});
          return jsonBody(200, page({'cases': {'reset': true, 'upserts': [{'id': 3}], 'deletes': [], 'has_more': false}}, cursor: 'neuf'));
        },
      }));
      await rig.pair.putText('cursor:pair', 'illisible');
      await rig.engine.sync(force: true);
      expect(n, greaterThanOrEqualTo(2));
      expect(rig.pair.text('cursor:pair'), 'neuf');
      expect(rig.pair.rows('cases').single['id'], 3);
    });

    test('compte sans espace pair : ni push ni collections du pair', () async {
      final rig = Rig(base(), runPair: false);
      await rig.engine.sync(force: true);
      expect(rig.api.calls('POST sync/push'), isEmpty);
      expect(rig.api.calls('GET pair/validation-rules'), isEmpty);
      expect(rig.api.calls('GET sync/pull'), isEmpty);
    });
  });

  group('heartbeat, règles, conflits', () {
    test('heartbeat annonce les éléments en attente et lit les conflits ouverts', () async {
      final rig = Rig(base(extra: {
        'POST heartbeat': (_) => jsonBody(200, {'data': {'server_time': 'x', 'device': {'device_uuid': 'dev-1'}, 'open_conflicts': 2}}),
        'GET sync/conflicts': (_) => jsonBody(200, {'data': [{'id': 1}, {'id': 2}], 'meta': {'open': 2}}),
      }));
      await rig.addCase('u1');
      expect(await rig.engine.heartbeat(), isTrue);
      final body = rig.api.calls('POST heartbeat').single.data as Map;
      expect(body['pending'], 1);
      expect(body['app_version'], isNotEmpty);
      expect(rig.engine.state.openConflicts, 2);
      expect(rig.engine.conflicts(), hasLength(2));
    });

    test('heartbeat hors ligne : false ; effacement à distance : onWipe', () async {
      var wiped = 0;
      final off = Rig(base(extra: {'POST heartbeat': (o) => throw offlineError(o)}));
      expect(await off.engine.heartbeat(), isFalse);
      expect(off.engine.state.offline, isTrue);

      final rig = Rig(base(extra: {
        'POST heartbeat': (_) => jsonBody(200, {'data': {'server_time': 'x', 'wipe': {'requested_at': 'x', 'reason': 'admin'}}}),
      }), onWipe: () async => wiped++);
      await rig.engine.heartbeat();
      expect(wiped, 1);
    });

    test('rules_updated_at différent : les règles sont relues (ETag)', () async {
      final rig = Rig(base(extra: {
        'POST heartbeat': (_) => jsonBody(200, {'data': {'open_conflicts': 0, 'rules_updated_at': 'r2'}}),
      }));
      await rig.engine.refreshRules();
      expect(rig.api.calls('GET pair/validation-rules'), hasLength(1));
      await rig.engine.heartbeat();
      expect(rig.api.calls('GET pair/validation-rules'), hasLength(2));
      expect(rig.api.calls('GET pair/validation-rules').last.headers['If-None-Match'], '"r1"');
    });

    test('règles : 304 → copie locale, hors ligne → copie locale', () async {
      var mode = 0;
      final rig = Rig(base(extra: {
        'GET pair/validation-rules': (o) {
          if (mode == 1) return notModified();
          if (mode == 2) throw offlineError(o);
          return jsonBody(200, {'data': [{'key': 'min_len_20'}], 'meta': {'updated_at': 'r1', 'options': {'notes_min_length': 20}}}, headers: {'ETag': '"r1"'});
        },
      }));
      final first = await rig.engine.refreshRules();
      expect((first!['body'] as Map)['data'], isNotEmpty);
      mode = 1;
      expect(await rig.engine.refreshRules(), isNotNull);
      mode = 2;
      expect(await rig.engine.refreshRules(), isNotNull);
    });

    test('le pair tranche : POST sync/conflicts/{id}/resolve, l’élément est « tranché par toi », les dossiers sont relus', () async {
      final rig = Rig(base(extra: {
        'POST sync/conflicts/42/resolve': (_) => jsonBody(200, {'data': {'id': 42, 'status': 'resolu', 'resolution': 'appareil'}, 'meta': {'open': 0}}),
      }));
      await rig.pair.putJson('conflicts', {'items': [{'id': 42}, {'id': 43}]});
      await rig.queue.save(QueueEntry(
        uuid: 'c',
        kind: QueueKind.caseUpdate,
        payload: {},
        createdAt: rig.now,
        status: QueueStatus.conflict,
        conflicts: const [ConflictRef('sex', 42)],
      ));
      await rig.engine.resolveConflict(42, 'appareil');
      final call = rig.api.calls('POST sync/conflicts/42/resolve').single;
      expect((call.data as Map)['resolution'], 'appareil');
      final e = rig.queue.find('c')!;
      expect(e.status, QueueStatus.sent);
      expect(e.resolution, 'appareil');
      expect(rig.engine.conflicts().map((c) => c['id']), [43]);
      await Future<void>.delayed(const Duration(milliseconds: 20));
      expect(rig.api.calls('GET sync/pull'), isNotEmpty);
    });

    test('conflit déjà tranché (422) : aligné sans erreur ; autre erreur : remontée', () async {
      final rig = Rig(base(extra: {
        'POST sync/conflicts/1/resolve': (_) => jsonBody(422, {'message': 'x', 'code': 'conflict_already_resolved'}),
        'POST sync/conflicts/2/resolve': (_) => jsonBody(404, {'message': 'x', 'code': 'not_found'}),
      }));
      await rig.pair.putJson('conflicts', {'items': [{'id': 1}]});
      await rig.engine.resolveConflict(1, 'serveur');
      expect(rig.engine.conflicts(), isEmpty);
      await expectLater(rig.engine.resolveConflict(2, 'serveur'), throwsA(isA<DioException>()));
    });
  });

  group('attente après échec et enregistrement de l’appareil', () {
    test('exponentielle avec gigue, plafonnée à 5 min', () {
      final r = Random(3);
      final d = [for (var i = 1; i <= 10; i++) syncBackoff(i, r)];
      expect(syncBackoff(0), Duration.zero);
      expect(d[0].inMilliseconds, inInclusiveRange(3750, 6250));
      expect(d[1].inMilliseconds, greaterThan(d[0].inMilliseconds * 0.9));
      for (final x in d) {
        expect(x.inSeconds, lessThanOrEqualTo(375));
      }
      expect(d.last.inSeconds, greaterThan(200));
    });

    test('après un échec 5xx, les passages automatiques attendent ; une action de la personne passe outre', () async {
      var fail = true;
      final rig = Rig(base(extra: {
        'POST sync/push': (o) {
          if (fail) return jsonBody(503, {'message': 'x', 'code': 'retry_later'});
          return jsonBody(200, pushOk([{'uuid': 'u1', 'result': 'created', 'status': 201, 'reference': 'VBG-1'}]));
        },
      }));
      await rig.addCase('u1');
      await rig.engine.sync();
      expect(rig.engine.canAutoRun, isFalse);
      final before = rig.api.calls('POST sync/push').length;
      await rig.engine.sync();
      expect(rig.api.calls('POST sync/push'), hasLength(before), reason: 'attente respectée');
      fail = false;
      await rig.engine.sync(force: true);
      expect(rig.queue.find('u1')!.status, QueueStatus.sent);
      expect(rig.engine.canAutoRun, isTrue);
      // Avec le temps, l’attente expire d’elle-même.
      fail = true;
      await rig.addCase('u2');
      await rig.engine.sync();
      expect(rig.engine.canAutoRun, isFalse);
      rig.now = rig.now.add(const Duration(minutes: 10));
      expect(rig.engine.canAutoRun, isTrue);
    });

    test('l’appareil est enregistré avant toute synchronisation, une seule fois', () async {
      final calls = <int>[];
      final rig = Rig(base(), deviceCalls: calls);
      await rig.engine.sync(force: true);
      await rig.engine.sync(force: true);
      expect(calls, hasLength(1));
    });
  });

  group('403 device_required / device_revoked', () {
    Future<(ProviderContainer, TestEnv)> signedIn({String? deviceUuid}) async {
      SharedPreferences.setMockInitialValues({kDeviceUuidPref: ?deviceUuid});
      final prefs = await SharedPreferences.getInstance();
      final env = TestEnv(
        api: FakeApi({
          'GET sync/pull': (_) => jsonBody(403, {'message': 'x', 'code': 'device_required'}),
        }),
        token: 'tok',
      );
      final c = ProviderContainer(overrides: env.overrides(prefs));
      addTearDown(c.dispose);
      c.read(sessionProvider);
      return (c, env);
    }

    test('appareil déjà enregistré : le serveur ne le connaît plus → purge totale', () async {
      final (c, env) = await signedIn(deviceUuid: 'dev-9');
      await env.db.peerData.put('c:cases:1', '{}');
      await expectLater(c.read(dioProvider).get<dynamic>('sync/pull'), throwsA(isA<DioException>()));
      await Future<void>.delayed(const Duration(milliseconds: 50));
      expect(c.read(sessionProvider).authenticated, isFalse);
      expect(env.db.peerData.keys, isEmpty);
    });

    test('appareil pas encore enregistré : rien n’est purgé (le moteur enregistre d’abord)', () async {
      final (c, env) = await signedIn();
      await env.db.peerData.put('c:cases:1', '{}');
      await expectLater(c.read(dioProvider).get<dynamic>('sync/pull'), throwsA(isA<DioException>()));
      await Future<void>.delayed(const Duration(milliseconds: 50));
      expect(c.read(sessionProvider).authenticated, isTrue);
      expect(env.db.peerData.keys, isNotEmpty);
    });
  });

  group('coffre de l’espace pair : clé propre', () {
    late Directory dir;
    setUp(() => dir = Directory.systemTemp.createTempSync('xomiton_peer'));
    tearDown(() async {
      await Hive.close();
      dir.deleteSync(recursive: true);
    });

    test('clé distincte de celle des coffres Membre ; données illisibles en clair', () async {
      final secrets = MemorySecretStore();
      final db = await LocalDb.open(secrets, path: dir.path);
      expect(secrets.data[SecretKeys.hiveKey], isNotNull);
      expect(secrets.data[SecretKeys.hiveKeyPeer], isNotNull);
      expect(secrets.data[SecretKeys.hiveKeyPeer], isNot(secrets.data[SecretKeys.hiveKey]));
      await db.peerData.put('c:cases:1', 'dossier-secret-reconnaissable');
      await db.privateCache.put('k', 'v');
      await Hive.close();
      final raw = File('${dir.path}/pair_data.hive').readAsBytesSync();
      expect(utf8.decode(raw, allowMalformed: true).contains('dossier-secret-reconnaissable'), isFalse);
      final again = await LocalDb.open(secrets, path: dir.path);
      expect(again.peerData.get('c:cases:1'), 'dossier-secret-reconnaissable');
    });

    test('effacer le pair détruit sa clé : la boîte repart vide avec une clé neuve, le Membre est intact', () async {
      final secrets = MemorySecretStore();
      final db = await LocalDb.open(secrets, path: dir.path);
      final old = secrets.data[SecretKeys.hiveKeyPeer];
      await db.peerData.put('c:cases:1', 'x');
      await db.privateCache.put('dashboard', 'membre');
      await db.clearPeer();
      expect(db.peerData.keys, isEmpty);
      expect(secrets.data[SecretKeys.hiveKeyPeer], isNotNull);
      expect(secrets.data[SecretKeys.hiveKeyPeer], isNot(old), reason: 'clé détruite et remplacée');
      expect(db.privateCache.get('dashboard'), 'membre');
      await db.peerData.put('c:cases:2', 'y');
      await Hive.close();
      final again = await LocalDb.open(secrets, path: dir.path);
      expect(again.peerData.get('c:cases:2'), 'y');
      expect(again.peerData.get('c:cases:1'), isNull);
    });

    test('ancienne boîte pair_data chiffrée avec la clé commune : recréée vide, sans planter', () async {
      final secrets = MemorySecretStore();
      Hive.init(dir.path);
      final key = List<int>.generate(32, (i) => i);
      await secrets.write(SecretKeys.hiveKey, base64Url.encode(key));
      final old = await Hive.openBox<String>('pair_data', encryptionCipher: HiveAesCipher(key));
      await old.put('x', 'y');
      await Hive.close();
      final db = await LocalDb.open(secrets, path: dir.path);
      expect(db.peerData.keys, isEmpty);
    });
  });

  group('lanceur (session pair)', () {
    Map<String, FakeHandler> routes() => {
      'POST me/devices': (_) => jsonBody(201, {'data': {'device_uuid': 'x'}}),
      'POST heartbeat': (_) => jsonBody(200, {'data': {'device': {}, 'open_conflicts': 0}}),
      ...base(),
    };
    Map<String, dynamic> user(List<String> spaces) => userJson(
      role: 'pair',
      roles: ['membre', 'pair'],
      spaces: spaces,
      permissions: ['peer_space.access'],
    );

    Future<(ProviderContainer, TestEnv)> boot(List<String> spaces, {Map<String, FakeHandler>? extra}) async {
      SharedPreferences.setMockInitialValues({kDeviceUuidPref: 'x'});
      final prefs = await SharedPreferences.getInstance();
      final env = TestEnv(api: FakeApi({...routes(), ...?extra}), token: 'tok');
      await env.db.session.put('user', jsonEncode(user(spaces)));
      final c = ProviderContainer(overrides: env.overrides(prefs));
      addTearDown(c.dispose);
      c.read(sessionProvider);
      return (c, env);
    }

    test('pair : appareil enregistré, signal de vie, puis synchronisation à l’ouverture', () async {
      final (c, env) = await boot(['member', 'peer']);
      c.read(syncRunnerProvider);
      await Future<void>.delayed(const Duration(milliseconds: 100));
      final order = env.api.requests.map((r) => '${r.method} ${r.path}').toList();
      expect(order.indexOf('POST me/devices'), isNonNegative);
      expect(order.indexOf('POST heartbeat'), greaterThan(order.indexOf('POST me/devices')));
      expect(order, contains('GET sync/pull'));
      expect(order, contains('GET pair/validation-rules'));
      expect(c.read(syncEngineProvider).state.lastSync, isNotNull);
    });

    test('membre seul : ni signal de vie ni règles, seulement les collections communes', () async {
      final (c, env) = await boot(['member']);
      c.read(syncRunnerProvider);
      await Future<void>.delayed(const Duration(milliseconds: 100));
      expect(env.api.calls('POST heartbeat'), isEmpty);
      expect(env.api.calls('GET pair/validation-rules'), isEmpty);
      final pulls = env.api.calls('GET sync/pull');
      expect(pulls, isNotEmpty);
      for (final r in pulls) {
        expect(r.queryParameters['collections'], ['emergency_contacts', 'directory_contacts', 'thematiques']);
      }
    });

    test('un autre compte sur ce téléphone : rien du précédent ne reste dans le coffre du pair', () async {
      SharedPreferences.setMockInitialValues({kDeviceUuidPref: 'x'});
      final prefs = await SharedPreferences.getInstance();
      final env = TestEnv(api: FakeApi(routes()), token: 'tok');
      await env.db.session.put('user', jsonEncode(user(['member', 'peer'])));
      await env.db.peerData.put('owner', '999');
      await env.db.peerData.put('c:cases:1', '{"code":"VBG-0001"}');
      final c = ProviderContainer(overrides: env.overrides(prefs));
      addTearDown(c.dispose);
      c.read(sessionProvider);
      c.read(syncRunnerProvider);
      await Future<void>.delayed(const Duration(milliseconds: 100));
      expect(env.db.peerData.get('c:cases:1'), isNull);
      expect(env.db.peerData.get('owner'), '12');
    });

    test('effacement à distance annoncé par heartbeat : coffre vidé, session fermée', () async {
      final (c, env) = await boot(['member', 'peer'], extra: {
        'POST heartbeat': (_) => jsonBody(200, {'data': {'server_time': 'x', 'wipe': {'requested_at': 'x', 'reason': 'peer_role_removed'}}}),
        'POST me/devices/x/wiped': (_) => jsonBody(200, {'data': {'wipe': null}}),
      });
      await env.db.peerData.put('c:cases:1', '{}');
      c.read(syncRunnerProvider);
      await Future<void>.delayed(const Duration(milliseconds: 150));
      expect(c.read(sessionProvider).authenticated, isFalse);
      expect(env.db.peerData.keys, isEmpty);
      expect(env.api.calls('POST me/devices/x/wiped'), hasLength(1), reason: 'l’accusé part avant la purge');
    });
  });
}

bool isUuidV4(String s) => RegExp(r'^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$').hasMatch(s);
