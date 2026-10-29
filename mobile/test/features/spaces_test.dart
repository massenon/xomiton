import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/core/auth_spaces/app_space.dart';
import 'package:xomiton/core/auth_spaces/space_providers.dart';
import 'package:xomiton/core/providers/core_providers.dart';
import 'package:xomiton/features/auth/application/session_controller.dart';

import '../support/harness.dart';

final S = loadStrings();

Map<String, dynamic> member() => userJson(roles: ['membre'], spaces: ['member']);
Map<String, dynamic> peer() =>
    userJson(role: 'pair', roles: ['membre', 'pair'], spaces: ['member', 'peer'], permissions: ['peer_space.access']);
Map<String, dynamic> admin() => userJson(
      role: 'admin',
      roles: ['membre', 'admin'],
      spaces: ['member', 'admin'],
      permissions: ['admin_mobile.access'],
    );
Map<String, dynamic> peerAdmin() => userJson(
      role: 'admin',
      roles: ['membre', 'pair', 'admin'],
      spaces: ['member', 'peer', 'admin'],
      permissions: ['peer_space.access', 'admin_mobile.access'],
    );

Map<String, FakeHandler> routes(Map<String, dynamic> Function() me, {Map<String, FakeHandler> extra = const {}}) => {
      'GET me': (_) => jsonBody(200, {'data': me()}),
      'GET dashboard': (_) => jsonBody(200, {
            'data': {
              'tiles': [],
              'counters': {'notifications': 0, 'messages': 0},
              'profile': {'completion': 100, 'complete': true},
              'featured': {'total': 0, 'items': []},
              'next_event': null,
              'peer': null,
            }
          }),
      'GET me/role-requests': (_) => jsonBody(200, {'data': [], 'meta': {'can_request': true}}),
      ...extra,
    };

Future<void> tapPin(WidgetTester tester, String pin) async {
  for (final d in pin.split('')) {
    await tester.tap(find.byKey(Key('pin-key-$d')));
    await tester.pump();
  }
  await tester.pumpAndSettle();
}

/// Premier accès à l'espace Pair ou Admin : le code est obligatoire, on le choisit (deux saisies).
Future<void> choosePin(WidgetTester tester, [String pin = '4821']) async {
  expect(find.byKey(const ValueKey('pin-setup-choose')), findsOneWidget);
  await tester.tap(find.byKey(const ValueKey('pin-setup-choose')));
  await tester.pumpAndSettle();
  await tapPin(tester, pin);
  await tapPin(tester, pin);
}

const _pin = '4821';

Future<void> pump(WidgetTester tester, TestEnv env, {bool unlock = false}) async {
  usePhone(tester);
  addTearDown(tester.view.reset);
  await tester.pumpWidget(await env.app());
  await tester.pumpAndSettle();
  if (unlock) await tapPin(tester, _pin);
}

GoRouter routerOf(WidgetTester tester) => GoRouter.of(tester.element(find.byType(Scaffold).first));
String path(WidgetTester tester) => routerOf(tester).routerDelegate.currentConfiguration.uri.path;
ProviderContainer containerOf(WidgetTester tester) => ProviderScope.containerOf(tester.element(find.byType(Scaffold).first));

Future<void> signInThroughLogin(WidgetTester tester) async {
  await tester.enterText(find.widgetWithText(TextField, S.t('auth_login_identifier')), 'awa@exemple.bj');
  await tester.enterText(find.widgetWithText(TextField, S.t('auth_login_password')), 'Secret0Fort1');
  await tester.tap(find.text(S.t('auth_login_submit')).last);
  await tester.pumpAndSettle();
}

Map<String, FakeHandler> loginRoutes(Map<String, dynamic> user) => routes(() => user, extra: {
      'POST auth/login': (_) => jsonBody(200, {
            'data': {'token': '1|abc', 'token_type': 'Bearer', 'user': user, 'device_token': 'd', 'device_token_expires_at': 'x'}
          }),
    });

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('connexion unique : le serveur décide des espaces', () {
    testWidgets('membre simple : directement au tableau de bord, aucune trace des autres espaces', (tester) async {
      await pump(tester, TestEnv(api: FakeApi(loginRoutes(member())), seen: true));
      await signInThroughLogin(tester);
      expect(path(tester), '/home');
      expect(find.byKey(const ValueKey('space-pill')), findsNothing);
      expect(find.byKey(const ValueKey('tab-other-space')), findsNothing);
      expect(find.descendant(of: find.byType(NavigationBar), matching: find.byType(NavigationDestination)), findsNWidgets(5));
    });

    testWidgets('pair + membre : sélecteur d’espace après la connexion, puis Membre avec pastille et onglet « Espace pair »', (tester) async {
      await pump(tester, TestEnv(api: FakeApi(loginRoutes(peer())), seen: true));
      await signInThroughLogin(tester);
      expect(path(tester), '/spaces');
      expect(find.text(S.t('mobile_space_selector_greeting', {'name': 'Awa_S'})), findsOneWidget);
      expect(find.byKey(const ValueKey('space-tile-member')), findsOneWidget);
      expect(find.byKey(const ValueKey('space-tile-peer')), findsOneWidget);
      expect(find.byKey(const ValueKey('space-tile-admin')), findsNothing);
      // Cibles d'au moins 44 px (64 ici).
      expect(tester.getSize(find.byKey(const ValueKey('space-tile-peer'))).height, greaterThanOrEqualTo(44));

      await tester.tap(find.byKey(const ValueKey('space-tile-member')));
      await tester.pumpAndSettle();
      expect(path(tester), '/home');
      expect(find.byKey(const ValueKey('space-pill')), findsOneWidget);
      expect(find.descendant(of: find.byKey(const ValueKey('space-pill')), matching: find.text(S.t('peer_home_title'))), findsNothing);
      expect(find.text('Membre'), findsWidgets);
      expect(find.descendant(of: find.byType(NavigationBar), matching: find.text('Espace pair')), findsOneWidget);
      // Les cinq onglets membres sont inchangés (libellé « SOS » compris).
      for (final k in ['menus_mobile_home', 'menus_mobile_feed', 'menus_mobile_messages', 'menus_mobile_profile']) {
        expect(find.descendant(of: find.byType(NavigationBar), matching: find.text(S.t(k))), findsOneWidget, reason: k);
      }
      expect(find.descendant(of: find.byType(NavigationBar), matching: find.text('SOS')), findsOneWidget);
    });

    testWidgets('relancement sans espace mémorisé : Membre, pas de sélecteur', (tester) async {
      final env = TestEnv(api: FakeApi(routes(peer)), seen: true, token: 'tok', cachedUser: peer());
      await pump(tester, env);
      expect(path(tester), '/home');
      expect(find.byKey(const ValueKey('space-tile-member')), findsNothing);
    });

    testWidgets('relancement : retour au dernier espace mémorisé, sans repasser par le sélecteur', (tester) async {
      final env = TestEnv(
        api: FakeApi(routes(peer)),
        seen: true,
        token: 'tok',
        cachedUser: peer(),
        initialPrefs: {'active_space': 'peer'},
        pin: _pin,
      );
      await pump(tester, env, unlock: true);
      expect(path(tester), '/pair/accueil');
      expect(find.byKey(const ValueKey('space-tile-peer')), findsNothing);
    });

    testWidgets('espace mémorisé que le serveur ne déclare plus : Membre', (tester) async {
      final env = TestEnv(
        api: FakeApi(routes(member)),
        seen: true,
        token: 'tok',
        cachedUser: member(),
        initialPrefs: {'active_space': 'admin'},
      );
      await pump(tester, env);
      expect(path(tester), '/home');
      expect(containerOf(tester).read(activeSpaceProvider), AppSpace.member);
    });

    testWidgets('petit écran 360 × 640 : six onglets Membre sans débordement, cibles ≥ 44 px', (tester) async {
      tester.view.physicalSize = const Size(360 * 2, 640 * 2);
      tester.view.devicePixelRatio = 2;
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await TestEnv(api: FakeApi(routes(peerAdmin)), seen: true, token: 'tok', cachedUser: peerAdmin()).app());
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      for (final d in tester.widgetList<NavigationDestination>(find.byType(NavigationDestination))) {
        final size = tester.getSize(find.byWidget(d));
        expect(size.height, greaterThanOrEqualTo(44));
        expect(size.width, greaterThanOrEqualTo(44));
      }
      expect(find.byType(NavigationDestination), findsNWidgets(6));
    });

    testWidgets('lecteur d’écran : le sélecteur annonce chaque espace, sa description et l’espace courant', (tester) async {
      final handle = tester.ensureSemantics();
      await pump(tester, TestEnv(api: FakeApi(loginRoutes(peer())), seen: true));
      await signInThroughLogin(tester);
      expect(find.bySemanticsLabel(RegExp('Pair éducateur·rice\\. Alertes routées')), findsOneWidget);
      expect(find.bySemanticsLabel(RegExp('^Membre\\. Réseau social')), findsOneWidget);
      handle.dispose();
    });

    testWidgets('admin + membre : onglet « Admin » ouvre l’espace Admin, onglet « Réseau » ramène au fil Membre', (tester) async {
      await pump(tester, TestEnv(api: FakeApi(loginRoutes(admin())), seen: true));
      await signInThroughLogin(tester);
      expect(path(tester), '/spaces');
      await tester.tap(find.byKey(const ValueKey('space-tile-admin')));
      await tester.pumpAndSettle();
      expect(path(tester), '/pin-setup', reason: 'premier accès : code obligatoire');
      await choosePin(tester);
      expect(path(tester), '/admin/astreinte');
      expect(containerOf(tester).read(activeSpaceProvider), AppSpace.admin);
      // Barre Admin : Astreinte · Alertes · Gérer · Notifs · Réseau.
      for (final k in ['mobile_admin_tab_duty', 'mobile_admin_tab_alerts', 'mobile_admin_tab_manage', 'mobile_admin_tab_notifications', 'mobile_tab_network']) {
        expect(find.descendant(of: find.byType(NavigationBar), matching: find.text(S.t(k))), findsOneWidget, reason: k);
      }
      await tester.tap(find.byKey(const ValueKey('tab-admin-manage')));
      await tester.pumpAndSettle();
      expect(path(tester), '/admin/gerer');
      await tester.tap(find.byKey(const ValueKey('tab-admin-network')));
      await tester.pumpAndSettle();
      expect(path(tester), '/feed');
      expect(containerOf(tester).read(activeSpaceProvider), AppSpace.member);
      expect(find.descendant(of: find.byType(NavigationBar), matching: find.text('Admin')), findsOneWidget);
    });

    testWidgets('pair : barre Accueil · Alertes · SOS · Dossiers · Réseau, SOS toujours présent', (tester) async {
      await pump(tester, TestEnv(api: FakeApi(loginRoutes(peer())), seen: true));
      await signInThroughLogin(tester);
      await tester.tap(find.byKey(const ValueKey('space-tile-peer')));
      await tester.pumpAndSettle();
      await choosePin(tester);
      expect(path(tester), '/pair/accueil');
      for (final k in ['menus_mobile_home', 'mobile_peer_tab_alerts', 'mobile_tab_sos', 'mobile_peer_tab_cases', 'mobile_tab_network']) {
        expect(find.descendant(of: find.byType(NavigationBar), matching: find.text(S.t(k))), findsOneWidget, reason: k);
      }
      await tester.tap(find.byKey(const ValueKey('tab-peer-sos')));
      await tester.pumpAndSettle();
      expect(path(tester), '/pair/sos');
      expect(find.byKey(const ValueKey('tab-peer-sos')), findsOneWidget);
    });

    testWidgets('pair + admin : l’onglet « Espaces » ouvre la feuille « Choisir mon espace »', (tester) async {
      await pump(tester, TestEnv(api: FakeApi(routes(peerAdmin)), seen: true, token: 'tok', cachedUser: peerAdmin(), pin: _pin), unlock: true);
      expect(find.descendant(of: find.byType(NavigationBar), matching: find.text(S.t('mobile_space_tab_both'))), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('tab-other-space')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_space_choose_title')), findsOneWidget);
      for (final k in ['member', 'peer', 'admin']) {
        expect(find.byKey(ValueKey('space-tile-$k')), findsOneWidget);
      }
      await tester.tap(find.byKey(const ValueKey('space-tile-admin')));
      await tester.pumpAndSettle();
      expect(path(tester), '/admin/astreinte');
      expect(find.text(S.t('mobile_space_choose_title')), findsNothing);
    });

    testWidgets('pastille d’en-tête : feuille de choix, bascule Membre → Pair', (tester) async {
      await pump(tester, TestEnv(api: FakeApi(routes(peer)), seen: true, token: 'tok', cachedUser: peer(), pin: _pin), unlock: true);
      await tester.tap(find.byKey(const ValueKey('space-pill')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_space_choose_title')), findsOneWidget);
      expect(find.text(S.t('mobile_space_here')), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('space-tile-peer')));
      await tester.pumpAndSettle();
      expect(path(tester), '/pair/accueil');
      expect(find.byKey(const ValueKey('space-pill')), findsOneWidget);
      // La pastille annonce l'espace courant.
      final handle = tester.ensureSemantics();
      expect(find.bySemanticsLabel(S.t('mobile_space_pill_label', {'space': 'Pair éducateur·rice'})), findsOneWidget);
      handle.dispose();
    });
  });

  group('garde de rôle : le client n’est jamais une frontière de sécurité', () {
    testWidgets('membre simple : lien profond vers /admin/** ou /pair/** → accueil Membre, aucune requête admin ni pair', (tester) async {
      final api = FakeApi(routes(member));
      await pump(tester, TestEnv(api: api, seen: true, token: 'tok', cachedUser: member()));
      routerOf(tester).go('/admin/astreinte');
      await tester.pumpAndSettle();
      expect(path(tester), '/home');
      routerOf(tester).go('/pair/alertes');
      await tester.pumpAndSettle();
      expect(path(tester), '/home');
      expect(api.requests.where((r) => r.path.startsWith('admin/') || r.path.startsWith('pair/')), isEmpty);
    });

    testWidgets('droit retiré en cours de session : GET me relu, espace fermé, message affiché', (tester) async {
      var current = peer();
      final api = FakeApi(routes(() => current));
      await pump(tester, TestEnv(api: api, seen: true, token: 'tok', cachedUser: peer(), pin: _pin), unlock: true);
      routerOf(tester).go('/pair/accueil');
      await tester.pumpAndSettle();
      expect(path(tester), '/pair/accueil');

      current = member(); // l'admin retire le rôle pair
      unawaited(containerOf(tester).read(sessionProvider.notifier).refresh());
      await tester.pumpAndSettle();
      expect(path(tester), '/home');
      expect(find.text(S.t('mobile_space_unavailable')), findsOneWidget);
      expect(containerOf(tester).read(activeSpaceProvider), AppSpace.member);
      expect(find.byKey(const ValueKey('tab-other-space')), findsNothing);
      expect(find.byKey(const ValueKey('space-pill')), findsNothing);
    });

    testWidgets('403 forbidden d’une route pair/* : le serveur est relu', (tester) async {
      var current = peer();
      final api = FakeApi({
        ...routes(() => current),
        'GET pair/alerts': (_) => jsonBody(403, {'message': 'Refusé', 'code': 'forbidden'}),
      });
      await pump(tester, TestEnv(api: api, seen: true, token: 'tok', cachedUser: peer()));
      final before = api.calls('GET me').length;
      current = member();
      final dio = containerOf(tester).read(dioProvider);
      final failed = expectLater(dio.get<dynamic>('pair/alerts'), throwsA(anything));
      await tester.pumpAndSettle();
      await failed;
      expect(api.calls('GET me').length, greaterThan(before));
      expect(containerOf(tester).read(spacesProvider), [AppSpace.member]);
    });

    testWidgets('403 d’une route hors espace (ex. e-mail non confirmé) : pas de relecture', (tester) async {
      final api = FakeApi({
        ...routes(peer),
        'POST feed': (_) => jsonBody(403, {'message': 'Confirme', 'code': 'email_unverified'}),
      });
      await pump(tester, TestEnv(api: api, seen: true, token: 'tok', cachedUser: peer()));
      final before = api.calls('GET me').length;
      final failed = expectLater(containerOf(tester).read(dioProvider).post<dynamic>('feed'), throwsA(anything));
      await tester.pumpAndSettle();
      await failed;
      expect(api.calls('GET me').length, before);
    });

    testWidgets('un espace non déclaré par le serveur ne peut pas être activé', (tester) async {
      await pump(tester, TestEnv(api: FakeApi(routes(member)), seen: true, token: 'tok', cachedUser: member()));
      final c = containerOf(tester);
      expect(c.read(activeSpaceProvider.notifier).select(AppSpace.admin), isFalse);
      expect(c.read(activeSpaceProvider), AppSpace.member);
      expect(chooseSpace(WidgetRefOf(c), AppSpace.peer), isNull);
    });

    testWidgets('espace actif mémorisé, mais purgé s’il n’est plus déclaré ; effacé à la déconnexion', (tester) async {
      final env = TestEnv(api: FakeApi(routes(peer)), seen: true, token: 'tok', cachedUser: peer());
      await pump(tester, env);
      final c = containerOf(tester);
      expect(c.read(activeSpaceProvider.notifier).select(AppSpace.peer), isTrue);
      expect(c.read(sharedPreferencesProvider).getString(kPrefActiveSpace), 'peer');
      await c.read(sessionProvider.notifier).expire();
      await tester.pumpAndSettle();
      expect(c.read(sharedPreferencesProvider).getString(kPrefActiveSpace), isNull);
      expect(c.read(spacesProvider), isEmpty);
    });
  });

  group('build « membre seul » (--dart-define=SPACES=member)', () {
    testWidgets('un compte pair + admin ne voit que Membre : ni sélecteur, ni pastille, ni onglet, /pair et /admin refusés', (tester) async {
      final env = TestEnv(
        api: FakeApi(routes(peerAdmin)),
        seen: true,
        token: 'tok',
        cachedUser: peerAdmin(),
        extra: [buildSpacesProvider.overrideWithValue({AppSpace.member})],
      );
      await pump(tester, env);
      expect(find.byKey(const ValueKey('space-pill')), findsNothing);
      expect(find.byKey(const ValueKey('tab-other-space')), findsNothing);
      routerOf(tester).go('/pair/accueil');
      await tester.pumpAndSettle();
      expect(path(tester), '/home');
    });
  });

  group('Profil › Mes espaces', () {
    Future<void> openProfile(WidgetTester tester) async {
      await tester.tap(find.descendant(of: find.byType(NavigationBar), matching: find.text(S.t('menus_mobile_profile'))));
      await tester.pumpAndSettle();
    }

    testWidgets('compte à plusieurs espaces : rôles et bouton « Changer d’espace »', (tester) async {
      await pump(tester, TestEnv(api: FakeApi(routes(peer)), seen: true, token: 'tok', cachedUser: peer(), pin: _pin), unlock: true);
      await openProfile(tester);
      expect(find.byKey(const ValueKey('my-spaces-card')), findsOneWidget);
      expect(find.text(S.t('mobile_my_spaces_title')), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('my-spaces-switch')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_space_choose_title')), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('space-tile-peer')));
      await tester.pumpAndSettle();
      expect(path(tester), '/pair/accueil');
    });

    testWidgets('membre simple : demande du rôle pair (D-MOB-15), clé d’idempotence et motif envoyés', (tester) async {
      var pending = false;
      final api = FakeApi({
        ...routes(member),
        'GET me/role-requests': (_) => jsonBody(200, {
              'data': pending
                  ? [
                      {'id': '01J', 'role': 'pair', 'status': 'pending', 'reason': 'Je mène des ateliers', 'decision_reason': null, 'created_at': '2026-10-29T10:00:00+01:00', 'decided_at': null}
                    ]
                  : [],
              'meta': {'can_request': !pending},
            }),
        'POST me/role-requests': (_) {
          pending = true;
          return jsonBody(201, {
            'data': {'id': '01J', 'role': 'pair', 'status': 'pending', 'reason': 'Je mène des ateliers', 'decision_reason': null, 'created_at': '2026-10-29T10:00:00+01:00', 'decided_at': null}
          });
        },
      });
      await pump(tester, TestEnv(api: api, seen: true, token: 'tok', cachedUser: member()));
      await openProfile(tester);
      expect(find.byKey(const ValueKey('my-spaces-switch')), findsNothing);
      await tester.tap(find.byKey(const ValueKey('role-request-open')));
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const ValueKey('role-request-reason')), 'Je mène des ateliers');
      await tester.tap(find.byKey(const ValueKey('role-request-send')));
      await tester.pumpAndSettle();
      final call = api.calls('POST me/role-requests').single;
      expect(call.data, {'reason': 'Je mène des ateliers'}, reason: 'jamais de champ de rôle : on ne demande que le rôle pair');
      expect(call.headers['Idempotency-Key'], isNotEmpty);
      expect(find.text(S.t('mobile_role_request_sent')), findsOneWidget);
      // La demande est en cours : plus de bouton, l'état est affiché.
      expect(find.byKey(const ValueKey('role-request-open')), findsNothing);
      expect(find.text(S.t('mobile_role_request_pending')), findsOneWidget);
    });

    testWidgets('demande refusée par le serveur (e-mail non confirmé) : message du serveur, feuille ouverte', (tester) async {
      final api = FakeApi({
        ...routes(member),
        'POST me/role-requests': (_) => jsonBody(422, {'message': 'Confirme d’abord ton adresse e-mail pour demander ce rôle.', 'code': 'email_unverified'}),
      });
      await pump(tester, TestEnv(api: api, seen: true, token: 'tok', cachedUser: member()));
      await openProfile(tester);
      await tester.tap(find.byKey(const ValueKey('role-request-open')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('role-request-send')));
      await tester.pumpAndSettle();
      expect(find.text('Confirme d’abord ton adresse e-mail pour demander ce rôle.'), findsOneWidget);
      expect(find.byKey(const ValueKey('role-request-send')), findsOneWidget);
    });

    testWidgets('dernière demande refusée : le motif écrit pour la personne est affiché', (tester) async {
      final api = FakeApi({
        ...routes(member),
        'GET me/role-requests': (_) => jsonBody(200, {
              'data': [
                {'id': '01J', 'role': 'pair', 'status': 'rejected', 'reason': null, 'decision_reason': 'Reviens après la formation.', 'created_at': '2026-10-20T10:00:00+01:00', 'decided_at': '2026-10-21T10:00:00+01:00'}
              ],
              'meta': {'can_request': true},
            }),
      });
      await pump(tester, TestEnv(api: api, seen: true, token: 'tok', cachedUser: member()));
      await openProfile(tester);
      expect(find.textContaining('Reviens après la formation.'), findsOneWidget);
      expect(find.byKey(const ValueKey('role-request-open')), findsOneWidget);
    });
  });
}

/// Petit adaptateur : `chooseSpace` attend un `WidgetRef` ; le conteneur suffit pour ces tests.
class WidgetRefOf implements WidgetRef {
  WidgetRefOf(this.c);
  final ProviderContainer c;
  @override
  T read<T>(ProviderListenable<T> provider) => c.read(provider);
  @override
  dynamic noSuchMethod(Invocation invocation) => throw UnimplementedError();
}
