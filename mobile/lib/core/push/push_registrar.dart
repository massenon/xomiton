import 'dart:io' show Platform;

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:uuid/uuid.dart';

import '../network/api_exception.dart';
import '../providers/core_providers.dart';
import '../storage/secret_store.dart';
import 'push_service.dart';

const kAppVersion = '1.0.0';
const _prefPermission = 'push_permission'; // 'granted' | 'denied'
const _secretPushToken = 'push_token';

class DeviceDescriptor {
  const DeviceDescriptor({required this.label, required this.platform, this.appVersion = kAppVersion});
  final String label;
  final String platform;
  final String appVersion;

  factory DeviceDescriptor.current() {
    final os = Platform.isIOS ? 'iOS' : (Platform.isAndroid ? 'Android' : Platform.operatingSystem);
    return DeviceDescriptor(label: os, platform: os);
  }
}

final pushServiceProvider = Provider<PushService>((_) => const NoopPushService());
final deviceDescriptorProvider = Provider<DeviceDescriptor>((_) => DeviceDescriptor.current());

/// Enregistrement de l'appareil (`me/devices`) et du jeton (`me/push-subscriptions`).
/// Le jeton FCM n'est jamais journalisé.
class PushRegistrar {
  PushRegistrar({
    required this.dio,
    required this.prefs,
    required this.secrets,
    required this.push,
    required this.device,
    String Function()? newUuid,
  }) : _newUuid = newUuid ?? (() => const Uuid().v4());

  final Dio dio;
  final SharedPreferences prefs;
  final SecretStore secrets;
  final PushService push;
  final DeviceDescriptor device;
  final String Function() _newUuid;

  /// Identifiant d'appareil enregistré ; régénéré si l'appareil est révoqué ou
  /// déjà pris par un autre compte (téléphone partagé).
  Future<String> ensureDevice() async {
    for (var attempt = 0; attempt < 2; attempt++) {
      final uuid = prefs.getString(kDeviceUuidPref) ?? _newUuid();
      try {
        await dio.post<dynamic>('me/devices', data: {
          'device_uuid': uuid,
          'label': device.label,
          'platform': device.platform,
          'app_version': device.appVersion,
        });
        await prefs.setString(kDeviceUuidPref, uuid);
        return uuid;
      } catch (e) {
        final err = ApiException.from(e);
        final regenerate = err.code == 'device_revoked' || err.code == 'device_taken' || err.firstError('device_uuid') != null;
        if (!regenerate || attempt == 1) rethrow;
        await prefs.remove(kDeviceUuidPref);
      }
    }
    throw StateError('unreachable');
  }

  bool get permissionGranted => prefs.getString(_prefPermission) == 'granted';
  bool get permissionAsked => prefs.getString(_prefPermission) != null;

  /// Après la 1re connexion : demande l'autorisation (une seule fois) puis
  /// enregistre l'appareil et le jeton. Retourne `true` si un jeton est enregistré.
  Future<bool> onSignedIn() async {
    if (!push.available) return false;
    if (!permissionAsked) {
      final granted = await push.requestPermission();
      await prefs.setString(_prefPermission, granted ? 'granted' : 'denied');
    }
    if (!permissionGranted) return false;
    final token = await push.token();
    if (token == null || token.isEmpty) return false;
    await ensureDevice();
    await _subscribe(token);
    return true;
  }

  Future<void> onTokenRefresh(String token) async {
    if (!permissionGranted || token.isEmpty) return;
    final old = await secrets.read(_secretPushToken);
    await ensureDevice();
    await _subscribe(token);
    if (old != null && old != token) await _unsubscribe(old);
  }

  Future<void> _subscribe(String token) async {
    await dio.post<dynamic>('me/push-subscriptions', data: {
      'kind': 'fcm',
      'token': token,
      'device_label': device.label,
    });
    await secrets.write(_secretPushToken, token);
  }

  Future<void> _unsubscribe(String token) =>
      dio.delete<dynamic>('me/push-subscriptions', data: {'kind': 'fcm', 'token': token});

  /// Déconnexion voulue (avant l'effacement du jeton d'accès) : supprime
  /// l'abonnement côté serveur, au mieux.
  Future<void> unregister() async {
    final token = await secrets.read(_secretPushToken);
    if (token != null) {
      try {
        await _unsubscribe(token);
      } catch (_) {}
    }
    final uuid = prefs.getString(kDeviceUuidPref);
    if (uuid != null) {
      try {
        await dio.delete<dynamic>('me/devices/$uuid');
      } catch (_) {}
    }
  }

  /// Effacement local (déconnexion, 401) : jeton FCM, identifiant d'appareil, autorisation.
  Future<void> wipeLocal() async {
    await secrets.delete(_secretPushToken);
    await prefs.remove(_prefPermission);
    await prefs.remove(kDeviceUuidPref);
    try {
      await push.deleteToken();
    } catch (_) {}
  }
}

final pushRegistrarProvider = Provider<PushRegistrar>(
  (ref) => PushRegistrar(
    dio: ref.watch(dioProvider),
    prefs: ref.watch(sharedPreferencesProvider),
    secrets: ref.watch(secretStoreProvider),
    push: ref.watch(pushServiceProvider),
    device: ref.watch(deviceDescriptorProvider),
  ),
);
