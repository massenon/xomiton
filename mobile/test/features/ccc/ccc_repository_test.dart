import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:xomiton/core/storage/local_db.dart';
import 'package:xomiton/features/ccc/data/ccc_repository.dart';

import '../../support/fakes.dart';
import '../../support/harness.dart';
import 'ccc_support.dart';

/// Dépôt CCC : copie locale de la dernière réponse réussie, par compte, sans jamais
/// servir un contenu dont le droit a changé.
void main() {
  final t0 = DateTime.utc(2026, 11, 12, 9);
  late MemoryStringBox box;
  late DateTime now;
  late FakeHandler handler;
  late FakeApi api;

  CccRepository repo({int? account = 12}) {
    final dio = Dio(BaseOptions(baseUrl: 'https://api.test/v1/'))..httpClientAdapter = api;
    return CccRepository(dio: dio, box: box, account: account, now: () => now);
  }

  FakeHandler ok(Map<String, dynamic> body) =>
      (_) => jsonBody(200, body, headers: {'Cache-Control': 'no-store, private'});
  FakeHandler status(int code) => (_) => jsonBody(code, {'message': 'x', 'code': 'x'});

  setUp(() {
    box = MemoryStringBox();
    now = t0;
    handler = ok(cccBody());
    api = FakeApi({'GET ccc': (o) => handler(o)});
  });

  Map<String, dynamic>? stored(int account) {
    final raw = box.get(cccCopyKey(account));
    return raw == null ? null : jsonDecode(raw) as Map<String, dynamic>;
  }

  test('succès réseau : catalogue servi, copie écrite avec la date', () async {
    final r = await repo().load();
    expect(r.fromCopy, isFalse);
    expect(r.syncedAt, t0);
    expect(r.catalog.manual, hasLength(2));
    expect(stored(12)!['synced_at'], t0.toIso8601String());
  });

  test('un succès réseau REMPLACE la copie : un outil masqué disparaît', () async {
    await repo().load();
    handler = ok(cccBody(manuel: [], boites: [], bd: []));
    now = t0.add(const Duration(days: 2));
    await repo().load();
    handler = (o) => throw offlineError(o);
    final r = await repo().load();
    expect(r.fromCopy, isTrue);
    expect(r.catalog.manual, isEmpty);
    expect(r.catalog.imageBoxes, isEmpty);
    expect(r.catalog.comics, isEmpty);
    expect(r.syncedAt, t0.add(const Duration(days: 2)));
  });

  test('échec réseau avec copie : copie servie avec sa date', () async {
    await repo().load();
    now = t0.add(const Duration(days: 3));
    handler = (o) => throw offlineError(o);
    final r = await repo().load();
    expect(r.fromCopy, isTrue);
    expect(r.syncedAt, t0);
    expect(r.catalog.comics.single.key, 'bd1');
  });

  test('délai dépassé : copie servie', () async {
    await repo().load();
    handler = (o) => throw DioException.receiveTimeout(timeout: const Duration(seconds: 30), requestOptions: o);
    expect((await repo().load()).fromCopy, isTrue);
  });

  test('5xx (503 compris) avec copie : copie servie', () async {
    await repo().load();
    for (final code in [500, 502, 503]) {
      handler = status(code);
      expect((await repo().load()).fromCopy, isTrue, reason: '$code');
    }
  });

  test('échec réseau sans copie : l’erreur est relancée (« Réessayer »)', () async {
    handler = (o) => throw offlineError(o);
    await expectLater(repo().load(), throwsA(isA<DioException>()));
  });

  test('403 : pas de copie servie et copie purgée', () async {
    await repo().load();
    handler = status(403);
    await expectLater(repo().load(), throwsA(isA<DioException>()));
    expect(stored(12), isNull);
    // Le droit retiré ne revient pas par la porte du hors ligne.
    handler = (o) => throw offlineError(o);
    await expectLater(repo().load(), throwsA(isA<DioException>()));
  });

  test('401 et 404 neutre : copie jamais servie', () async {
    for (final code in [401, 404]) {
      await repo().load();
      handler = status(code);
      await expectLater(repo().load(), throwsA(isA<DioException>()), reason: '$code');
      expect(stored(12), isNull, reason: '$code');
      handler = ok(cccBody());
    }
  });

  test('autre 4xx (422, 429) : erreur relancée, copie conservée mais non servie', () async {
    await repo().load();
    handler = status(429);
    await expectLater(repo().load(), throwsA(isA<DioException>()));
    expect(stored(12), isNotNull);
  });

  test('copie de 30 jours pile servie, au-delà non servie et supprimée', () async {
    await repo().load();
    handler = (o) => throw offlineError(o);
    now = t0.add(kCccOfflineRetention);
    expect((await repo().load()).fromCopy, isTrue);
    now = t0.add(kCccOfflineRetention + const Duration(minutes: 1));
    await expectLater(repo().load(), throwsA(isA<CccCopyExpiredException>()));
    expect(stored(12), isNull);
    // Suivant : plus de copie, erreur ordinaire.
    await expectLater(repo().load(), throwsA(isA<DioException>()));
  });

  test('la durée de rétention est de 30 jours', () => expect(kCccOfflineRetention, const Duration(days: 30)));

  test('horloge reculée : copie considérée comme périmée', () async {
    await repo().load();
    handler = (o) => throw offlineError(o);
    now = t0.subtract(const Duration(days: 2));
    await expectLater(repo().load(), throwsA(isA<CccCopyExpiredException>()));
  });

  test('copie illisible : comme s’il n’y en avait pas', () async {
    await box.put(cccCopyKey(12), '{pas du json');
    handler = (o) => throw offlineError(o);
    await expectLater(repo().load(), throwsA(isA<DioException>()));
    expect(box.get(cccCopyKey(12)), isNull);
  });

  test('copies séparées par compte : l’un ne lit jamais la copie de l’autre', () async {
    await repo(account: 12).load();
    handler = (o) => throw offlineError(o);
    await expectLater(repo(account: 13).load(), throwsA(isA<DioException>()));
    expect((await repo(account: 12).load()).fromCopy, isTrue);
  });

  test('une synchro réussie d’un autre compte retire la copie du précédent', () async {
    await repo(account: 12).load();
    await repo(account: 13).load();
    expect(stored(12), isNull);
    expect(stored(13), isNotNull);
  });

  test('sans identifiant de compte : rien n’est gardé', () async {
    await repo(account: null).load();
    expect(box.keys, isEmpty);
    handler = (o) => throw offlineError(o);
    await expectLater(repo(account: null).load(), throwsA(isA<DioException>()));
  });

  test('purge à la déconnexion (clearPrivate vide la boîte privée)', () async {
    final db = LocalDb.memory();
    final r = CccRepository(
      dio: Dio(BaseOptions(baseUrl: 'https://api.test/v1/'))..httpClientAdapter = api,
      box: db.privateCache,
      account: 12,
      now: () => now,
    );
    await r.load();
    expect(db.privateCache.get(cccCopyKey(12)), isNotNull);
    await db.clearPrivate();
    expect(db.privateCache.get(cccCopyKey(12)), isNull);
  });

  test('adresses d’images : couvertures et planches, sans doublon', () {
    final urls = bodyImages(cccBody());
    expect(urls, hasLength(9)); // bi1 : couverture + 2 planches, bi2 : 1 + 1, bd1 : 1 + 3
  });
}
