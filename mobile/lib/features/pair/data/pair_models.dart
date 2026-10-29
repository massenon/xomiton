Map<String, dynamic> _map(Object? v) => v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};
List<Map<String, dynamic>> _list(Object? v) =>
    v is List ? v.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : const [];
int? _int(Object? v) => v is num ? v.toInt() : int.tryParse('$v');
DateTime? _date(Object? v) => v is String ? DateTime.tryParse(v) : null;
String? _str(Object? v) => v == null ? null : '$v';

// --- Alertes routées (docs/API.md §22) ----------------------------------------

/// Ce que le serveur permet au compte sur une alerte (`can{…}`) : les boutons
/// n'existent que si le serveur les autorise.
class AlertCan {
  const AlertCan({this.acknowledge = false, this.take = false, this.close = false, this.statuses = const []});
  final bool acknowledge;
  final bool take;
  final bool close;
  final List<String> statuses;

  factory AlertCan.fromJson(Object? v) {
    final m = _map(v);
    return AlertCan(
      acknowledge: m['acknowledge'] == true,
      take: m['take'] == true,
      close: m['close'] == true,
      statuses: [for (final s in (m['statuses'] as List?) ?? const []) '$s'],
    );
  }
}

/// Alerte de la liste (`RoutedAlertResource` : résumé, jamais le récit, le contact ni la position).
class PeerAlertSummary {
  const PeerAlertSummary({
    required this.id,
    required this.type,
    required this.typeLabel,
    required this.status,
    required this.statusLabel,
    required this.priority,
    required this.priorityLabel,
    required this.place,
    required this.acknowledged,
    this.categoryLabel,
    this.createdAt,
  });

  final String id;
  final String type;
  final String typeLabel;
  final String status;
  final String statusLabel;
  final String priority;
  final String priorityLabel;
  final String place;
  final bool acknowledged;
  final String? categoryLabel;
  final DateTime? createdAt;

  bool get isSos => type == 'sos';

  /// Nouvelle et pas encore accusée : celle qui presse (escalade à 5 min).
  bool get isNew => status == 'nouveau' && !acknowledged;

  factory PeerAlertSummary.fromJson(Map<String, dynamic> j) => PeerAlertSummary(
    id: '${j['id']}',
    type: '${j['type'] ?? ''}',
    typeLabel: '${j['type_label'] ?? j['type'] ?? ''}',
    status: '${j['status'] ?? ''}',
    statusLabel: '${j['status_label'] ?? j['status'] ?? ''}',
    priority: '${j['priority'] ?? 'normale'}',
    priorityLabel: '${j['priority_label'] ?? j['priority'] ?? ''}',
    place: '${j['place'] ?? ''}',
    acknowledged: j['acknowledged'] == true,
    categoryLabel: _str(j['category_label']),
    createdAt: _date(j['created_at']),
  );
}

class PeerAlertsPage {
  const PeerAlertsPage({
    required this.items,
    this.newCount = 0,
    this.page = 1,
    this.lastPage = 1,
    this.total,
    this.stale = false,
    this.onDuty,
  });
  final List<PeerAlertSummary> items;
  final int newCount;
  final int page;
  final int lastPage;
  final int? total;

  /// Liste issue de la copie locale (hors ligne).
  final bool stale;

  /// `null` : le serveur ne l'annonce pas (on suppose alors « pas de garde »).
  final bool? onDuty;

  bool get hasMore => page < lastPage;
  List<PeerAlertSummary> get open => [for (final a in items) if (a.status != 'clos' && a.status != 'traite') a];
}

class AlertHistoryItem {
  const AlertHistoryItem(this.status, this.statusLabel, this.at);
  final String status;
  final String statusLabel;
  final DateTime? at;
}

class AlertPosition {
  const AlertPosition(this.lat, this.lng, this.accuracy);
  final double lat;
  final double lng;
  final double? accuracy;
}

/// Détail d'une alerte routée (`PeerAlertResource`).
class PeerAlertDetail {
  const PeerAlertDetail({
    required this.summary,
    this.description,
    this.author,
    this.anonymous = false,
    this.contact,
    this.channelLabel,
    this.needs = const [],
    this.position,
    this.history = const [],
    this.can = const AlertCan(),
    this.cancelled = false,
    this.safe = false,
    this.occurredOn,
    this.acknowledgedAt,
  });
  final PeerAlertSummary summary;
  final String? description;
  final String? author;
  final bool anonymous;
  final String? contact;
  final String? channelLabel;
  final List<String> needs;
  final AlertPosition? position;
  final List<AlertHistoryItem> history;
  final AlertCan can;
  final bool cancelled;
  final bool safe;
  final String? occurredOn;
  final DateTime? acknowledgedAt;

  String get id => summary.id;

  factory PeerAlertDetail.fromJson(Map<String, dynamic> j) {
    final pos = j['position'] is Map ? _map(j['position']) : null;
    final lat = pos == null ? null : (pos['lat'] as num?)?.toDouble();
    final lng = pos == null ? null : (pos['lng'] as num?)?.toDouble();
    return PeerAlertDetail(
      summary: PeerAlertSummary.fromJson(j),
      description: _str(j['description']),
      author: _str(j['author']),
      anonymous: j['anonymous'] == true,
      contact: _str(j['contact']),
      channelLabel: _str(j['preferred_channel_label']),
      needs: [for (final n in _list(j['needs'])) '${n['label'] ?? n['slug']}'],
      position: lat == null || lng == null ? null : AlertPosition(lat, lng, (pos!['accuracy'] as num?)?.toDouble()),
      history: [
        for (final h in _list(j['history']))
          AlertHistoryItem('${h['status']}', '${h['status_label'] ?? h['status']}', _date(h['at'])),
      ],
      can: AlertCan.fromJson(j['can']),
      cancelled: j['cancelled'] == true,
      safe: j['safe'] == true,
      occurredOn: _str(j['occurred_on']),
      acknowledgedAt: _date(j['acknowledged_at']),
    );
  }
}

// --- Dossiers (docs/API.md §22) ---------------------------------------------------

class FollowupItem {
  const FollowupItem({
    required this.id,
    required this.date,
    required this.step,
    required this.outcome,
    required this.outcomeLabel,
    this.nextOn,
    this.overdue = false,
    this.comment,
    this.author,
    this.byMe = false,
    this.queuedUuid,
    this.createdAt,
  });

  /// Identifiant serveur (0 : suivi encore en file sur cet appareil).
  final int id;
  final String date;
  final String step;
  final String outcome;
  final String outcomeLabel;
  final String? nextOn;
  final bool overdue;
  final String? comment;
  final String? author;
  final bool byMe;

  /// Suivi pas encore parti : uuid de l'entrée de la file.
  final String? queuedUuid;
  final DateTime? createdAt;

  bool get queued => queuedUuid != null;
  bool get pending => outcome == 'planifie';

  factory FollowupItem.fromJson(Map<String, dynamic> j, {String Function(String)? outcomeLabel}) {
    final outcome = '${j['outcome'] ?? 'planifie'}';
    return FollowupItem(
      id: _int(j['id']) ?? 0,
      date: '${j['date'] ?? ''}',
      step: '${j['step'] ?? ''}',
      outcome: outcome,
      outcomeLabel: '${j['outcome_label'] ?? outcomeLabel?.call(outcome) ?? outcome}',
      nextOn: _str(j['next_on']),
      overdue: j['overdue'] == true,
      comment: _str(j['comment']),
      author: _str(j['author']),
      byMe: j['by_me'] == true,
      createdAt: _date(j['created_at']),
    );
  }
}

class CaseCan {
  const CaseCan({this.update = false, this.addFollowup = false});
  final bool update;
  final bool addFollowup;
}

/// Dossier (liste ou fiche). Les notes ne viennent que du détail ; en liste
/// elles sont toujours absentes (ADR-33).
class PeerCase {
  const PeerCase({
    required this.code,
    required this.kind,
    required this.status,
    this.id,
    this.kindLabel,
    this.statusLabel,
    this.occurredOn,
    this.zone,
    this.commune,
    this.population,
    this.sex,
    this.sexLabel,
    this.ageRange,
    this.ageRangeLabel,
    this.violence,
    this.referral,
    this.notes,
    this.followups = const [],
    this.followupsCount = 0,
    this.anonymized = false,
    this.can = const CaseCan(),
    this.queuedUuid,
    this.createdAt,
    this.updatedAt,
    this.closedAt,
  });

  final int? id;
  final String code;
  final String kind;
  final String? kindLabel;
  final String status;
  final String? statusLabel;
  final String? occurredOn;
  final String? zone;
  final String? commune;
  final String? population;
  final String? sex;
  final String? sexLabel;
  final String? ageRange;
  final String? ageRangeLabel;
  final String? violence;
  final String? referral;
  final String? notes;
  final List<FollowupItem> followups;
  final int followupsCount;
  final bool anonymized;
  final CaseCan can;

  /// Dossier encore en file (collecte hors ligne) : `code` est alors vide.
  final String? queuedUuid;
  final DateTime? createdAt;
  final DateTime? updatedAt;
  final DateTime? closedAt;

  bool get queued => queuedUuid != null;
  bool get locked => anonymized || status == 'archive';

  PeerCase copyWith({List<FollowupItem>? followups, int? followupsCount, CaseCan? can}) => PeerCase(
    id: id,
    code: code,
    kind: kind,
    status: status,
    kindLabel: kindLabel,
    statusLabel: statusLabel,
    occurredOn: occurredOn,
    zone: zone,
    commune: commune,
    population: population,
    sex: sex,
    sexLabel: sexLabel,
    ageRange: ageRange,
    ageRangeLabel: ageRangeLabel,
    violence: violence,
    referral: referral,
    notes: notes,
    followups: followups ?? this.followups,
    followupsCount: followupsCount ?? this.followupsCount,
    anonymized: anonymized,
    can: can ?? this.can,
    queuedUuid: queuedUuid,
    createdAt: createdAt,
    updatedAt: updatedAt,
    closedAt: closedAt,
  );

  factory PeerCase.fromJson(Map<String, dynamic> j) {
    final fu = [for (final f in _list(j['followups'])) FollowupItem.fromJson(f)];
    final can = _map(j['can']);
    return PeerCase(
      id: _int(j['id']),
      code: '${j['code'] ?? ''}',
      kind: '${j['kind'] ?? ''}',
      kindLabel: _str(j['kind_label']),
      status: '${j['status'] ?? ''}',
      statusLabel: _str(j['status_label']),
      occurredOn: _str(j['occurred_on']),
      zone: _str(_map(j['zone'])['name']),
      commune: _str(_map(j['commune'])['name']),
      population: _str(_map(j['population'])['label']),
      sex: _str(j['sex']),
      sexLabel: _str(j['sex_label']),
      ageRange: _str(j['age_range']),
      ageRangeLabel: _str(j['age_range_label']),
      violence: _str(_map(j['violence_type'])['label']),
      referral: _str(j['referral']),
      notes: _str(j['notes']),
      followups: fu,
      followupsCount: _int(j['followups_count']) ?? fu.length,
      anonymized: j['anonymized'] == true,
      can: CaseCan(update: can['update'] == true, addFollowup: can['add_followup'] == true),
      createdAt: _date(j['created_at']),
      updatedAt: _date(j['updated_at']),
      closedAt: _date(j['closed_at']),
    );
  }
}

class PeerCasesPage {
  const PeerCasesPage({required this.items, this.page = 1, this.lastPage = 1, this.total, this.stale = false});
  final List<PeerCase> items;
  final int page;
  final int lastPage;
  final int? total;
  final bool stale;
  bool get hasMore => page < lastPage;
}

// --- Règles de validation et listes fermées de la collecte ---------------------------

class ValidationRule {
  const ValidationRule({required this.key, this.label, this.params = const {}, this.locked = false});
  final String key;
  final String? label;
  final Map<String, dynamic> params;
  final bool locked;
}

class Option {
  const Option(this.value, this.label);
  final String value;
  final String label;
}

/// Règles actives et listes fermées (`GET pair/validation-rules`, ETag, copie
/// chiffrée). Règle absente du catalogue = active (API §22).
class ValidationRules {
  const ValidationRules({
    this.rules = const [],
    this.updatedAt,
    this.ageRanges = const [],
    this.referrals = const [],
    this.followupSteps = const [],
    this.notesMinLength = 20,
    this.loaded = false,
  });

  final List<ValidationRule> rules;
  final String? updatedAt;
  final List<Option> ageRanges;
  final List<String> referrals;
  final List<String> followupSteps;
  final int notesMinLength;

  /// Les règles viennent bien du serveur (ou de sa copie), pas d'un défaut.
  final bool loaded;

  bool isActive(String key) => !loaded || rules.any((r) => r.key == key);
  ValidationRule? rule(String key) {
    for (final r in rules) {
      if (r.key == key) return r;
    }
    return null;
  }

  factory ValidationRules.fromBody(Object? body) {
    final b = _map(body);
    final meta = _map(b['meta']);
    final options = _map(meta['options']);
    return ValidationRules(
      loaded: true,
      updatedAt: _str(meta['updated_at']),
      rules: [
        for (final r in _list(b['data']))
          ValidationRule(
            key: '${r['key']}',
            label: _str(r['label']),
            params: _map(r['params']),
            locked: r['locked'] == true,
          ),
      ],
      ageRanges: [for (final a in _list(options['age_ranges'])) Option('${a['value']}', '${a['label'] ?? a['value']}')],
      referrals: [for (final r in (options['referrals'] as List?) ?? const []) '$r'],
      followupSteps: [for (final r in (options['followup_steps'] as List?) ?? const []) '$r'],
      notesMinLength: _int(options['notes_min_length']) ?? 20,
    );
  }
}

class IdLabel {
  const IdLabel(this.id, this.label);
  final int id;
  final String label;
}

/// Listes de la collecte : départements et communes, populations, types de violence.
class CollectLists {
  const CollectLists({this.zones = const [], this.populations = const [], this.violenceTypes = const []});
  final List<CollectZone> zones;
  final List<IdLabel> populations;
  final List<IdLabel> violenceTypes;

  String? zoneName(int? id) {
    for (final z in zones) {
      if (z.id == id) return z.name;
    }
    return null;
  }

  String? communeName(int? id) {
    for (final z in zones) {
      for (final c in z.communes) {
        if (c.id == id) return c.label;
      }
    }
    return null;
  }

  String? populationLabel(int? id) {
    for (final p in populations) {
      if (p.id == id) return p.label;
    }
    return null;
  }

  String? violenceLabel(int? id) {
    for (final v in violenceTypes) {
      if (v.id == id) return v.label;
    }
    return null;
  }

  /// Commune appartenant au département : règle `commune_in_zone`.
  bool communeInZone(int? communeId, int? zoneId) {
    for (final z in zones) {
      if (z.id != zoneId) continue;
      return z.communes.any((c) => c.id == communeId);
    }
    return false;
  }
}

class CollectZone {
  const CollectZone(this.id, this.name, this.communes);
  final int id;
  final String name;
  final List<IdLabel> communes;
}
