import 'dart:async';

/// Événement d'information à afficher en toast (le texte vient de l'ARB, dans
/// l'interface : le réseau ne connaît aucun libellé).
sealed class AppToast {
  const AppToast();
}

/// 429 : « Trop de requêtes. Nouvelle tentative possible dans {seconds} secondes. »
class RateLimitedToast extends AppToast {
  const RateLimitedToast(this.seconds);
  final int seconds;
}

/// 401 : la session a été fermée par le serveur.
class SessionClosedToast extends AppToast {
  const SessionClosedToast();
}

/// Le serveur a retiré un espace (pair, admin) : « Cet espace n'est plus disponible pour ce compte ».
class SpaceUnavailableToast extends AppToast {
  const SpaceUnavailableToast();
}

/// 403 `device_revoked` : l'appareil n'est plus autorisé, tout est effacé.
class DeviceRevokedToast extends AppToast {
  const DeviceRevokedToast();
}

/// `heartbeat` a annoncé un effacement à distance : données de l'espace Pair effacées, session fermée.
class DeviceWipedToast extends AppToast {
  const DeviceWipedToast();
}

/// Plus de N jours sans contact : données de l'espace Pair effacées.
class OfflineExpiredToast extends AppToast {
  const OfflineExpiredToast(this.days);
  final int days;
}

/// Mode discret appliqué par défaut au premier accès à l'espace Pair ou Admin (D-MOB-8).
class DiscreetDefaultToast extends AppToast {
  const DiscreetDefaultToast();
}

class ToastBus {
  final StreamController<AppToast> _c = StreamController<AppToast>.broadcast();
  Stream<AppToast> get stream => _c.stream;
  void emit(AppToast t) {
    if (!_c.isClosed) _c.add(t);
  }

  void dispose() => _c.close();
}
