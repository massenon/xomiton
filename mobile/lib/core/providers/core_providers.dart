import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../network/api_client.dart';
import '../network/app_toast.dart';
import '../storage/api_cache.dart';
import '../storage/local_db.dart';
import '../storage/secret_store.dart';
import '../storage/token_store.dart';
import '../../features/auth/application/session_controller.dart';

/// Initialisés dans `main()` (bootstrap) puis surchargés dans le `ProviderScope`.
final sharedPreferencesProvider =
    Provider<SharedPreferences>((ref) => throw UnimplementedError('sharedPreferencesProvider'));
final localDbProvider = Provider<LocalDb>((ref) => throw UnimplementedError('localDbProvider'));
final tokenStoreProvider =
    Provider<TokenStore>((ref) => throw UnimplementedError('tokenStoreProvider'));
final secretStoreProvider = Provider<SecretStore>((ref) => FlutterSecretStore());

final toastBusProvider = Provider<ToastBus>((ref) {
  final bus = ToastBus();
  ref.onDispose(bus.dispose);
  return bus;
});

/// Identifiant d'appareil enregistré (`POST me/devices`, lot suivant) renvoyé
/// en `X-Device-Id` ; absent tant que l'appareil n'est pas enregistré.
const kDeviceUuidPref = 'device_uuid';

/// Adaptateur HTTP, surchargé dans les tests.
final httpAdapterProvider = Provider<HttpClientAdapter?>((ref) => null);

final dioProvider = Provider<Dio>((ref) {
  final prefs = ref.watch(sharedPreferencesProvider);
  return buildApiClient(
    tokens: ref.watch(tokenStoreProvider),
    toasts: ref.watch(toastBusProvider),
    adapter: ref.watch(httpAdapterProvider),
    deviceId: () => prefs.getString(kDeviceUuidPref),
    onUnauthorized: () => ref.read(sessionProvider.notifier).expire(),
  );
});

final apiCacheProvider = Provider<ApiCache>((ref) {
  final db = ref.watch(localDbProvider);
  return ApiCache(
    dio: ref.watch(dioProvider),
    publicBox: db.publicCache,
    privateBox: db.privateCache,
  );
});

/// Source de connectivité, injectable pour les tests.
final connectivityStreamProvider = Provider<Stream<bool>>((ref) {
  final c = Connectivity();
  bool online(List<ConnectivityResult> r) =>
      r.isNotEmpty && !r.every((e) => e == ConnectivityResult.none);
  Stream<bool> run() async* {
    yield online(await c.checkConnectivity());
    yield* c.onConnectivityChanged.map(online);
  }

  return run();
});

/// `true` = réseau disponible. Sert au bandeau « hors connexion » et à la
/// reprise des envois en attente.
final connectivityProvider = StreamProvider<bool>((ref) {
  return ref.watch(connectivityStreamProvider).distinct();
});

final isOnlineProvider = Provider<bool>((ref) => ref.watch(connectivityProvider).value ?? true);
