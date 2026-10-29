import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/application/session_controller.dart';
import '../auth_spaces/app_space.dart';
import '../network/app_toast.dart';
import '../providers/core_providers.dart';
import '../push/push_registrar.dart';
import 'collection_store.dart';
import 'sync_engine.dart';
import 'sync_queue.dart';

/// Lignes des collections du pair (coffre chiffré à clé propre).
final peerStoreProvider = Provider<CollectionStore>(
  (ref) => CollectionStore(ref.watch(localDbProvider).peerData),
);

/// Lignes des collections communes (coffre public, espace de noms `sync:`).
final commonStoreProvider = Provider<CollectionStore>(
  (ref) => CollectionStore(ref.watch(localDbProvider).publicCache, ns: 'sync:'),
);

final syncQueueProvider = Provider<SyncQueue>((ref) {
  final q = SyncQueue(ref.watch(localDbProvider).peerData);
  ref.onDispose(q.dispose);
  return q;
});

/// Entrées de la file du pair, mises à jour à chaque changement.
final syncQueueEntriesProvider = StreamProvider<List<QueueEntry>>((ref) async* {
  final q = ref.watch(syncQueueProvider);
  yield q.list();
  yield* q.changes;
});

/// Le compte a-t-il l'espace Pair ? (le serveur décide : `GET me`)
final hasPeerSpaceProvider = Provider<bool>(
  (ref) => ref.watch(sessionProvider.select((s) => s.spaces.contains(AppSpace.peer))),
);

final syncEngineProvider = Provider<SyncEngine>((ref) {
  final peer = ref.watch(hasPeerSpaceProvider);
  final engine = SyncEngine(
    dio: ref.watch(dioProvider),
    queue: ref.watch(syncQueueProvider),
    pair: ref.watch(peerStoreProvider),
    common: ref.watch(commonStoreProvider),
    ensureDevice: () => ref.read(pushRegistrarProvider).ensureDevice(),
    flushMemberOutbox: () => ref.read(outboxProvider).flush(),
    extraPending: () => ref.read(outboxProvider).pending().length,
    runPair: peer,
    runCommon: peer,
    appVersion: kAppVersion,
    onWipe: () async {
      final prefs = ref.read(sharedPreferencesProvider);
      final uuid = prefs.getString(kDeviceUuidPref);
      if (uuid != null) {
        try {
          await ref.read(dioProvider).post<dynamic>('me/devices/$uuid/wiped');
        } catch (_) {}
      }
      await ref.read(localDbProvider).clearPeer();
      ref.read(toastBusProvider).emit(const DeviceWipedToast());
      await ref.read(sessionProvider.notifier).expire();
    },
  );
  ref.onDispose(engine.dispose);
  return engine;
});

/// État de la synchronisation (en cours, dernière réussie, hors ligne, conflits).
final syncStateProvider = StreamProvider<SyncState>((ref) async* {
  final e = ref.watch(syncEngineProvider);
  yield e.state;
  yield* e.states;
});

/// Intervalle du signal de vie et de la synchronisation périodique au premier
/// plan (5 minutes ; nul dans les tests).
final syncIntervalProvider = Provider<Duration?>((_) => const Duration(minutes: 5));

/// Lance la synchronisation de l'espace Pair : à l'ouverture, au retour du
/// réseau, au retour au premier plan et toutes les 5 minutes. Rien ne tourne
/// pour un compte sans espace Pair ni hors session.
final syncRunnerProvider = Provider<void>((ref) {
  final auth = ref.watch(sessionProvider.select((s) => s.authenticated));
  final peer = ref.watch(hasPeerSpaceProvider);
  if (!auth || !peer) return;
  final engine = ref.watch(syncEngineProvider);
  final every = ref.watch(syncIntervalProvider);
  var started = false;

  Future<void> start() async {
    // Un autre compte sur ce téléphone : rien des données du précédent ne reste.
    final uid = ref.read(sessionProvider).user?.id;
    if (uid != null) {
      final store = ref.read(peerStoreProvider);
      final owner = store.text('owner');
      if (owner != null && owner != '$uid') {
        await ref.read(localDbProvider).clearPeer();
      }
      await store.putText('owner', '$uid');
    }
    if (await engine.heartbeat()) await engine.sync(force: true);
  }

  void onNet(bool online) {
    if (!online) return;
    if (!started) {
      started = true;
      unawaited(start());
    } else {
      unawaited(engine.sync(force: true));
    }
  }

  ref.listen<bool>(isOnlineProvider, (_, now) => onNet(now), fireImmediately: true);
  Timer? timer;
  if (every != null) {
    timer = Timer.periodic(every, (_) {
      if (!ref.read(isOnlineProvider)) return;
      unawaited(engine.heartbeat());
      unawaited(engine.sync());
    });
  }
  // Retour au premier plan (désactivé quand l'intervalle est nul : tests).
  final observer = every == null
      ? null
      : _ResumeObserver(() {
          if (ref.read(isOnlineProvider)) {
            unawaited(engine.heartbeat());
            unawaited(engine.sync());
          }
        });
  if (observer != null) WidgetsBinding.instance.addObserver(observer);
  ref.onDispose(() {
    timer?.cancel();
    if (observer != null) WidgetsBinding.instance.removeObserver(observer);
  });
});

class _ResumeObserver with WidgetsBindingObserver {
  _ResumeObserver(this.onResume);
  final VoidCallback onResume;
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) onResume();
  }
}
