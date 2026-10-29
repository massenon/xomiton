/// Modèles de l'espace Admin (docs/API.md §23). Tolérants : un champ absent ou
/// d'un autre type donne une valeur neutre, jamais une erreur. Rien de ce qui
/// est lu ici n'est écrit sur l'appareil (l'espace Admin ne persiste rien).
library;

Map<String, dynamic> _map(Object? v) => v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};
List<Map<String, dynamic>> _list(Object? v) =>
    v is List ? v.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : const [];
String? _str(Object? v) => (v is String && v.isNotEmpty) ? v : (v is num ? '$v' : null);
int? _int(Object? v) => v is num ? v.toInt() : int.tryParse('$v');
bool _bool(Object? v) => v == true;
DateTime? _date(Object? v) => v is String ? DateTime.tryParse(v) : null;
List<String> _strings(Object? v) => v is List ? [for (final e in v) if (e is String) e] : const [];

// --- tableau d'astreinte ------------------------------------------------------------

class AdminAlertCounts {
  const AdminAlertCounts({
    this.fresh = 0,
    this.sosActive = 0,
    this.criticalOpen = 0,
    this.handled = 0,
    this.sosUnacknowledged = 0,
    this.open = 0,
  });
  final int fresh;
  final int sosActive;
  final int criticalOpen;
  final int handled;
  final int sosUnacknowledged;
  final int open;

  factory AdminAlertCounts.fromJson(Map<String, dynamic> j) => AdminAlertCounts(
    fresh: _int(j['new']) ?? 0,
    sosActive: _int(j['sos_active']) ?? 0,
    criticalOpen: _int(j['critical_open']) ?? 0,
    handled: _int(j['handled']) ?? 0,
    sosUnacknowledged: _int(j['sos_unacknowledged']) ?? 0,
    open: _int(j['open']) ?? 0,
  );
}

class AdminMaintenanceBrief {
  const AdminMaintenanceBrief({this.state = 'inactive', this.active = false, this.endsAt});
  final String state;
  final bool active;
  final DateTime? endsAt;
}

class AdminDashboard {
  const AdminDashboard({
    this.alerts,
    this.pendingAccounts,
    this.pendingPeerRequests,
    this.pendingModeration,
    this.openSyncConflicts,
    this.maintenance = const AdminMaintenanceBrief(),
    this.onCallMe = false,
    this.onCallAdmins = 0,
    this.onCallPeers = 0,
    this.pollSeconds = 30,
    this.serverTime,
  });

  /// `null` quand le compte n'a pas la permission du bloc (`alerts.view`, etc.).
  final AdminAlertCounts? alerts;
  final int? pendingAccounts;
  final int? pendingPeerRequests;
  final int? pendingModeration;
  final int? openSyncConflicts;
  final AdminMaintenanceBrief maintenance;
  final bool onCallMe;
  final int onCallAdmins;
  final int onCallPeers;

  /// 10 pour la garde, 30 sinon (le serveur décide).
  final int pollSeconds;
  final DateTime? serverTime;

  /// Inscriptions + demandes de rôle : le compteur de « Inscriptions à valider ».
  int get pendingPeople => (pendingAccounts ?? 0) + (pendingPeerRequests ?? 0);

  factory AdminDashboard.fromJson(Map<String, dynamic> j) {
    final a = j['alerts'];
    final m = _map(j['maintenance']);
    final c = _map(j['on_call']);
    final poll = _int(j['poll_seconds']);
    return AdminDashboard(
      alerts: a is Map ? AdminAlertCounts.fromJson(_map(a)) : null,
      pendingAccounts: _int(j['pending_accounts']),
      pendingPeerRequests: _int(j['pending_peer_requests']),
      pendingModeration: _int(j['pending_moderation']),
      openSyncConflicts: _int(j['open_sync_conflicts']),
      maintenance: AdminMaintenanceBrief(
        state: _str(m['state']) ?? 'inactive',
        active: _bool(m['active']),
        endsAt: _date(m['ends_at']),
      ),
      onCallMe: _bool(c['me']),
      onCallAdmins: _int(c['admins']) ?? 0,
      onCallPeers: _int(c['peers']) ?? 0,
      pollSeconds: (poll == null || poll < 5) ? 30 : poll,
      serverTime: _date(j['server_time']),
    );
  }
}

// --- alertes --------------------------------------------------------------------------

class AdminAlert {
  const AdminAlert({
    required this.id,
    required this.type,
    required this.status,
    this.code,
    this.priority = 'normale',
    this.place = '',
    this.zone,
    this.commune,
    this.routedTo,
    this.acknowledged = false,
    this.acknowledgedAt,
    this.escalated = false,
    this.ageMinutes,
  });

  final String id;
  final String? code;
  final String type;
  final String status;
  final String priority;
  final String place;
  final String? zone;
  final String? commune;
  final String? routedTo;
  final bool acknowledged;
  final DateTime? acknowledgedAt;
  final bool escalated;
  final int? ageMinutes;

  bool get isSos => type == 'sos';
  bool get isNew => status == 'nouveau' && !acknowledged;
  bool get isOpen => status != 'clos' && status != 'traite';

  /// Minutes avant l'escalade nationale d'un SOS sans accusé (5 min par défaut,
  /// `alerts.escalation.sos.escalate_after_minutes`) ; `null` si sans objet.
  int? escalationMinutesLeft({int after = 5}) {
    if (!isSos || acknowledged || escalated || !isOpen || ageMinutes == null) return null;
    return (after - ageMinutes!).clamp(0, after);
  }

  factory AdminAlert.fromJson(Map<String, dynamic> j) => AdminAlert(
    id: '${j['id']}',
    code: _str(j['code']),
    type: _str(j['type']) ?? '',
    status: _str(j['status']) ?? '',
    priority: _str(j['priority']) ?? 'normale',
    place: _str(j['place']) ?? '',
    zone: _str(j['zone']) ?? _str(_map(j['zone'])['name']),
    commune: _str(j['commune']) ?? _str(_map(j['commune'])['name']),
    routedTo: _str(j['routed_to']),
    acknowledged: _bool(j['acknowledged']),
    acknowledgedAt: _date(j['acknowledged_at']),
    escalated: _bool(j['escalated']),
    ageMinutes: _int(j['age_minutes']),
  );
}

class AdminAlertsPage {
  const AdminAlertsPage({required this.items, this.fresh = 0, this.page = 1, this.lastPage = 1, this.total});
  final List<AdminAlert> items;
  final int fresh;
  final int page;
  final int lastPage;
  final int? total;
  bool get hasMore => page < lastPage;
}

/// Position ARRONDIE (≈ 1 km) : le serveur ne sert jamais la position exacte.
class AdminPosition {
  const AdminPosition({required this.lat, required this.lng, this.precisionM, this.rounded = true});
  final double lat;
  final double lng;
  final int? precisionM;
  final bool rounded;

  static AdminPosition? maybe(Object? v) {
    final m = _map(v);
    final lat = m['lat'], lng = m['lng'];
    if (lat is! num || lng is! num) return null;
    return AdminPosition(
      lat: lat.toDouble(),
      lng: lng.toDouble(),
      precisionM: _int(m['precision_m']),
      rounded: m['rounded'] != false,
    );
  }
}

class AdminNotified {
  const AdminNotified({
    required this.key,
    required this.label,
    this.kind,
    this.role,
    this.state,
    this.stateLabel,
    this.phone,
    this.seenAt,
  });
  final String key;
  final String label;
  final String? kind;
  final String? role;
  final String? state;
  final String? stateLabel;

  /// `null` pour le contact de confiance de l'auteur·e.
  final String? phone;
  final DateTime? seenAt;

  factory AdminNotified.fromJson(Map<String, dynamic> j) => AdminNotified(
    key: '${j['key'] ?? j['label'] ?? ''}',
    label: _str(j['label']) ?? '',
    kind: _str(j['kind']),
    role: _str(j['role']),
    state: _str(j['state']),
    stateLabel: _str(j['state_label']),
    phone: _str(j['phone']),
    seenAt: _date(j['seen_at']),
  );
}

class AdminHistoryItem {
  const AdminHistoryItem({required this.label, this.at});
  final String label;
  final DateTime? at;

  factory AdminHistoryItem.fromJson(Map<String, dynamic> j) => AdminHistoryItem(
    label: _str(j['status_label']) ?? _str(j['label']) ?? _str(j['status']) ?? _str(j['event']) ?? '',
    at: _date(j['at'] ?? j['created_at']),
  );
}

class AdminNote {
  const AdminNote({required this.id, required this.author, required this.body, this.at});
  final String id;
  final String author;
  final String body;
  final DateTime? at;

  factory AdminNote.fromJson(Map<String, dynamic> j) => AdminNote(
    id: '${j['id']}',
    author: _str(j['author']) ?? '',
    body: _str(j['body']) ?? '',
    at: _date(j['at'] ?? j['created_at']),
  );
}

class AdminAlertCan {
  const AdminAlertCan({
    this.acknowledge = false,
    this.take = false,
    this.close = false,
    this.reassign = false,
    this.note = false,
    this.statuses = const [],
  });
  final bool acknowledge;
  final bool take;
  final bool close;
  final bool reassign;
  final bool note;
  final List<String> statuses;

  factory AdminAlertCan.fromJson(Map<String, dynamic> j) => AdminAlertCan(
    acknowledge: _bool(j['acknowledge']),
    take: _bool(j['take']),
    close: _bool(j['close']),
    reassign: _bool(j['reassign']),
    note: _bool(j['note']),
    statuses: _strings(j['statuses']),
  );
}

class AdminAlertDetail {
  const AdminAlertDetail({
    required this.summary,
    this.category,
    this.needs = const [],
    this.occurredOn,
    this.description,
    this.author,
    this.contact,
    this.preferredChannel,
    this.position,
    this.history = const [],
    this.notes = const [],
    this.escalations = const [],
    this.notified = const [],
    this.can = const AdminAlertCan(),
  });

  final AdminAlert summary;
  final String? category;
  final List<String> needs;
  final String? occurredOn;
  final String? description;
  final String? author;
  final String? contact;
  final String? preferredChannel;
  final AdminPosition? position;
  final List<AdminHistoryItem> history;
  final List<AdminNote> notes;
  final List<String> escalations;
  final List<AdminNotified> notified;
  final AdminAlertCan can;

  factory AdminAlertDetail.fromJson(Map<String, dynamic> j) {
    String label(Map<String, dynamic> m) => _str(m['label']) ?? _str(m['slug']) ?? '';
    return AdminAlertDetail(
      summary: AdminAlert.fromJson(j),
      category: _str(j['category_label']) ?? _str(j['category']),
      needs: [
        for (final n in (j['needs'] is List ? j['needs'] as List : const []))
          if (n is Map) label(Map<String, dynamic>.from(n)) else if (n is String) n,
      ],
      occurredOn: _str(j['occurred_on']),
      description: _str(j['description']),
      author: _str(j['author']),
      contact: _str(j['contact']),
      preferredChannel: _str(j['preferred_channel_label']) ?? _str(j['preferred_channel']),
      position: AdminPosition.maybe(j['position']),
      history: [for (final h in _list(j['history'])) AdminHistoryItem.fromJson(h)],
      notes: [for (final n in _list(j['notes'])) AdminNote.fromJson(n)],
      escalations: [
        for (final e in (j['escalations'] is List ? j['escalations'] as List : const []))
          if (e is Map) (_str(e['label']) ?? _str(e['level']) ?? '') else '$e',
      ],
      notified: [for (final n in _list(j['notified'])) AdminNotified.fromJson(n)],
      can: AdminAlertCan.fromJson(_map(j['can'])),
    );
  }
}

class AdminContact {
  const AdminContact({required this.id, required this.name, this.role, this.zone});
  final int id;
  final String name;
  final String? role;
  final String? zone;

  factory AdminContact.fromJson(Map<String, dynamic> j) => AdminContact(
    id: _int(j['id']) ?? 0,
    name: _str(j['name']) ?? '',
    role: _str(j['role']),
    zone: _str(j['zone']),
  );
}

// --- personnes : comptes, inscriptions, demandes, invitations --------------------------------

class AdminMemberCan {
  const AdminMemberCan({this.grantPeer = false, this.revokePeer = false, this.changeRole = false, this.validate = false});
  final bool grantPeer;
  final bool revokePeer;
  final bool changeRole;
  final bool validate;
}

/// Compte vu par l'administration (§23.4) : jamais le nom réel, le contact de
/// confiance ni un réglage. L'e-mail et le téléphone ne sont pas lus ici.
class AdminMember {
  const AdminMember({
    required this.id,
    required this.pseudo,
    this.status,
    this.role,
    this.roles = const [],
    this.zone,
    this.isPeer = false,
    this.emailVerified = false,
    this.requestedRole,
    this.createdAt,
    this.lastSeenAt,
    this.can = const AdminMemberCan(),
  });

  final int id;
  final String pseudo;
  final String? status;
  final String? role;
  final List<String> roles;
  final String? zone;
  final bool isPeer;
  final bool emailVerified;
  final String? requestedRole;
  final DateTime? createdAt;
  final DateTime? lastSeenAt;
  final AdminMemberCan can;

  /// Compte d'administration (le rôle admin ne se gère jamais depuis le mobile).
  bool get isStaff => roles.any((r) => r == 'admin' || r == 'superadmin' || r == 'moderateur') ||
      role == 'admin' ||
      role == 'superadmin' ||
      role == 'moderateur';

  factory AdminMember.fromJson(Map<String, dynamic> j) {
    final can = _map(j['can']);
    final zone = j['zone'];
    final roles = _strings(j['roles']);
    return AdminMember(
      id: _int(j['id']) ?? 0,
      pseudo: _str(j['pseudo']) ?? '',
      status: _str(j['status']),
      role: _str(j['role']),
      roles: roles,
      zone: zone is Map ? _str(zone['name']) : _str(zone),
      isPeer: _bool(j['is_peer']) || roles.contains('pair'),
      emailVerified: _bool(j['email_verified']),
      requestedRole: _str(j['requested_role']),
      createdAt: _date(j['created_at']),
      lastSeenAt: _date(j['last_seen_at']),
      can: AdminMemberCan(
        grantPeer: _bool(can['grant_peer']),
        revokePeer: _bool(can['revoke_peer']),
        changeRole: _bool(can['change_role']),
        validate: _bool(can['validate']),
      ),
    );
  }
}

/// Nature d'une ligne de « Inscriptions à valider » (maquette A4).
enum RegistrationKind { invited, roleRequest, member }

/// Une ligne à valider : inscription (`admin/registrations`) ou demande du
/// rôle pair (`admin/role-requests`).
class AdminRegistration {
  const AdminRegistration({
    required this.kind,
    required this.member,
    this.flags = const [],
    this.requestId,
    this.reason,
  });

  final RegistrationKind kind;
  final AdminMember member;

  /// Motifs du signalement (`disposable_email`, `repeated_fingerprint`).
  final List<String> flags;

  /// ULID d'une demande de rôle ; `null` pour une inscription (alors `member.id`).
  final String? requestId;

  /// Mot de la personne (demande de rôle).
  final String? reason;

  bool get isRoleRequest => requestId != null;

  /// Clé stable de la ligne.
  String get key => isRoleRequest ? 'req-$requestId' : 'reg-${member.id}';

  factory AdminRegistration.fromRegistration(Map<String, dynamic> j) {
    final invited = _bool(j['invited']);
    final peerReq = _str(j['kind']) == 'peer_request';
    return AdminRegistration(
      kind: invited ? RegistrationKind.invited : (peerReq ? RegistrationKind.roleRequest : RegistrationKind.member),
      member: AdminMember.fromJson(j),
      flags: _strings(j['flags']),
    );
  }

  factory AdminRegistration.fromRoleRequest(Map<String, dynamic> j) {
    final u = _map(j['user']);
    return AdminRegistration(
      kind: RegistrationKind.roleRequest,
      member: AdminMember.fromJson({...u, 'requested_role': 'pair'}),
      requestId: '${j['id']}',
      reason: _str(j['reason']),
    );
  }
}

class AdminInvitation {
  const AdminInvitation({
    required this.id,
    required this.status,
    this.role = 'pair',
    this.codeHint,
    this.email,
    this.note,
    this.expiresAt,
    this.code,
    this.link,
  });
  final String id;
  final String status;
  final String role;
  final String? codeHint;
  final String? email;
  final String? note;
  final DateTime? expiresAt;

  /// Rendus UNE SEULE FOIS à la création : à copier tout de suite.
  final String? code;
  final String? link;

  bool get pending => status == 'pending';

  factory AdminInvitation.fromJson(Map<String, dynamic> j) => AdminInvitation(
    id: '${j['id']}',
    status: _str(j['status']) ?? 'pending',
    role: _str(j['role']) ?? 'pair',
    codeHint: _str(j['code_hint']),
    email: _str(j['email']),
    note: _str(j['note']),
    expiresAt: _date(j['expires_at']),
    code: _str(j['code']),
    link: _str(j['link']),
  );
}

// --- modération ------------------------------------------------------------------------------

class AdminReport {
  const AdminReport({
    required this.id,
    required this.kind,
    this.reason,
    this.excerpt,
    this.reportedUser,
    this.status,
    this.action,
    this.hidden = false,
    this.reports = 1,
    this.people = 1,
    this.createdAt,
    this.handledAt,
    this.canHide = false,
    this.canRestore = false,
    this.canDismiss = false,
    this.canWarn = false,
  });
  final String id;
  final String kind;
  final String? reason;
  final String? excerpt;
  final String? reportedUser;
  final String? status;
  final String? action;
  final bool hidden;
  final int reports;
  final int people;
  final DateTime? createdAt;
  final DateTime? handledAt;
  final bool canHide;
  final bool canRestore;
  final bool canDismiss;
  final bool canWarn;

  /// Les messages privés ne sont JAMAIS proposés à la modération mobile.
  bool get isPrivateMessage => kind == 'message';

  bool get handled => handledAt != null || (status != null && status != 'pending' && status != 'en_attente');

  factory AdminReport.fromJson(Map<String, dynamic> j) {
    final can = _map(j['can']);
    final user = _map(j['reported_user']);
    return AdminReport(
      id: '${j['id']}',
      kind: _str(j['kind']) ?? '',
      reason: _str(j['reason']),
      excerpt: _str(j['excerpt']),
      reportedUser: _str(user['pseudo']),
      status: _str(j['status']),
      action: _str(j['action']),
      hidden: _bool(j['hidden']),
      reports: _int(j['reports']) ?? 1,
      people: _int(j['people']) ?? 1,
      createdAt: _date(j['created_at']),
      handledAt: _date(j['handled_at']),
      canHide: _bool(can['hide']),
      canRestore: _bool(can['restore']),
      canDismiss: _bool(can['dismiss']),
      canWarn: _bool(can['warn']),
    );
  }
}

// --- maintenance -------------------------------------------------------------------------------

class AdminMaintenance {
  const AdminMaintenance({
    this.state = 'inactive',
    this.stateLabel,
    this.active = false,
    this.message,
    this.startsAt,
    this.endsAt,
    this.overdue = false,
  });
  final String state;
  final String? stateLabel;
  final bool active;
  final String? message;
  final DateTime? startsAt;
  final DateTime? endsAt;
  final bool overdue;

  factory AdminMaintenance.fromJson(Map<String, dynamic> j) => AdminMaintenance(
    state: _str(j['state']) ?? 'inactive',
    stateLabel: _str(j['state_label']),
    active: _bool(j['active']),
    message: _str(j['message']),
    startsAt: _date(j['starts_at']),
    endsAt: _date(j['ends_at']),
    overdue: _bool(j['overdue']),
  );
}
