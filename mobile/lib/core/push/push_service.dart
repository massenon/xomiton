import 'dart:async';

/// Préférences locales lues aussi hors de l'interface (isolat d'arrière-plan).
const kPrefMaskPreviews = 'pref_mask_previews';
const kPrefSounds = 'pref_sounds';

/// Une synchronisation (`sync`) ou une relecture des règles (`rules`) demandée
/// par un push silencieux reçu application fermée : consommée au prochain
/// premier plan (l'isolat d'arrière-plan n'a pas accès au moteur).
const kPrefPendingSync = 'push_pending_sync';
const kPrefPendingRules = 'push_pending_rules';

/// Canaux Android : « Astreinte » (`duty`, importance maximale) porte
/// `alert_routed`, `duty_sos` et `duty_alert` — le serveur l'indique dans le
/// message (API §24) ; « SOS et alertes » (importance maximale, sonore même si
/// « Sons » est coupé) pour les types historiques ; « Communauté » (et sa
/// variante muette) pour le reste.
abstract final class PushChannels {
  static const duty = 'duty';
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

  /// Types d'astreinte (API §24) : pair prévenu (`alert_routed`), admin de garde
  /// (`duty_sos`, `duty_alert`).
  static const dutyTypes = {'alert_routed', 'duty_sos', 'duty_alert'};

  /// Types d'équipe : tout ce qui concerne l'espace Pair ou l'espace Admin.
  static const teamTypes = {...dutyTypes, 'peer_pending', 'content_reported'};

  static PushPayload? fromData(Map<String, dynamic>? data) {
    final t = data?['type'];
    if (t is! String || !_type.hasMatch(t)) return null;
    final i = data?['id'];
    final id = (i is String || i is num) && _id.hasMatch('$i') ? '$i' : null;
    return PushPayload(t, id);
  }

  Map<String, String> toJson() => {'type': type, 'id': ?id};

  /// `sync` et `rules` sont silencieux : aucun texte, aucune notification, ils
  /// déclenchent seulement une synchronisation ou une relecture des règles.
  bool get isSilent => type == 'sync' || type == 'rules';

  bool get isDuty => dutyTypes.contains(type);
  bool get isTeam => teamTypes.contains(type);

  bool get isUrgent => type == 'sos' || type == 'alert' || type == 'alerte' || isDuty;

  /// Canal Android : « Astreinte » pour l'astreinte, « SOS et alertes » pour les
  /// types historiques urgents, sinon « Communauté » (muette si les sons sont
  /// coupés, sauf pour l'équipe : une demande à valider reste audible).
  String channel({required bool sounds}) => isDuty
      ? PushChannels.duty
      : (isUrgent
            ? PushChannels.sos
            : (sounds || isTeam ? PushChannels.community : PushChannels.communitySilent));

  /// Ouverture profonde : jamais d'URL libre, seulement des routes connues.
  /// Les routes `/pair/**` et `/admin/**` sont revérifiées par le serveur (403)
  /// et par la garde d'espace ; l'application ne les ouvre que si le compte a
  /// l'espace (voir `push_runner.dart`).
  String get route {
    final i = id;
    switch (type) {
      case 'alert_routed':
        return i == null ? '/pair/alertes' : '/pair/alertes/$i';
      case 'duty_sos':
      case 'duty_alert':
        return i == null ? '/admin/alertes' : '/admin/alertes/$i';
      case 'peer_pending':
        return '/admin/inscriptions';
      case 'content_reported':
        return '/admin/moderation';
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

  /// Texte local selon le type : la charge utile n'a aucun contenu. Neutre quand
  /// les aperçus sont masqués (clés de `pending_strings.dart`) : pour l'équipe,
  /// titre « Xomiton » et corps neutre, jamais « SOS », le lieu ni le motif.
  String titleKey({required bool masked}) {
    if (isTeam) return masked ? 'mobile_push_team_title' : 'mobile_push_${type}_title';
    return masked
        ? 'mobile_push_neutral_title'
        : switch (type) {
            'message' || 'conversation' => 'mobile_push_message_title',
            'sos' || 'alert' || 'alerte' => 'mobile_push_alert_title',
            _ => 'mobile_push_generic_title',
          };
  }

  String bodyKey({required bool masked}) {
    if (isTeam) return masked ? 'mobile_push_${type}_neutral' : 'mobile_push_open_body';
    return masked ? 'mobile_push_neutral_body' : 'mobile_push_open_body';
  }
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

  /// Messages silencieux (`sync`, `rules`) reçus application ouverte.
  Stream<PushPayload> get onSilent;

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
  Stream<PushPayload> get onSilent => const Stream.empty();
  @override
  Future<PushPayload?> initialPayload() async => null;
  @override
  Future<void> deleteToken() async {}
}
