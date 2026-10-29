import 'dart:io' show HttpDate;

import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../features/auth/application/session_controller.dart';
import '../../features/security/application/pin_lock.dart';
import '../auth_spaces/app_space.dart';
import '../network/app_toast.dart';
import '../providers/core_providers.dart';

const kPrefLastContactLocal = 'last_contact_local_ms';
const kPrefLastContactServer = 'last_contact_server_ms';

/// Jours sans contact avec le serveur avant la purge des données de l'espace
/// Pair (D-MOB-7 : 14 jours par défaut, à adapter au terrain ; le serveur
/// pourra fournir la valeur par `app/version` ou `me/security`).
final peerOfflineMaxDaysProvider = Provider<int>((_) => 14);

/// Dernier contact réussi avec le serveur : l'horloge du téléphone ET la date
/// que le serveur a annoncée (en-tête `Date`). Reculer l'horloge ne prolonge
/// pas la durée : une horloge en retard sur le dernier instant serveur connu,
/// ou sur le dernier contact local, compte comme expirée.
class ContactClock {
  ContactClock(this._prefs, this._now);
  final SharedPreferences _prefs;
  final DateTime Function() _now;

  /// Tolérance aux petits décalages d'horloge (changement d'heure réseau, dérive).
  static const Duration tolerance = Duration(hours: 6);

  DateTime? _read(String key) {
    final ms = _prefs.getInt(key);
    return ms == null ? null : DateTime.fromMillisecondsSinceEpoch(ms, isUtc: true);
  }

  DateTime? get lastLocal => _read(kPrefLastContactLocal);
  DateTime? get lastServer => _read(kPrefLastContactServer);

  /// Contact réussi : [serverTime] est la date de la réponse (si connue).
  void record([DateTime? serverTime]) {
    _prefs.setInt(kPrefLastContactLocal, _now().toUtc().millisecondsSinceEpoch);
    if (serverTime != null) _prefs.setInt(kPrefLastContactServer, serverTime.toUtc().millisecondsSinceEpoch);
  }

  /// Démarre l'horloge si aucun contact n'a jamais été noté (installation existante).
  void startIfUnknown() {
    if (lastLocal == null) record();
  }

  void reset() {
    _prefs.remove(kPrefLastContactLocal);
    _prefs.remove(kPrefLastContactServer);
  }

  bool expired(int maxDays) {
    final local = lastLocal;
    if (local == null) return false;
    final now = _now().toUtc();
    if (now.isBefore(local.subtract(tolerance))) return true; // horloge reculée
    final server = lastServer;
    if (server != null && now.isBefore(server.subtract(tolerance))) return true;
    return now.difference(local) > Duration(days: maxDays);
  }
}

/// Date de la réponse (en-tête HTTP `Date`), `null` si absente ou illisible.
DateTime? parseServerDate(String? header) {
  if (header == null || header.isEmpty) return null;
  try {
    return HttpDate.parse(header);
  } catch (_) {
    return null;
  }
}

final contactClockProvider = Provider<ContactClock>(
  (ref) => ContactClock(ref.watch(sharedPreferencesProvider), ref.watch(clockProvider)),
);

/// Contrôle de l'expiration : à l'ouverture et au retour au premier plan.
/// Compte avec l'espace Pair seulement (l'espace Admin ne garde aucune donnée).
/// Expiré : données Pair effacées, session fermée ; reconnexion et nouveau code
/// exigés.
class OfflineExpiryGuard with WidgetsBindingObserver {
  OfflineExpiryGuard(this._ref);
  final Ref _ref;

  void start() {
    WidgetsBinding.instance.addObserver(this);
    Future<void>.microtask(check);
  }

  void stop() => WidgetsBinding.instance.removeObserver(this);

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) check();
  }

  Future<bool> check() async {
    final session = _ref.read(sessionProvider);
    if (!session.authenticated || !session.spaces.contains(AppSpace.peer)) return false;
    final clock = _ref.read(contactClockProvider)..startIfUnknown();
    final days = _ref.read(peerOfflineMaxDaysProvider);
    if (!clock.expired(days)) return false;
    await _ref.read(localDbProvider).clearPeer();
    await _ref.read(sessionProvider.notifier).expire();
    _ref.read(toastBusProvider).emit(OfflineExpiredToast(days));
    return true;
  }
}

final offlineExpiryGuardProvider = Provider<OfflineExpiryGuard>((ref) {
  final g = OfflineExpiryGuard(ref);
  ref.onDispose(g.stop);
  return g;
});
