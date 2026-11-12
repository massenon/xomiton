import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/core_providers.dart';

/// Annonce globale (P18-13, `GET announcements/active`) : seulement dans
/// l'application et l'espace membre, jamais pour un·e visiteur·se.
class Announcement {
  const Announcement({
    required this.id,
    required this.title,
    required this.message,
    this.important = false,
    this.link,
  });

  final int id;
  final String title;
  final String message;
  final bool important;

  /// Chemin interne facultatif (`/forums`…).
  final String? link;

  static Announcement? maybe(Object? v) {
    if (v is! Map) return null;
    final j = Map<String, dynamic>.from(v);
    final id = j['id'];
    if (id is! num) return null;
    return Announcement(
      id: id.toInt(),
      title: '${j['title'] ?? ''}',
      message: '${j['message'] ?? ''}',
      important: j['level'] == 'important',
      link: j['link'] as String?,
    );
  }
}

/// Option d'une liste (valeur et libellé du serveur).
class Choice {
  const Choice(this.value, this.label);
  final String value;
  final String label;

  static List<Choice> list(Object? v) => v is List
      ? [
          for (final e in v.whereType<Map>())
            Choice('${e['value']}', '${e['label']}'),
        ]
      : const [];
}

/// Options d'un formulaire de contribution (types, catégories, limites).
class ContributeOptions {
  const ContributeOptions({
    this.kinds = const [],
    this.categories = const [],
    this.limits = const {},
  });

  final List<Choice> kinds;
  final List<Choice> categories;
  final Map<String, int> limits;

  int limit(String key, int fallback) => limits[key] ?? fallback;

  factory ContributeOptions.fromJson(Map<String, dynamic> j) {
    final limits = <String, int>{};
    if (j['limits'] is Map) {
      (j['limits'] as Map).forEach((k, v) {
        if (v is num) limits['$k'] = v.toInt();
      });
    }
    return ContributeOptions(
      kinds: Choice.list(j['kinds']),
      categories: Choice.list(j['categories']),
      limits: limits,
    );
  }
}

/// Forum (P18-10, `GET forums`) : liste ordonnée par l'administration ; un
/// forum archivé reste lisible mais n'accepte plus de sujet.
class ForumSummary {
  const ForumSummary({
    required this.key,
    required this.name,
    this.description = '',
    this.topics = 0,
    this.archived = false,
  });

  final String key;
  final String name;
  final String description;
  final int topics;
  final bool archived;

  factory ForumSummary.fromJson(Map<String, dynamic> j) => ForumSummary(
    key: '${j['key']}',
    name: '${j['name'] ?? ''}',
    description: '${j['description'] ?? ''}',
    topics: j['topics_count'] is num ? (j['topics_count'] as num).toInt() : 0,
    archived: j['is_archived'] == true,
  );
}

/// Contributions des membres et annonce globale (docs/API.md §5 bis, §11 bis,
/// §16, §16 bis).
class EngagementApi {
  EngagementApi(this._dio);
  final Dio _dio;

  Map<String, dynamic> _data(Response<dynamic> r) {
    final body = r.data is Map ? Map<String, dynamic>.from(r.data as Map) : <String, dynamic>{};
    return body['data'] is Map ? Map<String, dynamic>.from(body['data'] as Map) : <String, dynamic>{};
  }

  Future<Announcement?> activeAnnouncement() async {
    final r = await _dio.get<dynamic>('announcements/active');
    final body = r.data is Map ? r.data as Map : const {};
    return Announcement.maybe(body['data']);
  }

  Future<void> dismissAnnouncement(int id) async {
    await _dio.post<dynamic>('announcements/$id/dismiss', data: <String, dynamic>{});
  }

  Future<ContributeOptions> proposalOptions() async =>
      ContributeOptions.fromJson(_data(await _dio.get<dynamic>('resource-proposals/options')));

  /// `POST resource-proposals` (multipart) ; [filePath] : PDF choisi sur l'appareil.
  Future<void> proposeResource({
    required String title,
    required String kind,
    required String summary,
    String? categoryId,
    String? link,
    String? filePath,
    String? fileName,
    bool credit = false,
  }) async {
    final form = FormData.fromMap({
      'title': title,
      'kind': kind,
      'summary': summary,
      if (categoryId != null && categoryId.isNotEmpty) 'category_id': categoryId,
      if (link != null && link.isNotEmpty) 'external_url': link,
      'credit_author': credit ? '1' : '0',
      if (filePath != null)
        'file': await MultipartFile.fromFile(filePath, filename: fileName ?? 'document.pdf'),
    });
    await _dio.post<dynamic>(
      'resource-proposals',
      data: form,
      options: Options(contentType: Headers.multipartFormDataContentType),
    );
  }

  Future<ContributeOptions> suggestionOptions() async =>
      ContributeOptions.fromJson(_data(await _dio.get<dynamic>('suggestions/options')));

  Future<void> suggest({
    required String category,
    required String message,
    bool anonymous = false,
  }) async {
    await _dio.post<dynamic>(
      'suggestions',
      data: {'category': category, 'message': message, 'is_anonymous': anonymous},
    );
  }

  Future<List<ForumSummary>> forums() async {
    final r = await _dio.get<dynamic>('forums');
    final body = r.data is Map ? r.data as Map : const {};
    final rows = body['data'] is List ? body['data'] as List : const [];
    return [
      for (final e in rows.whereType<Map>())
        ForumSummary.fromJson(Map<String, dynamic>.from(e)),
    ];
  }
}

final engagementApiProvider = Provider<EngagementApi>(
  (ref) => EngagementApi(ref.watch(dioProvider)),
);
