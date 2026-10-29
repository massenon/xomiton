import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:xomiton/core/network/idempotency.dart';
import 'package:xomiton/features/profile/data/role_requests_api.dart';

import '../support/harness.dart';

Map<String, dynamic> item(String status, {String? reason, String? decision}) => {
      'id': '01HZ',
      'role': 'pair',
      'status': status,
      'reason': reason,
      'decision_reason': decision,
      'created_at': '2026-10-29T10:00:00+01:00',
      'decided_at': decision == null ? null : '2026-10-30T10:00:00+01:00',
    };

void main() {
  late FakeApi api;
  late RoleRequestsApi client;
  setUp(() {
    api = FakeApi();
    client = RoleRequestsApi(Dio(BaseOptions(baseUrl: 'http://x/api/v1/'))..httpClientAdapter = api);
  });

  test('GET me/role-requests : demandes, can_request, demande en cours et refus', () async {
    api.routes['GET me/role-requests'] = (_) => jsonBody(200, {
          'data': [item('pending', reason: 'Je mène des ateliers'), item('rejected', decision: 'Pas cette fois.')],
          'meta': {'can_request': false},
        });
    final v = await client.list();
    expect(v.items, hasLength(2));
    expect(v.canRequest, isFalse);
    expect(v.pending?.reason, 'Je mène des ateliers');
    expect(v.pending?.isPending, isTrue);
    expect(v.lastRejected, isNull, reason: 'une demande en cours masque l’ancien refus');
  });

  test('refus : motif de la décision lu, dernière demande refusée exposée', () async {
    api.routes['GET me/role-requests'] = (_) => jsonBody(200, {
          'data': [item('rejected', decision: 'Reviens après la formation.')],
          'meta': {'can_request': true},
        });
    final v = await client.list();
    expect(v.pending, isNull);
    expect(v.lastRejected?.decisionReason, 'Reviens après la formation.');
    expect(v.lastRejected?.status, RoleRequestStatus.rejected);
    expect(v.lastRejected?.decidedAt, isNotNull);
    expect(v.canRequest, isTrue);
  });

  test('statut inconnu : jamais d’exception', () async {
    api.routes['GET me/role-requests'] = (_) => jsonBody(200, {'data': [item('archived')], 'meta': {}});
    final v = await client.list();
    expect(v.items.single.status, RoleRequestStatus.unknown);
    expect(v.canRequest, isFalse);
  });

  test('POST : motif seul (jamais de rôle), Idempotency-Key fournie, motif coupé à 200', () async {
    api.routes['POST me/role-requests'] = (_) => jsonBody(201, {'data': item('pending', reason: 'x')});
    final r = await client.request(reason: '  ${'a' * 300}  ', idempotencyKey: 'key-1');
    final call = api.calls('POST me/role-requests').single;
    expect((call.data as Map).keys, ['reason']);
    expect(((call.data as Map)['reason'] as String).length, RoleRequestsApi.reasonMax);
    expect(call.headers['Idempotency-Key'], 'key-1');
    expect(r.isPending, isTrue);
  });

  test('POST sans motif : corps vide, clé générée', () async {
    api.routes['POST me/role-requests'] = (_) => jsonBody(201, {'data': item('pending')});
    await client.request(reason: '   ');
    final call = api.calls('POST me/role-requests').single;
    expect(call.data, <String, dynamic>{});
    expect(call.headers['Idempotency-Key'], isNotEmpty);
  });

  test('la route exige une clé d’idempotence (rejeu sûr après coupure)', () {
    expect(needsIdempotencyKey('POST', 'me/role-requests'), isTrue);
    expect(needsIdempotencyKey('GET', 'me/role-requests'), isFalse);
  });
}
