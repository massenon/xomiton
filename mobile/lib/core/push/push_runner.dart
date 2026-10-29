import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../app/router.dart';
import '../../features/auth/application/session_controller.dart';
import '../../features/security/application/pin_lock.dart';
import '../auth_spaces/app_space.dart';
import '../auth_spaces/space_providers.dart';
import '../providers/core_providers.dart';
import '../sync/sync_providers.dart';
import 'push_registrar.dart';
import 'push_service.dart';

const _tabRoutes = {'/sos', '/feed', '/messages', '/home', '/profile'};

/// Ouverture profonde d'une notification touchée. Les onglets sont atteints
/// par `go`, les autres écrans empilés au-dessus de l'accueil.
void openPushRoute(GoRouter router, PushPayload p) {
  final route = p.route;
  if (_tabRoutes.contains(route)) {
    router.go(route);
  } else {
    router.go('/home');
    router.push(route);
  }
}

/// Ouverture d'une route d'un espace (Pair, Admin) : l'espace du compte est
/// activé d'abord (le routeur le refuse de toute façon si le serveur ne l'a pas
/// déclaré), puis l'écran cible est empilé sur l'accueil de l'espace.
void openSpaceRoute(GoRouter router, PushPayload p, AppSpace space) {
  final route = p.route;
  router.go(space.homeRoute);
  if (route != space.homeRoute) router.push(route);
}

/// Branche le push : autorisation et enregistrement après la connexion,
/// renouvellement du jeton, ouverture profonde des notifications touchées
/// (avec changement d'espace, après le code PIN), messages silencieux
/// (`sync`, `rules`).
final pushRunnerProvider = Provider<void>((ref) {
  final push = ref.watch(pushServiceProvider);
  final subs = <StreamSubscription<Object?>>[];
  ref.onDispose(() {
    for (final s in subs) {
      s.cancel();
    }
  });
  bool authenticated() => ref.read(sessionProvider).authenticated;

  // Notification en attente du code PIN : elle ne s'ouvre qu'une fois l'application déverrouillée.
  PushPayload? waiting;

  void open(PushPayload p) {
    if (p.isSilent) return;
    final router = ref.read(routerProvider);
    final space = AppSpace.forPath(p.route);
    if (space != null) {
      // Jamais d'écran d'un espace que le serveur n'a pas déclaré au compte.
      if (!ref.read(spacesProvider).contains(space)) {
        router.go(AppSpace.member.homeRoute);
        return;
      }
      ref.read(activeSpaceProvider.notifier).select(space);
      ref.read(sessionProvider.notifier).markSpaceChosen();
      openSpaceRoute(router, p, space);
      return;
    }
    openPushRoute(router, p);
  }

  void request(PushPayload p) {
    if (!authenticated() || p.isSilent) return;
    final lock = ref.read(pinLockProvider);
    if (!lock.ready || lock.locked) {
      waiting = p; // après le PIN
      return;
    }
    open(p);
  }

  ref.listen<PinLockState>(pinLockProvider, (prev, now) {
    final p = waiting;
    if (p != null && now.ready && !now.locked && authenticated()) {
      waiting = null;
      open(p);
    }
  });

  // Silencieux : la synchronisation et la relecture des règles ne concernent que le pair.
  Future<void> silent(PushPayload p) async {
    if (!authenticated() || !ref.read(hasPeerSpaceProvider)) return;
    final engine = ref.read(syncEngineProvider);
    try {
      if (p.type == 'rules') {
        await engine.refreshRules();
      } else {
        await engine.sync(force: true);
      }
    } catch (_) {
      // Hors ligne : le prochain contact (heartbeat, rules_updated_at) rattrape.
    }
  }

  Future<void> consumePending() async {
    if (!authenticated()) return;
    final prefs = ref.read(sharedPreferencesProvider);
    try {
      await prefs.reload();
    } catch (_) {}
    final sync = prefs.getBool(kPrefPendingSync) ?? false;
    final rules = prefs.getBool(kPrefPendingRules) ?? false;
    if (!sync && !rules) return;
    await prefs.remove(kPrefPendingSync);
    await prefs.remove(kPrefPendingRules);
    if (rules) await silent(const PushPayload('rules', null));
    if (sync) await silent(const PushPayload('sync', null));
  }

  subs.add(push.onOpen.listen(request));
  subs.add(push.onSilent.listen(silent));
  subs.add(push.onTokenRefresh.listen((t) async {
    if (!authenticated()) return;
    try {
      await ref.read(pushRegistrarProvider).onTokenRefresh(t);
    } catch (_) {}
  }));

  final observer = _ResumeObserver(() => unawaited(consumePending()));
  WidgetsBinding.instance.addObserver(observer);
  ref.onDispose(() => WidgetsBinding.instance.removeObserver(observer));

  var coldStartHandled = false;
  Future<void> start() async {
    try {
      await ref.read(pushRegistrarProvider).onSignedIn();
    } catch (_) {
      // Hors ligne ou serveur indisponible : repris au prochain lancement.
    }
    unawaited(consumePending());
    if (!coldStartHandled) {
      coldStartHandled = true;
      final p = await push.initialPayload();
      if (p != null) request(p);
    }
  }

  ref.listen<bool>(sessionProvider.select((s) => s.authenticated), (prev, now) {
    if (now && prev != true) start();
    if (!now) waiting = null;
  }, fireImmediately: true);
});

class _ResumeObserver with WidgetsBindingObserver {
  _ResumeObserver(this.onResume);
  final VoidCallback onResume;
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) onResume();
  }
}
