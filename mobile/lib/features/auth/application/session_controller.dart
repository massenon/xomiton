import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/core_providers.dart';
import '../../../core/push/push_registrar.dart';
import '../../../core/push/push_service.dart';
import '../../security/application/pin_lock.dart';
import '../data/app_user.dart';

/// Session : jeton dans le stockage sécurisé, utilisateur en copie Hive chiffrée.
class SessionState {
  const SessionState({required this.authenticated, this.user});
  const SessionState.signedOut() : this(authenticated: false);

  final bool authenticated;
  final AppUser? user;
}

const _userKey = 'user';

class SessionController extends Notifier<SessionState> {
  @override
  SessionState build() {
    final tokens = ref.read(tokenStoreProvider);
    if (!tokens.hasToken) return const SessionState.signedOut();
    AppUser? user;
    final raw = ref.read(localDbProvider).session.get(_userKey);
    if (raw != null) {
      try {
        user = AppUser.fromJson(jsonDecode(raw) as Map<String, dynamic>);
      } catch (_) {}
    }
    // Rafraîchit le compte en arrière-plan ; hors ligne, la copie locale suffit.
    Future<void>.microtask(refresh);
    return SessionState(authenticated: true, user: user);
  }

  /// Ouvre la session avec le jeton Sanctum reçu.
  Future<void> signIn(String token, Map<String, dynamic>? userJson) async {
    await ref.read(tokenStoreProvider).save(token);
    AppUser? user;
    if (userJson != null) {
      user = AppUser.fromJson(userJson);
      await ref.read(localDbProvider).session.put(_userKey, jsonEncode(user.toJson()));
    }
    state = SessionState(authenticated: true, user: user);
  }

  Future<void> setUser(Map<String, dynamic> userJson) async {
    final user = AppUser.fromJson(userJson);
    await ref.read(localDbProvider).session.put(_userKey, jsonEncode(user.toJson()));
    if (state.authenticated) state = SessionState(authenticated: true, user: user);
  }

  /// `GET me` ; les erreurs réseau sont ignorées, un 401 ferme la session
  /// (intercepteur).
  Future<void> refresh() async {
    if (!state.authenticated) return;
    try {
      final res = await ref.read(dioProvider).get<dynamic>('me');
      final data = (res.data as Map)['data'];
      if (data is Map<String, dynamic>) await setUser(data);
    } catch (_) {}
  }

  /// Déconnexion voulue : révoque le jeton côté serveur (au mieux), efface
  /// tout ce qui est propre au compte.
  Future<void> signOut() async {
    if (state.authenticated) {
      // Abonnement push et appareil retirés tant que le jeton d'accès existe.
      try {
        await ref.read(pushRegistrarProvider).unregister();
      } catch (_) {}
      try {
        await ref.read(dioProvider).post<dynamic>('auth/logout');
      } catch (_) {}
    }
    await expire();
  }

  /// Session fermée localement (401, déconnexion, PIN épuisé) : tout le
  /// stockage privé part — jeton, appareil reconnu (le prochain accès
  /// redemande un code OTP), Hive privé, code PIN et compteur, jeton push,
  /// identifiant d'appareil et préférences liées au compte.
  Future<void> expire() async {
    final tokens = ref.read(tokenStoreProvider);
    await tokens.clear();
    await tokens.clearDeviceToken();
    await ref.read(localDbProvider).clearPrivate();
    await ref.read(pinServiceProvider).clear();
    await ref.read(pushRegistrarProvider).wipeLocal();
    final prefs = ref.read(sharedPreferencesProvider);
    await prefs.remove(kPrefMaskPreviews);
    await prefs.remove(kPrefSounds);
    state = const SessionState.signedOut();
  }
}

final sessionProvider = NotifierProvider<SessionController, SessionState>(SessionController.new);
