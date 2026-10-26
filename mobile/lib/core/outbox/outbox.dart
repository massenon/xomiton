import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';

import '../network/api_exception.dart';
import '../network/idempotency.dart';
import '../storage/local_db.dart';
import 'sos_retry.dart';

/// Ce qui attend l'envoi (docs/API.md §1.3, ADR-26, FR-ALT-06).
enum OutboxType { sos, report, message, post }

enum OutboxStatus { pending, failed }

/// Entrée de la boîte d'envoi : `{uuid, type, payload, created_at, attempts}`.
///
/// `uuid` est l'identité de l'entrée **et** l'`Idempotency-Key` de chaque
/// rejeu (corps identique, jamais de doublon). `key` ne change qu'après un 422
/// de SOS (champs retirés, clé dérivée). `owner` : compte auteur d'un message
/// ou d'une publication (purgé à la déconnexion) ; vide pour un SOS ou un
/// signalement anonyme.
class OutboxEntry {
  OutboxEntry({
    required this.uuid,
    required this.type,
    required this.payload,
    required this.createdAt,
    this.attempts = 0,
    this.owner,
    this.target,
    String? key,
    this.status = OutboxStatus.pending,
    this.failure,
  }) : key = key ?? uuid;

  final String uuid;
  final OutboxType type;
  Map<String, dynamic> payload;
  final DateTime createdAt;
  int attempts;
  final int? owner;

  /// Conversation (ULID) d'un message.
  final String? target;
  String key;
  OutboxStatus status;
  String? failure;

  bool get isPending => status == OutboxStatus.pending;

  Map<String, dynamic> toJson() => {
    'uuid': uuid,
    'type': type.name,
    'payload': payload,
    'created_at': createdAt.toIso8601String(),
    'attempts': attempts,
    'owner': owner,
    'target': target,
    'key': key,
    'status': status.name,
    'failure': failure,
  };

  static OutboxEntry? fromJson(Object? raw) {
    if (raw is! Map) return null;
    final type = OutboxType.values.where((t) => t.name == raw['type']);
    if (type.isEmpty || raw['uuid'] is! String) return null;
    return OutboxEntry(
      uuid: raw['uuid'] as String,
      type: type.first,
      payload: Map<String, dynamic>.from((raw['payload'] as Map?) ?? const {}),
      createdAt: DateTime.tryParse('${raw['created_at']}') ?? DateTime.now(),
      attempts: (raw['attempts'] as num?)?.toInt() ?? 0,
      owner: (raw['owner'] as num?)?.toInt(),
      target: raw['target'] as String?,
      key: raw['key'] as String?,
      status: raw['status'] == 'failed'
          ? OutboxStatus.failed
          : OutboxStatus.pending,
      failure: raw['failure'] as String?,
    );
  }
}

/// Un envoi abouti (le code de suivi, le jeton SOS… sont dans [data], en
/// mémoire seulement : rien n'est écrit sur disque).
class OutboxResult {
  const OutboxResult(this.entry, this.data, {this.duplicate = false});
  final OutboxEntry entry;
  final Map<String, dynamic> data;
  final bool duplicate;
}

/// Boîte d'envoi locale (Hive chiffré), SOS d'abord, jamais de doublon.
class OutboxService {
  OutboxService({required this.dio, required this.db, this.ownerId});

  final Dio dio;
  final LocalDb db;
  final int? Function()? ownerId;

  final _changes = StreamController<List<OutboxEntry>>.broadcast();
  final _results = StreamController<OutboxResult>.broadcast();
  bool _flushing = false;
  final Set<String> _inFlight = {};
  final Map<String, OutboxResult?> _direct = {};
  final Map<String, ApiException> _errors = {};

  /// SOS dont le jeton de session a été refusé (401) : renvoyé sans compte.
  final Set<String> _anonymousSos = {};

  /// Dernier refus du serveur pour une entrée (mémoire seulement).
  ApiException? errorOf(String uuid) => _errors[uuid];

  Stream<List<OutboxEntry>> get changes => _changes.stream;
  Stream<OutboxResult> get results => _results.stream;

  List<OutboxEntry> list() {
    final out = <OutboxEntry>[];
    for (final k in db.outbox.keys) {
      try {
        final e = OutboxEntry.fromJson(jsonDecode(db.outbox.get(k) ?? 'null'));
        if (e != null) out.add(e);
      } catch (_) {}
    }
    // SOS d'abord, puis par ancienneté.
    out.sort((a, b) {
      final p = (a.type == OutboxType.sos ? 0 : 1).compareTo(
        b.type == OutboxType.sos ? 0 : 1,
      );
      return p != 0 ? p : a.createdAt.compareTo(b.createdAt);
    });
    return out;
  }

  List<OutboxEntry> pending() => list().where((e) => e.isPending).toList();

  OutboxEntry? find(String uuid) {
    final raw = db.outbox.get(uuid);
    if (raw == null) return null;
    return OutboxEntry.fromJson(jsonDecode(raw));
  }

  Future<void> _save(OutboxEntry e) async {
    await db.outbox.put(e.uuid, jsonEncode(e.toJson()));
    _changes.add(list());
  }

  Future<void> add(OutboxEntry e) => _save(e);

  Future<void> remove(String uuid) async {
    await db.outbox.delete(uuid);
    _changes.add(list());
  }

  /// Remet une entrée refusée en file (« Réessayer »).
  Future<void> retry(String uuid) async {
    final e = find(uuid);
    if (e == null) return;
    e.status = OutboxStatus.pending;
    e.failure = null;
    await _save(e);
  }

  /// Envoie tout ce qui attend, SOS en premier. Une entrée n'est jamais
  /// envoyée deux fois en parallèle ; un échec réseau arrête le lot.
  Future<void> flush() async {
    if (_flushing) return;
    _flushing = true;
    try {
      for (final e in pending()) {
        final outcome = await _send(e);
        if (outcome == _Outcome.offline) break;
      }
    } finally {
      _flushing = false;
    }
  }

  /// Envoi immédiat d'une entrée (SOS : sans attendre le lot). Écrite d'abord
  /// dans le coffre chiffré ; renvoie le résultat si l'envoi a abouti.
  Future<OutboxResult?> sendNow(OutboxEntry e) async {
    await _save(e);
    _direct[e.uuid] = null;
    await _send(e);
    return _direct.remove(e.uuid);
  }

  Future<_Outcome> _send(OutboxEntry e) async {
    if (!_inFlight.add(e.uuid)) return _Outcome.kept;
    try {
      return await _attempt(e);
    } finally {
      _inFlight.remove(e.uuid);
    }
  }

  Future<_Outcome> _attempt(OutboxEntry e, {bool stripped = false}) async {
    try {
      final res = await _post(e);
      final body = res.data;
      final data = body is Map && body['data'] is Map
          ? Map<String, dynamic>.from(body['data'] as Map)
          : <String, dynamic>{};
      // 202 `processing` : la requête d'origine court encore, on garde l'entrée.
      if (res.statusCode == 202 ||
          (data['status'] == 'processing' && data['id'] == null)) {
        e.attempts++;
        await _save(e);
        return _Outcome.kept;
      }
      final duplicate =
          res.headers.value('idempotent-replayed') == 'true' ||
          (body is Map &&
              body['meta'] is Map &&
              (body['meta'] as Map)['duplicate'] == true);
      final meta = body is Map && body['meta'] is Map
          ? Map<String, dynamic>.from(body['meta'] as Map)
          : null;
      if (meta != null) data['_meta'] = meta;
      await remove(e.uuid);
      final result = OutboxResult(e, data, duplicate: duplicate);
      if (_direct.containsKey(e.uuid)) _direct[e.uuid] = result;
      _results.add(result);
      return _Outcome.sent;
    } on DioException catch (err) {
      final api = ApiException.from(err);
      // Jeton expiré : un SOS part quand même, sans compte (RG-01), même clé.
      if (e.type == OutboxType.sos &&
          api.status == 401 &&
          _anonymousSos.add(e.uuid)) {
        return _attempt(e, stripped: stripped);
      }
      // SOS refusé par la validation : un seul nouvel envoi sans les champs
      // refusés, sous une clé dérivée (RG-01 : un SOS part toujours).
      if (e.type == OutboxType.sos && api.status == 422 && !stripped) {
        e.payload = stripSosFields(e.payload, api.errors.keys.toList());
        e.key = derivedSosKey(e.key);
        await _save(e);
        return _attempt(e, stripped: true);
      }
      if (keepInOutbox(api.status, api.code) || api.isNetwork) {
        e.attempts++;
        await _save(e);
        return api.isNetwork ? _Outcome.offline : _Outcome.kept;
      }
      _errors[e.uuid] = api;
      e.attempts++;
      e.status = OutboxStatus.failed;
      e.failure = api.code ?? '${api.status}';
      await _save(e);
      return _Outcome.failed;
    }
  }

  /// Le rejeu est à la charge de la boîte d'envoi (même clé, même corps) : pas
  /// de rejeu automatique dans l'intercepteur, qui retarderait l'état « hors ligne ».
  Options _options(Map<String, String> headers, {bool anonymous = false}) =>
      Options(
        headers: headers,
        validateStatus: _is2xx,
        extra: {'no_auto_replay': true, if (anonymous) 'anonymous': true},
      );

  Future<Response<dynamic>> _post(OutboxEntry e) {
    final headers = {'Idempotency-Key': e.key};
    switch (e.type) {
      case OutboxType.sos:
        return dio.post<dynamic>(
          'sos',
          data: e.payload,
          options: _options(headers, anonymous: _anonymousSos.contains(e.uuid)),
        );
      case OutboxType.report:
        return _postReport(e, headers);
      case OutboxType.message:
        return dio.post<dynamic>(
          'conversations/${e.target}/messages',
          data: {...e.payload, 'client_uuid': e.uuid},
          options: _options(headers),
        );
      case OutboxType.post:
        return dio.post<dynamic>(
          'posts',
          data: e.payload,
          options: _options(headers),
        );
    }
  }

  Future<Response<dynamic>> _postReport(
    OutboxEntry e,
    Map<String, String> headers,
  ) async {
    final body = Map<String, dynamic>.from(e.payload);
    final paths =
        (body.remove('attachment_paths') as List?)?.cast<String>() ?? const [];
    final files = paths.where((p) => File(p).existsSync()).toList();
    if (files.isEmpty) {
      return dio.post<dynamic>(
        'signalements',
        data: body,
        options: _options(headers),
      );
    }
    final form = FormData();
    body.forEach((k, v) {
      if (v == null) return;
      if (v is List) {
        for (final x in v) {
          form.fields.add(MapEntry('$k[]', '$x'));
        }
      } else if (v is bool) {
        form.fields.add(MapEntry(k, v ? '1' : '0'));
      } else {
        form.fields.add(MapEntry(k, '$v'));
      }
    });
    for (final p in files) {
      form.files.add(
        MapEntry(
          'attachments[]',
          await MultipartFile.fromFile(
            p,
            filename: p.split(RegExp(r'[\\/]')).last,
          ),
        ),
      );
    }
    return dio.post<dynamic>(
      'signalements',
      data: form,
      options: Options(
        headers: headers,
        validateStatus: _is2xx,
        contentType: Headers.multipartFormDataContentType,
        extra: {'no_auto_replay': true},
      ),
    );
  }

  void dispose() {
    _changes.close();
    _results.close();
  }
}

enum _Outcome { sent, kept, failed, offline }

bool _is2xx(int? s) => s != null && s >= 200 && s < 300;
