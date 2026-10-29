import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../network/api_client.dart';
import '../outbox/outbox.dart';
import '../network/app_toast.dart';
import '../storage/api_cache.dart';
import '../storage/local_db.dart';
import '../storage/secret_store.dart';
import '../storage/token_store.dart';
import '../../features/auth/application/session_controller.dart';
import '../security/offline_expiry.dart';

/// Initialisés dans `main()` (bootstrap) puis surchargés dans le `ProviderScope`.
final sharedPreferencesProvider = Provider<SharedPreferences>(
  (ref) => throw UnimplementedError('sharedPreferencesProvider'),
);
final localDbProvider = Provider<LocalDb>(
  (ref) => throw UnimplementedError('localDbProvider'),
);
final tokenStoreProvider = Provider<TokenStore>(
  (ref) => throw UnimplementedError('tokenStoreProvider'),
);
final secretStoreProvider = Provider<SecretStore>(
  (ref) => FlutterSecretStore(),
);

final toastBusProvider = Provider<ToastBus>((ref) {
  final bus = ToastBus();
  ref.onDispose(bus.dispose);
  return bus;
});

/// Identifiant d'appareil enregistré (`POST me/devices`, par le moteur de
/// synchronisation ou l'abonnement push) renvoyé en `X-Device-Id` ; absent
/// tant que l'appareil n'est pas enregistré.
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
    onSpaceForbidden: () => ref.read(sessionProvider.notifier).refresh(),
    onDeviceRevoked: () => ref.read(sessionProvider.notifier).expire(),
    deviceRegistered: () => (prefs.getString(kDeviceUuidPref) ?? '').isNotEmpty,
    onContact: (date) => ref.read(contactClockProvider).record(parseServerDate(date)),
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

final isOnlineProvider = Provider<bool>(
  (ref) => ref.watch(connectivityProvider).value ?? true,
);

/// Boîte d'envoi locale (SOS, signalements, messages, publications).
final outboxProvider = Provider<OutboxService>((ref) {
  final svc = OutboxService(
    dio: ref.watch(dioProvider),
    db: ref.watch(localDbProvider),
    ownerId: () => ref.read(sessionProvider).user?.id,
  );
  ref.onDispose(svc.dispose);
  return svc;
});

/// Entrées en attente (SOS en tête) ; se met à jour à chaque changement.
final outboxEntriesProvider = StreamProvider<List<OutboxEntry>>((ref) async* {
  final svc = ref.watch(outboxProvider);
  yield svc.list();
  yield* svc.changes;
});

/// Envois aboutis depuis la boîte d'envoi (mémoire seulement).
final outboxResultsProvider = StreamProvider<OutboxResult>(
  (ref) => ref.watch(outboxProvider).results,
);

/// Intervalle de reprise périodique (nul dans les tests).
final outboxRetryIntervalProvider = Provider<Duration?>(
  (ref) => const Duration(seconds: 45),
);

/// Rejoue la boîte d'envoi : au démarrage, au retour du réseau et, tant qu'il
/// reste des entrées, à intervalle régulier.
final outboxRunnerProvider = Provider<void>((ref) {
  final svc = ref.watch(outboxProvider);
  final every = ref.watch(outboxRetryIntervalProvider);
  void run() {
    if (ref.read(isOnlineProvider) && svc.pending().isNotEmpty) {
      svc.flush();
    }
  }

  ref.listen<bool>(isOnlineProvider, (prev, now) {
    if (now) run();
  }, fireImmediately: true);
  Timer? timer;
  if (every != null) timer = Timer.periodic(every, (_) => run());
  ref.onDispose(() => timer?.cancel());
});
