import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../../core/outbox/outbox.dart';
import '../../../core/platform/platform_services.dart';
import '../../../core/providers/core_providers.dart';
import '../../auth/application/session_controller.dart';
import '../data/alerts_api.dart';
import '../data/alerts_models.dart';

/// idle → countdown (5 s, annulable, local) → locating → manual → sending →
/// queued | sent → cancelled | failed (Q21, Q39, FR-ALT-03).
enum SosPhase {
  idle,
  countdown,
  locating,
  manual,
  sending,
  queued,
  sent,
  cancelled,
  failed,
}

const sosCountdownSeconds = 5;
const sosManualSeconds = 15;
const sosHoldDuration = Duration(seconds: 2);

class SosState {
  const SosState({
    this.phase = SosPhase.idle,
    this.countdown = sosCountdownSeconds,
    this.manualLeft = sosManualSeconds,
    this.status,
    this.zoneId,
    this.communeId,
    this.error,
    this.entryUuid,
    this.actionBusy = false,
  });

  final SosPhase phase;
  final int countdown;
  final int manualLeft;
  final SosStatus? status;
  final int? zoneId;
  final int? communeId;
  final String? error;
  final String? entryUuid;
  final bool actionBusy;

  SosState copy({
    SosPhase? phase,
    int? countdown,
    int? manualLeft,
    SosStatus? status,
    Object? zoneId = _keep,
    Object? communeId = _keep,
    Object? error = _keep,
    Object? entryUuid = _keep,
    bool? actionBusy,
  }) => SosState(
    phase: phase ?? this.phase,
    countdown: countdown ?? this.countdown,
    manualLeft: manualLeft ?? this.manualLeft,
    status: status ?? this.status,
    zoneId: identical(zoneId, _keep) ? this.zoneId : zoneId as int?,
    communeId: identical(communeId, _keep) ? this.communeId : communeId as int?,
    error: identical(error, _keep) ? this.error : error as String?,
    entryUuid: identical(entryUuid, _keep)
        ? this.entryUuid
        : entryUuid as String?,
    actionBusy: actionBusy ?? this.actionBusy,
  );
}

const Object _keep = Object();

/// Charge utile de `POST sos` : champs vides retirés, position seulement si
/// elle est partagée (même règle que `buildPayload` du web).
Map<String, dynamic> buildSosPayload({
  required bool share,
  GeoPoint? position,
  int? zoneId,
  int? communeId,
  String phone = '',
  String message = '',
}) {
  final p = <String, dynamic>{'share_location': share};
  if (share && position != null) {
    p['lat'] = double.parse(position.lat.toStringAsFixed(6));
    p['lng'] = double.parse(position.lng.toStringAsFixed(6));
    p['accuracy'] = position.accuracy.round().clamp(0, 65535);
  }
  if (zoneId != null && zoneId > 0) {
    p['zone_id'] = zoneId;
    if (communeId != null && communeId > 0) p['commune_id'] = communeId;
  }
  if (phone.trim().isNotEmpty) p['phone'] = phone.trim();
  if (message.trim().isNotEmpty) p['message'] = message.trim();
  return p;
}

const kSosSharePref = 'sos_share_gps';

/// Un seul SOS à la fois. Le jeton `X-Sos-Token` d'un SOS anonyme reste dans ce
/// champ privé : ni état exposé, ni disque, ni journal.
class SosController extends Notifier<SosState> {
  Timer? _tick;
  Timer? _poll;
  Timer? _retry;
  StreamSubscription<OutboxResult>? _sub;
  String? _token;
  String? _id;
  bool _share = true;
  String _phone = '';
  String _message = '';
  int _retryDelay = 0;

  @override
  SosState build() {
    _sub = ref.read(outboxProvider).results.listen(_onOutboxResult);
    ref.onDispose(() {
      _tick?.cancel();
      _poll?.cancel();
      _retry?.cancel();
      _sub?.cancel();
    });
    final zone = ref.read(sessionProvider).user?.raw['profile'];
    int? z, c;
    if (zone is Map) {
      z = ((zone['zone'] as Map?)?['id'] as num?)?.toInt();
      c = ((zone['commune'] as Map?)?['id'] as num?)?.toInt();
    }
    return SosState(zoneId: z, communeId: c);
  }

  /// Préférence « partager ma position » : réglage du compte, sinon choix gardé
  /// sur l'appareil (visiteur·se, vrai par défaut).
  bool get shareDefault {
    final settings = ref.read(sessionProvider).user?.raw['settings'];
    if (settings is Map && settings['sos_share_gps'] is bool) {
      return settings['sos_share_gps'] as bool;
    }
    return ref.read(sharedPreferencesProvider).getBool(kSosSharePref) ?? true;
  }

  void configure({
    required bool share,
    String phone = '',
    String message = '',
  }) {
    _share = share;
    _phone = phone;
    _message = message;
  }

  void setZone(int? zoneId, [int? communeId]) =>
      state = state.copy(zoneId: zoneId, communeId: communeId);

  /// Fin du maintien (2 s) ou confirmation du double appui : décompte local.
  void begin() {
    if (state.phase != SosPhase.idle && state.phase != SosPhase.failed) return;
    state = state.copy(
      phase: SosPhase.countdown,
      countdown: sosCountdownSeconds,
      error: null,
    );
    _tick?.cancel();
    _tick = Timer.periodic(const Duration(seconds: 1), (t) {
      final left = state.countdown - 1;
      if (left <= 0) {
        t.cancel();
        _dispatch();
      } else {
        state = state.copy(countdown: left);
      }
    });
  }

  /// Annulation pendant le décompte : rien n'a quitté l'appareil.
  void cancelCountdown() {
    if (state.phase != SosPhase.countdown) return;
    _tick?.cancel();
    state = state.copy(phase: SosPhase.idle, countdown: sosCountdownSeconds);
  }

  Future<void> _dispatch() async {
    final uuid = const Uuid().v4();
    state = state.copy(entryUuid: uuid);
    GeoPoint? pos;
    if (_share) {
      state = state.copy(phase: SosPhase.locating);
      pos = await ref.read(locationServiceProvider).locate();
      if (state.phase != SosPhase.locating) return;
    }
    if (pos != null || state.zoneId != null) {
      await _submit(uuid, pos);
    } else {
      _manual(uuid);
    }
  }

  void _manual(String uuid) {
    state = state.copy(phase: SosPhase.manual, manualLeft: sosManualSeconds);
    _tick?.cancel();
    // Personne qui ne peut plus agir : l'alerte part quand même, sans zone.
    _tick = Timer.periodic(const Duration(seconds: 1), (t) {
      final left = state.manualLeft - 1;
      if (left <= 0) {
        t.cancel();
        _submit(uuid, null);
      } else {
        state = state.copy(manualLeft: left);
      }
    });
  }

  /// « Envoyer maintenant » du repli manuel.
  void sendNow() {
    final uuid = state.entryUuid;
    if (state.phase != SosPhase.manual || uuid == null) return;
    _tick?.cancel();
    _submit(uuid, null);
  }

  /// Retour depuis le repli manuel : rien n'est encore parti.
  void abortManual() {
    if (state.phase != SosPhase.manual) return;
    _tick?.cancel();
    state = state.copy(phase: SosPhase.idle, entryUuid: null);
  }

  /// Envoi sans attendre la position.
  void skipLocate() {
    final uuid = state.entryUuid;
    if (state.phase != SosPhase.locating || uuid == null) return;
    if (state.zoneId != null) {
      _submit(uuid, null);
    } else {
      _manual(uuid);
    }
  }

  Future<void> _submit(String uuid, GeoPoint? pos) async {
    state = state.copy(phase: SosPhase.sending);
    final payload = buildSosPayload(
      share: _share,
      position: pos,
      zoneId: state.zoneId,
      communeId: state.communeId,
      phone: _phone,
      message: _message,
    );
    // Écrit dans le Hive chiffré avant tout envoi : aucune perte au redémarrage.
    final entry = OutboxEntry(
      uuid: uuid,
      type: OutboxType.sos,
      payload: payload,
      createdAt: DateTime.now(),
    );
    final res = await ref.read(outboxProvider).sendNow(entry);
    if (res != null) {
      _onOutboxResult(res);
      return;
    }
    final left = ref.read(outboxProvider).find(uuid);
    if (left == null) return;
    if (left.status == OutboxStatus.failed) {
      state = state.copy(phase: SosPhase.failed, error: left.failure);
      return;
    }
    state = state.copy(phase: SosPhase.queued);
    _scheduleRetry();
  }

  /// Reprise avec recul (5 s → 60 s) tant que la page reste ouverte, en plus
  /// du retour du réseau (outboxRunner).
  void _scheduleRetry() {
    _retry?.cancel();
    _retryDelay = (_retryDelay == 0 ? 5 : (_retryDelay * 2).clamp(5, 60));
    _retry = Timer(Duration(seconds: _retryDelay), () async {
      if (state.phase != SosPhase.queued) return;
      if (ref.read(isOnlineProvider)) await ref.read(outboxProvider).flush();
      final uuid = state.entryUuid;
      if (state.phase == SosPhase.queued && uuid != null) {
        final left = ref.read(outboxProvider).find(uuid);
        if (left != null && left.status == OutboxStatus.failed) {
          state = state.copy(phase: SosPhase.failed, error: left.failure);
        } else {
          _scheduleRetry();
        }
      }
    });
  }

  void _onOutboxResult(OutboxResult r) {
    if (r.entry.type != OutboxType.sos || r.entry.uuid != state.entryUuid) {
      return;
    }
    _retry?.cancel();
    final data = r.data;
    final token = data['sos_token'];
    if (token is String && token.isNotEmpty) _token = token;
    final status = SosStatus.fromJson(
      Map<String, dynamic>.from(data)..remove('sos_token'),
      meta: data['_meta'] is Map
          ? Map<String, dynamic>.from(data['_meta'] as Map)
          : null,
    );
    _id = status.id;
    state = state.copy(
      phase: status.cancelled ? SosPhase.cancelled : SosPhase.sent,
      status: status,
    );
    _startPolling(status);
  }

  void _startPolling(SosStatus s) {
    _poll?.cancel();
    final secs = s.pollSeconds;
    if (secs == null || _id == null) return;
    _poll = Timer(Duration(seconds: secs), _pollOnce);
  }

  Future<void> _pollOnce() async {
    final id = _id;
    if (id == null) return;
    try {
      final s = await ref.read(alertsApiProvider).sosShow(id, token: _token);
      _apply(s);
    } catch (_) {
      // Réseau coupé : on réessaie au prochain tour.
      _poll = Timer(const Duration(seconds: 10), _pollOnce);
    }
  }

  void _apply(SosStatus s) {
    state = state.copy(
      status: s,
      phase: s.cancelled ? SosPhase.cancelled : SosPhase.sent,
    );
    _startPolling(s);
  }

  /// Repentir (60 s après l'envoi, Q39) : « fausse alerte ».
  Future<void> cancelSent() =>
      _action((api, id) => api.sosCancel(id, token: _token));

  /// « Je suis en sécurité ».
  Future<void> markSafe() =>
      _action((api, id) => api.sosSafe(id, token: _token));

  Future<void> _action(
    Future<SosStatus> Function(AlertsApi, String) call,
  ) async {
    final id = _id;
    if (id == null || state.actionBusy) return;
    state = state.copy(actionBusy: true, error: null);
    try {
      final s = await call(ref.read(alertsApiProvider), id);
      state = state.copy(actionBusy: false);
      _apply(s);
    } catch (_) {
      state = state.copy(actionBusy: false, error: 'action_failed');
    }
  }

  /// Nouvelle alerte : oublie l'état et le jeton.
  void reset() {
    _tick?.cancel();
    _poll?.cancel();
    _retry?.cancel();
    _token = null;
    _id = null;
    _retryDelay = 0;
    state = SosState(zoneId: state.zoneId, communeId: state.communeId);
  }

  /// SOS refusé définitivement (4xx) : on abandonne l'entrée locale.
  Future<void> discardFailed() async {
    final uuid = state.entryUuid;
    if (uuid != null) await ref.read(outboxProvider).remove(uuid);
    reset();
  }
}

final sosControllerProvider = NotifierProvider<SosController, SosState>(
  SosController.new,
);
