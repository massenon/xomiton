import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:xomiton/features/ccc/data/ccc_image_store.dart';

import '../../support/memory_image_disk.dart';
import 'ccc_support.dart';

void main() {
  late MemoryImageDisk disk;
  late FakeImageServer server;

  CccImageStore store({int? maxFile, int? maxTotal}) => CccImageStore(
        disk: disk,
        dio: Dio()..httpClientAdapter = server,
        maxFileBytes: maxFile ?? kCccImageMaxFileBytes,
        maxTotalBytes: maxTotal ?? kCccImageMaxTotalBytes,
      );
  final urls = [for (var i = 1; i <= 4; i++) 'https://xomiton.test/storage/ccc/$i.jpg'];

  setUp(() {
    disk = MemoryImageDisk();
    server = FakeImageServer();
  });

  test('téléchargement : une image à la fois, progression, fichiers nommés par empreinte', () async {
    final steps = <String>[];
    final r = await store().download(urls, onProgress: (d, t) => steps.add('$d/$t'));
    expect(r.saved, 4);
    expect(r.failed, 0);
    expect(steps, ['1/4', '2/4', '3/4', '4/4']);
    expect(server.urls, urls, reason: 'séquentiel, dans l’ordre');
    expect(disk.files.keys.toSet(), {for (final u in urls) CccImageStore.nameFor(u)});
    expect(disk.files.keys.any((k) => k.contains('xomiton')), isFalse, reason: 'jamais l’adresse en clair');
  });

  test('les images déjà présentes ne sont pas relues', () async {
    await store().download(urls.take(2));
    server.urls.clear();
    final r = await store().download(urls);
    expect(r.saved, 2);
    expect(server.urls, urls.skip(2).toList());
  });

  test('aucun jeton ni en-tête d’authentification n’est envoyé', () async {
    final seen = <Map<String, dynamic>>[];
    server = FakeImageServer(handler: (u) => imageBody([1, 2, 3]));
    final dio = Dio()..httpClientAdapter = server;
    dio.interceptors.add(InterceptorsWrapper(onRequest: (o, h) {
      seen.add(o.headers);
      h.next(o);
    }));
    await CccImageStore(disk: disk, dio: dio).download(urls.take(1));
    expect(seen.single.keys.map((k) => k.toLowerCase()), isNot(contains('authorization')));
  });

  test('taille maximale par fichier : image trop lourde ignorée et comptée en échec', () async {
    server = FakeImageServer(handler: (u) => imageBody(List.filled(u.endsWith('2.jpg') ? 50 : 10, 7)));
    final r = await store(maxFile: 20).download(urls);
    expect(r.saved, 3);
    expect(r.failed, 1);
    expect(disk.files.containsKey(CccImageStore.nameFor(urls[1])), isFalse);
  });

  test('taille maximale au total : le téléchargement s’arrête, le reste est signalé', () async {
    server = FakeImageServer(handler: (u) => imageBody(List.filled(10, 7)));
    final r = await store(maxTotal: 25).download(urls);
    expect(r.full, isTrue);
    expect(r.saved, 2);
    expect(r.failed, 2);
    expect(disk.files.length, 2);
  });

  test('réponse qui n’est pas une image, vide ou en erreur : échec sans rien écrire', () async {
    server = FakeImageServer(handler: (u) {
      if (u.endsWith('1.jpg')) return imageBody([1, 2], type: 'text/html');
      if (u.endsWith('2.jpg')) return imageBody([]);
      if (u.endsWith('3.jpg')) return imageBody([1, 2], status: 404);
      throw DioException.connectionError(requestOptions: RequestOptions(path: u), reason: 'x');
    });
    final r = await store().download(urls);
    expect(r.saved, 0);
    expect(r.failed, 4);
    expect(disk.files, isEmpty);
  });

  test('annulation : arrêt net, ce qui est déjà enregistré reste', () async {
    final cancel = CancelToken();
    final r = await store().download(urls, cancel: cancel, onProgress: (d, t) {
      if (d == 2) cancel.cancel('user');
    });
    expect(r.cancelled, isTrue);
    expect(r.saved, 2);
    expect(disk.files.length, 2);
  });

  test('purge : seules les images qui ne sont plus dans la réponse partent', () async {
    await store().download(urls);
    final removed = await store().prune({urls[0], urls[3]});
    expect(removed, 2);
    expect(disk.files.keys.toSet(), {CccImageStore.nameFor(urls[0]), CccImageStore.nameFor(urls[3])});
  });

  test('clear : tout part ; names et pathFor lisent le disque', () async {
    await store().download(urls);
    expect(await store().names(), hasLength(4));
    expect(store().pathFor(urls[0]), '/memory/${CccImageStore.nameFor(urls[0])}');
    await store().clear();
    expect(await store().names(), isEmpty);
  });

  test('sans dossier disponible : prepare() est faux', () async {
    disk.available = false;
    expect(await store().prepare(), isFalse);
    expect(store().pathFor(urls[0]), isNull);
  });

  group('DirectoryImageDisk (vrai dossier)', () {
    late Directory dir;
    setUp(() => dir = Directory.systemTemp.createTempSync('ccc_img_'));
    tearDown(() => dir.deleteSync(recursive: true));

    test('écriture, lecture depuis le disque, nettoyage des .part, suppression', () async {
      final d = DirectoryImageDisk(() async => Directory('${dir.path}/ccc_images'));
      final st = CccImageStore(disk: d, dio: Dio()..httpClientAdapter = FakeImageServer(bytes: [9, 8, 7, 6]));
      expect(await st.prepare(), isTrue);
      await File('${dir.path}/ccc_images/reste.part').writeAsBytes([1]);
      final r = await st.download(urls.take(2));
      expect(r.saved, 2);
      final names = await st.names();
      expect(names, hasLength(2), reason: 'le .part est supprimé');
      expect(File(st.pathFor(urls[0])!).readAsBytesSync(), [9, 8, 7, 6]);
      await st.prune({urls[0]});
      expect(File(st.pathFor(urls[1])!).existsSync(), isFalse);
      await st.clear();
      expect(await st.names(), isEmpty);
    });

    test('dossier introuvable : prepare() est faux, rien ne casse', () async {
      final d = DirectoryImageDisk(() async => null);
      expect(await d.prepare(), isFalse);
      expect(await d.list(), isEmpty);
    });
  });
}
