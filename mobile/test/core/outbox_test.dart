import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:xomiton/core/network/api_client.dart';
import 'package:xomiton/core/network/app_toast.dart';
import 'package:xomiton/core/outbox/outbox.dart';
import 'package:xomiton/core/outbox/sos_retry.dart';
import 'package:xomiton/core/storage/local_db.dart';
import 'package:xomiton/core/storage/secret_store.dart';
import 'package:xomiton/core/storage/token_store.dart';

import '../support/fakes.dart';
import '../support/harness.dart';

OutboxService serviceFor(FakeApi api, LocalDb db) {
  final dio = buildApiClient(
    tokens: TokenStore(MemorySecretStore()),
    toasts: ToastBus(),
    onUnauthorized: () async {},
    baseUrl: 'https://api.test/api/v1',
    adapter: api,
    replayWait: (_) async {},
  );
  return OutboxService(dio: dio, db: db);
}

OutboxEntry sos(String uuid, {DateTime? at}) => OutboxEntry(
  uuid: uuid,
  type: OutboxType.sos,
  payload: {'share_location': true, 'lat': 6.37, 'lng': 2.39},
  createdAt: at ?? DateTime(2026, 10, 26, 10),
);

OutboxEntry report(String uuid, {DateTime? at}) => OutboxEntry(
  uuid: uuid,
  type: OutboxType.report,
  payload: {
    'type': 'need',
    'description': 'x' * 30,
    'needs': ['securite'],
    'zone_id': 1,
    'anonymous': true,
  },
  createdAt: at ?? DateTime(2026, 10, 26, 9),
);

Map<String, dynamic> alertJson(String id, {String? code}) => {
  'data': {
    'id': id,
    'tracking_code': code ?? 'SIG-ABC',
    'status': 'nouveau',
    'sos_token': 'secret-token',
  },
};

void main() {
  test(
    'clé dérivée d’un SOS refusé : identique à shared/sos-retry.js du web',
    () {
      expect(
        derivedSosKey('123e4567-e89b-42d3-a456-426614174000'),
        'acb2761e-0bd8-4ce2-bb9c-2965994adc1f',
      );
      expect(
        derivedSosKey('AAAAAAAA-0000-4000-8000-000000000001'),
        '6100bc0a-4d28-4e0e-8704-86ad75b242e7',
      );
      expect(
        stripSosFields(
          {
            'lat': 1,
            'lng': 2,
            'accuracy': 3,
            'zone_id': 4,
            'commune_id': 5,
            'phone': 'x',
          },
          ['lat'],
        ),
        {'zone_id': 4, 'commune_id': 5, 'phone': 'x'},
      );
      expect(
        stripSosFields(
          {'zone_id': 4, 'commune_id': 5, 'phone': 'x'},
          ['zone_id'],
        ),
        {'phone': 'x'},
      );
    },
  );

  test('le SOS part en premier, avant un signalement plus ancien', () async {
    final db = LocalDb.memory();
    final order = <String>[];
    final api = FakeApi({
      'POST sos': (o) {
        order.add('sos');
        return jsonBody(201, alertJson('S1'));
      },
      'POST signalements': (o) {
        order.add('report');
        return jsonBody(201, alertJson('R1'));
      },
    });
    final svc = serviceFor(api, db);
    await svc.add(report('r-1'));
    await svc.add(sos('s-1'));
    expect(svc.list().map((e) => e.type), [OutboxType.sos, OutboxType.report]);
    await svc.flush();
    expect(order, ['sos', 'report']);
    expect(svc.list(), isEmpty);
  });

  test('rejeu sans doublon : coupure puis succès avec la même Idempotency-Key, une seule entrée', () async {
    final db = LocalDb.memory();
    var online = false;
    final api = FakeApi({
      'POST sos': (o) {
        if (!online) throw offlineError(o);
        return jsonBody(201, alertJson('S1'));
      },
    });
    final svc = serviceFor(api, db);
    final results = <OutboxResult>[];
    svc.results.listen(results.add);
    await svc.add(sos('11111111-1111-4111-8111-111111111111'));
    await svc.flush(); // mode avion
    expect(svc.pending(), hasLength(1));
    expect(svc.pending().first.attempts, 1);
    online = true;
    await svc.flush();
    await svc.flush(); // un 2e passage ne renvoie rien
    final calls = api.calls('POST sos');
    expect(calls, hasLength(2));
    expect(calls.map((c) => c.headers['Idempotency-Key']).toSet(), {
      '11111111-1111-4111-8111-111111111111',
    });
    expect(
      calls.map((c) => jsonEncode(c.data)).toSet(),
      hasLength(1),
      reason: 'corps identique à chaque rejeu',
    );
    expect(svc.list(), isEmpty);
    expect(results, hasLength(1));
  });

  test(
    '202 processing : l’entrée est gardée, jamais comptée comme envoyée',
    () async {
      final db = LocalDb.memory();
      var n = 0;
      final api = FakeApi({
        'POST sos': (o) {
          n++;
          return n == 1
              ? jsonBody(202, {
                  'data': {'status': 'processing', 'retry_after': 1},
                })
              : jsonBody(201, alertJson('S1'));
        },
      });
      final svc = serviceFor(api, db);
      await svc.add(sos('s-2'));
      await svc.flush();
      expect(svc.pending(), hasLength(1));
      await svc.flush();
      expect(svc.list(), isEmpty);
    },
  );

  test('SOS refusé en 422 : un seul renvoi sans les champs refusés, sous la clé dérivée', () async {
    final db = LocalDb.memory();
    var n = 0;
    final api = FakeApi({
      'POST sos': (o) {
        n++;
        if (n == 1) {
          return jsonBody(422, {
            'message': 'x',
            'code': 'validation_failed',
            'errors': {
              'lat': ['invalide'],
            },
          });
        }
        return jsonBody(201, alertJson('S1'));
      },
    });
    final svc = serviceFor(api, db);
    await svc.add(sos('123e4567-e89b-42d3-a456-426614174000'));
    await svc.flush();
    final calls = api.calls('POST sos');
    expect(calls, hasLength(2));
    expect(
      calls[1].headers['Idempotency-Key'],
      'acb2761e-0bd8-4ce2-bb9c-2965994adc1f',
    );
    expect((calls[1].data as Map).containsKey('lat'), isFalse);
    expect(svc.list(), isEmpty);
  });

  test(
    'refus 4xx d’un signalement : gardé en « échec », jamais renvoyé en boucle',
    () async {
      final db = LocalDb.memory();
      final api = FakeApi({
        'POST signalements': (o) => jsonBody(422, {
          'message': 'Invalide',
          'code': 'validation_failed',
          'errors': {
            'description': ['x'],
          },
        }),
      });
      final svc = serviceFor(api, db);
      await svc.add(report('r-2'));
      await svc.flush();
      await svc.flush();
      expect(api.calls('POST signalements'), hasLength(1));
      expect(svc.list().single.status, OutboxStatus.failed);
      expect(svc.errorOf('r-2')?.status, 422);
      await svc.retry('r-2');
      expect(svc.pending(), hasLength(1));
    },
  );

  test('confidentialité : la boîte d’envoi vit dans le coffre chiffré, le jeton SOS n’est jamais écrit', () async {
    final db = LocalDb.memory();
    final api = FakeApi({'POST sos': (o) => jsonBody(201, alertJson('S1'))});
    final svc = serviceFor(api, db);
    OutboxResult? res;
    svc.results.listen((r) => res = r);
    await svc.add(sos('s-3'));
    await svc.flush();
    await Future<void>.delayed(Duration.zero);
    expect(res?.data['sos_token'], 'secret-token'); // en mémoire seulement
    final all = [
      db.outbox,
      db.drafts,
      db.privateCache,
      db.publicCache,
      db.session,
    ].expand((b) => b.keys.map(b.get)).join();
    expect(all.contains('secret-token'), isFalse);
  });

  test('déconnexion : messages et publications partent, SOS et signalement anonyme restent', () async {
    final db = LocalDb.memory();
    final svc = serviceFor(FakeApi(), db);
    await svc.add(sos('s-4'));
    await svc.add(report('r-4'));
    await svc.add(
      OutboxEntry(
        uuid: 'm-1',
        type: OutboxType.message,
        payload: {'body': 'salut'},
        createdAt: DateTime.now(),
        owner: 12,
        target: 'C1',
      ),
    );
    await db.clearPrivate();
    expect(svc.list().map((e) => e.uuid).toSet(), {'s-4', 'r-4'});
  });
}
