Map<String, dynamic> _map(Object? v) =>
    v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};
List<Map<String, dynamic>> _list(Object? v) =>
    v is List ? v.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList() : const [];
int? _int(Object? v) => v is num ? v.toInt() : int.tryParse('$v');
DateTime? _date(Object? v) => v is String ? DateTime.tryParse(v) : null;

class Category {
  const Category({required this.slug, required this.label, this.icon, this.color, this.count});
  final String slug;
  final String label;
  final String? icon;
  final String? color;
  final int? count;

  static Category? maybe(Object? v) {
    final m = _map(v);
    if (m['slug'] == null) return null;
    return Category(
      slug: '${m['slug']}',
      label: '${m['label'] ?? m['slug']}',
      icon: m['icon'] as String?,
      color: m['color'] as String?,
      count: _int(m['count']),
    );
  }
}

class ThemeCardData {
  const ThemeCardData({
    required this.id,
    required this.slug,
    required this.title,
    this.summary,
    this.coverUrl,
    this.readingMinutes,
    this.category,
    this.publishedAt,
  });

  final int id;
  final String slug;
  final String title;
  final String? summary;
  final String? coverUrl;
  final int? readingMinutes;
  final Category? category;
  final DateTime? publishedAt;

  factory ThemeCardData.fromJson(Map<String, dynamic> j) => ThemeCardData(
        id: _int(j['id']) ?? 0,
        slug: '${j['slug']}',
        title: '${j['title']}',
        summary: j['summary'] as String?,
        coverUrl: j['cover_url'] as String?,
        readingMinutes: _int(j['reading_minutes']),
        category: Category.maybe(j['category']),
        publishedAt: _date(j['published_at']),
      );
}

class Contact {
  const Contact({
    required this.id,
    required this.name,
    this.roleLabel,
    this.org,
    this.phone,
    this.whatsapp,
    this.availability,
  });
  final int id;
  final String name;
  final String? roleLabel;
  final String? org;
  final String? phone;
  final String? whatsapp;
  final String? availability;

  factory Contact.fromJson(Map<String, dynamic> j) => Contact(
        id: _int(j['id']) ?? 0,
        name: '${j['name']}',
        roleLabel: j['role_label'] as String?,
        org: j['org'] as String?,
        phone: j['phone'] as String?,
        whatsapp: j['whatsapp'] as String?,
        availability: j['availability'] as String?,
      );
}

class Faq {
  const Faq(this.question, this.answer);
  final String question;
  final String answer;
}

class ThemeSection {
  const ThemeSection(this.title, this.body);
  final String title;
  final String body;
}

class ThemeDetailData {
  const ThemeDetailData({
    required this.card,
    this.contentWarning,
    this.body,
    this.sections = const [],
    this.contacts = const [],
    this.related = const [],
    this.primaryResource,
    this.faqs = const [],
  });

  final ThemeCardData card;
  final String? contentWarning;
  final String? body;
  final List<ThemeSection> sections;
  final List<Contact> contacts;
  final List<ThemeCardData> related;
  final ResourceCardData? primaryResource;
  final List<Faq> faqs;

  factory ThemeDetailData.fromJson(Map<String, dynamic> j) => ThemeDetailData(
        card: ThemeCardData.fromJson(j),
        contentWarning: j['content_warning'] as String?,
        body: j['body'] as String?,
        sections: _list(j['sections']).map((s) => ThemeSection('${s['title']}', '${s['body']}')).toList(),
        contacts: _list(j['contacts']).map(Contact.fromJson).toList(),
        related: _list(j['related']).map(ThemeCardData.fromJson).toList(),
        primaryResource: j['primary_resource'] is Map
            ? ResourceCardData.fromJson(_map(j['primary_resource']))
            : null,
        faqs: _list(j['faqs']).map((f) => Faq('${f['question']}', '${f['answer']}')).toList(),
      );
}

class ResourceCardData {
  const ResourceCardData({
    required this.id,
    required this.title,
    this.kind,
    this.kindLabel,
    this.summary,
    this.category,
    this.coverUrl,
    this.fileSize,
    this.fileSizeLabel,
    this.durationMinutes,
    this.downloadable = false,
    this.externalUrl,
  });

  final int id;
  final String title;
  final String? kind;
  final String? kindLabel;
  final String? summary;
  final Category? category;
  final String? coverUrl;

  /// Octets ; null sans fichier.
  final int? fileSize;
  final String? fileSizeLabel;
  final int? durationMinutes;
  final bool downloadable;
  final String? externalUrl;

  factory ResourceCardData.fromJson(Map<String, dynamic> j) => ResourceCardData(
        id: _int(j['id']) ?? 0,
        title: '${j['title']}',
        kind: j['kind'] as String?,
        kindLabel: j['kind_label'] as String?,
        summary: j['summary'] as String?,
        category: Category.maybe(j['category']),
        coverUrl: j['cover_url'] as String?,
        fileSize: _int(j['file_size']),
        fileSizeLabel: j['file_size_label'] as String?,
        durationMinutes: _int(j['duration_minutes']),
        downloadable: j['downloadable'] == true,
        externalUrl: j['external_url'] as String?,
      );
}

class PagedResources {
  const PagedResources({
    required this.items,
    required this.page,
    required this.lastPage,
    required this.total,
    required this.categories,
    this.stale = false,
  });
  final List<ResourceCardData> items;
  final int page;
  final int lastPage;
  final int total;
  final List<Category> categories;
  final bool stale;
  bool get hasMore => page < lastPage;
}

class ThemeList {
  const ThemeList({required this.items, required this.categories, required this.total, this.stale = false});
  final List<ThemeCardData> items;
  final List<Category> categories;
  final int total;
  final bool stale;
}

class DashboardTile {
  const DashboardTile(this.key, this.count);
  final String key;
  final int? count;
}

class PeerKpi {
  const PeerKpi({this.collected = 0, this.thisMonth = 0, this.inProgress = 0, this.closed = 0});
  final int collected;
  final int thisMonth;
  final int inProgress;
  final int closed;
}

class PeerTask {
  const PeerTask(this.key, this.count);
  final String key;
  final int count;
}

class PeerSection {
  const PeerSection({required this.kpi, required this.routedOpen, required this.routedNew, required this.tasks});
  final PeerKpi kpi;
  final int routedOpen;
  final int routedNew;
  final List<PeerTask> tasks;
}

class NextEvent {
  const NextEvent({required this.id, required this.title, this.startsAt, this.isOnline = false});
  final String id;
  final String title;
  final DateTime? startsAt;
  final bool isOnline;
}

class DashboardData {
  const DashboardData({
    required this.tiles,
    required this.notifications,
    required this.messages,
    required this.completion,
    required this.profileComplete,
    required this.featured,
    this.nextEvent,
    this.peer,
    this.stale = false,
  });

  final List<DashboardTile> tiles;
  final int notifications;
  final int messages;
  final int completion;
  final bool profileComplete;
  final List<ThemeCardData> featured;
  final NextEvent? nextEvent;
  final PeerSection? peer;
  final bool stale;

  factory DashboardData.fromJson(Map<String, dynamic> j, {bool stale = false}) {
    final counters = _map(j['counters']);
    final profile = _map(j['profile']);
    final peer = j['peer'] is Map ? _map(j['peer']) : null;
    final ev = j['next_event'] is Map ? _map(j['next_event']) : null;
    return DashboardData(
      tiles: _list(j['tiles']).map((t) => DashboardTile('${t['key']}', _int(t['count']))).toList(),
      notifications: _int(counters['notifications']) ?? 0,
      messages: _int(counters['messages']) ?? 0,
      completion: _int(profile['completion']) ?? 0,
      profileComplete: profile['complete'] == true,
      featured: _list(_map(j['featured'])['items']).map(ThemeCardData.fromJson).toList(),
      nextEvent: ev == null
          ? null
          : NextEvent(
              id: '${ev['id']}',
              title: '${ev['title']}',
              startsAt: _date(ev['starts_at']),
              isOnline: ev['is_online'] == true),
      peer: peer == null
          ? null
          : PeerSection(
              kpi: PeerKpi(
                collected: _int(_map(peer['kpi'])['collected']) ?? 0,
                thisMonth: _int(_map(peer['kpi'])['this_month']) ?? 0,
                inProgress: _int(_map(peer['kpi'])['in_progress']) ?? 0,
                closed: _int(_map(peer['kpi'])['closed']) ?? 0,
              ),
              routedOpen: _int(_map(peer['routed_alerts'])['open']) ?? 0,
              routedNew: _int(_map(peer['routed_alerts'])['new']) ?? 0,
              tasks: _list(peer['tasks']).map((t) => PeerTask('${t['key']}', _int(t['count']) ?? 0)).toList(),
            ),
      stale: stale,
    );
  }
}

class NotificationItem {
  const NotificationItem({
    required this.id,
    required this.category,
    required this.title,
    this.body,
    this.read = false,
    this.createdAt,
    this.sensitive = false,
  });
  final String id;
  final String category;
  final String title;
  final String? body;
  final bool read;
  final DateTime? createdAt;
  final bool sensitive;

  NotificationItem asRead() => NotificationItem(
      id: id, category: category, title: title, body: body, read: true, createdAt: createdAt, sensitive: sensitive);

  factory NotificationItem.fromJson(Map<String, dynamic> j) => NotificationItem(
        id: '${j['id']}',
        category: '${j['category'] ?? ''}',
        title: '${j['title'] ?? ''}',
        body: j['body'] as String?,
        read: j['read'] == true,
        createdAt: _date(j['created_at']),
        sensitive: j['sensitive'] == true,
      );
}

class NotificationsPage {
  const NotificationsPage({
    required this.items,
    required this.page,
    required this.lastPage,
    this.stale = false,
  });
  final List<NotificationItem> items;
  final int page;
  final int lastPage;
  final bool stale;
  bool get hasMore => page < lastPage;
}
