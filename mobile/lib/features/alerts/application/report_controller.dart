import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../../core/outbox/outbox.dart';
import '../../../core/platform/platform_services.dart';
import '../../../core/providers/core_providers.dart';
import '../../auth/application/session_controller.dart';

/// Limites de `StoreReportRequest` (docs/API.md §6.1).
const kDescriptionMin = 20;
const kDescriptionMax = 4000;
const kAttachmentsMax = 5;

/// Six étapes (Q11 a) : type, faits, besoins + lieu, preuves, contact, vérification.
enum ReportStep { type, facts, needs, evidence, contact, review }

final RegExp _email = RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$');

/// Numéro béninois (mêmes règles que `PhoneBj::normalize()` et le web).
bool isPhoneBj(String value) {
  var digits = value.trim().replaceAll(RegExp(r'[\s.\-()]'), '');
  if (!RegExp(r'^\+?\d+$').hasMatch(digits)) return false;
  digits = digits.replaceFirst('+', '');
  for (final prefix in const ['00229', '229']) {
    if (digits.startsWith(prefix) &&
        const [8, 10].contains(digits.length - prefix.length)) {
      digits = digits.substring(prefix.length);
      break;
    }
  }
  if (digits.length == 8) digits = '01$digits';
  return digits.length == 10 && digits.startsWith('01');
}

bool isPhoneOrEmail(String v) =>
    v.trim().isNotEmpty && (_email.hasMatch(v.trim()) || isPhoneBj(v));

class ReportForm {
  const ReportForm({
    this.step = 0,
    this.type = 'report',
    this.category,
    this.description = '',
    this.occurredOn,
    this.needs = const [],
    this.zoneId,
    this.communeId,
    this.position,
    this.files = const [],
    this.anonymous = true,
    this.contactable = false,
    this.contact = '',
    this.channel,
    this.sending = false,
    this.error,
    this.outcome,
  });

  final int step;
  final String type;
  final String? category;
  final String description;
  final DateTime? occurredOn;
  final List<String> needs;
  final int? zoneId;
  final int? communeId;
  final GeoPoint? position;
  final List<PickedEvidence> files;
  final bool anonymous;
  final bool contactable;
  final String contact;
  final String? channel;
  final bool sending;
  final String? error;
  final ReportOutcome? outcome;

  ReportForm copy({
    int? step,
    String? type,
    Object? category = _keep,
    String? description,
    Object? occurredOn = _keep,
    List<String>? needs,
    Object? zoneId = _keep,
    Object? communeId = _keep,
    Object? position = _keep,
    List<PickedEvidence>? files,
    bool? anonymous,
    bool? contactable,
    String? contact,
    Object? channel = _keep,
    bool? sending,
    Object? error = _keep,
    ReportOutcome? outcome,
  }) => ReportForm(
    step: step ?? this.step,
    type: type ?? this.type,
    category: identical(category, _keep) ? this.category : category as String?,
    description: description ?? this.description,
    occurredOn: identical(occurredOn, _keep)
        ? this.occurredOn
        : occurredOn as DateTime?,
    needs: needs ?? this.needs,
    zoneId: identical(zoneId, _keep) ? this.zoneId : zoneId as int?,
    communeId: identical(communeId, _keep) ? this.communeId : communeId as int?,
    position: identical(position, _keep)
        ? this.position
        : position as GeoPoint?,
    files: files ?? this.files,
    anonymous: anonymous ?? this.anonymous,
    contactable: contactable ?? this.contactable,
    contact: contact ?? this.contact,
    channel: identical(channel, _keep) ? this.channel : channel as String?,
    sending: sending ?? this.sending,
    error: identical(error, _keep) ? this.error : error as String?,
    outcome: outcome ?? this.outcome,
  );

  /// Charge utile partagée avec le web (`alerts.needs`, `category`, `lat/lng`, pièces).
  Map<String, dynamic> payload() {
    final p = <String, dynamic>{
      'type': type,
      if (type == 'report' && category != null) 'category': category,
      'description': description.trim(),
      if (occurredOn != null) 'occurred_on': _ymd(occurredOn!),
      if (needs.isNotEmpty) 'needs': needs,
      'zone_id': zoneId,
      if (communeId != null) 'commune_id': communeId,
      if (position != null) ...{
        'lat': double.parse(position!.lat.toStringAsFixed(6)),
        'lng': double.parse(position!.lng.toStringAsFixed(6)),
        'accuracy': position!.accuracy.round().clamp(0, 65535),
      },
      'anonymous': anonymous,
      'contactable': contactable,
      if (contactable) ...{
        'contact': contact.trim(),
        'preferred_channel': channel,
      },
      if (files.isNotEmpty)
        'attachment_paths': files.map((f) => f.path).toList(),
    };
    return p;
  }

  /// Erreurs d'une étape : champ → clé d'attribut (le message est composé par l'écran).
  Map<String, String> errors(ReportStep s, {DateTime? today}) {
    final e = <String, String>{};
    switch (s) {
      case ReportStep.type:
        if (type == 'report' && (category == null || category!.isEmpty)) {
          e['category'] = 'category';
        }
      case ReportStep.facts:
        final n = description.trim().runes.length;
        if (n == 0) {
          e['description'] = 'required';
        } else if (n < kDescriptionMin) {
          e['description'] = 'min';
        } else if (n > kDescriptionMax) {
          e['description'] = 'max';
        }
        final t = today ?? DateTime.now();
        if (occurredOn != null && _ymd(occurredOn!).compareTo(_ymd(t)) > 0) {
          e['occurred_on'] = 'future';
        }
      case ReportStep.needs:
        if (type == 'need' && needs.isEmpty) e['needs'] = 'needs';
        if (zoneId == null) e['zone_id'] = 'zone_id';
      case ReportStep.evidence:
        if (files.length > kAttachmentsMax) e['attachments'] = 'too_many';
      case ReportStep.contact:
        if (contactable) {
          if (contact.trim().isEmpty) {
            e['contact'] = 'contact';
          } else if (!isPhoneOrEmail(contact)) {
            e['contact'] = 'invalid';
          }
          if (channel == null) e['preferred_channel'] = 'preferred_channel';
        }
      case ReportStep.review:
        break;
    }
    return e;
  }

  /// Première étape en erreur, ou -1.
  int firstInvalid() {
    for (final s in ReportStep.values) {
      if (errors(s).isNotEmpty) return s.index;
    }
    return -1;
  }
}

const Object _keep = Object();

String _ymd(DateTime d) =>
    '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

/// Fin d'un envoi : code de suivi reçu, ou mise en attente hors connexion.
class ReportOutcome {
  const ReportOutcome({
    this.code,
    this.uuid,
    this.queued = false,
    this.duplicate = false,
    this.need = false,
  });
  final String? code;
  final String? uuid;
  final bool queued;
  final bool duplicate;
  final bool need;
}

const _draftKey = 'report';
const _draftDays = 7;

/// Brouillon par étape (Hive chiffré). Jamais le contact, la position ni les
/// preuves : `anonymous` et `contactable` y figurent, pas de quoi recontacter.
class ReportDraft {
  const ReportDraft(this.data, this.savedAt);
  final Map<String, dynamic> data;
  final DateTime savedAt;
}

class ReportController extends AutoDisposeNotifier<ReportForm> {
  @override
  ReportForm build() {
    final zone = ref.read(sessionProvider).user?.raw['profile'];
    int? z, c;
    if (zone is Map) {
      z = ((zone['zone'] as Map?)?['id'] as num?)?.toInt();
      c = ((zone['commune'] as Map?)?['id'] as num?)?.toInt();
    }
    return ReportForm(zoneId: z, communeId: c);
  }

  void update(ReportForm Function(ReportForm) f) => state = f(state);

  void setType(String t) => state = state.copy(
    type: t,
    category: t == 'need' ? null : state.category,
  );

  void toggleNeed(String slug, bool on) {
    final list = [...state.needs]..remove(slug);
    if (on) list.add(slug);
    state = state.copy(needs: list);
  }

  Future<void> goTo(int step) async {
    state = state.copy(
      step: step.clamp(0, ReportStep.values.length - 1),
      error: null,
    );
    await _saveDraft();
  }

  Future<void> next() async {
    if (state.errors(ReportStep.values[state.step]).isNotEmpty) return;
    await goTo(state.step + 1);
  }

  Future<void> back() => goTo(state.step - 1);

  // --- Brouillon -----------------------------------------------------------

  Future<void> _saveDraft() async {
    final s = state;
    final data = {
      'step': s.step,
      'type': s.type,
      'category': s.category,
      'description': s.description,
      'occurred_on': s.occurredOn == null ? null : _ymd(s.occurredOn!),
      'needs': s.needs,
      'zone_id': s.zoneId,
      'commune_id': s.communeId,
      'contactable': s.contactable,
      'preferred_channel': s.channel,
      'anonymous': s.anonymous,
      'saved_at': DateTime.now().toIso8601String(),
    };
    await ref.read(localDbProvider).drafts.put(_draftKey, jsonEncode(data));
  }

  ReportDraft? findDraft() {
    final raw = ref.read(localDbProvider).drafts.get(_draftKey);
    if (raw == null) return null;
    try {
      final m = Map<String, dynamic>.from(jsonDecode(raw) as Map);
      final at = DateTime.tryParse('${m['saved_at']}');
      if (at == null || DateTime.now().difference(at).inDays > _draftDays) {
        return null;
      }
      return ReportDraft(m, at);
    } catch (_) {
      return null;
    }
  }

  void restoreDraft() {
    final d = findDraft();
    if (d == null) return;
    final m = d.data;
    state = ReportForm(
      step: ((m['step'] as num?)?.toInt() ?? 0).clamp(
        0,
        ReportStep.values.length - 1,
      ),
      type: m['type'] == 'need' ? 'need' : 'report',
      category: m['category'] as String?,
      description: '${m['description'] ?? ''}',
      occurredOn: DateTime.tryParse('${m['occurred_on']}'),
      needs: ((m['needs'] as List?) ?? const []).map((e) => '$e').toList(),
      zoneId: (m['zone_id'] as num?)?.toInt(),
      communeId: (m['commune_id'] as num?)?.toInt(),
      contactable: m['contactable'] == true,
      channel: m['preferred_channel'] as String?,
      anonymous: m['anonymous'] != false,
    );
  }

  Future<void> clearDraft() =>
      ref.read(localDbProvider).drafts.delete(_draftKey);

  // --- Envoi ---------------------------------------------------------------

  /// Écrit l'entrée dans la boîte d'envoi (Hive chiffré), tente l'envoi, et
  /// efface le brouillon dès que l'entrée est en sécurité.
  Future<void> submit() async {
    final bad = state.firstInvalid();
    if (bad >= 0) {
      state = state.copy(step: bad);
      return;
    }
    if (state.sending) return;
    state = state.copy(sending: true, error: null);
    final session = ref.read(sessionProvider);
    final form = state;
    final entry = OutboxEntry(
      uuid: const Uuid().v4(),
      type: OutboxType.report,
      payload: form.payload(),
      createdAt: DateTime.now(),
      owner: !form.anonymous && session.authenticated ? session.user?.id : null,
    );
    final outbox = ref.read(outboxProvider);
    final res = await outbox.sendNow(entry);
    await clearDraft();
    if (res != null) {
      state = state.copy(
        sending: false,
        outcome: ReportOutcome(
          code:
              (res.data['tracking_code_display'] ?? res.data['tracking_code'])
                  as String?,
          duplicate: res.duplicate,
          need: form.type == 'need',
        ),
      );
      return;
    }
    final left = outbox.find(entry.uuid);
    if (left != null && left.status == OutboxStatus.failed) {
      // Refus du serveur : on corrige puis on renvoie sous une nouvelle clé.
      final api = outbox.errorOf(entry.uuid);
      await outbox.remove(entry.uuid);
      await _saveDraft();
      state = state.copy(sending: false, error: api?.message ?? 'error');
      return;
    }
    state = state.copy(
      sending: false,
      outcome: ReportOutcome(
        uuid: entry.uuid,
        queued: true,
        need: form.type == 'need',
      ),
    );
  }
}

final reportControllerProvider =
    NotifierProvider.autoDispose<ReportController, ReportForm>(
      ReportController.new,
    );
