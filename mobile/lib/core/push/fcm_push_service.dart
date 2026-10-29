// Le serveur (me/push-subscriptions kind fcm) attend un jeton FCM : on garde
// getToken / onTokenRefresh / deleteToken, dépréciés en faveur de l'API FID
// (register / onRegistered) qui ne correspond pas au contrat d'API actuel.
// ignore_for_file: deprecated_member_use
import 'dart:async';
import 'dart:convert';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../l10n/pending_strings.dart';
import 'push_service.dart';

final _local = FlutterLocalNotificationsPlugin();
final _opened = StreamController<PushPayload>.broadcast();
final _silent = StreamController<PushPayload>.broadcast();
bool _localReady = false;

void _onLocalTap(NotificationResponse r) {
  try {
    final p = PushPayload.fromData(jsonDecode(r.payload ?? '{}') as Map<String, dynamic>);
    if (p != null) _opened.add(p);
  } catch (_) {}
}

Future<void> _initLocal() async {
  if (_localReady) return;
  await _local.initialize(
    settings: const InitializationSettings(
      android: AndroidInitializationSettings('@mipmap/ic_launcher'),
      iOS: DarwinInitializationSettings(requestAlertPermission: false, requestBadgePermission: false, requestSoundPermission: false),
    ),
    onDidReceiveNotificationResponse: _onLocalTap,
  );
  final android = _local.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
  // Les réglages d'un canal Android ne changent plus après sa création : le
  // son coupé passe donc par un canal « Communauté » muet distinct.
  await android?.createNotificationChannel(const AndroidNotificationChannel(
    PushChannels.duty,
    'Astreinte',
    description: 'Alertes SOS et alertes routées vers l’équipe d’astreinte : toujours sonores.',
    importance: Importance.max,
  ));
  await android?.createNotificationChannel(const AndroidNotificationChannel(
    PushChannels.sos,
    'SOS et alertes',
    description: 'Alertes SOS et alertes routées : toujours sonores.',
    importance: Importance.max,
  ));
  await android?.createNotificationChannel(const AndroidNotificationChannel(
    PushChannels.community,
    'Communauté',
    description: 'Messages, réponses et événements.',
    importance: Importance.defaultImportance,
  ));
  await android?.createNotificationChannel(const AndroidNotificationChannel(
    PushChannels.communitySilent,
    'Communauté (sans son)',
    description: 'Messages, réponses et événements, sans son.',
    importance: Importance.low,
    playSound: false,
  ));
  _localReady = true;
}

/// Affiche une notification locale à partir de `{type, id}` : le texte est
/// choisi ici, jamais transporté par le message. Aperçus masqués ⇒ texte neutre.
Future<void> showLocalFor(PushPayload p) async {
  await _initLocal();
  final prefs = await SharedPreferences.getInstance();
  final masked = prefs.getBool(kPrefMaskPreviews) ?? true;
  final sounds = prefs.getBool(kPrefSounds) ?? true;
  final urgent = p.isUrgent;
  final channel = p.channel(sounds: sounds);
  final channelName = switch (channel) {
    PushChannels.duty => 'Astreinte',
    PushChannels.sos => 'SOS et alertes',
    PushChannels.community => 'Communauté',
    _ => 'Communauté (sans son)',
  };
  await _local.show(
    id: Object.hash(p.type, p.id) & 0x7fffffff,
    title: kPendingStringsFr[p.titleKey(masked: masked)],
    body: kPendingStringsFr[p.bodyKey(masked: masked)],
    payload: jsonEncode(p.toJson()),
    notificationDetails: NotificationDetails(
      android: AndroidNotificationDetails(
        channel,
        channelName,
        importance: urgent
            ? Importance.max
            : (channel == PushChannels.communitySilent ? Importance.low : Importance.defaultImportance),
        priority: urgent ? Priority.max : Priority.defaultPriority,
        visibility: NotificationVisibility.private,
      ),
    ),
  );
}

/// Messages « données seules » reçus application fermée ou en arrière-plan.
/// Un message portant un bloc `notification` est déjà affiché par le système.
@pragma('vm:entry-point')
Future<void> fcmBackgroundHandler(RemoteMessage message) async {
  final p = PushPayload.fromData(message.data);
  if (p == null) return;
  // Silencieux (`sync`, `rules`) : aucun texte ; on note la demande, l'application
  // la traitera à son prochain premier plan (le moteur n'existe pas dans cet isolat).
  if (p.isSilent) {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(p.type == 'sync' ? kPrefPendingSync : kPrefPendingRules, true);
    return;
  }
  if (message.notification != null) return;
  await showLocalFor(p);
}

/// Implémentation FCM. Créée seulement si Firebase s'initialise (voir
/// [createPushService]).
class FcmPushService implements PushService {
  FcmPushService._(this._fm);
  final FirebaseMessaging _fm;

  @override
  bool get available => true;

  @override
  Future<bool> requestPermission() async {
    final s = await _fm.requestPermission();
    return s.authorizationStatus == AuthorizationStatus.authorized ||
        s.authorizationStatus == AuthorizationStatus.provisional;
  }

  @override
  Future<String?> token() => _fm.getToken();

  @override
  Stream<String> get onTokenRefresh => _fm.onTokenRefresh;

  @override
  Stream<PushPayload> get onOpen => _opened.stream;

  @override
  Stream<PushPayload> get onSilent => _silent.stream;

  @override
  Future<PushPayload?> initialPayload() async {
    final m = await _fm.getInitialMessage();
    if (m != null) return PushPayload.fromData(m.data);
    final launch = await _local.getNotificationAppLaunchDetails();
    final raw = launch?.didNotificationLaunchApp == true ? launch?.notificationResponse?.payload : null;
    if (raw == null) return null;
    try {
      return PushPayload.fromData(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }

  @override
  Future<void> deleteToken() => _fm.deleteToken();

  static Future<FcmPushService> create() async {
    await Firebase.initializeApp(); // lève une erreur si google-services.json est absent
    final fm = FirebaseMessaging.instance;
    await _initLocal();
    FirebaseMessaging.onBackgroundMessage(fcmBackgroundHandler);
    FirebaseMessaging.onMessage.listen((m) {
      final p = PushPayload.fromData(m.data);
      if (p == null) return;
      if (p.isSilent) {
        _silent.add(p);
        return;
      }
      // Au premier plan le système n'affiche pas la notification FCM : l'astreinte
      // doit pourtant se voir, on l'affiche localement.
      if (m.notification != null && !p.isDuty) return;
      showLocalFor(p);
    });
    FirebaseMessaging.onMessageOpenedApp.listen((m) {
      final p = PushPayload.fromData(m.data);
      if (p != null) _opened.add(p);
    });
    return FcmPushService._(fm);
  }
}

/// FCM si Firebase est configuré, sinon [NoopPushService] (aucun secret dans
/// le dépôt : `google-services.json` est fourni hors dépôt, voir README).
Future<PushService> createPushService() async {
  try {
    return await FcmPushService.create();
  } catch (e) {
    debugPrint('Push désactivé (Firebase non configuré) : ${e.runtimeType}');
    return const NoopPushService();
  }
}
