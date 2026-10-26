import 'dart:async';

/// Préférences locales lues aussi hors de l'interface (isolat d'arrière-plan).
const kPrefMaskPreviews = 'pref_mask_previews';
const kPrefSounds = 'pref_sounds';

/// Canaux Android : « SOS et alertes » (importance maximale, sonore même si
/// « Sons » est coupé) distinct de « Communauté » (et de sa variante muette).
abstract final class PushChannels {
  static const sos = 'sos_alerts';
  static const community = 'community';
  static const communitySilent = 'comm_silent';
}

/// Charge utile d'une notification : `{type, id}` SEULEMENT (aucun contenu
/// sensible ne transite par FCM). Toute autre clé est ignorée.
class PushPayload {
  const PushPayload(this.type, this.id);
  final String type;
  final String? id;

  static final _type = RegExp(r'^[a-z][a-z0-9_]{0,31}$');
  static final _id = RegExp(r'^[A-Za-z0-9_-]{1,64}$');

  static PushPayload? fromData(Map<String, dynamic>? data) {
    final t = data?['type'];
    if (t is! String || !_type.hasMatch(t)) return null;
    final i = data?['id'];
    final id = (i is String || i is num) && _id.hasMatch('$i') ? '$i' : null;
    return PushPayload(t, id);
  }

  Map<String, String> toJson() => {'type': type, 'id': ?id};

  bool get isUrgent => type == 'sos' || type == 'alert' || type == 'alerte';

  /// Ouverture profonde : jamais d'URL libre, seulement des routes connues.
  String get route {
    final i = id;
    switch (type) {
      case 'message':
      case 'conversation':
        return i == null ? '/messages' : '/messages/$i';
      case 'sos':
        return '/sos';
      case 'alert':
      case 'alerte':
        return '/signalements';
      case 'event':
      case 'evenement':
        return i == null ? '/evenements' : '/evenements/$i';
      case 'group':
      case 'groupe':
        return i == null ? '/groupes' : '/groupes/$i';
      case 'post':
      case 'comment':
        return i == null ? '/feed' : '/feed/$i';
      default:
        return '/notifications';
    }
  }

  /// Texte local selon le type : la charge utile n'a aucun contenu. Neutre
  /// quand les aperçus sont masqués (clés de `pending_strings.dart`).
  String titleKey({required bool masked}) => masked
      ? 'mobile_push_neutral_title'
      : switch (type) {
          'message' || 'conversation' => 'mobile_push_message_title',
          'sos' || 'alert' || 'alerte' => 'mobile_push_alert_title',
          _ => 'mobile_push_generic_title',
        };

  String bodyKey({required bool masked}) => masked ? 'mobile_push_neutral_body' : 'mobile_push_open_body';
}

/// Notifications push. Deux implémentations : FCM (si Firebase est configuré)
/// et [NoopPushService] (sinon : l'application fonctionne sans push).
abstract interface class PushService {
  /// `false` pour [NoopPushService].
  bool get available;

  /// Demande l'autorisation (Android 13 : POST_NOTIFICATIONS).
  Future<bool> requestPermission();

  Future<String?> token();
  Stream<String> get onTokenRefresh;

  /// Notification touchée (application en arrière-plan ou au premier plan).
  Stream<PushPayload> get onOpen;

  /// Notification qui a lancé l'application (fermée).
  Future<PushPayload?> initialPayload();

  /// Invalide le jeton local (déconnexion).
  Future<void> deleteToken();
}

class NoopPushService implements PushService {
  const NoopPushService();
  @override
  bool get available => false;
  @override
  Future<bool> requestPermission() async => false;
  @override
  Future<String?> token() async => null;
  @override
  Stream<String> get onTokenRefresh => const Stream.empty();
  @override
  Stream<PushPayload> get onOpen => const Stream.empty();
  @override
  Future<PushPayload?> initialPayload() async => null;
  @override
  Future<void> deleteToken() async {}
}
