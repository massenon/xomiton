import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/auth_spaces/app_space.dart';
import '../../../core/network/app_toast.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/security/offline_expiry.dart' show kPrefLastContactLocal, kPrefLastContactServer;
import '../../../core/push/push_registrar.dart';
import '../../../core/push/push_service.dart';
import '../../security/application/pin_lock.dart';
import '../data/app_user.dart';

/// Session : jeton dans le stockage sécurisé, utilisateur en copie Hive chiffrée.
class SessionState {
  const SessionState({required this.authenticated, this.user, this.spaceChoicePending = false});
  const SessionState.signedOut() : this(authenticated: false);

  final bool authenticated;
  final AppUser? user;

  /// Vrai juste après une connexion : un compte à plusieurs espaces passe
  /// d'abord par l'écran « Choix de l'espace » (jamais après un simple relancement).
  final bool spaceChoicePending;

  /// Espaces proposés par le serveur ; sans session, aucun.
  List<AppSpace> get spaces => !authenticated ? const [] : (user?.spaces ?? const [AppSpace.member]);

  SessionState copyWith({AppUser? user, bool? spaceChoicePending}) => SessionState(
        authenticated: authenticated,
        user: user ?? this.user,
        spaceChoicePending: spaceChoicePending ?? this.spaceChoicePending,
      );
}

/// Préférence : dernier espace actif (jamais une autorisation : relue puis
/// validée contre les `spaces` du serveur).
const kPrefActiveSpace = 'active_space';

/// Le mode discret par défaut a déjà été proposé à ce compte sur cet appareil (D-MOB-8).
const kPrefDiscreetDefaultApplied = 'discreet_default_applied';

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
    state = SessionState(authenticated: true, user: user, spaceChoicePending: true);
  }

  Future<void> setUser(Map<String, dynamic> userJson) async {
    final user = AppUser.fromJson(userJson);
    await ref.read(localDbProvider).session.put(_userKey, jsonEncode(user.toJson()));
    if (!state.authenticated) return;
    final before = state.spaces;
    state = state.copyWith(user: user);
    // Un espace retiré par le serveur se ferme tout de suite, avec un message.
    final lost = before.any((e) => !user.spaces.contains(e));
    if (lost) ref.read(toastBusProvider).emit(const SpaceUnavailableToast());
  }

  /// La personne a choisi son espace : le sélecteur ne revient pas avant la prochaine connexion.
  void markSpaceChosen() {
    if (state.authenticated && state.spaceChoicePending) state = state.copyWith(spaceChoicePending: false);
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
    await prefs.remove(kPrefActiveSpace);
    await prefs.remove(kPrefLastContactLocal);
    await prefs.remove(kPrefLastContactServer);
    await prefs.remove(kPrefDiscreetDefaultApplied);
    state = const SessionState.signedOut();
  }
}

final sessionProvider = NotifierProvider<SessionController, SessionState>(SessionController.new);
