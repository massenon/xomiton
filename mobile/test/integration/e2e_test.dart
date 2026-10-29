import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/core/auth_spaces/app_space.dart';
import 'package:xomiton/core/auth_spaces/space_providers.dart';
import 'package:xomiton/core/maintenance/maintenance.dart';
import 'package:xomiton/core/providers/core_providers.dart';
import 'package:xomiton/core/push/push_registrar.dart';
import 'package:xomiton/core/push/push_service.dart';
import 'package:xomiton/core/security/offline_expiry.dart';
import 'package:xomiton/core/sync/sync_providers.dart';
import 'package:xomiton/core/sync/sync_queue.dart';
import 'package:xomiton/features/alerts/presentation/sos_page.dart';
import 'package:xomiton/features/auth/application/session_controller.dart';
import 'package:xomiton/features/security/application/pin_lock.dart';

import '../features/pair_collect_test.dart' show draftAtReview;
import '../support/admin_harness.dart';
import '../support/fakes.dart';
import '../support/harness.dart';
import '../support/pair_harness.dart';
import '../support/sim_server.dart';

/// Scénarios de bout en bout côté application (P15-21) : l'application réelle
/// (routeur, garde d'espace, code PIN, file de synchro, push) contre un serveur
/// simulé à état unique (`SimServer`, adaptateur Dio). Aucun réseau, aucune
/// interface native.
final S = loadStrings();

GoRouter routerOf(WidgetTester t) => GoRouter.of(t.element(find.byType(Scaffold).first));
/// Adresse affichée, écran empilé (push) compris.
String path(WidgetTester t) => routerOf(t).routerDelegate.state.matchedLocation;
ProviderContainer containerOf(WidgetTester t) => ProviderScope.containerOf(t.element(find.byType(Scaffold).first));

Future<void> tapPin(WidgetTester tester, String pin) async {
  for (final d in pin.split('')) {
    await tester.tap(find.byKey(Key('pin-key-$d')));
    await tester.pump();
  }
  await tester.pumpAndSettle();
}

/// Premier accès à l'espace Pair ou Admin : le code est obligatoire (deux saisies).
Future<void> choosePin(WidgetTester tester, [String pin = '4821']) async {
  await tester.tap(find.byKey(const ValueKey('pin-setup-choose')));
  await tester.pumpAndSettle();
  await tapPin(tester, pin);
  await tapPin(tester, pin);
}

Future<void> signIn(WidgetTester tester) async {
  await tester.enterText(find.widgetWithText(TextField, S.t('auth_login_identifier')), 'awa@exemple.bj');
  await tester.enterText(find.widgetWithText(TextField, S.t('auth_login_password')), 'Secret0Fort1');
  await tester.tap(find.text(S.t('auth_login_submit')).last);
  await tester.pumpAndSettle();
}

Future<TestEnv> boot(
  WidgetTester tester,
  SimServer sim, {
  String? token,
  Map<String, dynamic>? cachedUser,
  String? pin,
  bool online = true,
  Stream<bool>? net,
  Map<String, Object> prefs = const {},
  List<Override> extra = const [],
  void Function(TestEnv env)? before,
}) async {
  final env = TestEnv(
    api: sim.api(),
    seen: true,
    token: token,
    cachedUser: cachedUser,
    pin: pin,
    online: online,
    initialPrefs: {'discreet_default_applied': true, ...prefs},
    extra: [...simOverrides, if (net != null) connectivityStreamProvider.overrideWithValue(net), ...extra],
  );
  before?.call(env);
  usePhone(tester);
  addTearDown(tester.view.reset);
  await tester.pumpWidget(await env.app());
  await tester.pumpAndSettle();
  return env;
}

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('1. connexion unique : le serveur décide des espaces', () {
    testWidgets('membre : connexion, accueil Membre, aucune trace des autres espaces', (tester) async {
      final sim = SimServer(userJson());
      final env = await boot(tester, sim);
      await signIn(tester);
      expect(path(tester), '/home');
      expect(find.byKey(const ValueKey('space-pill')), findsNothing);
      expect(find.byKey(const ValueKey('tab-other-space')), findsNothing);
      expect(env.api.requests.where((r) => r.path.startsWith('pair/') || r.path.startsWith('admin/')), isEmpty);
    });

    testWidgets('pair + membre : sélecteur d’espace, code PIN au premier accès, espace Pair, retour au Membre', (tester) async {
      final sim = SimServer(peerUser());
      await boot(tester, sim);
      await signIn(tester);
      expect(path(tester), '/spaces');
      expect(find.byKey(const ValueKey('space-tile-admin')), findsNothing);
      await tester.tap(find.byKey(const ValueKey('space-tile-peer')));
      await tester.pumpAndSettle();
      expect(path(tester), '/pin-setup', reason: 'code obligatoire pour entrer en Pair');
      await choosePin(tester);
      expect(path(tester), '/pair/accueil');
      expect(containerOf(tester).read(activeSpaceProvider), AppSpace.peer);
      expect(find.byKey(const ValueKey('tab-peer-sos')), findsOneWidget, reason: 'SOS toujours à portée');
      // Commutateur d'en-tête : retour à l'espace Membre sans nouvelle connexion.
      await tester.tap(find.byKey(const ValueKey('space-pill')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('space-tile-member')));
      await tester.pumpAndSettle();
      expect(path(tester), '/home');
      expect(containerOf(tester).read(sessionProvider).authenticated, isTrue);
    });

    testWidgets('admin + membre : sélecteur, espace Admin, relancement au dernier espace sans repasser par le sélecteur', (tester) async {
      final sim = SimServer(adminUser());
      await boot(tester, sim);
      await signIn(tester);
      expect(path(tester), '/spaces');
      await tester.tap(find.byKey(const ValueKey('space-tile-admin')));
      await tester.pumpAndSettle();
      await choosePin(tester);
      expect(path(tester), '/admin/astreinte');
      expect(find.byKey(const ValueKey('tab-admin-manage')), findsOneWidget);
      final prefs = containerOf(tester).read(sharedPreferencesProvider);
      expect(prefs.getString('active_space'), 'admin');
      // Relancement : l'espace mémorisé revient, après le code, sans sélecteur.
      await disposeApp(tester);
      final again = await boot(tester, sim, token: '1|abc', cachedUser: adminUser(), pin: '4821', prefs: {'active_space': 'admin'});
      await tapPin(tester, '4821');
      expect(path(tester), '/admin/astreinte');
      expect(again.api.calls('POST auth/login'), isEmpty);
    });
  });

  group('2. retrait d’un droit en cours de session', () {
    testWidgets('le rôle pair est retiré : la prochaine action est refusée (403), l’espace se ferme, l’accès direct est refusé', (tester) async {
      final sim = SimServer(peerUser());
      await boot(
        tester,
        sim,
        token: 'tok',
        cachedUser: peerUser(),
        pin: kPin,
        prefs: {'active_space': 'peer'},
      );
      await tapPin(tester, kPin);
      routerOf(tester).go('/pair/alertes');
      await tester.pumpAndSettle();
      expect(path(tester), '/pair/alertes');
      expect(find.byKey(const ValueKey('ack-a1')), findsOneWidget);

      // L'admin retire le rôle pair pendant que la personne est dans l'application.
      sim
        ..peerAccess = false
        ..account = userJson();
      await tester.tap(find.byKey(const ValueKey('ack-a1')));
      await tester.pumpAndSettle();
      expect(sim.alerts['a1']!.ackBy, isEmpty, reason: 'une action refusée ne produit aucun effet');
      expect(path(tester), '/home');
      expect(find.text(S.t('mobile_space_unavailable')), findsOneWidget);
      expect(containerOf(tester).read(spacesProvider), [AppSpace.member]);
      expect(find.byKey(const ValueKey('space-pill')), findsNothing);

      routerOf(tester).go('/pair/alertes');
      await tester.pumpAndSettle();
      expect(path(tester), '/home', reason: 'le routeur ne rouvre plus l’espace');
    });
  });

  group('3. collecte hors ligne, synchronisation, conflit 409 tranché par le pair', () {
    testWidgets('statut changé sans réseau → file → conflit au retour du réseau → le pair choisit son appareil', (tester) async {
      final sim = SimServer(peerUser());
      final net = StreamController<bool>()..add(false);
      addTearDown(net.close);
      final env = await boot(
        tester,
        sim,
        token: 'tok',
        cachedUser: peerUser(),
        pin: kPin,
        net: net.stream,
        prefs: {'active_space': 'peer', 'device_uuid': 'dev-1'},
      );
      await tapPin(tester, kPin);

      // Sans réseau : une fiche collectée et un changement de statut attendent sur l'appareil.
      draftAtReview(env);
      routerOf(tester).go('/pair/dossiers/collecte');
      await tester.pumpAndSettle();
      sim.down = true;
      await tester.ensureVisible(find.byKey(const ValueKey('col-submit')));
      await tester.tap(find.byKey(const ValueKey('col-submit')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_pair_col_queued_title')), findsOneWidget);
      expect(sim.pushed, isEmpty);

      sim.down = false;
      routerOf(tester).go('/pair/dossiers/VBG-0042');
      await tester.pumpAndSettle();
      sim.down = true;
      await revealKey(tester, 'case-status');
      await tester.tap(find.byKey(const ValueKey('case-status')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Clos').last);
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('case-status-submit')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_pair_followup_queued')), findsOneWidget);
      final queue = containerOf(tester).read(syncQueueProvider);
      expect(queue.open(), hasLength(2));

      // Pendant ce temps, quelqu'un d'autre a changé le même statut côté serveur : conflit 409.
      sim
        ..down = false
        ..caseStatus = 'en_cours'
        ..conflicts.add({
          'id': 42,
          'subject': {'type': 'collecte', 'server_id': 1, 'reference': 'VBG-0042'},
          'field': 'status',
          'status': 'a_resoudre',
          'server_value': 'en_cours',
          'device_value': 'clos',
          'created_at': '2026-10-29T08:00:00Z',
        })
        ..pushResult = (i) {
          if (i['op'] == 'update') {
            return {
              'uuid': i['uuid'],
              'result': 'conflict',
              'status': 409,
              'code': 'conflict',
              'conflicts': [
                {'field': 'status', 'conflict_id': 42},
              ],
            };
          }
          return {'uuid': i['uuid'], 'result': 'created', 'status': 201, 'reference': 'VBG-0123'};
        };
      net.add(true); // le réseau revient : la boîte d'envoi et la synchro repartent
      await settle(tester, 10);
      await tester.runAsync(() => containerOf(tester).read(syncEngineProvider).sync(force: true));
      final created = queue.list().firstWhere((e) => e.kind == QueueKind.caseCreate);
      expect(created.status, QueueStatus.sent);
      expect(created.resultCode, 'VBG-0123', reason: 'code attribué par le serveur au rejeu');
      final update = queue.list().firstWhere((e) => e.kind == QueueKind.caseUpdate);
      expect(update.status, QueueStatus.conflict);
      expect(sim.pushed.where((i) => i['op'] == 'create'), hasLength(1), reason: 'jamais de doublon');

      // Le pair tranche lui-même, depuis la file de synchronisation.
      routerOf(tester).go('/pair/accueil/synchro');
      await tester.pumpAndSettle();
      await revealKey(tester, 'resolve-42');
      await tester.tap(find.byKey(const ValueKey('resolve-42')));
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(find.byKey(const ValueKey('option-appareil')), 250, scrollable: find.byType(Scrollable).first);
      await tester.ensureVisible(find.byKey(const ValueKey('option-appareil')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('option-appareil')));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byKey(const ValueKey('conflict-confirm')));
      await tester.tap(find.byKey(const ValueKey('conflict-confirm')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('conflict-yes')));
      await tester.pumpAndSettle();
      expect(sim.resolutions.single['resolution'], 'appareil');
      expect(sim.conflicts, isEmpty);
      expect(find.text(S.t('mobile_conflict_done_title')), findsOneWidget);
      expect(queue.find(update.uuid)!.resolution, 'appareil');
      expect(queue.find(update.uuid)!.status, QueueStatus.sent);
    });
  });

  group('4. accusé d’une alerte : un pair, puis un admin', () {
    testWidgets('le pair accuse réception, l’admin voit l’escalade arrêtée puis accuse à son tour', (tester) async {
      final sim = SimServer(peerUser());
      // Côté pair.
      await boot(tester, sim, token: 'tok', cachedUser: peerUser(), pin: kPin, prefs: {'active_space': 'peer'});
      await tapPin(tester, kPin);
      routerOf(tester).go('/pair/alertes');
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('ack-a1')));
      await tester.pumpAndSettle();
      expect(sim.alerts['a1']!.ackBy, {'pair'});
      expect(find.byKey(const ValueKey('ack-a1')), findsNothing);
      await disposeApp(tester);

      // Côté admin (autre compte, même serveur).
      sim.account = adminUser();
      final env = await boot(tester, sim, token: 'tok2', cachedUser: adminUser(), pin: kAdminPin, prefs: {'active_space': 'admin'});
      await tapPin(tester, kAdminPin);
      routerOf(tester).go('/admin/alertes/a1');
      await tester.pumpAndSettle();
      expect(env.api.calls('GET admin/alerts/a1'), isNotEmpty);
      await revealKey(tester, 'admin-act-ack');
      await tester.tap(find.byKey(const ValueKey('admin-act-ack')));
      await tester.pumpAndSettle();
      final req = env.api.calls('POST admin/alerts/a1/acknowledge').single;
      expect(req.headers['Idempotency-Key'], isNotEmpty);
      expect(sim.alerts['a1']!.ackBy, {'pair', 'admin'});
    });
  });

  group('5. push : ouverture profonde seulement après le code PIN', () {
    testWidgets('alert_routed touché pendant le verrouillage : rien ne s’ouvre avant le code, puis l’alerte de l’espace Pair', (tester) async {
      final sim = SimServer(peerUser());
      final push = FakePush();
      await boot(
        tester,
        sim,
        token: 'tok',
        cachedUser: peerUser(),
        pin: kPin,
        prefs: {'active_space': 'member'},
        extra: [pushServiceProvider.overrideWithValue(push)],
      );
      expect(find.byKey(const Key('pin-key-1')), findsOneWidget, reason: 'application verrouillée');
      push.opened.add(const PushPayload('alert_routed', 'a1'));
      await settle(tester, 6);
      expect(find.byKey(const Key('pin-key-1')), findsOneWidget);
      expect(sim.alerts['a1']!.ackBy, isEmpty);
      await tapPin(tester, kPin);
      await settle(tester, 10);
      expect(path(tester), '/pair/alertes/a1');
      expect(containerOf(tester).read(activeSpaceProvider), AppSpace.peer);
    });

    testWidgets('même push pour un compte qui n’a plus l’espace : accueil Membre, aucune donnée de l’alerte demandée', (tester) async {
      final sim = SimServer(userJson());
      final push = FakePush();
      final env = await boot(tester, sim, token: 'tok', cachedUser: userJson(), extra: [pushServiceProvider.overrideWithValue(push)]);
      push.opened.add(const PushPayload('alert_routed', 'a1'));
      await settle(tester, 8);
      expect(path(tester), '/home');
      expect(env.api.calls('GET pair/alerts/a1'), isEmpty);
    });
  });

  group('6. effacement à distance et expiration de 14 jours', () {
    testWidgets('ordre d’effacement annoncé par le signal de vie : accusé, coffre du pair vidé, session fermée, retour à la connexion', (tester) async {
      final sim = SimServer(peerUser())..wipeRequested = true;
      final env = await boot(
        tester,
        sim,
        token: 'tok',
        cachedUser: peerUser(),
        pin: kPin,
        prefs: {'active_space': 'peer', 'device_uuid': 'dev-1'},
        before: (e) => e.db.peerData.put('c:cases:1', jsonEncode({'id': 1, 'code': 'VBG-0001'})),
      );
      await settle(tester, 10);
      expect(sim.wiped, ['dev-1'], reason: 'l’accusé part avant la purge');
      expect(env.db.peerData.keys, isEmpty);
      expect(containerOf(tester).read(sessionProvider).authenticated, isFalse);
      expect(path(tester), '/login');
      expect(find.text(S.t('mobile_device_wiped')), findsOneWidget);
    });

    testWidgets('plus de 14 jours sans contact : données du pair effacées, session fermée, message ; 13 jours : rien', (tester) async {
      var now = DateTime.now();
      final sim = SimServer(peerUser());
      final env = await boot(
        tester,
        sim,
        token: 'tok',
        cachedUser: peerUser(),
        pin: kPin,
        prefs: {'active_space': 'peer'},
        extra: [clockProvider.overrideWithValue(() => now)],
        before: (e) => e.db.peerData.put('c:cases:1', '{}'),
      );
      await tapPin(tester, kPin);
      final guard = containerOf(tester).read(offlineExpiryGuardProvider);
      now = now.add(const Duration(days: 13));
      expect(await guard.check(), isFalse);
      expect(env.db.peerData.keys, isNotEmpty);
      now = DateTime.now().add(const Duration(days: 15));
      final purged = guard.check();
      await tester.pumpAndSettle(const Duration(seconds: 1));
      expect(await purged, isTrue);
      expect(env.db.peerData.keys, isEmpty);
      expect(containerOf(tester).read(sessionProvider).authenticated, isFalse);
      expect(find.text(S.t('mobile_offline_expired', {'days': 14})), findsOneWidget);
    });
  });

  group('7. maintenance (RG-14)', () {
    testWidgets('membre : écran de maintenance, SOS à portée, réouverture détectée ; l’équipe garde l’accès', (tester) async {
      final sim = SimServer(userJson())..maintenance = true;
      final env = await boot(tester, sim, token: 'tok', cachedUser: userJson(), extra: [maintenancePollIntervalProvider.overrideWithValue(null)]);
      await settle(tester, 6);
      expect(find.byKey(const Key('maintenance-title')), findsOneWidget);
      expect(find.text('Mise à jour en cours.'), findsOneWidget);
      expect(find.byKey(const Key('maintenance-sos')), findsOneWidget);
      expect(containerOf(tester).read(sessionProvider).authenticated, isTrue, reason: 'la maintenance ne révoque jamais le jeton');

      // Le SOS reste ouvert pendant la maintenance.
      await tester.tap(find.byKey(const Key('maintenance-sos')));
      await settle(tester, 8);
      expect(find.byType(SosPage), findsOneWidget);
      expect(find.byKey(const Key('maintenance-title')), findsNothing);
      routerOf(tester).go('/maintenance');
      await tester.pumpAndSettle();

      // Réouverture : le sondage de GET status la voit, l'écran se retire seul.
      sim.maintenance = false;
      await tester.tap(find.byKey(const Key('maintenance-retry')));
      await settle(tester, 8);
      expect(find.byKey(const Key('maintenance-title')), findsNothing);
      expect(env.api.calls('GET status'), isNotEmpty);
      expect(path(tester), '/home');
    });

    testWidgets('admin + membre : la maintenance ne l’empêche ni de se connecter ni de travailler', (tester) async {
      final sim = SimServer(adminUser())..maintenance = true;
      await boot(tester, sim, extra: [maintenancePollIntervalProvider.overrideWithValue(null)]);
      await signIn(tester);
      expect(path(tester), '/spaces');
      await tester.tap(find.byKey(const ValueKey('space-tile-admin')));
      await tester.pumpAndSettle();
      await choosePin(tester);
      expect(path(tester), '/admin/astreinte');
      expect(find.byKey(const Key('maintenance-title')), findsNothing);
    });

    testWidgets('un membre ne peut pas se connecter pendant la maintenance : refus, connexion et SOS restent ouverts', (tester) async {
      final sim = SimServer(userJson())..maintenance = true;
      final env = await boot(tester, sim, extra: [maintenancePollIntervalProvider.overrideWithValue(null)]);
      await signIn(tester);
      await settle(tester, 6);
      expect(containerOf(tester).read(maintenanceProvider).active, isTrue);
      expect(containerOf(tester).read(sessionProvider).authenticated, isFalse);
      expect(path(tester), '/login', reason: 'la connexion reste ouverte (l’équipe doit pouvoir entrer)');
      expect(find.text(S.t('errors_api_maintenance')), findsOneWidget);
      expect(env.api.calls('POST auth/login'), hasLength(1));
    });
  });
}
