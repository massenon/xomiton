import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:xomiton/core/network/api_client.dart';
import 'package:xomiton/core/network/api_exception.dart';
import 'package:xomiton/core/network/app_toast.dart';
import 'package:xomiton/core/network/idempotency.dart';
import 'package:xomiton/core/storage/secret_store.dart';
import 'package:xomiton/core/storage/token_store.dart';

import '../support/harness.dart';

Dio client(FakeApi api, {String? token, void Function()? onUnauthorized, ToastBus? toasts}) {
  return buildApiClient(
    tokens: TokenStore(MemorySecretStore(), initial: token),
    toasts: toasts ?? ToastBus(),
    onUnauthorized: () async => onUnauthorized?.call(),
    baseUrl: 'https://api.test/api/v1',
    adapter: api,
    replayWait: (_) async {},
  );
}

void main() {
  group('en-têtes', () {
    test('Authorization Bearer et Accept JSON', () async {
      final api = FakeApi({'GET me': (_) => jsonBody(200, {'data': {}})});
      await client(api, token: 'abc').get<dynamic>('me');
      final h = api.requests.single.headers;
      expect(h['Authorization'], 'Bearer abc');
      expect(h['Accept'], 'application/json');
      expect(h.containsKey('Idempotency-Key'), isFalse);
    });

    test('pas d’Authorization sans jeton', () async {
      final api = FakeApi({'GET zones': (_) => jsonBody(200, {'data': []})});
      await client(api).get<dynamic>('zones');
      expect(api.requests.single.headers.containsKey('Authorization'), isFalse);
    });

    test('Idempotency-Key générée sur signalements, sos et sync/push seulement', () async {
      final api = FakeApi({
        'POST signalements': (_) => jsonBody(201, {'data': {}}),
        'POST sos': (_) => jsonBody(201, {'data': {}}),
        'POST sync/push': (_) => jsonBody(200, {'data': {}}),
        'POST signalements/XM-1/x': (_) => jsonBody(200, {'data': {}}),
        'POST auth/login': (_) => jsonBody(200, {'data': {}}),
      });
      final dio = client(api);
      for (final p in ['signalements', 'sos', 'sync/push']) {
        await dio.post<dynamic>(p, data: {'a': 1});
      }
      await dio.post<dynamic>('signalements/XM-1/x', data: {});
      await dio.post<dynamic>('auth/login', data: {});
      final keyed = api.requests.map((r) => r.headers['Idempotency-Key']).toList();
      expect(keyed[0], matches(RegExp(r'^[0-9a-f-]{36}$')));
      expect(keyed[1], isNotNull);
      expect(keyed[2], isNotNull);
      expect(keyed[3], isNull);
      expect(keyed[4], isNull);
    });

    test('la clé de la boîte d’envoi fournie par l’appelant est conservée', () async {
      final api = FakeApi({'POST sos': (_) => jsonBody(201, {'data': {}})});
      await client(api).post<dynamic>('sos',
          data: {}, options: Options(headers: {'Idempotency-Key': 'entree-1234'}));
      expect(api.requests.single.headers['Idempotency-Key'], 'entree-1234');
    });
  });

  group('rejeu avec la même Idempotency-Key', () {
    test('POST sos coupé puis relancé : une seule alerte côté serveur', () async {
      final alerts = <String>{};
      var attempt = 0;
      final api = FakeApi({
        'POST sos': (o) {
          attempt++;
          final key = o.headers['Idempotency-Key'] as String;
          // 1re tentative : le serveur a traité la requête mais la réponse est perdue.
          alerts.add(key);
          if (attempt == 1) {
            throw DioException.connectionError(requestOptions: o, reason: 'coupure');
          }
          return jsonBody(201, {'data': {'id': 'A1', 'dup': alerts.length}},
              headers: {'Idempotent-Replayed': 'true'});
        },
      });
      final res = await client(api).post<dynamic>('sos', data: {'lat': 6.37, 'lng': 2.39});
      expect(res.statusCode, 201);
      expect(api.requests, hasLength(2));
      expect(api.requests[0].headers['Idempotency-Key'], api.requests[1].headers['Idempotency-Key']);
      expect(api.requests[0].data, api.requests[1].data);
      expect(alerts, hasLength(1));
    });

    test('5xx rejoué avec la même clé', () async {
      var n = 0;
      final api = FakeApi({
        'POST signalements': (o) =>
            ++n < 3 ? jsonBody(503, {'message': 'x', 'code': 'maintenance'}) : jsonBody(201, {'data': {}}),
      });
      await client(api).post<dynamic>('signalements', data: {'a': 1});
      expect(api.requests.map((r) => r.headers['Idempotency-Key']).toSet(), hasLength(1));
      expect(api.requests, hasLength(3));
    });

    test('202 processing (SOS) : la même clé est rejouée jusqu’à la réponse d’origine', () async {
      var n = 0;
      final api = FakeApi({
        'POST sos': (o) => ++n == 1
            ? jsonBody(202, {'data': {'status': 'processing', 'retry_after': 1}})
            : jsonBody(201, {'data': {'id': 'A1'}}),
      });
      final res = await client(api).post<dynamic>('sos', data: {});
      expect(res.statusCode, 201);
      expect(api.requests.map((r) => r.headers['Idempotency-Key']).toSet(), hasLength(1));
    });

    test('429 idempotency_in_progress : rejoué, sans toast', () async {
      var n = 0;
      final toasts = ToastBus();
      final seen = <AppToast>[];
      toasts.stream.listen(seen.add);
      final api = FakeApi({
        'POST signalements': (o) => ++n == 1
            ? jsonBody(429, {'message': 'x', 'code': 'idempotency_in_progress', 'retry_after': 1})
            : jsonBody(201, {'data': {}}),
      });
      await client(api, toasts: toasts).post<dynamic>('signalements', data: {});
      await Future<void>.delayed(Duration.zero);
      expect(api.requests, hasLength(2));
      expect(seen, isEmpty);
    });

    test('422 : jamais rejoué', () async {
      final api = FakeApi({
        'POST signalements': (o) => jsonBody(422, {'message': 'x', 'code': 'validation_failed', 'errors': {'body': ['Trop court']}}),
      });
      await expectLater(client(api).post<dynamic>('signalements', data: {}), throwsA(isA<DioException>()));
      expect(api.requests, hasLength(1));
    });

    test('une route non idempotente n’est pas rejouée', () async {
      final api = FakeApi({
        'POST auth/login': (o) => throw DioException.connectionError(requestOptions: o, reason: 'x'),
      });
      await expectLater(client(api).post<dynamic>('auth/login', data: {}), throwsA(isA<DioException>()));
      expect(api.requests, hasLength(1));
    });
  });

  group('erreurs', () {
    test('401 avec jeton : déconnexion + toast ; sans jeton : rien', () async {
      var closed = 0;
      final toasts = ToastBus();
      final seen = <AppToast>[];
      toasts.stream.listen(seen.add);
      final api = FakeApi({
        'GET me': (_) => jsonBody(401, {'message': 'Connecte-toi pour continuer.', 'code': 'unauthenticated'}),
      });
      await expectLater(
          client(api, token: 't', onUnauthorized: () => closed++, toasts: toasts).get<dynamic>('me'),
          throwsA(isA<DioException>()));
      await Future<void>.delayed(Duration.zero);
      expect(closed, 1);
      expect(seen.single, isA<SessionClosedToast>());

      closed = 0;
      seen.clear();
      await expectLater(
          client(api, onUnauthorized: () => closed++, toasts: toasts).get<dynamic>('me'),
          throwsA(isA<DioException>()));
      await Future<void>.delayed(Duration.zero);
      expect(closed, 0);
      expect(seen, isEmpty);
    });

    test('429 : toast avec le délai du serveur', () async {
      final toasts = ToastBus();
      final seen = <AppToast>[];
      toasts.stream.listen(seen.add);
      final api = FakeApi({
        'GET thematiques': (_) => jsonBody(429, {'message': 'x', 'code': 'too_many_requests', 'retry_after': 12}),
      });
      await expectLater(client(api, toasts: toasts).get<dynamic>('thematiques'), throwsA(isA<DioException>()));
      await Future<void>.delayed(Duration.zero);
      expect((seen.single as RateLimitedToast).seconds, 12);
    });

    test('ApiException normalise code, message et erreurs de champ', () async {
      final api = FakeApi({
        'POST auth/register': (_) => jsonBody(422, {
              'message': 'Le champ pseudonyme est déjà utilisé.',
              'code': 'validation_failed',
              'errors': {'pseudo': ['Déjà utilisé']},
            }),
      });
      try {
        await client(api).post<dynamic>('auth/register', data: {});
        fail('devait échouer');
      } on DioException catch (e) {
        final a = ApiException.from(e);
        expect(a.status, 422);
        expect(a.code, 'validation_failed');
        expect(a.firstError('pseudo'), 'Déjà utilisé');
        expect(a.isNetwork, isFalse);
      }
    });

    test('réseau absent : isNetwork', () async {
      final api = FakeApi({
        'GET zones': (o) => throw DioException.connectionError(requestOptions: o, reason: 'x'),
      });
      try {
        await client(api).get<dynamic>('zones');
        fail('devait échouer');
      } on DioException catch (e) {
        expect(ApiException.from(e).isNetwork, isTrue);
      }
    });
  });

  group('sync/push', () {
    test('duplicate est un succès, conflict et rejected non', () {
      expect(isSyncSuccess('created'), isTrue);
      expect(isSyncSuccess('updated'), isTrue);
      expect(isSyncSuccess('duplicate'), isTrue);
      expect(isSyncSuccess('conflict'), isFalse);
      expect(isSyncSuccess('rejected'), isFalse);
    });

    test('boîte d’envoi : 429 et 5xx restent, le reste sort', () {
      expect(keepInOutbox(429, 'too_many_requests'), isTrue);
      expect(keepInOutbox(503, 'retry_later'), isTrue);
      expect(keepInOutbox(null, null), isTrue);
      expect(keepInOutbox(201, null), isFalse);
      expect(keepInOutbox(409, 'conflict'), isFalse);
      expect(keepInOutbox(422, 'validation_failed'), isFalse);
    });
  });
}
