import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/core/platform/platform_services.dart';

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
}) async {
  await initializeDateFormatting('fr');
  final launcher = FakeLauncher();
  final u = user ?? memberUser();
  final env = TestEnv(
    api: FakeApi(cccRoutes(ccc: ccc, user: u)),
    token: 'tok',
    cachedUser: u,
    initialPrefs: prefs,
    extra: [linkLauncherProvider.overrideWithValue(launcher)],
  );
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
