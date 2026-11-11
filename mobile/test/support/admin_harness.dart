import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:xomiton/features/admin/application/admin_providers.dart';

import 'harness.dart';

const kAdminPin = '4821';

Map<String, dynamic> adminUser({List<String>? permissions}) => userJson(
  role: 'admin',
  roles: ['membre', 'admin'],
  spaces: ['member', 'admin'],
  permissions: permissions ?? ['admin_mobile.access', 'alerts.view', 'alerts.take', 'alerts.close', 'alerts.manage'],
);

Map<String, dynamic> dashboardBody({
  Map<String, dynamic>? alerts,
  int pendingAccounts = 2,
  int pendingPeer = 1,
  int pendingModeration = 3,
  int conflicts = 0,
  bool maintenance = false,
  bool onCallMe = true,
}) => {
  'data': {
    'alerts':
        alerts ??
        {'new': 3, 'sos_active': 1, 'critical_open': 2, 'handled': 4, 'sos_unacknowledged': 1, 'open': 6},
    'pending_accounts': pendingAccounts,
    'pending_peer_requests': pendingPeer,
    'pending_moderation': pendingModeration,
    'open_sync_conflicts': conflicts,
    'maintenance': {'state': maintenance ? 'ongoing' : 'inactive', 'active': maintenance, 'ends_at': null},
    'on_call': {'me': onCallMe, 'admins': 2, 'peers': 3},
    'poll_seconds': 10,
    'server_time': DateTime.now().toUtc().toIso8601String(),
  },
};

Map<String, dynamic> adminAlertRow(
  String id, {
  String type = 'sos',
  String status = 'nouveau',
  bool ack = false,
  int age = 2,
  String place = 'Abomey-Calavi',
  String priority = 'critique',
  String? routedTo = 'Point focal Atlantique',
  bool escalated = false,
}) => {
  'id': id,
  'code': 'AL-$id',
  'type': type,
  'status': status,
  'priority': priority,
  'place': place,
  'zone': 'Atlantique',
  'commune': place,
  'routed_to': routedTo,
  'acknowledged': ack,
  'acknowledged_at': ack ? DateTime.now().toUtc().toIso8601String() : null,
  'escalated': escalated,
  'age_minutes': age,
};

Map<String, dynamic> adminAlertDetail(
  String id, {
  String status = 'nouveau',
  bool ack = false,
  Map<String, dynamic>? can,
  Map<String, dynamic>? position,
}) => {
  ...adminAlertRow(id, status: status, ack: ack),
  'category': 'physique',
  'needs': [
    {'slug': 'securite', 'label': 'Mise en sécurité'},
  ],
  'description': 'Elle demande de l’aide.',
  'author': 'Anonyme',
  'contact': null,
  'position': position ?? {'lat': 6.45, 'lng': 2.36, 'precision_m': 35, 'rounded': true},
  'history': [
    {'status': 'nouveau', 'status_label': 'Alerte reçue (Nouveau)', 'at': DateTime.now().toUtc().toIso8601String()},
  ],
  'notes': [
    {'id': 1, 'author': 'Équipe', 'body': 'Appel tenté.', 'at': DateTime.now().toUtc().toIso8601String()},
  ],
  'escalations': [],
  'notified': [
    {
      'key': 'k1',
      'kind': 'directory',
      'label': 'Point focal Atlantique',
      'role': 'point_focal',
      'state': 'sent',
      'state_label': 'Envoyée',
      'phone': '+22997000000',
    },
    {'key': 'k2', 'kind': 'trusted', 'label': 'Contact de confiance n° 1', 'state': 'sent', 'state_label': 'Envoyée', 'phone': null},
  ],
  'can':
      can ??
      {
        'acknowledge': !ack,
        'take': true,
        'close': true,
        'reassign': true,
        'note': true,
        'statuses': ['pris_en_charge', 'en_cours', 'traite', 'clos'],
      },
};

Map<String, FakeHandler> adminRoutes({Map<String, dynamic>? user, Map<String, FakeHandler> extra = const {}}) => {
  'GET me': (_) => jsonBody(200, {'data': user ?? adminUser()}),
  'GET dashboard': (_) => jsonBody(200, {
    'data': {
      'tiles': [],
      'counters': {'notifications': 0, 'messages': 0},
      'profile': {'completion': 100, 'complete': true},
      'featured': {'total': 0, 'items': []},
      'next_event': null,
    },
  }),
  'GET me/security': (_) => jsonBody(200, {
    'data': {'discreet': true, 'mask_previews': false, 'pin_enabled': true, 'neutral_tab_title': false},
  }),
  'POST me/devices': (_) => jsonBody(201, {
    'data': {'device_uuid': 'x'},
  }),
  'GET admin/dashboard': (_) => jsonBody(200, dashboardBody()),
  'GET admin/alerts': (o) => jsonBody(200, {
    'data': [adminAlertRow('a1'), adminAlertRow('a2', type: 'besoin', priority: 'normale', place: 'Cotonou', ack: true, status: 'pris_en_charge')],
    'meta': {'current_page': 1, 'last_page': 1, 'total': 2, 'new': 1},
  }),
  ...extra,
};

/// Ouvre l'application dans l'espace Admin, code saisi : l'écran est `/admin/astreinte`.
Future<TestEnv> openAdmin(
  WidgetTester tester, {
  Map<String, FakeHandler>? routes,
  bool online = true,
  List<Override> overrides = const [],
  Map<String, Object> prefs = const {},
  String? goTo_,
}) async {
  final env = TestEnv(
    api: FakeApi(routes ?? adminRoutes()),
    token: 'tok',
    cachedUser: adminUser(),
    pin: kAdminPin,
    online: online,
    initialPrefs: {'active_space': 'admin', 'discreet_default_applied': true, ...prefs},
    extra: [adminPollEnabledProvider.overrideWithValue(false), ...overrides],
  );
  usePhone(tester);
  addTearDown(tester.view.reset);
  await tester.pumpWidget(await env.app());
  await tester.pumpAndSettle();
  for (final d in kAdminPin.split('')) {
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
