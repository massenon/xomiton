import 'dart:async';
import 'dart:convert';

import '../storage/local_db.dart';

/// Ce qui attend l'envoi dans l'espace Pair (docs/API.md §21.2 et §22).
///
/// - `caseCreate` : `sync/push` type `collecte`, `op: create` ;
/// - `caseUpdate` : `sync/push` type `collecte`, `op: update` (`changes` + `base`,
///   dont le changement de statut) ;
/// - `followupCreate` : `POST pair/cases/{code}/followups` (`client_uuid`) ;
/// - `followupDone` : `PATCH pair/cases/{code}/followups/{id}` (« marquer comme réalisée »).
enum QueueKind { caseCreate, caseUpdate, followupCreate, followupDone }

enum QueueStatus { pending, failed, conflict, sent }

class ConflictRef {
  const ConflictRef(this.field, this.id);
  final String field;
  final int id;
  Map<String, dynamic> toJson() => {'field': field, 'id': id};
  static ConflictRef? from(Object? raw) {
    if (raw is! Map) return null;
    final id = (raw['id'] ?? raw['conflict_id']) as num?;
    if (id == null) return null;
    return ConflictRef('${raw['field'] ?? ''}', id.toInt());
  }
}

/// Entrée de la file : `uuid` = identité **et** clé d'idempotence (jamais de
/// doublon, même après un rejeu manuel).
class QueueEntry {
  QueueEntry({
    required this.uuid,
    required this.kind,
    required this.payload,
    required this.createdAt,
    this.status = QueueStatus.pending,
    this.attempts = 0,
    this.batch,
    this.caseCode,
    this.caseUuid,
    this.failure,
    this.errors = const {},
    this.conflicts = const [],
    this.warnings = const [],
    this.resultCode,
    this.sentAt,
    this.resolution,
    this.summary = const {},
  });

  final String uuid;
  final QueueKind kind;
  Map<String, dynamic> payload;
  final DateTime createdAt;
  QueueStatus status;
  int attempts;

  /// Lot (`Idempotency-Key` de `sync/push`) : posé avant l'envoi et gardé tant
  /// que la réponse n'est pas connue, pour rejouer **le même corps** sous la même clé.
  String? batch;

  /// Dossier visé par un suivi ou une modification : code serveur…
  String? caseCode;

  /// … ou uuid de la collecte encore en file (le code arrive à l'envoi).
  String? caseUuid;

  /// Code stable d'un refus (`validation_failed`, `case_locked`…).
  String? failure;
  Map<String, List<String>> errors;
  List<ConflictRef> conflicts;

  /// Avertissements de plausibilité rendus à l'enregistrement.
  List<Map<String, dynamic>> warnings;

  /// Code du dossier attribué par le serveur (collecte) une fois envoyé.
  String? resultCode;
  DateTime? sentAt;

  /// `serveur` | `appareil` une fois le conflit tranché.
  String? resolution;

  /// Résumé affichable sans le contenu sensible (nature, date, commune, étape…).
  Map<String, dynamic> summary;

  bool get isPending => status == QueueStatus.pending;
  bool get isCase =>
      kind == QueueKind.caseCreate || kind == QueueKind.caseUpdate;

  Map<String, dynamic> toJson() => {
    'uuid': uuid,
    'kind': kind.name,
    'payload': payload,
    'created_at': createdAt.toUtc().toIso8601String(),
    'status': status.name,
    'attempts': attempts,
    'batch': batch,
    'case_code': caseCode,
    'case_uuid': caseUuid,
    'failure': failure,
    'errors': errors,
    'conflicts': [for (final c in conflicts) c.toJson()],
    'warnings': warnings,
    'result_code': resultCode,
    'sent_at': sentAt?.toUtc().toIso8601String(),
    'resolution': resolution,
    'summary': summary,
  };

  static QueueEntry? fromJson(Object? raw) {
    if (raw is! Map) return null;
    final kind = QueueKind.values.where((k) => k.name == raw['kind']);
    if (kind.isEmpty || raw['uuid'] is! String) return null;
    final errs = <String, List<String>>{};
    if (raw['errors'] is Map) {
      (raw['errors'] as Map).forEach((k, v) {
        errs['$k'] = v is List ? [for (final x in v) '$x'] : ['$v'];
      });
    }
    return QueueEntry(
      uuid: raw['uuid'] as String,
      kind: kind.first,
      payload: Map<String, dynamic>.from((raw['payload'] as Map?) ?? const {}),
      createdAt: DateTime.tryParse('${raw['created_at']}') ?? DateTime.now(),
      status: QueueStatus.values.firstWhere(
        (s) => s.name == raw['status'],
        orElse: () => QueueStatus.pending,
      ),
      attempts: (raw['attempts'] as num?)?.toInt() ?? 0,
      batch: raw['batch'] as String?,
      caseCode: raw['case_code'] as String?,
      caseUuid: raw['case_uuid'] as String?,
      failure: raw['failure'] as String?,
      errors: errs,
      conflicts: [
        for (final c in (raw['conflicts'] as List?) ?? const [])
          ?ConflictRef.from(c),
      ],
      warnings: [
        for (final w in (raw['warnings'] as List?) ?? const [])
          if (w is Map) Map<String, dynamic>.from(w),
      ],
      resultCode: raw['result_code'] as String?,
      sentAt: DateTime.tryParse('${raw['sent_at']}'),
      resolution: raw['resolution'] as String?,
      summary: Map<String, dynamic>.from((raw['summary'] as Map?) ?? const {}),
    );
  }
}

/// File d'envoi de l'espace Pair, dans le coffre chiffré du pair (`q:<uuid>`).
/// Les envois partis sont gardés quelques instants pour l'affichage (« Envoyés »).
class SyncQueue {
  SyncQueue(this.box, {this.keepSent = 10});
  final StringBox box;
  final int keepSent;

  static const _prefix = 'q:';
  final _changes = StreamController<List<QueueEntry>>.broadcast();
  Stream<List<QueueEntry>> get changes => _changes.stream;

  List<QueueEntry> list() {
    final out = <QueueEntry>[];
    for (final k in box.keys) {
      if (!k.startsWith(_prefix)) continue;
      try {
        final e = QueueEntry.fromJson(jsonDecode(box.get(k) ?? 'null'));
        if (e != null) out.add(e);
      } catch (_) {}
    }
    out.sort((a, b) {
      final c = a.createdAt.compareTo(b.createdAt);
      return c != 0 ? c : a.uuid.compareTo(b.uuid);
    });
    return out;
  }

  /// En attente ou à corriger / trancher : ce qui n'est pas encore parti pour de bon.
  List<QueueEntry> open() =>
      list().where((e) => e.status != QueueStatus.sent).toList();
  List<QueueEntry> pending() => list().where((e) => e.isPending).toList();
  List<QueueEntry> sent() =>
      list().where((e) => e.status == QueueStatus.sent).toList();

  QueueEntry? find(String uuid) {
    final raw = box.get('$_prefix$uuid');
    if (raw == null) return null;
    try {
      return QueueEntry.fromJson(jsonDecode(raw));
    } catch (_) {
      return null;
    }
  }

  Future<void> save(QueueEntry e) async {
    await box.put('$_prefix${e.uuid}', jsonEncode(e.toJson()));
    _changes.add(list());
  }

  Future<void> remove(String uuid) async {
    await box.delete('$_prefix$uuid');
    _changes.add(list());
  }

  /// Garde les [keepSent] derniers envois partis, supprime les plus anciens.
  Future<void> trimSent() async {
    final sent = this.sent()
      ..sort(
        (a, b) => (b.sentAt ?? b.createdAt).compareTo(a.sentAt ?? a.createdAt),
      );
    for (final e in sent.skip(keepSent)) {
      await box.delete('$_prefix${e.uuid}');
    }
    _changes.add(list());
  }

  /// Remet une entrée refusée en file (après correction).
  Future<void> retry(String uuid) async {
    final e = find(uuid);
    if (e == null) return;
    e
      ..status = QueueStatus.pending
      ..failure = null
      ..errors = {}
      ..batch = null;
    await save(e);
  }

  void dispose() => _changes.close();
}
