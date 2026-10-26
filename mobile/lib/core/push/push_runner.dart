import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../app/router.dart';
import '../../features/auth/application/session_controller.dart';
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

/// Branche le push : autorisation et enregistrement après la connexion,
/// renouvellement du jeton, ouverture profonde des notifications touchées.
final pushRunnerProvider = Provider<void>((ref) {
  final push = ref.watch(pushServiceProvider);
  final subs = <StreamSubscription<Object?>>[];
  ref.onDispose(() {
    for (final s in subs) {
      s.cancel();
    }
  });
  bool authenticated() => ref.read(sessionProvider).authenticated;

  subs.add(push.onOpen.listen((p) {
    if (authenticated()) openPushRoute(ref.read(routerProvider), p);
  }));
  subs.add(push.onTokenRefresh.listen((t) async {
    if (!authenticated()) return;
    try {
      await ref.read(pushRegistrarProvider).onTokenRefresh(t);
    } catch (_) {}
  }));

  var coldStartHandled = false;
  Future<void> start() async {
    try {
      await ref.read(pushRegistrarProvider).onSignedIn();
    } catch (_) {
      // Hors ligne ou serveur indisponible : repris au prochain lancement.
    }
    if (!coldStartHandled) {
      coldStartHandled = true;
      final p = await push.initialPayload();
      if (p != null && authenticated()) openPushRoute(ref.read(routerProvider), p);
    }
  }

  ref.listen<bool>(sessionProvider.select((s) => s.authenticated), (prev, now) {
    if (now && prev != true) start();
  }, fireImmediately: true);
});
