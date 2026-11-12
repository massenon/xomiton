import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:xomiton/core/platform/platform_services.dart';
import 'package:xomiton/features/ccc/application/ccc_offline.dart';
import 'package:xomiton/features/ccc/data/ccc_image_store.dart';
import 'package:xomiton/features/ccc/data/ccc_models.dart';
import 'package:xomiton/features/ccc/data/ccc_repository.dart';

import '../../support/fakes.dart';
import '../../support/harness.dart';

final S = loadStrings();

/// Réponse réaliste de `GET /api/v1/ccc` (champs de `CccToolResource`).
Map<String, dynamic> cccBody({
  List<Map<String, dynamic>>? manuel,
  List<Map<String, dynamic>>? boites,
  List<Map<String, dynamic>>? bd,
  int laws = 12,
  String? lawsUrl = 'https://xomiton.test/lois',
}) => {
  'data': {
    'manuel': manuel ?? [
      {
        'key': 'm1',
        'family': 'manuel',
        'title': 'Module 1 : Poser un cadre sûr',
        'description': 'Présentation du module.',
        'cover': null,
        'duration_minutes': 45,
        'chapters': ['Confidentialité', 'Écoute active', 'Limites du rôle'],
        'chapters_count': 3,
      },
      {
        'key': 'm2',
        'family': 'manuel',
        'title': 'Module 2 : Orienter',
        'description': null,
        'cover': null,
        'duration_minutes': null,
        'chapters': ['Annuaire', 'Urgences'],
        'chapters_count': 2,
      },
    ],
    'boites_images': boites ?? [
      {
        'key': 'bi1',
        'family': 'boites_images',
        'title': 'Le consentement',
        'description': 'Laisse chaque personne parler avant de réagir.',
        'cover': 'https://xomiton.test/storage/ccc/bi1-cover.jpg',
        'pages': [
          {'position': 1, 'image': 'https://xomiton.test/storage/ccc/bi1-1.jpg', 'caption': 'Deux amies discutent', 'alt': 'Deux amies assises sur un banc'},
          {'position': 2, 'image': 'https://xomiton.test/storage/ccc/bi1-2.jpg', 'caption': null, 'alt': 'Le consentement – 2'},
        ],
        'pages_count': 2,
      },
      {
        'key': 'bi2',
        'family': 'boites_images',
        'title': 'La pression des pairs',
        'description': null,
        'cover': 'https://xomiton.test/storage/ccc/bi2-cover.jpg',
        'pages': [
          {'position': 1, 'image': 'https://xomiton.test/storage/ccc/bi2-1.jpg', 'caption': 'Une soirée', 'alt': 'Un groupe dans une cour'},
        ],
        'pages_count': 1,
      },
    ],
    'bd': bd ?? [
      {
        'key': 'bd1',
        'family': 'bd',
        'title': 'Awa et le centre de santé',
        'description': 'Une histoire courte.',
        'cover': 'https://xomiton.test/storage/ccc/bd1-cover.jpg',
        'public_url': 'https://xomiton.test/bd/bd1',
        'pages': [
          {'position': 1, 'image': 'https://xomiton.test/storage/ccc/bd1-1.jpg', 'caption': 'Awa hésite devant la porte', 'alt': 'Awa devant un centre de santé'},
          {'position': 2, 'image': 'https://xomiton.test/storage/ccc/bd1-2.jpg', 'caption': null, 'alt': 'Awa parle à une infirmière'},
          {'position': 3, 'image': 'https://xomiton.test/storage/ccc/bd1-3.jpg', 'caption': 'Elle repart soulagée', 'alt': 'Awa sourit en sortant'},
        ],
        'pages_count': 3,
      },
    ],
  },
  'meta': {'laws': {'count': laws, 'url': ?lawsUrl}},
};

Map<String, FakeHandler> cccRoutes({FakeHandler? ccc, Map<String, dynamic>? user}) => {
  'GET me': (_) => jsonBody(200, {'data': user ?? memberUser()}),
  'GET dashboard': (_) => jsonBody(200, {
    'data': {
      'tiles': [],
      'counters': {'notifications': 0, 'messages': 0},
      'profile': {'completion': 100, 'complete': true},
      'featured': {'total': 0, 'items': []},
      'next_event': null,
      'peer': null,
    },
  }, headers: {'Cache-Control': 'no-store'}),
  'GET ccc': ccc ?? (_) => jsonBody(200, cccBody(), headers: {'Cache-Control': 'no-store, private'}),
};

Map<String, dynamic> memberUser() => userJson(permissions: ['ccc_tools.view']);

Map<String, dynamic> peerUserCcc() => userJson(
  role: 'pair',
  roles: ['membre', 'pair'],
  spaces: ['member', 'peer'],
  permissions: ['peer_space.access', 'ccc_tools.view'],
);

class CccHarness {
  CccHarness(this.env, this.launcher);
  final TestEnv env;
  final FakeLauncher launcher;
  FakeApi get api => env.api;
}

/// Ouvre l'application connectée puis pousse [path].
Future<CccHarness> openCcc(
  WidgetTester tester, {
  FakeHandler? ccc,
  Map<String, dynamic>? user,
  String path = '/ccc',
  Map<String, Object> prefs = const {},
  DateTime? copyAt,
  Map<String, dynamic>? copyBody,
  Iterable<String> savedImages = const [],
  HttpClientAdapter? imageApi,
  bool diskAvailable = true,
}) async {
  await initializeDateFormatting('fr');
  final launcher = FakeLauncher();
  final u = user ?? memberUser();
  final env = TestEnv(
    api: FakeApi(cccRoutes(ccc: ccc, user: u)),
    token: 'tok',
    cachedUser: u,
    initialPrefs: prefs,
    extra: [
      linkLauncherProvider.overrideWithValue(launcher),
      if (imageApi != null) cccImageDioProvider.overrideWithValue(Dio()..httpClientAdapter = imageApi),
    ],
  );
  env.imageDisk.available = diskAvailable;
  if (copyAt != null) await env.db.privateCache.put(cccCopyKey(12), cccCopyJson(copyAt, copyBody ?? cccBody()));
  for (final url in savedImages) {
    env.imageDisk.files[CccImageStore.nameFor(url)] = [1, 2, 3];
  }
  usePhone(tester);
  addTearDown(tester.view.reset);
  await tester.pumpWidget(await env.app());
  await tester.pumpAndSettle();
  GoRouter.of(tester.element(find.byType(Scaffold).first)).push(path);
  await tester.pumpAndSettle();
  return CccHarness(env, launcher);
}

Future<void> openTab(WidgetTester tester, String key) async {
  await tester.ensureVisible(find.text(S.t('ccc_tabs_$key')));
  await tester.pumpAndSettle();
  await tester.tap(find.text(S.t('ccc_tabs_$key')));
  await tester.pumpAndSettle();
}

ProviderContainer container(WidgetTester tester) =>
    ProviderScope.containerOf(tester.element(find.byType(Scaffold).first));

/// Contenu d'une copie locale, tel que l'écrit `CccRepository`.
String cccCopyJson(DateTime at, Map<String, dynamic> body) =>
    jsonEncode({'synced_at': at.toUtc().toIso8601String(), 'body': body});

/// Toutes les adresses d'images d'un corps de réponse (couvertures et planches).
Set<String> bodyImages(Map<String, dynamic> body) =>
    cccImageUrls(CccCatalog.fromJson(body));

/// Faux serveur d'images : renvoie [bytes] (PNG par défaut) pour chaque adresse, sauf si [handler] décide autrement.
class FakeImageServer implements HttpClientAdapter {
  FakeImageServer({this.handler, this.bytes});
  final FutureOr<ResponseBody> Function(String url)? handler;
  final List<int>? bytes;
  final List<String> urls = [];

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    final url = options.uri.toString();
    urls.add(url);
    if (handler != null) return handler!(url);
    return imageBody(bytes ?? const [137, 80, 78, 71, 1, 2, 3, 4]);
  }

  @override
  void close({bool force = false}) {}
}

ResponseBody imageBody(List<int> bytes, {String type = 'image/png', int status = 200}) =>
    ResponseBody.fromBytes(bytes, status, headers: {Headers.contentTypeHeader: [type]});
