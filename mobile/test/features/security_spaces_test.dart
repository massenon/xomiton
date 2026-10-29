import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/app/router.dart';
import 'package:xomiton/core/auth_spaces/app_space.dart';
import 'package:xomiton/core/auth_spaces/space_providers.dart';
import 'package:xomiton/core/platform/discreet_icon.dart';
import 'package:xomiton/core/providers/core_providers.dart';
import 'package:xomiton/core/security/offline_expiry.dart';
import 'package:xomiton/core/security/screen_security.dart';
import 'package:xomiton/core/storage/secret_store.dart';
import 'package:xomiton/features/auth/application/session_controller.dart';
import 'package:xomiton/features/security/application/pin_lock.dart';
import 'package:xomiton/features/security/presentation/pin_widgets.dart' show isPinFreeLocation;
import 'package:shared_preferences/shared_preferences.dart';

import '../support/harness.dart';

final S = loadStrings();
const _pin = '4821';

Map<String, dynamic> member() => userJson(roles: ['membre'], spaces: ['member']);
Map<String, dynamic> peer() =>
    userJson(role: 'pair', roles: ['membre', 'pair'], spaces: ['member', 'peer'], permissions: ['peer_space.access']);
Map<String, dynamic> admin() =>
    userJson(role: 'admin', roles: ['membre', 'admin'], spaces: ['member', 'admin'], permissions: ['admin_mobile.access']);

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
      'GET me/security': (_) => jsonBody(200, {'data': {'discreet': false, 'mask_previews': false, 'pin_enabled': false, 'neutral_tab_title': false}}),
      'PATCH me/security': (_) => jsonBody(200, {'data': {}}),
      ...extra,
    };

Future<void> pump(WidgetTester tester, TestEnv env) async {
  usePhone(tester);
  addTearDown(tester.view.reset);
  await tester.pumpWidget(await env.app());
  await tester.pumpAndSettle();
}

Future<void> tapPin(WidgetTester tester, String pin) async {
  for (final d in pin.split('')) {
    await tester.tap(find.byKey(Key('pin-key-$d')));
    await tester.pump();
  }
  await tester.pumpAndSettle();
}

GoRouter routerOf(WidgetTester tester) => GoRouter.of(tester.element(find.byType(Scaffold).first));
String path(WidgetTester tester) => routerOf(tester).routerDelegate.currentConfiguration.uri.toString();
ProviderContainer containerOf(WidgetTester tester) => ProviderScope.containerOf(tester.element(find.byType(Scaffold).first));

class FakeScreenSecurity implements ScreenSecurityService {
  final List<bool> calls = [];
  @override
  Future<void> setSecure(bool secure) async => calls.add(secure);
}

class FakeDiscreetIcon implements DiscreetIconService {
  final List<bool> applied = [];
  @override
  bool get supported => true;
  @override
  Future<void> apply(bool discreet) async => applied.add(discreet);
}

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('appRedirect : code obligatoire pour entrer en Pair ou Admin', () {
    String? r(String path, {bool needed = true, Set<AppSpace> spaces = const {AppSpace.member, AppSpace.peer, AppSpace.admin}}) =>
        appRedirect(authenticated: true, onboardingSeen: true, uri: Uri.parse(path), spaces: spaces, pinSetupNeeded: needed);

    test('sans code : /pair/** et /admin/** passent d’abord par le choix du code, avec retour', () {
      expect(r('/pair/dossiers'), '/pin-setup?from=%2Fpair%2Fdossiers');
      expect(r('/admin/astreinte'), '/pin-setup?from=%2Fadmin%2Fastreinte');
    });

    test('Membre, SOS, urgences, signalement : jamais bloqués par l’absence de code', () {
      for (final p in ['/home', '/feed', '/profile', '/securite', '/urgences', '/signaler', '/alerte-sos', '/sos']) {
        expect(r(p), p == '/alerte-sos' ? '/sos' : null, reason: p);
      }
    });

    test('code choisi : retour à l’écran demandé, sinon on reste', () {
      expect(r('/pin-setup?from=%2Fpair%2Faccueil', needed: false), '/pair/accueil');
      expect(r('/pin-setup', needed: false), isNull);
      expect(r('/pair/accueil', needed: false), isNull);
    });

    test('un espace refusé reste refusé avant toute question de code', () {
      expect(r('/admin/astreinte', spaces: const {AppSpace.member}), '/home');
    });
  });

  group('choix du code (premier accès)', () {
    testWidgets('lien profond vers l’espace Admin sans code : écran du code, puis l’espace demandé', (tester) async {
      await pump(tester, TestEnv(api: FakeApi(routes(admin)), seen: true, token: 'tok', cachedUser: admin()));
      routerOf(tester).go('/admin/gerer');
      await tester.pumpAndSettle();
      expect(path(tester), startsWith('/pin-setup'));
      expect(find.text(S.t('mobile_pin_required_title')), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('pin-setup-choose')));
      await tester.pumpAndSettle();
      await tapPin(tester, _pin);
      await tapPin(tester, _pin);
      expect(path(tester), '/admin/gerer');
      expect(containerOf(tester).read(pinLockProvider).configured, isTrue);
    });

    testWidgets('« Retourner à l’espace Membre » : aucun code, aucun accès à l’espace', (tester) async {
      await pump(tester, TestEnv(api: FakeApi(routes(peer)), seen: true, token: 'tok', cachedUser: peer()));
      routerOf(tester).go('/pair/accueil');
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('pin-setup-member')));
      await tester.pumpAndSettle();
      expect(path(tester), '/home');
      expect(containerOf(tester).read(pinLockProvider).configured, isFalse);
    });

    testWidgets('l’adresse de retour ne sort jamais de l’application', (tester) async {
      await pump(tester, TestEnv(api: FakeApi(routes(peer)), seen: true, token: 'tok', cachedUser: peer()));
      routerOf(tester).go('/pin-setup?from=https%3A%2F%2Fevil.example');
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('pin-setup-choose')));
      await tester.pumpAndSettle();
      await tapPin(tester, _pin);
      await tapPin(tester, _pin);
      expect(path(tester), '/home');
    });

    testWidgets('compte pair ou admin : le code ne peut pas être désactivé dans Sécurité', (tester) async {
      final env = TestEnv(api: FakeApi(routes(peer, extra: {'GET me/security': (_) => jsonBody(200, {'data': {'discreet': false, 'mask_previews': false, 'pin_enabled': true, 'neutral_tab_title': false}})})), seen: true, token: 'tok', cachedUser: peer(), pin: _pin);
      await pump(tester, env);
      await tapPin(tester, _pin);
      routerOf(tester).go('/securite');
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('switch-pin')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('mobile_pin_required_cannot_disable')), findsOneWidget);
      expect(env.secrets.data.containsKey(SecretKeys.pinRecord), isTrue);
    });
  });

  group('verrou : code demandé à l’ouverture, délai par espace', () {
    testWidgets('démarrage dans l’espace Pair : verrou, contenu inaccessible, SOS sans code', (tester) async {
      final env = TestEnv(api: FakeApi(routes(peer)), seen: true, token: 'tok', cachedUser: peer(), pin: _pin, initialPrefs: {'active_space': 'peer'});
      await pump(tester, env);
      expect(path(tester), '/pair/accueil');
      expect(find.byKey(const Key('pin-key-1')), findsOneWidget, reason: 'écran de code affiché');
      // L'onglet SOS de l'espace Pair reste accessible sans code (RG-01).
      routerOf(tester).go('/pair/sos');
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('pin-key-1')), findsNothing);
      expect(isPinFreeLocation('/pair/sos'), isTrue);
    });

    test('délais : Membre 60 s, Pair 60 s, Admin 30 s', () {
      final c = ProviderContainer();
      addTearDown(c.dispose);
      expect(c.read(pinLockDelayProvider), const Duration(seconds: 60));
      expect(c.read(pinLockDelayPeerProvider), const Duration(seconds: 60));
      expect(c.read(pinLockDelayAdminProvider), const Duration(seconds: 30));
      expect(spaceRequiresPin(AppSpace.peer), isTrue);
      expect(spaceRequiresPin(AppSpace.admin), isTrue);
      expect(spaceRequiresPin(AppSpace.member), isFalse);
      expect(spaceRequiresPin(null), isFalse);
    });

    testWidgets('retour au premier plan : verrou après 31 s en Admin, pas après 45 s en Pair', (tester) async {
      var now = DateTime(2026, 10, 29, 10);
      final env = TestEnv(api: FakeApi(routes(admin)), seen: true, token: 'tok', cachedUser: admin(), pin: _pin, initialPrefs: {'active_space': 'admin'}, extra: [clockProvider.overrideWithValue(() => now)]);
      await pump(tester, env);
      await tapPin(tester, _pin);
      final c = containerOf(tester);
      expect(c.read(activeSpaceProvider), AppSpace.admin);
      c.read(pinLockProvider.notifier).onBackground();
      now = now.add(const Duration(seconds: 20));
      c.read(pinLockProvider.notifier).onForeground();
      expect(c.read(pinLockProvider).locked, isFalse);
      c.read(pinLockProvider.notifier).onBackground();
      now = now.add(const Duration(seconds: 31));
      c.read(pinLockProvider.notifier).onForeground();
      expect(c.read(pinLockProvider).locked, isTrue, reason: 'Admin : 30 s');
    });

    testWidgets('Pair : 45 s d’absence ne verrouillent pas, 61 s oui', (tester) async {
      var now = DateTime(2026, 10, 29, 10);
      final env = TestEnv(api: FakeApi(routes(peer)), seen: true, token: 'tok', cachedUser: peer(), pin: _pin, initialPrefs: {'active_space': 'peer'}, extra: [clockProvider.overrideWithValue(() => now)]);
      await pump(tester, env);
      await tapPin(tester, _pin);
      final c = containerOf(tester);
      c.read(pinLockProvider.notifier).onBackground();
      now = now.add(const Duration(seconds: 45));
      c.read(pinLockProvider.notifier).onForeground();
      expect(c.read(pinLockProvider).locked, isFalse);
      c.read(pinLockProvider.notifier).onBackground();
      now = now.add(const Duration(seconds: 61));
      c.read(pinLockProvider.notifier).onForeground();
      expect(c.read(pinLockProvider).locked, isTrue);
    });

    testWidgets('5 codes faux dans l’espace Pair : session fermée, données Pair effacées', (tester) async {
      final env = TestEnv(api: FakeApi(routes(peer)), seen: true, token: 'tok', cachedUser: peer(), pin: _pin, initialPrefs: {'active_space': 'peer'});
      await env.db.peerData.put('case:VBG-0001', '{"secret":"x"}');
      await pump(tester, env);
      for (var i = 0; i < 5; i++) {
        await tapPin(tester, '0000');
      }
      await tester.pumpAndSettle(const Duration(seconds: 1));
      expect(env.db.peerData.keys, isEmpty);
      expect(env.db.session.get('user'), isNull);
      expect(env.secrets.data.containsKey(SecretKeys.token), isFalse);
      expect(env.secrets.data.containsKey(SecretKeys.pinRecord), isFalse);
    });
  });

  group('écrans sensibles : capture interdite (FLAG_SECURE)', () {
    test('quels écrans sont sensibles', () {
      for (final p in ['/pair/accueil', '/pair/dossiers/VBG-0001', '/admin/astreinte', '/admin/alertes/01J', '/messages', '/messages/12', '/signaler', '/signalements', '/suivi', '/sos', '/alerte-sos', '/securite', '/appareils', '/pin-setup']) {
        expect(isSecureLocation(p), isTrue, reason: p);
      }
      for (final p in ['/home', '/feed', '/feed/12', '/thematiques', '/ressources', '/profile', '/mon-profil', '/spaces', '/login', '/groupes', '/evenements', '/pairs-de-confiance']) {
        expect(isSecureLocation(p), isFalse, reason: p);
      }
    });

    test('coordination : posé tant qu’un écran sensible reste, retiré avec le dernier', () {
      final svc = FakeScreenSecurity();
      final c = SecureScreenCoordinator(svc, allowScreenshots: false);
      final a = Object(), b = Object();
      c.acquire(a);
      c.acquire(b);
      c.release(a);
      expect(c.active, isTrue);
      c.release(b);
      expect(c.active, isFalse);
      expect(svc.calls, [true, false], reason: 'un seul appel à chaque changement d’état');
    });

    test('build de recette : captures permises hors release, jamais en release', () {
      expect(screenshotsAllowed(define: true, release: false), isTrue);
      expect(screenshotsAllowed(define: true, release: true), isFalse);
      expect(screenshotsAllowed(define: false, release: false), isFalse);
      expect(screenshotsAllowed(define: false, release: true), isFalse);
      final svc = FakeScreenSecurity();
      final c = SecureScreenCoordinator(svc, allowScreenshots: true)..acquire(Object());
      expect(c.active, isFalse);
      expect(svc.calls, [false]);
    });

    testWidgets('le drapeau suit l’adresse : Pair oui, fil non, messages oui, retour au fil non', (tester) async {
      final svc = FakeScreenSecurity();
      final env = TestEnv(api: FakeApi(routes(peer)), seen: true, token: 'tok', cachedUser: peer(), pin: _pin, extra: [screenSecurityProvider.overrideWithValue(svc)]);
      await pump(tester, env);
      await tapPin(tester, _pin);
      final c = containerOf(tester);
      expect(c.read(secureScreenProvider).active, isFalse, reason: 'accueil Membre : capturable');
      routerOf(tester).go('/feed');
      await tester.pumpAndSettle();
      expect(c.read(secureScreenProvider).active, isFalse, reason: 'le fil reste capturable');
      routerOf(tester).go('/messages');
      await tester.pumpAndSettle();
      expect(c.read(secureScreenProvider).active, isTrue);
      routerOf(tester).go('/pair/accueil');
      await tester.pumpAndSettle();
      expect(c.read(secureScreenProvider).active, isTrue);
      routerOf(tester).go('/feed');
      await tester.pumpAndSettle();
      expect(c.read(secureScreenProvider).active, isFalse);
      expect(svc.calls, [false, true, false], reason: 'au démarrage le drapeau est retiré, puis posé et retiré avec l’écran sensible');
    });

    testWidgets('widget SecureScreen : posé à l’affichage, retiré à la sortie', (tester) async {
      final svc = FakeScreenSecurity();
      await tester.pumpWidget(ProviderScope(
        overrides: [screenSecurityProvider.overrideWithValue(svc), screenshotsAllowedProvider.overrideWithValue(false)],
        child: const MaterialApp(home: SecureScreen(child: Text('sensible'))),
      ));
      expect(svc.calls, [true]);
      await tester.pumpWidget(ProviderScope(
        overrides: [screenSecurityProvider.overrideWithValue(svc), screenshotsAllowedProvider.overrideWithValue(false)],
        child: const MaterialApp(home: Text('ailleurs')),
      ));
      expect(svc.calls, [true, false]);
    });

    testWidgets('en arrière-plan sur un écran sensible : recouvrement opaque (aperçu du sélecteur d’applications)', (tester) async {
      final env = TestEnv(api: FakeApi(routes(peer)), seen: true, token: 'tok', cachedUser: peer(), pin: _pin, initialPrefs: {'active_space': 'peer'});
      await pump(tester, env);
      await tapPin(tester, _pin);
      expect(find.byKey(const ValueKey('privacy-cover')), findsNothing);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
      await tester.pump();
      expect(find.byKey(const ValueKey('privacy-cover')), findsOneWidget);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
      await tester.pump();
      expect(find.byKey(const ValueKey('privacy-cover')), findsNothing);
      // Écran non sensible : pas de recouvrement.
      routerOf(tester).go('/feed');
      await tester.pumpAndSettle();
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.inactive);
      await tester.pump();
      expect(find.byKey(const ValueKey('privacy-cover')), findsNothing);
      tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    });
  });

  group('appareil révoqué et effacement', () {
    testWidgets('403 device_revoked : purge totale, message, retour à la connexion', (tester) async {
      final api = FakeApi({
        ...routes(peer),
        'GET pair/alerts': (_) => jsonBody(403, {'message': 'Appareil révoqué', 'code': 'device_revoked'}),
      });
      final env = TestEnv(api: api, seen: true, token: 'tok', cachedUser: peer());
      await env.db.peerData.put('case:1', '{}');
      await env.db.privateCache.put('k', 'v');
      await pump(tester, env);
      final failed = expectLater(containerOf(tester).read(dioProvider).get<dynamic>('pair/alerts'), throwsA(anything));
      await tester.pumpAndSettle();
      await failed;
      await tester.pumpAndSettle(const Duration(seconds: 1));
      expect(env.db.peerData.keys, isEmpty);
      expect(env.db.privateCache.keys, isEmpty);
      expect(env.db.session.get('user'), isNull);
      expect(env.secrets.data.containsKey(SecretKeys.token), isFalse);
      expect(find.text(S.t('auth_login_heading')), findsOneWidget);
      expect(find.text(S.t('mobile_device_revoked')), findsOneWidget);
    });

    testWidgets('device_revoked sur l’enregistrement de l’appareil : pas de purge (nouvel identifiant généré)', (tester) async {
      final api = FakeApi({
        ...routes(peer),
        'POST me/devices': (_) => jsonBody(403, {'message': 'Appareil révoqué', 'code': 'device_revoked'}),
      });
      final env = TestEnv(api: api, seen: true, token: 'tok', cachedUser: peer());
      await pump(tester, env);
      final failed = expectLater(containerOf(tester).read(dioProvider).post<dynamic>('me/devices', data: {}), throwsA(anything));
      await tester.pumpAndSettle();
      await failed;
      expect(containerOf(tester).read(sessionProvider).authenticated, isTrue);
    });

    testWidgets('une autre erreur 403 ne purge rien', (tester) async {
      final api = FakeApi({
        ...routes(peer),
        'GET pair/alerts': (_) => jsonBody(403, {'message': 'Refusé', 'code': 'forbidden'}),
      });
      final env = TestEnv(api: api, seen: true, token: 'tok', cachedUser: peer());
      await env.db.peerData.put('case:1', '{}');
      await pump(tester, env);
      final failed = expectLater(containerOf(tester).read(dioProvider).get<dynamic>('pair/alerts'), throwsA(anything));
      await tester.pumpAndSettle();
      await failed;
      expect(env.db.peerData.keys, isNotEmpty);
    });

    testWidgets('déconnexion : données Pair effacées avec le reste', (tester) async {
      final env = TestEnv(api: FakeApi(routes(peer)), seen: true, token: 'tok', cachedUser: peer());
      await env.db.peerData.put('case:1', '{}');
      await pump(tester, env);
      await containerOf(tester).read(sessionProvider.notifier).expire();
      await tester.pumpAndSettle();
      expect(env.db.peerData.keys, isEmpty);
    });
  });

  group('expiration hors contact (D-MOB-7)', () {
    test('ContactClock : 14 jours, horloge reculée, date du serveur', () async {
      SharedPreferences.setMockInitialValues({});
      final prefs = await SharedPreferences.getInstance();
      var now = DateTime.utc(2026, 10, 29, 10);
      final clock = ContactClock(prefs, () => now);
      expect(clock.expired(14), isFalse, reason: 'aucun contact noté : horloge non démarrée');
      clock.record(DateTime.utc(2026, 10, 29, 10, 0, 5));
      now = now.add(const Duration(days: 13, hours: 23));
      expect(clock.expired(14), isFalse);
      now = now.add(const Duration(hours: 2));
      expect(clock.expired(14), isTrue);
      // Horloge reculée sous le dernier contact : jamais une durée « prolongée ».
      now = DateTime.utc(2026, 10, 20);
      expect(clock.expired(14), isTrue);
      // Petit décalage d'horloge (moins de 6 h) toléré.
      now = DateTime.utc(2026, 10, 29, 7);
      expect(clock.expired(14), isFalse);
      clock.reset();
      expect(clock.expired(14), isFalse);
    });

    test('en-tête Date du serveur lu, valeur illisible ignorée', () {
      expect(parseServerDate('Thu, 29 Oct 2026 10:00:00 GMT'), DateTime.utc(2026, 10, 29, 10));
      expect(parseServerDate('pas une date'), isNull);
      expect(parseServerDate(null), isNull);
    });

    testWidgets('compte pair, 15 jours sans contact : données Pair effacées, session fermée, message', (tester) async {
      var now = DateTime.now();
      final env = TestEnv(api: FakeApi(routes(peer)), seen: true, token: 'tok', cachedUser: peer(), extra: [clockProvider.overrideWithValue(() => now)]);
      await env.db.peerData.put('case:1', '{}');
      await pump(tester, env);
      final c = containerOf(tester);
      expect(await c.read(offlineExpiryGuardProvider).check(), isFalse, reason: 'contact récent');
      now = now.add(const Duration(days: 15));
      final purged = c.read(offlineExpiryGuardProvider).check();
      await tester.pumpAndSettle(const Duration(seconds: 1));
      expect(await purged, isTrue);
      expect(env.db.peerData.keys, isEmpty);
      expect(c.read(sessionProvider).authenticated, isFalse);
      expect(find.text(S.t('mobile_offline_expired', {'days': 14})), findsOneWidget);
    });

    testWidgets('membre simple ou admin : aucune expiration (rien n’est conservé hors ligne)', (tester) async {
      var now = DateTime.now();
      final env = TestEnv(api: FakeApi(routes(admin)), seen: true, token: 'tok', cachedUser: admin(), extra: [clockProvider.overrideWithValue(() => now)]);
      await pump(tester, env);
      final c = containerOf(tester);
      c.read(contactClockProvider).record();
      now = now.add(const Duration(days: 60));
      expect(await c.read(offlineExpiryGuardProvider).check(), isFalse);
      expect(c.read(sessionProvider).authenticated, isTrue);
    });

    testWidgets('chaque réponse du serveur renouvelle le contact (date de la réponse mémorisée)', (tester) async {
      final api = FakeApi({
        ...routes(peer),
        'GET ping': (_) => jsonBody(200, {'data': 1}, headers: {'date': 'Thu, 29 Oct 2026 10:00:00 GMT'}),
      });
      final env = TestEnv(api: api, seen: true, token: 'tok', cachedUser: peer());
      await pump(tester, env);
      final c = containerOf(tester);
      c.read(contactClockProvider).reset();
      unawaited(c.read(dioProvider).get<dynamic>('ping'));
      await tester.pumpAndSettle();
      expect(c.read(contactClockProvider).lastServer, DateTime.utc(2026, 10, 29, 10));
      expect(c.read(contactClockProvider).lastLocal, isNotNull);
    });
  });

  group('mode discret par défaut au premier accès (D-MOB-8)', () {
    testWidgets('premier accès à l’espace Pair : mode discret activé, icône changée, message, plus jamais redemandé', (tester) async {
      final icon = FakeDiscreetIcon();
      var discreet = false;
      final api = FakeApi({
        ...routes(peer),
        'GET me/security': (_) => jsonBody(200, {'data': {'discreet': discreet, 'mask_previews': discreet, 'pin_enabled': true, 'neutral_tab_title': false}}),
        'PATCH me/security': (o) {
          discreet = (o.data as Map)['discreet'] == true;
          return jsonBody(200, {'data': {}});
        },
      });
      final env = TestEnv(api: api, seen: true, token: 'tok', cachedUser: peer(), pin: _pin, extra: [discreetIconProvider.overrideWithValue(icon)]);
      await pump(tester, env);
      await tapPin(tester, _pin);
      routerOf(tester).go('/pair/accueil');
      await tester.pumpAndSettle();
      expect(api.calls('PATCH me/security').single.data, {'discreet': true});
      expect(icon.applied, [true]);
      expect(find.text(S.t('mobile_discreet_default_applied')), findsOneWidget);
      // Sorti puis revenu : jamais écrasé (la personne peut l'avoir désactivé).
      routerOf(tester).go('/home');
      await tester.pumpAndSettle();
      discreet = false;
      routerOf(tester).go('/pair/alertes');
      await tester.pumpAndSettle();
      expect(api.calls('PATCH me/security'), hasLength(1));
    });

    testWidgets('espace Membre : le mode discret n’est pas touché', (tester) async {
      final icon = FakeDiscreetIcon();
      final api = FakeApi(routes(peer));
      await pump(tester, TestEnv(api: api, seen: true, token: 'tok', cachedUser: peer(), extra: [discreetIconProvider.overrideWithValue(icon)]));
      expect(api.calls('PATCH me/security'), isEmpty);
      expect(icon.applied, isEmpty);
    });

    testWidgets('hors ligne : pas de blocage, nouvel essai à l’accès suivant', (tester) async {
      final icon = FakeDiscreetIcon();
      var fail = true;
      final api = FakeApi({
        ...routes(peer),
        'GET me/security': (_) => fail
            ? jsonBody(503, {'message': 'Indisponible', 'code': 'service_unavailable'})
            : jsonBody(200, {'data': {'discreet': false, 'mask_previews': false, 'pin_enabled': true, 'neutral_tab_title': false}}),
      });
      final env = TestEnv(api: api, seen: true, token: 'tok', cachedUser: peer(), pin: _pin, extra: [discreetIconProvider.overrideWithValue(icon)]);
      await pump(tester, env);
      await tapPin(tester, _pin);
      routerOf(tester).go('/pair/accueil');
      await tester.pumpAndSettle();
      expect(path(tester), '/pair/accueil');
      expect(icon.applied, isEmpty);
      fail = false;
      routerOf(tester).go('/home');
      await tester.pumpAndSettle();
      routerOf(tester).go('/pair/alertes');
      await tester.pumpAndSettle();
      expect(icon.applied, [true]);
    });
  });
}
