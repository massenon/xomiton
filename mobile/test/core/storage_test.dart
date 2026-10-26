import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hive/hive.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:xomiton/core/network/api_client.dart';
import 'package:xomiton/core/network/app_toast.dart';
import 'package:xomiton/core/providers/core_providers.dart';
import 'package:xomiton/core/storage/api_cache.dart';
import 'package:xomiton/core/storage/local_db.dart';
import 'package:xomiton/core/storage/secret_store.dart';
import 'package:xomiton/core/storage/token_store.dart';
import 'package:xomiton/features/auth/application/session_controller.dart';

import '../support/harness.dart';

ApiCache cacheFor(FakeApi api, LocalDb db) {
  final dio = buildApiClient(
    tokens: TokenStore(MemorySecretStore()),
    toasts: ToastBus(),
    onUnauthorized: () async {},
    baseUrl: 'https://api.test/api/v1',
    adapter: api,
  );
  return ApiCache(dio: dio, publicBox: db.publicCache, privateBox: db.privateCache);
}

void main() {
  group('ApiCache (ETag + copie locale)', () {
    test('200 gardé avec son ETag, 304 → copie locale, If-None-Match renvoyé', () async {
      final db = LocalDb.memory();
      var n = 0;
      final api = FakeApi({
        'GET thematiques': (o) {
          n++;
          if (o.headers['If-None-Match'] == '"v1"') return notModified();
          return jsonBody(200, {'data': [{'id': 1, 'slug': 'a'}]}, headers: {'ETag': '"v1"'});
        },
      });
      final cache = cacheFor(api, db);
      final first = await cache.get('thematiques', query: {'categorie': 'vbg'});
      expect(first.data, isNotEmpty);
      expect(first.fromCache, isFalse);
      final second = await cache.get('thematiques', query: {'categorie': 'vbg'});
      expect(second.fromCache, isTrue);
      expect(second.data, first.data);
      expect(n, 2);
      expect(api.requests.last.headers['If-None-Match'], '"v1"');
      expect(api.requests.last.queryParameters['categorie'], 'vbg');
    });

    test('hors ligne : la copie locale est servie, marquée périmée', () async {
      final db = LocalDb.memory();
      var offline = false;
      final api = FakeApi({
        'GET zones': (o) {
          if (offline) throw DioException.connectionError(requestOptions: o, reason: 'x');
          return jsonBody(200, {'data': [{'id': 1}]}, headers: {'ETag': '"z"'});
        },
      });
      final cache = cacheFor(api, db);
      await cache.get('zones');
      offline = true;
      final r = await cache.get('zones');
      expect(r.stale, isTrue);
      expect(r.data, [{'id': 1}]);
    });

    test('hors ligne sans copie : l’erreur remonte', () async {
      final api = FakeApi({
        'GET zones': (o) => throw DioException.connectionError(requestOptions: o, reason: 'x'),
      });
      await expectLater(cacheFor(api, LocalDb.memory()).get('zones'), throwsA(isA<DioException>()));
    });

    test('no-store (section pair du tableau de bord) : jamais gardé', () async {
      final db = LocalDb.memory();
      final api = FakeApi({
        'GET dashboard': (_) => jsonBody(200, {'data': {'peer': {}}},
            headers: {'ETag': '"p"', 'Cache-Control': 'no-store'}),
      });
      await cacheFor(api, db).get('dashboard', private: true);
      expect((db.privateCache as MemoryStringBox).data, isEmpty);
    });

    test('réponse sans ETag : pas de copie', () async {
      final db = LocalDb.memory();
      final api = FakeApi({'GET zones': (_) => jsonBody(200, {'data': []})});
      await cacheFor(api, db).get('zones');
      expect((db.publicCache as MemoryStringBox).data, isEmpty);
    });
  });

  group('Hive chiffré', () {
    late Directory dir;
    setUp(() => dir = Directory.systemTemp.createTempSync('xomiton_hive'));
    tearDown(() async {
      await Hive.close();
      dir.deleteSync(recursive: true);
    });

    test('clé AES générée une fois, gardée dans le stockage sécurisé, données illisibles en clair', () async {
      final secrets = MemorySecretStore();
      final db = await LocalDb.open(secrets, path: dir.path);
      expect(secrets.data[SecretKeys.hiveKey], isNotNull);
      expect(base64Url.decode(secrets.data[SecretKeys.hiveKey]!), hasLength(32));
      await db.privateCache.put('k', 'texte-secret-reconnaissable');
      await Hive.close();

      final raw = File('${dir.path}/cache_private.hive').readAsBytesSync();
      expect(utf8.decode(raw, allowMalformed: true).contains('texte-secret-reconnaissable'), isFalse);

      final again = await LocalDb.open(secrets, path: dir.path);
      expect(again.privateCache.get('k'), 'texte-secret-reconnaissable');
    });

    test('clearPrivate efface le privé et la session, garde le public', () async {
      final db = await LocalDb.open(MemorySecretStore(), path: dir.path);
      await db.publicCache.put('zones', '1');
      await db.privateCache.put('dashboard', '2');
      await db.session.put('user', '3');
      await db.clearPrivate();
      expect(db.publicCache.get('zones'), '1');
      expect(db.privateCache.get('dashboard'), isNull);
      expect(db.session.get('user'), isNull);
    });
  });

  group('session', () {
    test('signIn stocke jeton et utilisateur ; 401 sur GET me ferme la session et vide le privé', () async {
      SharedPreferences.setMockInitialValues({});
      final prefs = await SharedPreferences.getInstance();
      final env = TestEnv(api: FakeApi({
        'GET me': (_) => jsonBody(401, {'message': 'Connecte-toi pour continuer.', 'code': 'unauthenticated'}),
      }));
      final c = ProviderContainer(overrides: env.overrides(prefs));
      addTearDown(c.dispose);

      await c.read(sessionProvider.notifier).signIn('tok', userJson());
      expect(c.read(sessionProvider).authenticated, isTrue);
      expect(c.read(tokenStoreProvider).token, 'tok');
      expect(env.secrets.data[SecretKeys.token], 'tok');
      expect(env.db.session.get('user'), isNotNull);
      await env.db.privateCache.put('dashboard', 'x');

      await c.read(sessionProvider.notifier).refresh();
      await Future<void>.delayed(const Duration(milliseconds: 50));
      expect(c.read(sessionProvider).authenticated, isFalse);
      expect(env.secrets.data.containsKey(SecretKeys.token), isFalse);
      expect(env.db.privateCache.get('dashboard'), isNull);
    });

    test('démarrage avec jeton : session ouverte hors ligne avec l’utilisateur en copie locale', () async {
      SharedPreferences.setMockInitialValues({});
      final prefs = await SharedPreferences.getInstance();
      final env = TestEnv(
        api: FakeApi({'GET me': (o) => throw DioException.connectionError(requestOptions: o, reason: 'x')}),
        token: 'tok',
      );
      await env.db.session.put('user', jsonEncode(userJson()));
      final c = ProviderContainer(overrides: env.overrides(prefs));
      addTearDown(c.dispose);
      final s = c.read(sessionProvider);
      expect(s.authenticated, isTrue);
      expect(s.user?.pseudo, 'Awa_S');
      await Future<void>.delayed(const Duration(milliseconds: 20));
      expect(c.read(sessionProvider).authenticated, isTrue);
    });

    test('signOut révoque côté serveur puis efface tout', () async {
      SharedPreferences.setMockInitialValues({});
      final prefs = await SharedPreferences.getInstance();
      final env = TestEnv(
        api: FakeApi({'POST auth/logout': (_) => jsonBody(200, {'data': {'logged_out': true}})}),
        token: 'tok',
      );
      final c = ProviderContainer(overrides: env.overrides(prefs));
      addTearDown(c.dispose);
      await c.read(sessionProvider.notifier).signOut();
      expect(env.api.calls('POST auth/logout'), hasLength(1));
      expect(c.read(sessionProvider).authenticated, isFalse);
    });
  });
}
