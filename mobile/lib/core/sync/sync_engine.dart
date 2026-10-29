import 'dart:async';
import 'dart:math';

import 'package:dio/dio.dart';
import 'package:uuid/uuid.dart';

import '../network/api_exception.dart';
import '../network/idempotency.dart';
import 'collection_store.dart';
import 'sync_queue.dart';

const _uuid = Uuid();

/// Collections du pair (docs/API.md §21.3) : espace pair actif + `cases.create`.
const kPairCollections = ['cases', 'case_followups', 'alerts', 'validation_rules'];

/// Collections de tous les comptes.
const kCommonCollections = ['emergency_contacts', 'directory_contacts', 'thematiques'];

/// Éléments par lot (`xomiton.sync.push_batch_size`).
const kPushBatchSize = 50;

/// Attente avant une nouvelle tentative automatique : exponentielle avec
/// gigue (±25 %), plafonnée à 5 minutes.
Duration syncBackoff(int failures, [Random? random]) {
  if (failures <= 0) return Duration.zero;
  final base = min(300, 5 * pow(2, failures - 1).toInt());
  final jitter = 0.75 + (random ?? Random()).nextDouble() * 0.5;
  return Duration(milliseconds: (base * 1000 * jitter).round());
}

class SyncState {
  const SyncState({
    this.syncing = false,
    this.lastSync,
    this.offline = false,
    this.openConflicts = 0,
    this.lastError,
    this.lastSent = 0,
  });
  final bool syncing;
  final DateTime? lastSync;
  final bool offline;
  final int openConflicts;
  final String? lastError;

  /// Éléments partis lors de la dernière synchronisation.
  final int lastSent;

  SyncState copyWith({
    bool? syncing,
    DateTime? lastSync,
    bool? offline,
    int? openConflicts,
    String? lastError,
    bool clearError = false,
    int? lastSent,
  }) => SyncState(
    syncing: syncing ?? this.syncing,
    lastSync: lastSync ?? this.lastSync,
    offline: offline ?? this.offline,
    openConflicts: openConflicts ?? this.openConflicts,
    lastError: clearError ? null : (lastError ?? this.lastError),
    lastSent: lastSent ?? this.lastSent,
  );
}

class _Offline implements Exception {
  const _Offline();
}

class _Retry implements Exception {
  const _Retry(this.after);
  final Duration? after;
}

/// Moteur de synchronisation de l'espace Pair (P15-12, SOFTWARE_ARCHITECTURE §5).
///
/// Ordre d'un passage : boîte d'envoi Membre (SOS d'abord) → `sync/push` par
/// lots de 50 (`Idempotency-Key` = uuid du lot, **même clé et même corps à
/// chaque rejeu**) → suivis (routes dédiées) → `sync/pull` avec curseur → règles
/// de validation → conflits ouverts. Aucune donnée n'est jamais retirée de la
/// file avant que le serveur ait répondu pour l'élément.
class SyncEngine {
  SyncEngine({
    required this.dio,
    required this.queue,
    required this.pair,
    required this.common,
    required this.ensureDevice,
    this.flushMemberOutbox,
    this.extraPending,
    this.runPair = true,
    this.runCommon = true,
    this.appVersion = '1.0.0',
    DateTime Function()? now,
    Future<void> Function(Duration)? wait,
    Random? random,
    this.onWipe,
  }) : _now = now ?? DateTime.now,
       _wait = wait ?? Future<void>.delayed,
       _random = random ?? Random();

  final Dio dio;
  final SyncQueue queue;
  final CollectionStore pair;
  final CollectionStore common;
  final Future<String> Function() ensureDevice;
  final Future<void> Function()? flushMemberOutbox;

  /// Éléments en attente hors de cette file (boîte d'envoi Membre), pour `heartbeat`.
  final int Function()? extraPending;
  final bool runPair;
  final bool runCommon;
  final String appVersion;
  final DateTime Function() _now;
  final Future<void> Function(Duration) _wait;
  final Random _random;

  /// Effacement à distance demandé par `heartbeat` (`device.wipe`).
  final Future<void> Function()? onWipe;

  final _states = StreamController<SyncState>.broadcast();
  SyncState _state = const SyncState();
  bool _running = false;
  int _failures = 0;
  DateTime? _retryAt;
  bool _deviceReady = false;

  SyncState get state => _state;
  Stream<SyncState> get states => _states.stream;
  bool get running => _running;

  void _emit(SyncState s) {
    _state = s;
    if (!_states.isClosed) _states.add(s);
  }

  /// Une tentative automatique est-elle permise (hors attente après échec) ?
  bool get canAutoRun => _retryAt == null || !_now().isBefore(_retryAt!);

  void _failed() {
    _failures++;
    _retryAt = _now().add(syncBackoff(_failures, _random));
  }

  void _succeeded() {
    _failures = 0;
    _retryAt = null;
  }

  int pendingCount() => queue.open().length + (extraPending?.call() ?? 0);

  // --- passage complet --------------------------------------------------------

  /// Un passage complet. [force] : action de la personne ou retour du réseau
  /// (ignore l'attente après échec). Rend le nombre d'éléments partis.
  Future<int> sync({bool force = false}) async {
    if (_running) return 0;
    if (!force && !canAutoRun) return 0;
    _running = true;
    _emit(_state.copyWith(syncing: true, clearError: true));
    var sent = 0;
    try {
      await _device();
      if (flushMemberOutbox != null) await flushMemberOutbox!();
      if (runPair) {
        sent += await _pushCases();
        sent += await _pushFollowups();
      }
      await _pullAll();
      if (runPair) {
        await refreshRules();
        await refreshConflicts();
      }
      await queue.trimSent();
      _succeeded();
      _emit(_state.copyWith(syncing: false, lastSync: _now(), offline: false, lastSent: sent, clearError: true));
    } on _Offline {
      _emit(_state.copyWith(syncing: false, offline: true));
    } on _Retry {
      _failed();
      _emit(_state.copyWith(syncing: false, lastError: 'retry'));
    } on DioException catch (e) {
      final api = ApiException.from(e);
      if (api.isNetwork) {
        _emit(_state.copyWith(syncing: false, offline: true));
      } else {
        _failed();
        _emit(_state.copyWith(syncing: false, lastError: api.code ?? '${api.status}'));
      }
    } catch (_) {
      _failed();
      _emit(_state.copyWith(syncing: false, lastError: 'error'));
    } finally {
      _running = false;
    }
    return sent;
  }

  Future<void> _device() async {
    if (_deviceReady) return;
    try {
      await ensureDevice();
      _deviceReady = true;
    } on DioException catch (e) {
      if (ApiException.from(e).isNetwork) throw const _Offline();
      rethrow;
    }
  }

  // --- push des dossiers (sync/push) -----------------------------------------

  Future<int> _pushCases() async {
    final all = queue.pending().where((e) => e.isCase).toList();
    if (all.isEmpty) return 0;
    // Lots déjà posés (réponse inconnue) d'abord : même clé, même corps.
    final byBatch = <String, List<QueueEntry>>{};
    final fresh = <QueueEntry>[];
    for (final e in all) {
      if (e.batch != null) {
        byBatch.putIfAbsent(e.batch!, () => []).add(e);
      } else {
        fresh.add(e);
      }
    }
    for (var i = 0; i < fresh.length; i += kPushBatchSize) {
      final chunk = fresh.sublist(i, min(i + kPushBatchSize, fresh.length));
      final id = _uuid.v4();
      for (final e in chunk) {
        e.batch = id;
        await queue.save(e);
      }
      byBatch[id] = chunk;
    }
    var sent = 0;
    for (final entry in byBatch.entries) {
      sent += await _sendBatch(entry.key, entry.value);
    }
    return sent;
  }

  Map<String, dynamic> _itemJson(QueueEntry e) => {
    'uuid': e.uuid,
    'type': 'collecte',
    'op': e.kind == QueueKind.caseCreate ? 'create' : 'update',
    'payload': e.payload,
    'created_at': e.createdAt.toUtc().toIso8601String(),
  };

  Future<int> _sendBatch(String batchId, List<QueueEntry> entries) async {
    entries.sort((a, b) {
      final c = a.createdAt.compareTo(b.createdAt);
      return c != 0 ? c : a.uuid.compareTo(b.uuid);
    });
    // Corps figé : même clé = même corps à chaque rejeu (le nombre d'éléments en
    // attente voyage par `heartbeat`, pas par le lot).
    final body = {'items': [for (final e in entries) _itemJson(e)]};
    Response<dynamic>? res;
    for (var attempt = 0; attempt < 3; attempt++) {
      try {
        res = await dio.post<dynamic>(
          'sync/push',
          data: body,
          options: Options(
            headers: {'Idempotency-Key': batchId},
            validateStatus: (s) => s != null && s >= 200 && s < 300,
            extra: {'no_auto_replay': true},
          ),
        );
      } on DioException catch (e) {
        final api = ApiException.from(e);
        if (api.isNetwork) throw const _Offline();
        if (api.status == 429 && api.code == 'idempotency_in_progress') {
          await _wait(Duration(seconds: api.retryAfter ?? 1));
          continue;
        }
        if (api.status == 422 && api.code == 'idempotency_key_reused') {
          // Erreur du client : le lot repartira sous une clé neuve.
          for (final x in entries) {
            x.batch = null;
            await queue.save(x);
          }
          throw const _Retry(null);
        }
        if (api.status == 422) {
          // Lot refusé en bloc : les éléments restent modifiables, avec le motif.
          for (final x in entries) {
            x
              ..status = QueueStatus.failed
              ..failure = api.code ?? 'validation_failed'
              ..errors = api.errors
              ..batch = null;
            x.attempts++;
            await queue.save(x);
          }
          return 0;
        }
        for (final x in entries) {
          x.attempts++;
          await queue.save(x);
        }
        throw const _Retry(null);
      }
      // 202 `processing` : la requête d'origine court encore → rejouer tel quel.
      final data = res.data is Map ? (res.data as Map)['data'] : null;
      if (res.statusCode == 202 || (data is Map && data['status'] == 'processing' && data['items'] == null)) {
        final ra = int.tryParse(res.headers.value('retry-after') ?? '') ?? (data is Map ? (data['retry_after'] as num?)?.toInt() : null);
        await _wait(Duration(seconds: ra ?? 1));
        res = null;
        continue;
      }
      break;
    }
    if (res == null) {
      for (final x in entries) {
        x.attempts++;
        await queue.save(x);
      }
      throw const _Retry(null);
    }
    final root = res.data is Map ? (res.data as Map)['data'] : null;
    final items = root is Map && root['items'] is List ? (root['items'] as List) : const [];
    final byUuid = <String, Map<String, dynamic>>{
      for (final it in items)
        if (it is Map && it['uuid'] is String) it['uuid'] as String: Map<String, dynamic>.from(it),
    };
    var sent = 0;
    for (final e in entries) {
      final item = byUuid[e.uuid];
      if (item == null) {
        // Pas de réponse pour cet élément : il repart dans un nouveau lot.
        e.batch = null;
        e.attempts++;
        await queue.save(e);
        continue;
      }
      if (await _applyItem(e, item)) sent++;
    }
    return sent;
  }

  /// Applique la réponse d'un élément de lot. Rend `true` s'il est parti.
  Future<bool> _applyItem(QueueEntry e, Map<String, dynamic> item) async {
    e.batch = null;
    final result = item['result'] as String?;
    if (isSyncSuccess(result)) {
      e
        ..status = QueueStatus.sent
        ..sentAt = _now()
        ..failure = null
        ..errors = {}
        ..resultCode = '${item['reference'] ?? item['code'] ?? ''}'.isEmpty ? null : '${item['reference'] ?? item['code']}'
        ..warnings = [
          for (final w in (item['warnings'] as List?) ?? const [])
            if (w is Map) Map<String, dynamic>.from(w),
        ];
      if (e.kind == QueueKind.caseCreate && e.resultCode != null) {
        await pair.putText('code:${e.uuid}', e.resultCode!);
      }
      final current = item['case'];
      if (current is Map && current['id'] != null) {
        await pair.put('cases', current['id'], Map<String, dynamic>.from(current));
      }
      await queue.save(e);
      return true;
    }
    switch (syncOutcome(result)) {
      case SyncItemOutcome.conflict:
        e
          ..status = QueueStatus.conflict
          ..conflicts = [
            for (final c in (item['conflicts'] as List?) ?? const []) ?ConflictRef.from(c),
          ];
        await queue.save(e);
        return false;
      case SyncItemOutcome.rejected:
        final code = item['code'] as String?;
        final status = (item['status'] as num?)?.toInt();
        if (keepInOutbox(status, code)) {
          e.attempts++;
          await queue.save(e);
          return false;
        }
        e
          ..status = QueueStatus.failed
          ..failure = code ?? 'rejected'
          ..errors = _errors(item['errors']);
        e.attempts++;
        await queue.save(e);
        return false;
      default:
        e.attempts++;
        await queue.save(e);
        return false;
    }
  }

  Map<String, List<String>> _errors(Object? raw) {
    final out = <String, List<String>>{};
    if (raw is Map) {
      raw.forEach((k, v) => out['$k'] = v is List ? [for (final x in v) '$x'] : ['$v']);
    }
    return out;
  }

  // --- suivis (routes dédiées) -------------------------------------------------

  Future<int> _pushFollowups() async {
    var sent = 0;
    final pending = queue.pending().where((e) => !e.isCase).toList();
    for (final e in pending) {
      final code = e.caseCode ?? (e.caseUuid == null ? null : pair.text('code:${e.caseUuid}'));
      // Dossier encore en file : le code arrive à l'envoi, on attend.
      if (code == null) continue;
      try {
        final res = e.kind == QueueKind.followupCreate
            ? await dio.post<dynamic>(
                'pair/cases/${Uri.encodeComponent(code)}/followups',
                data: {...e.payload, 'client_uuid': e.uuid},
                options: Options(
                  headers: {'Idempotency-Key': e.uuid},
                  validateStatus: (s) => s != null && s >= 200 && s < 300,
                  extra: {'no_auto_replay': true},
                ),
              )
            : await dio.patch<dynamic>(
                'pair/cases/${Uri.encodeComponent(code)}/followups/${e.payload['id']}',
                data: {'outcome': e.payload['outcome'] ?? 'realise'},
                options: Options(validateStatus: (s) => s != null && s >= 200 && s < 300, extra: {'no_auto_replay': true}),
              );
        final data = res.data is Map ? (res.data as Map)['data'] : null;
        e
          ..status = QueueStatus.sent
          ..sentAt = _now()
          ..caseCode = code
          ..failure = null
          ..errors = {}
          ..resultCode = code;
        if (data is Map && data['id'] != null) e.summary = {...e.summary, 'server_id': data['id']};
        await queue.save(e);
        sent++;
      } on DioException catch (err) {
        final api = ApiException.from(err);
        if (api.isNetwork) throw const _Offline();
        if (keepInOutbox(api.status, api.code)) {
          e.attempts++;
          await queue.save(e);
          continue;
        }
        e
          ..status = QueueStatus.failed
          ..failure = api.code ?? '${api.status}'
          ..errors = api.errors;
        e.attempts++;
        await queue.save(e);
      }
    }
    return sent;
  }

  // --- pull -------------------------------------------------------------------

  Future<void> _pullAll() async {
    if (runPair) await _pull('pair', kPairCollections, pair);
    if (runCommon) await _pull('common', kCommonCollections, common);
  }

  Future<void> _pull(String group, List<String> collections, CollectionStore store) async {
    final cursorKey = 'cursor:$group';
    String? since = store.text(cursorKey);
    var retriedFull = false;
    final pages = <Map<String, dynamic>>[];
    String? lastCursor;
    for (var i = 0; i < 100; i++) {
      Response<dynamic> res;
      try {
        res = await dio.get<dynamic>(
          'sync/pull',
          queryParameters: {'since': ?since, 'collections': collections},
        );
      } on DioException catch (e) {
        final api = ApiException.from(e);
        if (api.isNetwork) throw const _Offline();
        if (api.code == 'cursor_invalid' && !retriedFull) {
          // Point de reprise illisible : tirage complet.
          retriedFull = true;
          since = null;
          pages.clear();
          await store.delete(cursorKey);
          continue;
        }
        rethrow;
      }
      final body = res.data is Map ? res.data as Map : const {};
      final data = body['data'] is Map ? Map<String, dynamic>.from(body['data'] as Map) : <String, dynamic>{};
      final meta = body['meta'] is Map ? Map<String, dynamic>.from(body['meta'] as Map) : <String, dynamic>{};
      pages.add(data);
      lastCursor = meta['cursor'] as String? ?? lastCursor;
      if (meta['emergency_numbers'] is List) {
        await store.putJson('emergency_numbers', {'items': meta['emergency_numbers']});
      }
      if (meta['has_more'] != true || lastCursor == null) break;
      since = lastCursor;
    }
    // Application en bloc, curseur en dernier : un arrêt brutal pendant
    // l'écriture ne laisse jamais un curseur plus avancé que les données.
    final hasReset = pages.any((p) => p.values.any((c) => c is Map && c['reset'] == true));
    if (hasReset) await store.delete(cursorKey);
    for (final page in pages) {
      for (final col in collections) {
        final c = page[col];
        if (c is! Map) continue;
        if (c['reset'] == true) await store.clearCollection(col);
        for (final row in (c['upserts'] as List?) ?? const []) {
          if (row is Map && row['id'] != null) await store.put(col, row['id'], Map<String, dynamic>.from(row));
        }
        for (final d in (c['deletes'] as List?) ?? const []) {
          final id = d is Map ? d['id'] : d;
          if (id != null) await store.remove(col, id);
        }
      }
    }
    if (lastCursor != null) await store.putText(cursorKey, lastCursor);
  }

  // --- règles de validation ----------------------------------------------------

  /// `GET pair/validation-rules` (ETag) : règles actives et listes fermées de la
  /// collecte, gardées chiffrées. Hors ligne, la copie locale sert.
  Future<Map<String, dynamic>?> refreshRules() async {
    final cached = pair.json('rules');
    try {
      final res = await dio.get<dynamic>(
        'pair/validation-rules',
        options: Options(
          headers: {'If-None-Match': ?(cached?['etag'] as String?)},
          validateStatus: (s) => s != null && ((s >= 200 && s < 300) || s == 304),
        ),
      );
      if (res.statusCode == 304 && cached != null) return cached;
      final tag = res.headers.value('etag');
      final entry = {'etag': tag, 'body': res.data};
      await pair.putJson('rules', entry);
      return entry;
    } on DioException catch (e) {
      if (ApiException.from(e).isNetwork) return cached;
      rethrow;
    }
  }

  // --- conflits ---------------------------------------------------------------

  /// Conflits ouverts de l'appareil (`GET sync/conflicts`), gardés pour l'écran.
  Future<List<Map<String, dynamic>>> refreshConflicts() async {
    try {
      final res = await dio.get<dynamic>('sync/conflicts', queryParameters: {'status': 'open', 'per_page': 50});
      final list = [
        for (final r in ((res.data as Map)['data'] as List?) ?? const [])
          if (r is Map) Map<String, dynamic>.from(r),
      ];
      await pair.putJson('conflicts', {'items': list});
      _emit(_state.copyWith(openConflicts: list.length));
      return list;
    } on DioException catch (e) {
      if (ApiException.from(e).isNetwork) return conflicts();
      rethrow;
    }
  }

  List<Map<String, dynamic>> conflicts() => [
    for (final r in (pair.json('conflicts')?['items'] as List?) ?? const [])
      if (r is Map) Map<String, dynamic>.from(r),
  ];

  /// Le pair tranche lui-même (`POST sync/conflicts/{id}/resolve`, §21.5) :
  /// `serveur` garde la valeur en place, `appareil` applique la sienne. Effet
  /// immédiat, sans validation par l'équipe.
  Future<void> resolveConflict(int id, String resolution) async {
    try {
      await dio.post<dynamic>(
        'sync/conflicts/$id/resolve',
        data: {'resolution': resolution},
        options: Options(headers: {'Idempotency-Key': _uuid.v4()}),
      );
    } on DioException catch (e) {
      final api = ApiException.from(e);
      // Déjà tranché ailleurs : on aligne l'appareil, sans erreur.
      if (api.code != 'conflict_already_resolved') rethrow;
    }
    await _afterResolved(id, resolution);
  }

  Future<void> _afterResolved(int id, String resolution) async {
    final left = [for (final c in conflicts()) if (c['id'] != id) c];
    await pair.putJson('conflicts', {'items': left});
    for (final e in queue.list().where((x) => x.status == QueueStatus.conflict)) {
      if (e.conflicts.any((c) => c.id == id)) {
        e.conflicts = [for (final c in e.conflicts) if (c.id != id) c];
        if (e.conflicts.isEmpty) {
          e
            ..status = QueueStatus.sent
            ..sentAt = _now()
            ..resolution = resolution;
        }
        await queue.save(e);
      }
    }
    _emit(_state.copyWith(openConflicts: left.length));
    // La version retenue est appliquée côté serveur : on relit les dossiers.
    unawaited(_pullAfterResolve());
  }

  Future<void> _pullAfterResolve() async {
    try {
      await _pull('pair', kPairCollections, pair);
    } catch (_) {}
  }

  // --- heartbeat --------------------------------------------------------------

  /// Signal de vie (`POST heartbeat`) : à l'ouverture et toutes les 5 minutes au
  /// premier plan. Lit `open_conflicts`, l'effacement à distance et la version
  /// des règles. Rend `false` si la personne est hors ligne ou si l'appareil vient
  /// d'être effacé (rien ne doit alors repartir).
  Future<bool> heartbeat() async {
    try {
      await _device();
      final res = await dio.post<dynamic>(
        'heartbeat',
        data: {'pending': pendingCount(), 'app_version': appVersion},
      );
      final data = (res.data is Map && (res.data as Map)['data'] is Map)
          ? Map<String, dynamic>.from((res.data as Map)['data'] as Map)
          : <String, dynamic>{};
      final device = data['device'] is Map ? Map<String, dynamic>.from(data['device'] as Map) : <String, dynamic>{};
      // `wipe` : {requested_at, reason} tant que l'ordre attend son accusé, sinon null (API §3, §21.6).
      final wipe = data['wipe'] ?? device['wipe'];
      if (wipe != null && wipe != false) {
        await onWipe?.call();
        return false;
      }
      final open = (data['open_conflicts'] as num?)?.toInt();
      if (open != null) _emit(_state.copyWith(openConflicts: open, offline: false));
      final rulesAt = data['rules_updated_at'] as String?;
      if (rulesAt != null && runPair) {
        final known = pair.json('rules');
        final body = known?['body'];
        final knownAt = body is Map && body['meta'] is Map ? (body['meta'] as Map)['updated_at'] : null;
        if (knownAt != rulesAt) {
          try {
            await refreshRules();
          } catch (_) {}
        }
      }
      if (open != null && open != conflicts().length && runPair) {
        try {
          await refreshConflicts();
        } catch (_) {}
      }
      return true;
    } on _Offline {
      _emit(_state.copyWith(offline: true));
      return false;
    } on DioException catch (e) {
      if (ApiException.from(e).isNetwork) {
        _emit(_state.copyWith(offline: true));
        return false;
      }
      return true;
    }
  }

  void dispose() => _states.close();
}
