Map<String, dynamic> _map(Object? v) =>
    v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};
List<Map<String, dynamic>> _list(Object? v) => v is List
    ? v.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
    : const [];
int _int(Object? v, [int d = 0]) =>
    v is num ? v.toInt() : int.tryParse('$v') ?? d;
DateTime? _date(Object? v) => v is String ? DateTime.tryParse(v) : null;

class Conversation {
  const Conversation({
    required this.id,
    required this.kind,
    required this.name,
    this.preview,
    this.previewHidden = false,
    this.lastAt,
    this.unread = 0,
    this.canSend = true,
    this.blockedByMe = false,
    this.muted = false,
    this.otherId,
  });
  final String id;
  final String kind;
  final String name;
  final String? preview;
  final bool previewHidden;
  final DateTime? lastAt;
  final int unread;
  final bool canSend;
  final bool blockedByMe;
  final bool muted;
  final int? otherId;
  bool get isPrivate => kind == 'private';

  factory Conversation.fromJson(Map<String, dynamic> j) {
    final last = j['last_message'] is Map ? _map(j['last_message']) : null;
    final other = j['other'] is Map ? _map(j['other']) : null;
    return Conversation(
      id: '${j['id']}',
      kind: '${j['kind'] ?? 'private'}',
      name: '${j['name'] ?? ''}',
      preview: last?['preview'] as String?,
      previewHidden: last?['preview_hidden'] == true,
      lastAt: _date(j['last_message_at']),
      unread: _int(j['unread_count']),
      canSend: j['can_send'] != false,
      blockedByMe: j['blocked_by_me'] == true,
      muted: j['muted'] == true,
      otherId: other == null ? null : _int(other['id']),
    );
  }
}

class Message {
  const Message({
    required this.id,
    this.clientUuid,
    this.body,
    required this.deleted,
    required this.mine,
    required this.author,
    this.createdAt,
    this.status,
  });
  final int id;
  final String? clientUuid;
  final String? body;
  final bool deleted;
  final bool mine;
  final String author;
  final DateTime? createdAt;
  final String? status;

  factory Message.fromJson(Map<String, dynamic> j) => Message(
    id: _int(j['id']),
    clientUuid: j['client_uuid'] as String?,
    body: j['body'] as String?,
    deleted: j['deleted'] == true,
    mine: j['mine'] == true,
    author: '${_map(j['author'])['pseudo'] ?? ''}',
    createdAt: _date(j['created_at']),
    status: j['status'] as String?,
  );
}

class Recipient {
  const Recipient(this.id, this.pseudo);
  final int id;
  final String pseudo;
}

class Group {
  const Group({
    required this.id,
    required this.name,
    this.description,
    required this.privacy,
    required this.members,
    this.zone,
    this.membershipStatus,
    this.canJoin = false,
    this.canLeave = false,
    this.canDiscuss = false,
    this.canCancelRequest = false,
  });
  final String id;
  final String name;
  final String? description;
  final String privacy;
  final int members;
  final String? zone;
  final String? membershipStatus;
  final bool canJoin;
  final bool canLeave;
  final bool canDiscuss;
  final bool canCancelRequest;
  bool get isMember => membershipStatus == 'actif';
  bool get isRequested => membershipStatus == 'demande';

  factory Group.fromJson(Map<String, dynamic> j) {
    final can = _map(j['can']);
    final m = j['membership'] is Map ? _map(j['membership']) : null;
    return Group(
      id: '${j['id']}',
      name: '${j['name'] ?? ''}',
      description: j['description'] as String?,
      privacy: '${j['privacy'] ?? 'public'}',
      members: _int(j['members_count']),
      zone: j['zone_label'] as String?,
      membershipStatus: m?['status'] as String?,
      canJoin: can['join'] == true,
      canLeave: can['leave'] == true,
      canDiscuss: can['discuss'] == true,
      canCancelRequest: can['cancel_request'] == true,
    );
  }
}

class EventItem {
  const EventItem({
    required this.id,
    required this.title,
    this.startsAt,
    this.endsAt,
    this.dateLabel,
    this.timeLabel,
    this.locationLabel,
    this.place,
    this.address,
    this.onlineUrl,
    this.exactPlaceReserved = false,
    this.description,
    this.organizer,
    this.goingCount = 0,
    this.isPast = false,
    this.going = false,
    this.reminder = true,
    this.categoryLabel,
  });
  final String id;
  final String title;
  final DateTime? startsAt;
  final DateTime? endsAt;
  final String? dateLabel;
  final String? timeLabel;
  final String? locationLabel;
  final String? place;

  /// Seulement pour les participant·es (la clé est absente sinon).
  final String? address;
  final String? onlineUrl;
  final bool exactPlaceReserved;
  final String? description;
  final String? organizer;
  final int goingCount;
  final bool isPast;
  final bool going;
  final bool reminder;
  final String? categoryLabel;

  factory EventItem.fromJson(Map<String, dynamic> j) {
    final rsvp = j['rsvp'] is Map ? _map(j['rsvp']) : null;
    final cat = j['category'] is Map ? _map(j['category']) : null;
    return EventItem(
      id: '${j['id']}',
      title: '${j['title'] ?? ''}',
      startsAt: _date(j['starts_at']),
      endsAt: _date(j['ends_at']),
      dateLabel: j['date_label'] as String?,
      timeLabel: j['time_label'] as String?,
      locationLabel: j['location_label'] as String?,
      place: j['place'] as String?,
      address: j['address'] as String?,
      onlineUrl: j['online_url'] as String?,
      exactPlaceReserved: j['exact_place_reserved'] == true,
      description: j['description'] as String?,
      organizer: j['organizer'] as String?,
      goingCount: _int(j['going_count']),
      isPast: j['is_past'] == true,
      going: rsvp != null && rsvp['status'] == 'going',
      reminder: rsvp?['reminder'] != false,
      categoryLabel: cat?['label'] as String?,
    );
  }
}

class AssistantLink {
  const AssistantLink(this.label, this.url, this.variant);
  final String label;
  final String url;
  final String variant;
}

class AssistantAnswer {
  const AssistantAnswer({
    required this.text,
    required this.kind,
    required this.emergency,
    required this.suggestions,
    required this.links,
  });
  final String text;
  final String kind;
  final bool emergency;
  final List<String> suggestions;
  final List<AssistantLink> links;

  factory AssistantAnswer.fromJson(Map<String, dynamic> j) => AssistantAnswer(
    text: '${j['text'] ?? ''}',
    kind: '${j['kind'] ?? 'fallback'}',
    emergency: j['emergency'] == true,
    suggestions: ((j['suggestions'] as List?) ?? const [])
        .map((e) => e is Map ? '${e['question'] ?? e['label'] ?? ''}' : '$e')
        .where((s) => s.isNotEmpty)
        .toList(),
    links: _list(j['links'])
        .map(
          (l) => AssistantLink(
            '${l['label'] ?? ''}',
            '${l['url'] ?? ''}',
            '${l['variant'] ?? ''}',
          ),
        )
        .toList(),
  );
}
