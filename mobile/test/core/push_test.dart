import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:xomiton/app/router.dart';
import 'package:xomiton/core/push/fcm_push_service.dart';
import 'package:xomiton/core/push/push_registrar.dart';
import 'package:xomiton/core/push/push_service.dart';
import 'package:xomiton/core/providers/core_providers.dart';
import 'package:xomiton/core/storage/secret_store.dart';

import '../support/fakes.dart';
import '../support/harness.dart';

const _device = DeviceDescriptor(label: 'Android', platform: 'Android', appVersion: '1.0.0');

Future<(PushRegistrar, FakeApi, FakePush, MemorySecretStore, SharedPreferences)> registrar(
  Map<String, FakeHandler> routes, {
  FakePush? push,
  Map<String, Object> prefs = const {},
  List<String>? uuids,
}) async {
  SharedPreferences.setMockInitialValues(prefs);
  final p = await SharedPreferences.getInstance();
  final api = FakeApi(routes);
  final dio = Dio(BaseOptions(baseUrl: 'https://api.test/api/v1/'))..httpClientAdapter = api;
  final secrets = MemorySecretStore();
  final fake = push ?? FakePush();
  final ids = List<String>.from(uuids ?? ['uuid-1', 'uuid-2']);
  final reg = PushRegistrar(
    dio: dio,
    prefs: p,
    secrets: secrets,
    push: fake,
    device: _device,
    newUuid: () => ids.removeAt(0),
  );
  return (reg, api, fake, secrets, p);
}

Map<String, FakeHandler> okRoutes() => {
  'POST me/devices': (_) => jsonBody(201, {'data': {}}),
  'POST me/push-subscriptions': (_) => jsonBody(201, {'data': {}}),
  'DELETE me/push-subscriptions': (_) => jsonBody(204, null),
  'DELETE me/devices/uuid-1': (_) => jsonBody(204, null),
};

void main() {
  group('charge utile', () {
    test('seuls type et id sont retenus : aucun contenu ne passe', () {
      final p = PushPayload.fromData({
        'type': 'message',
        'id': '42',
        'body': 'texte sensible',
        'title': 'Awa',
        'sender': 'Binta',
      })!;
      expect(p.toJson(), {'type': 'message', 'id': '42'});
    });

    test('type invalide ou identifiant dangereux écartés', () {
      expect(PushPayload.fromData({'id': '1'}), isNull);
      expect(PushPayload.fromData({'type': '../x', 'id': '1'}), isNull);
      expect(PushPayload.fromData({'type': 'message', 'id': '../../admin'})!.id, isNull);
      expect(PushPayload.fromData({'type': 'message', 'id': '../../admin'})!.route, '/messages');
    });

    test('ouvertures profondes', () {
      String r(String t, [String? id]) => PushPayload(t, id).route;
      expect(r('message', '42'), '/messages/42');
      expect(r('sos'), '/sos');
      expect(r('alert'), '/signalements');
      expect(r('event', '01J'), '/evenements/01J');
      expect(r('event'), '/evenements');
      expect(r('group', '7'), '/groupes/7');
      expect(r('inconnu', '1'), '/notifications');
    });

    test('texte neutre si aperçus masqués', () {
      const p = PushPayload('message', '1');
      expect(p.titleKey(masked: true), 'mobile_push_neutral_title');
      expect(p.bodyKey(masked: true), 'mobile_push_neutral_body');
      expect(p.titleKey(masked: false), 'mobile_push_message_title');
      expect(const PushPayload('sos', null).isUrgent, isTrue);
      expect(p.isUrgent, isFalse);
    });

    test('canaux distincts : SOS et alertes / Communauté', () {
      expect(PushChannels.sos, isNot(PushChannels.community));
      expect(PushChannels.communitySilent, isNot(PushChannels.sos));
    });
  });

  group('enregistrement', () {
    test('1re connexion : autorisation, appareil (device_uuid) puis jeton fcm', () async {
      final (reg, api, push, secrets, prefs) = await registrar(okRoutes());
      expect(await reg.onSignedIn(), isTrue);
      expect(push.permissionAsks, 1);

      final dev = api.calls('POST me/devices').single.data as Map;
      expect(dev, {'device_uuid': 'uuid-1', 'label': 'Android', 'platform': 'Android', 'app_version': '1.0.0'});
      final sub = api.calls('POST me/push-subscriptions').single.data as Map;
      expect(sub, {'kind': 'fcm', 'token': 'fcm-token-1', 'device_label': 'Android'});
      expect(prefs.getString(kDeviceUuidPref), 'uuid-1');
      expect(secrets.data['push_token'], 'fcm-token-1');

      // Relancement : l'autorisation n'est plus redemandée, l'appareil est réutilisé.
      expect(await reg.onSignedIn(), isTrue);
      expect(push.permissionAsks, 1);
      expect((api.calls('POST me/devices').last.data as Map)['device_uuid'], 'uuid-1');
    });

    test('autorisation refusée : rien n’est enregistré, ni redemandé', () async {
      final (reg, api, push, _, _) = await registrar(okRoutes(), push: FakePush(grant: false));
      expect(await reg.onSignedIn(), isFalse);
      expect(await reg.onSignedIn(), isFalse);
      expect(push.permissionAsks, 1);
      expect(api.requests, isEmpty);
    });

    test('appareil pris par un autre compte : nouvel UUID', () async {
      var first = true;
      final (reg, api, _, _, prefs) = await registrar({
        ...okRoutes(),
        'POST me/devices': (_) {
          if (first) {
            first = false;
            return jsonBody(422, {'message': 'pris', 'code': 'device_taken', 'errors': {'device_uuid': ['pris']}});
          }
          return jsonBody(201, {'data': {}});
        },
      });
      await reg.onSignedIn();
      expect(api.calls('POST me/devices').map((r) => (r.data as Map)['device_uuid']), ['uuid-1', 'uuid-2']);
      expect(prefs.getString(kDeviceUuidPref), 'uuid-2');
    });

    test('onTokenRefresh : nouveau jeton envoyé, ancien supprimé', () async {
      final (reg, api, _, secrets, _) = await registrar(okRoutes());
      await reg.onSignedIn();
      await reg.onTokenRefresh('fcm-token-2');
      expect((api.calls('POST me/push-subscriptions').last.data as Map)['token'], 'fcm-token-2');
      expect(api.calls('DELETE me/push-subscriptions').single.data, {'kind': 'fcm', 'token': 'fcm-token-1'});
      expect(secrets.data['push_token'], 'fcm-token-2');
    });

    test('déconnexion : abonnement supprimé côté serveur puis effacement local', () async {
      final (reg, api, push, secrets, prefs) = await registrar(okRoutes());
      await reg.onSignedIn();
      await reg.unregister();
      expect(api.calls('DELETE me/push-subscriptions').single.data, {'kind': 'fcm', 'token': 'fcm-token-1'});
      await reg.wipeLocal();
      expect(secrets.data, isEmpty);
      expect(prefs.getKeys(), isEmpty);
      expect(push.tokenDeleted, isTrue);
    });

    test('Noop : rien n’est demandé ni enregistré', () async {
      SharedPreferences.setMockInitialValues({});
      final api = FakeApi();
      final reg = PushRegistrar(
        dio: Dio()..httpClientAdapter = api,
        prefs: await SharedPreferences.getInstance(),
        secrets: MemorySecretStore(),
        push: const NoopPushService(),
        device: _device,
      );
      expect(await reg.onSignedIn(), isFalse);
      expect(api.requests, isEmpty);
    });

    testWidgets('sans configuration Firebase, le service retenu est Noop', (tester) async {
      final svc = await tester.runAsync(createPushService);
      expect(svc, isA<NoopPushService>());
      expect(svc!.available, isFalse);
    });
  });

  group('intégration', () {
    FakeApi api() => FakeApi({
      'GET me': (_) => jsonBody(200, {'data': userJson()}),
      ...okRoutes(),
    });

    testWidgets('après la connexion : autorisation puis enregistrement', (tester) async {
      final a = api();
      final push = FakePush();
      final env = TestEnv(api: a, token: 'tok', cachedUser: userJson(), extra: [pushServiceProvider.overrideWithValue(push)]);
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await settle(tester, 10);
      expect(push.permissionAsks, 1);
      expect(a.calls('POST me/devices'), hasLength(1));
      expect((a.calls('POST me/push-subscriptions').single.data as Map)['kind'], 'fcm');
      await disposeApp(tester);
    });

    testWidgets('invité·e : aucune demande d’autorisation', (tester) async {
      final push = FakePush();
      final env = TestEnv(api: api(), extra: [pushServiceProvider.overrideWithValue(push)]);
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await settle(tester, 10);
      expect(push.permissionAsks, 0);
      await disposeApp(tester);
    });

    String location(WidgetTester tester) {
      final c = ProviderScope.containerOf(tester.element(find.byType(MaterialApp)));
      return c.read(routerProvider).routerDelegate.state.matchedLocation;
    }

    testWidgets('notification touchée : ouverture profonde de la conversation', (tester) async {
      final push = FakePush();
      final env = TestEnv(api: api(), token: 'tok', cachedUser: userJson(), extra: [pushServiceProvider.overrideWithValue(push)]);
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await settle(tester, 10);
      push.opened.add(const PushPayload('message', '42'));
      await settle(tester, 10);
      expect(location(tester), '/messages/42');
      push.opened.add(const PushPayload('alert', null));
      await settle(tester, 10);
      expect(location(tester), '/signalements');
      push.opened.add(const PushPayload('sos', null));
      await settle(tester, 10);
      expect(location(tester), '/sos');
      await disposeApp(tester);
    });

    testWidgets('application fermée : la notification de lancement ouvre l’écran cible', (tester) async {
      final push = FakePush(initial: const PushPayload('message', '7'));
      final env = TestEnv(api: api(), token: 'tok', cachedUser: userJson(), extra: [pushServiceProvider.overrideWithValue(push)]);
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await settle(tester, 12);
      expect(location(tester), '/messages/7');
      await disposeApp(tester);
    });

    testWidgets('session fermée : une notification n’ouvre rien', (tester) async {
      final push = FakePush();
      final env = TestEnv(api: api(), extra: [pushServiceProvider.overrideWithValue(push)]);
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await settle(tester, 10);
      push.opened.add(const PushPayload('message', '42'));
      await settle(tester, 10);
      expect(location(tester), '/login');
      await disposeApp(tester);
    });
  });
}
