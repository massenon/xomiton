import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/core_providers.dart';
import '../../auth/application/session_controller.dart';
import 'pin_service.dart';

/// Itérations PBKDF2 (réduites dans les tests).
final pinIterationsProvider = Provider<int>((_) => 60000);

final pinServiceProvider = Provider<PinService>(
  (ref) => PinService(ref.watch(secretStoreProvider), iterations: ref.watch(pinIterationsProvider)),
);

/// Délai passé en arrière-plan avant que le code soit redemandé.
final pinLockDelayProvider = Provider<Duration>((_) => const Duration(seconds: 60));

/// Horloge injectable (tests).
final clockProvider = Provider<DateTime Function()>((_) => DateTime.now);

class PinLockState {
  const PinLockState({this.configured = false, this.locked = false, this.remaining});
  final bool configured;
  final bool locked;

  /// Essais restants après une erreur (null = aucune erreur affichée).
  final int? remaining;

  PinLockState copyWith({bool? configured, bool? locked, int? remaining, bool clearRemaining = false}) =>
      PinLockState(
        configured: configured ?? this.configured,
        locked: locked ?? this.locked,
        remaining: clearRemaining ? null : (remaining ?? this.remaining),
      );
}

/// Verrou local : demandé à l'ouverture et au retour en avant-plan après délai.
class PinLockController extends Notifier<PinLockState> {
  DateTime? _backgroundAt;

  @override
  PinLockState build() {
    ref.listen(sessionProvider.select((s) => s.authenticated), (prev, now) {
      if (!now) state = const PinLockState();
    });
    Future<void>.microtask(_init);
    return const PinLockState();
  }

  Future<void> _init() async {
    final set = await ref.read(pinServiceProvider).isSet();
    final auth = ref.read(sessionProvider).authenticated;
    state = PinLockState(configured: set && auth, locked: set && auth);
  }

  /// Vérifie un code. Après le dernier essai raté, la session est fermée et
  /// tout le stockage privé effacé : il faut se reconnecter (mot de passe + OTP).
  Future<PinCheck> check(String pin) async {
    final res = await ref.read(pinServiceProvider).verify(pin);
    if (res.exhausted) {
      await ref.read(sessionProvider.notifier).expire();
    } else if (!res.ok) {
      state = state.copyWith(remaining: res.remaining);
    }
    return res;
  }

  Future<PinCheck> unlock(String pin) async {
    final res = await check(pin);
    if (res.ok) state = state.copyWith(locked: false, clearRemaining: true);
    return res;
  }

  Future<void> enable(String pin) async {
    await ref.read(pinServiceProvider).set(pin);
    state = const PinLockState(configured: true);
  }

  /// Retire le code après vérification de l'actuel.
  Future<PinCheck> disable(String currentPin) async {
    final res = await check(currentPin);
    if (res.ok) {
      await ref.read(pinServiceProvider).clear();
      state = const PinLockState();
    }
    return res;
  }

  void lockNow() {
    if (state.configured) state = state.copyWith(locked: true, clearRemaining: true);
  }

  void onBackground() => _backgroundAt = ref.read(clockProvider)();

  void onForeground() {
    final at = _backgroundAt;
    _backgroundAt = null;
    if (at == null || !state.configured) return;
    if (ref.read(clockProvider)().difference(at) >= ref.read(pinLockDelayProvider)) lockNow();
  }
}

final pinLockProvider = NotifierProvider<PinLockController, PinLockState>(PinLockController.new);
