import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/storage/api_cache.dart';
import '../../../core/sync/collection_store.dart';
import '../../../core/sync/sync_providers.dart';
import '../../../core/sync/sync_queue.dart';
import 'pair_models.dart';

Map<String, dynamic> _map(Object? v) =>
    v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};
List<Map<String, dynamic>> _list(Object? v) => v is List
    ? v.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
    : const [];
int? _int(Object? v) => v is num ? v.toInt() : int.tryParse('$v');

/// Clé de texte d'une tranche d'âge (`50+` → `50plus`, `10-14` → `10_14`).
String ageRangeKey(String value) =>
    'enums_reference_age_range_${value.replaceAll('+', 'plus').replaceAll('-', '_')}';

/// Espace pair (docs/API.md §22). Lectures : réseau d'abord, copie chiffrée du
/// pair (alimentée par `sync/pull`) hors ligne. Écritures : routes dédiées
/// quand elles sont immédiates (alertes) ; sinon via la file de synchronisation.
class PairApi {
  PairApi(this._dio, this._cache, this._store, this._queue);
  final Dio _dio;
  final ApiCache _cache;
  final CollectionStore _store;
  final SyncQueue _queue;

  // --- alertes ---------------------------------------------------------------

  Future<PeerAlertsPage> alerts({String state = 'open', int page = 1}) async {
    try {
      final res = await _dio.get<dynamic>(
        'pair/alerts',
        queryParameters: {'state': state, 'page': page},
      );
      final body = _map(res.data);
      final meta = _map(body['meta']);
      final items = [
        for (final r in _list(body['data'])) PeerAlertSummary.fromJson(r),
      ];
      return PeerAlertsPage(
        items: items,
        newCount: _int(meta['new']) ?? items.where((a) => a.isNew).length,
        page: _int(meta['current_page']) ?? page,
        lastPage: _int(meta['last_page']) ?? 1,
        total: _int(meta['total']),
        onDuty: meta['on_duty'] is bool ? meta['on_duty'] as bool : null,
      );
    } on DioException catch (e) {
      if (!ApiException.from(e).isNetwork) rethrow;
      final rows = _store.rows('alerts');
      if (rows.isEmpty && _store.text('cursor:pair') == null) rethrow;
      final items = [for (final r in rows) PeerAlertSummary.fromJson(r)]
        ..sort(
          (a, b) => (b.createdAt ?? DateTime(0)).compareTo(
            a.createdAt ?? DateTime(0),
          ),
        );
      final shown = state == 'open'
          ? items
                .where((a) => a.status != 'clos' && a.status != 'traite')
                .toList()
          : items;
      return PeerAlertsPage(
        items: shown,
        newCount: shown.where((a) => a.isNew).length,
        stale: true,
      );
    }
  }

  Future<PeerAlertDetail> alert(String id) async {
    final res = await _dio.get<dynamic>(
      'pair/alerts/${Uri.encodeComponent(id)}',
    );
    return PeerAlertDetail.fromJson(_map(_map(res.data)['data']));
  }

  Future<PeerAlertDetail> acknowledge(String id) async {
    final res = await _dio.post<dynamic>(
      'pair/alerts/${Uri.encodeComponent(id)}/acknowledge',
    );
    return PeerAlertDetail.fromJson(_map(_map(res.data)['data']));
  }

  Future<PeerAlertDetail> setStatus(String id, String status) async {
    final res = await _dio.post<dynamic>(
      'pair/alerts/${Uri.encodeComponent(id)}/status',
      data: {'status': status},
    );
    return PeerAlertDetail.fromJson(_map(_map(res.data)['data']));
  }

  // --- règles et listes fermées ------------------------------------------------

  /// Copie locale des règles (alimentée par le moteur) ; défauts si jamais lues.
  ValidationRules rulesLocal() {
    final entry = _store.json('rules');
    return entry == null
        ? const ValidationRules()
        : ValidationRules.fromBody(entry['body']);
  }

  Future<CollectLists> lists() async {
    var zones = <CollectZone>[];
    var types = <IdLabel>[];
    var populations = <IdLabel>[];
    try {
      final z = await _cache.get('zones');
      zones = [
        for (final r in _list(z.data))
          CollectZone(_int(r['id']) ?? 0, '${r['name']}', [
            for (final c in _list(r['communes']))
              IdLabel(_int(c['id']) ?? 0, '${c['name']}'),
          ]),
      ];
      final t = await _cache.get('violence-types');
      types = [
        for (final r in _list(t.data))
          IdLabel(_int(r['id']) ?? 0, '${r['label']}'),
      ];
    } on DioException catch (e) {
      if (!ApiException.from(e).isNetwork) rethrow;
    }
    try {
      // Populations : source `pair/collect-options` (CollectOptions::form) ; tolérant si absente.
      final p = await _cache.get('pair/collect-options', private: true);
      final d = _map(p.data);
      populations = [
        for (final r in _list(d['populations']))
          IdLabel(_int(r['id']) ?? 0, '${r['label']}'),
      ];
    } on DioException catch (_) {
      // Liste vide : le champ le dira, la collecte n'invente rien.
    }
    return CollectLists(
      zones: zones,
      populations: populations,
      violenceTypes: types,
    );
  }

  // --- dossiers --------------------------------------------------------------

  PeerCase _fromRow(
    Map<String, dynamic> r,
    CollectLists l,
    List<Map<String, dynamic>> fu,
  ) {
    final items = [
      for (final f in fu)
        if (_int(f['case_id']) == _int(r['id'])) FollowupItem.fromJson(f),
    ]..sort((a, b) => a.date.compareTo(b.date));
    final status = '${r['status'] ?? 'ouvert'}';
    final locked = r['anonymized'] == true || status == 'archive';
    return PeerCase(
      id: _int(r['id']),
      code: '${r['code'] ?? ''}',
      kind: '${r['kind'] ?? ''}',
      status: status,
      occurredOn: r['occurred_on'] as String?,
      zone: l.zoneName(_int(r['zone_id'])),
      commune: l.communeName(_int(r['commune_id'])),
      population: l.populationLabel(_int(r['population_id'])),
      sex: r['sex'] as String?,
      ageRange: r['age_range'] as String?,
      violence: l.violenceLabel(_int(r['violence_type_id'])),
      referral: r['referral'] as String?,
      notes: r['notes'] as String?,
      followups: items,
      followupsCount: items.length,
      anonymized: r['anonymized'] == true,
      can: CaseCan(update: !locked, addFollowup: !locked),
      createdAt: DateTime.tryParse('${r['created_at']}'),
      updatedAt: DateTime.tryParse('${r['updated_at']}'),
    );
  }

  /// Dossiers en file (collecte hors ligne), présentés comme des dossiers sans code.
  List<PeerCase> queuedCases() => [
    for (final e in _queue.open().where((e) => e.kind == QueueKind.caseCreate))
      PeerCase(
        code: '',
        queuedUuid: e.uuid,
        kind: '${e.payload['kind'] ?? e.summary['kind'] ?? ''}',
        status: 'ouvert',
        occurredOn: e.payload['occurred_on'] as String?,
        commune: e.summary['commune'] as String?,
        violence: e.summary['violence'] as String?,
        createdAt: e.createdAt,
      ),
  ];

  Future<PeerCasesPage> cases({
    String q = '',
    String status = '',
    String kind = '',
    int page = 1,
  }) async {
    try {
      final res = await _dio.get<dynamic>(
        'pair/cases',
        queryParameters: {
          if (q.trim().isNotEmpty) 'q': q.trim(),
          if (status.isNotEmpty) 'status': status,
          if (kind.isNotEmpty) 'kind': kind,
          'page': page,
        },
      );
      final body = _map(res.data);
      final meta = _map(body['meta']);
      return PeerCasesPage(
        items: [for (final r in _list(body['data'])) PeerCase.fromJson(r)],
        page: _int(meta['current_page']) ?? page,
        lastPage: _int(meta['last_page']) ?? 1,
        total: _int(meta['total']),
      );
    } on DioException catch (e) {
      if (!ApiException.from(e).isNetwork) rethrow;
      return _casesLocal(q: q, status: status, kind: kind);
    }
  }

  Future<PeerCasesPage> _casesLocal({
    String q = '',
    String status = '',
    String kind = '',
  }) async {
    final rows = _store.rows('cases');
    if (rows.isEmpty && _store.text('cursor:pair') == null) {
      throw const ApiException(isNetwork: true);
    }
    final l = await lists().catchError((_) => const CollectLists());
    final fu = _store.rows('case_followups');
    final needle = q.trim().toLowerCase();
    final items =
        [
          for (final r in rows)
            if ((needle.isEmpty ||
                    '${r['code']}'.toLowerCase().contains(needle)) &&
                (status.isEmpty
                    ? r['status'] != 'archive'
                    : r['status'] == status) &&
                (kind.isEmpty || r['kind'] == kind))
              _fromRow(r, l, fu),
        ]..sort(
          (a, b) => (b.createdAt ?? DateTime(0)).compareTo(
            a.createdAt ?? DateTime(0),
          ),
        );
    return PeerCasesPage(items: items, total: items.length, stale: true);
  }

  /// Fiche : serveur (labels, `can`), sinon copie locale. Les suivis en file
  /// s'y ajoutent, marqués « En file ».
  Future<PeerCase> caseDetail(String code) async {
    PeerCase c;
    try {
      final res = await _dio.get<dynamic>(
        'pair/cases/${Uri.encodeComponent(code)}',
      );
      c = PeerCase.fromJson(_map(_map(res.data)['data']));
    } on DioException catch (e) {
      if (!ApiException.from(e).isNetwork) rethrow;
      final row = _store.rows('cases').where((r) => r['code'] == code).toList();
      if (row.isEmpty) rethrow;
      final l = await lists().catchError((_) => const CollectLists());
      c = _fromRow(row.first, l, _store.rows('case_followups'));
    }
    return withQueued(c);
  }

  /// Ajoute au dossier ce qui attend dans la file : les suivis à envoyer et les
  /// marques « réalisée » (affichés « En file » jusqu'à confirmation).
  PeerCase withQueued(PeerCase c) {
    final open = _queue.open();
    final done = {
      for (final e in open.where(
        (e) => e.kind == QueueKind.followupDone && e.caseCode == c.code,
      ))
        _int(e.payload['id']): e.uuid,
    };
    final base = [
      for (final f in c.followups)
        done.containsKey(f.id)
            ? FollowupItem(
                id: f.id,
                date: f.date,
                step: f.step,
                outcome: 'realise',
                outcomeLabel: '',
                nextOn: f.nextOn,
                comment: f.comment,
                author: f.author,
                byMe: f.byMe,
                queuedUuid: done[f.id],
                createdAt: f.createdAt,
              )
            : f,
    ];
    final queued = [
      for (final e in open.where(
        (e) => e.kind == QueueKind.followupCreate && e.caseCode == c.code,
      ))
        FollowupItem(
          id: 0,
          date: '${e.payload['date'] ?? ''}',
          step: '${e.payload['step'] ?? ''}',
          outcome: '${e.payload['outcome'] ?? 'planifie'}',
          outcomeLabel: '',
          nextOn: e.payload['next_on'] as String?,
          comment: e.payload['comment'] as String?,
          byMe: true,
          queuedUuid: e.uuid,
          createdAt: e.createdAt,
        ),
    ];
    if (queued.isEmpty && done.isEmpty) return c;
    final all = [...base, ...queued]..sort((a, b) => a.date.compareTo(b.date));
    return c.copyWith(followups: all, followupsCount: all.length);
  }

  /// Dernier dossier de la file porteur du `uuid` : code attribué une fois parti.
  String? codeOf(String uuid) =>
      _store.text('code:$uuid') ?? _queue.find(uuid)?.resultCode;
}

final pairApiProvider = Provider<PairApi>(
  (ref) => PairApi(
    ref.watch(dioProvider),
    ref.watch(apiCacheProvider),
    ref.watch(peerStoreProvider),
    ref.watch(syncQueueProvider),
  ),
);
