Map<String, dynamic> _map(Object? v) =>
    v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};
List<Map<String, dynamic>> _list(Object? v) => v is List
    ? v.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
    : const [];
int? _int(Object? v) => v is num ? v.toInt() : int.tryParse('$v');
DateTime? _date(Object? v) => v is String ? DateTime.tryParse(v) : null;

/// Référentiels publics (docs/API.md §4) : jamais codés en dur.
class Commune {
  const Commune(this.id, this.name);
  final int id;
  final String name;
}

class Zone {
  const Zone(this.id, this.name, this.communes);
  final int id;
  final String name;
  final List<Commune> communes;

  factory Zone.fromJson(Map<String, dynamic> j) => Zone(
    _int(j['id']) ?? 0,
    '${j['name'] ?? ''}',
    _list(j['communes'])
        .map((c) => Commune(_int(c['id']) ?? 0, '${c['name'] ?? ''}'))
        .toList(),
  );
}

class Choice {
  const Choice(this.slug, this.label);
  final String slug;
  final String label;
  factory Choice.fromJson(Map<String, dynamic> j) =>
      Choice('${j['slug'] ?? ''}', '${j['label'] ?? j['slug'] ?? ''}');
}

class Referentials {
  const Referentials({
    required this.zones,
    required this.types,
    required this.needs,
    this.stale = false,
  });
  final List<Zone> zones;
  final List<Choice> types;
  final List<Choice> needs;
  final bool stale;
}

class EmergencyService {
  const EmergencyService(this.label, this.number, this.tel);
  final String label;
  final String number;
  final String tel;
}

class EmergencyContact {
  const EmergencyContact({
    required this.id,
    required this.name,
    this.roleLabel,
    this.org,
    this.phone,
    this.whatsapp,
    this.availability,
    this.zone,
  });
  final int id;
  final String name;
  final String? roleLabel;
  final String? org;
  final String? phone;
  final String? whatsapp;
  final String? availability;
  final String? zone;
}

/// `GET urgences` : numéros et fiches d'urgence, copie Hive pour le hors ligne.
class EmergencyData {
  const EmergencyData({
    required this.services,
    required this.contacts,
    this.stale = false,
  });
  final List<EmergencyService> services;
  final List<EmergencyContact> contacts;
  final bool stale;

  factory EmergencyData.fromJson(
    Map<String, dynamic> d, {
    bool stale = false,
  }) => EmergencyData(
    services: _list(d['services'])
        .map(
          (s) => EmergencyService(
            '${s['label'] ?? ''}',
            '${s['number'] ?? ''}',
            '${s['tel'] ?? ''}',
          ),
        )
        .toList(),
    contacts: _list(d['contacts'])
        .map(
          (c) => EmergencyContact(
            id: _int(c['id']) ?? 0,
            name: '${c['name'] ?? ''}',
            roleLabel: c['role_label'] as String?,
            org: c['org'] as String?,
            phone: c['phone'] as String?,
            whatsapp: c['whatsapp'] as String?,
            availability: c['availability'] as String?,
            zone: c['zone'] as String?,
          ),
        )
        .toList(),
    stale: stale,
  );

  /// Premier numéro d'urgence de l'API (jamais écrit en dur).
  EmergencyService? get firstService =>
      services.isEmpty ? null : services.first;
}

class AlertStep {
  const AlertStep(this.status, this.label, this.at);
  final String status;
  final String? label;
  final DateTime? at;
}

/// `AlertResource` / `AlertTrackingResource` (§6) : jamais le récit, le contact
/// ni la position.
class AlertSummary {
  const AlertSummary({
    required this.type,
    required this.status,
    required this.statusLabel,
    this.statusText,
    this.code,
    this.codeDisplay,
    this.createdAt,
    this.updatedAt,
    this.acknowledged = false,
    this.routedRole,
    this.routedName,
    this.history = const [],
  });
  final String type;
  final String status;
  final String? statusLabel;
  final String? statusText;
  final String? code;
  final String? codeDisplay;
  final DateTime? createdAt;
  final DateTime? updatedAt;
  final bool acknowledged;
  final String? routedRole;
  final String? routedName;
  final List<AlertStep> history;

  factory AlertSummary.fromJson(Map<String, dynamic> j) {
    final routed = _map(j['routed']);
    return AlertSummary(
      type: '${j['type'] ?? 'report'}',
      status: '${j['status'] ?? 'nouveau'}',
      statusLabel: j['status_label'] as String?,
      statusText: j['status_text'] as String?,
      code: j['tracking_code'] as String?,
      codeDisplay:
          (j['tracking_code_display'] ?? j['tracking_code']) as String?,
      createdAt: _date(j['created_at'] ?? j['received_at']),
      updatedAt: _date(j['updated_at']),
      acknowledged: j['acknowledged'] == true,
      routedRole: (routed['role'] ?? j['routed_role']) as String?,
      routedName: routed['name'] as String?,
      history: _list(j['history'])
          .map(
            (h) => AlertStep(
              '${h['status'] ?? ''}',
              h['status_label'] as String?,
              _date(h['at']),
            ),
          )
          .toList(),
    );
  }
}

class NotifiedPerson {
  const NotifiedPerson({
    required this.key,
    required this.label,
    this.role,
    required this.state,
    this.stateLabel,
    this.phone,
  });
  final String key;
  final String label;
  final String? role;
  final String state;
  final String? stateLabel;
  final String? phone;
}

/// État d'un SOS (`SosStatusResource`, §6.4-6.6). `token` (SOS anonyme) ne
/// vit qu'en mémoire.
class SosStatus {
  const SosStatus({
    required this.id,
    required this.status,
    required this.cancelled,
    required this.safe,
    required this.located,
    this.acknowledged = false,
    this.canCancelUntil,
    this.notified = const [],
    this.primaryPhone,
    this.primaryName,
    this.pollSeconds,
  });
  final String id;
  final String status;
  final bool cancelled;
  final bool safe;
  final bool located;
  final bool acknowledged;
  final DateTime? canCancelUntil;
  final List<NotifiedPerson> notified;
  final String? primaryPhone;
  final String? primaryName;

  /// `null` : le sondage s'arrête (SOS terminé).
  final int? pollSeconds;

  factory SosStatus.fromJson(
    Map<String, dynamic> j, {
    Map<String, dynamic>? meta,
  }) {
    final primary = j['primary_contact'] is Map
        ? _map(j['primary_contact'])
        : null;
    final m = meta ?? _map(j['meta']);
    return SosStatus(
      id: '${j['id'] ?? ''}',
      status: '${j['status'] ?? ''}',
      cancelled: j['cancelled'] == true,
      safe: j['safe'] == true,
      located: j['located'] == true,
      acknowledged: j['acknowledged'] == true,
      canCancelUntil: _date(j['can_cancel_until']),
      notified: _list(j['notified'])
          .map(
            (n) => NotifiedPerson(
              key: '${n['key'] ?? ''}',
              label: '${n['label'] ?? ''}',
              role: n['role'] as String?,
              state: '${n['state'] ?? 'pending'}',
              stateLabel: n['state_label'] as String?,
              phone: n['phone'] as String?,
            ),
          )
          .toList(),
      primaryPhone: primary?['phone'] as String?,
      primaryName: (primary?['label'] ?? primary?['name']) as String?,
      pollSeconds: m.containsKey('poll_interval_seconds')
          ? _int(m['poll_interval_seconds'])
          : 10,
    );
  }
}
