import 'package:flutter_test/flutter_test.dart';
import 'package:xomiton/core/network/api_client.dart';
import 'package:xomiton/core/network/app_toast.dart';
import 'package:xomiton/core/outbox/outbox.dart';
import 'package:xomiton/core/platform/platform_services.dart';
import 'package:xomiton/core/storage/local_db.dart';
import 'package:xomiton/core/storage/secret_store.dart';
import 'package:xomiton/core/storage/token_store.dart';

import '../support/harness.dart';

void main() {
  test('production : HTTP refusé, HTTPS accepté', () {
    expect(
      () => assertSecureBaseUrl('http://api.test/api/v1', release: true),
      throwsStateError,
    );
    expect(
      () => assertSecureBaseUrl('api.test', release: true),
      throwsStateError,
    );
    expect(
      () => assertSecureBaseUrl('https://api.test/api/v1', release: true),
      returnsNormally,
    );
    expect(
      () => assertSecureBaseUrl('http://10.0.2.2:8000/api/v1', release: false),
      returnsNormally,
    );
  });

  test('liens externes : seulement https et tel', () {
    expect(isAllowedExternalUri(Uri.parse('https://xomiton.test/a')), isTrue);
    expect(isAllowedExternalUri(telUri('+229 01 02 03 04')), isTrue);
    for (final bad in [
      'http://x.test',
      'javascript:alert(1)',
      'intent://x#Intent;end',
      'file:///etc/passwd',
      'sms:+22901',
      'tel:abc',
    ]) {
      expect(isAllowedExternalUri(Uri.parse(bad)), isFalse, reason: bad);
    }
  });

  test(
    'SOS avec jeton expiré (401) : renvoyé sans compte, même clé, jamais perdu',
    () async {
      final db = LocalDb.memory();
      final api = FakeApi({
        'POST sos': (o) => o.headers.containsKey('Authorization')
            ? jsonBody(401, {
                'message': 'Unauthenticated.',
                'code': 'unauthenticated',
              })
            : jsonBody(201, {
                'data': {'id': 'S1', 'status': 'nouveau'},
              }),
      });
      final dio = buildApiClient(
        tokens: TokenStore(MemorySecretStore(), initial: 'tok'),
        toasts: ToastBus(),
        onUnauthorized: () async {},
        baseUrl: 'https://api.test/api/v1',
        adapter: api,
        replayWait: (_) async {},
      );
      final svc = OutboxService(dio: dio, db: db);
      final res = await svc.sendNow(
        OutboxEntry(
          uuid: '11111111-1111-4111-8111-111111111111',
          type: OutboxType.sos,
          payload: {'share_location': false},
          createdAt: DateTime(2026, 10, 26),
        ),
      );
      expect(res, isNotNull);
      final calls = api.calls('POST sos');
      expect(calls, hasLength(2));
      expect(calls.last.headers.containsKey('Authorization'), isFalse);
      expect(
        calls.map((c) => c.headers['Idempotency-Key']).toSet(),
        hasLength(1),
      );
      expect(svc.list(), isEmpty);
    },
  );
}
