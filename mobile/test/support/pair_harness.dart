import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:xomiton/core/platform/platform_services.dart';

import 'fakes.dart';
import 'harness.dart';

const kPin = '4821';

Map<String, dynamic> peerUser() => userJson(
  role: 'pair',
  roles: ['membre', 'pair'],
  spaces: ['member', 'peer'],
  permissions: ['peer_space.access', 'cases.create', 'alerts.view_routed'],
);

Map<String, dynamic> peerSection({
  int collected = 12,
  int thisMonth = 3,
  int inProgress = 4,
  int closed = 8,
  int toTake = 1,
  int toContact = 2,
  bool onDuty = false,
}) => {
  'kpi': {
    'collected': collected,
    'this_month': thisMonth,
    'in_progress': inProgress,
    'closed': closed,
  },
  'routed_alerts': {'open': 2, 'new': toTake, 'items': []},
  'tasks': [
    if (toTake > 0) {'key': 'alerts_to_take', 'count': toTake},
    if (toContact > 0) {'key': 'cases_to_contact', 'count': toContact},
  ],
  if (onDuty) 'on_duty': true,
};

Map<String, dynamic> alertRow(
  String id, {
  String type = 'sos',
  String status = 'nouveau',
  bool ack = false,
  String place = 'Abomey-Calavi',
  String priority = 'critique',
  String? category,
}) => {
  'id': id,
  'type': type,
  'type_label': type == 'sos'
      ? 'SOS'
      : (type == 'besoin' ? 'Besoin' : 'Signalement'),
  'status': status,
  'status_label': status == 'nouveau' ? 'Nouveau' : 'Pris en charge',
  'priority': priority,
  'priority_label': 'Critique',
  'place': place,
  'category_label': category,
  'acknowledged': ack,
  'created_at': DateTime.now()
      .subtract(const Duration(minutes: 5))
      .toUtc()
      .toIso8601String(),
};

/// Routes communes de l'espace pair ; `extra` complète ou remplace.
Map<String, FakeHandler> pairRoutes({
  Map<String, dynamic>? peer,
  Map<String, FakeHandler> extra = const {},
}) => {
  'GET me': (_) => jsonBody(200, {'data': peerUser()}),
  'GET dashboard': (_) => jsonBody(200, {
    'data': {
      'tiles': [],
      'counters': {'notifications': 0, 'messages': 0},
      'profile': {'completion': 100, 'complete': true},
      'featured': {'total': 0, 'items': []},
      'next_event': null,
      'peer': peer ?? peerSection(),
    },
  }),
  'GET me/security': (_) => jsonBody(200, {
    'data': {
      'discreet': true,
      'mask_previews': false,
      'pin_enabled': true,
      'neutral_tab_title': false,
    },
  }),
  'POST me/devices': (_) => jsonBody(201, {
    'data': {'device_uuid': 'x'},
  }),
  'POST heartbeat': (_) => jsonBody(200, {
    'data': {'open_conflicts': 0, 'rules_updated_at': null, 'wipe': null},
  }),
  'GET sync/pull': (_) => jsonBody(200, {
    'data': <String, dynamic>{},
    'meta': {'cursor': 'c0', 'has_more': false},
  }),
  'GET sync/conflicts': (_) => jsonBody(200, {
    'data': [],
    'meta': {'open': 0},
  }),
  'GET pair/validation-rules': (_) => jsonBody(
    200,
    {
      'data': [],
      'meta': {
        'updated_at': 'r1',
        'options': {
          'age_ranges': [
            {'value': '10-14', 'label': '10-14 ans'},
            {'value': '15-17', 'label': '15-17 ans'},
            {'value': '18-24', 'label': '18-24 ans'},
          ],
          'referrals': ['Centre de santé', 'Service juridique'],
          'followup_steps': ['Premier entretien', 'Appel de suivi'],
          'notes_min_length': 20,
        },
      },
    },
    headers: {'ETag': '"r1"'},
  ),
  ...extra,
};

/// Ouvre l'application dans l'espace pair, code saisi : l'écran est `/pair/accueil`.
Future<TestEnv> openPair(
  WidgetTester tester, {
  Map<String, FakeHandler>? routes,
  bool online = true,
  List<Override> overrides = const [],
  Map<String, Object> prefs = const {},
  void Function(TestEnv env)? before,
  String? goTo_,
}) async {
  final env = TestEnv(
    api: FakeApi(routes ?? pairRoutes()),
    token: 'tok',
    cachedUser: peerUser(),
    pin: kPin,
    online: online,
    initialPrefs: {'active_space': 'peer', ...prefs},
    extra: overrides,
  );
  before?.call(env);
  usePhone(tester);
  addTearDown(tester.view.reset);
  await tester.pumpWidget(await env.app());
  await tester.pumpAndSettle();
  for (final d in kPin.split('')) {
    await tester.tap(find.byKey(Key('pin-key-$d')));
    await tester.pump();
  }
  await tester.pumpAndSettle();
  if (goTo_ != null) {
    final ctx = tester.element(find.byType(Scaffold).first);
    GoRouter.of(ctx).go(goTo_);
    await tester.pumpAndSettle();
  }
  return env;
}

String currentPath(WidgetTester tester) =>
    GoRouter.of(tester.element(find.byType(Scaffold).first))
        .routerDelegate
        .currentConfiguration
        .uri
        .toString();

List<Override> pairOverrides({FakeLauncher? launcher}) => [
  if (launcher != null) linkLauncherProvider.overrideWithValue(launcher),
];

DioException offline(RequestOptions o) => offlineError(o);

/// Fait défiler jusqu'à un élément, puis le ramène bien dans la zone visible.
Future<void> revealFinder(WidgetTester tester, Finder f) async {
  await tester.scrollUntilVisible(
    f,
    250,
    scrollable: find.byType(Scrollable).first,
  );
  await tester.ensureVisible(f);
  await tester.pumpAndSettle();
}

Future<void> revealKey(WidgetTester tester, String key) =>
    revealFinder(tester, find.byKey(ValueKey(key)));
