import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/core_providers.dart';
import 'community_models.dart';

/// Messagerie, groupes, événements, assistant (docs/API.md §5, §12, §13, §15).
/// Lectures sans cache disque : les corps de messages ne sont jamais gardés en
/// clair hors du Hive chiffré (et la messagerie répond `no-store`).
class CommunityApi {
  CommunityApi(this._dio);
  final Dio _dio;

  Map<String, dynamic> _body(Response<dynamic> r) =>
      Map<String, dynamic>.from(r.data as Map);
  List<Map<String, dynamic>> _rows(Object? v) => (v as List)
      .whereType<Map>()
      .map((e) => Map<String, dynamic>.from(e))
      .toList();

  // --- Conversations --------------------------------------------------------
  Future<List<Conversation>> conversations({
    String filter = 'all',
    String? q,
  }) async {
    final r = await _dio.get<dynamic>(
      'conversations',
      queryParameters: {'filter': filter, 'q': ?q},
    );
    return _rows(_body(r)['data']).map(Conversation.fromJson).toList();
  }

  Future<Conversation> conversation(String id) async {
    final r = await _dio.get<dynamic>('conversations/$id');
    return Conversation.fromJson(
      Map<String, dynamic>.from(_body(r)['data'] as Map),
    );
  }

  /// Historique (plus récent d'abord) ou, avec [after], sondage court.
  Future<({List<Message> items, String? next, int? lastId})> messages(
    String id, {
    String? cursor,
    int? after,
  }) async {
    final r = await _dio.get<dynamic>(
      'conversations/$id/messages',
      queryParameters: {'cursor': ?cursor, 'after': ?after},
    );
    final b = _body(r);
    final meta = b['meta'] is Map
        ? Map<String, dynamic>.from(b['meta'] as Map)
        : <String, dynamic>{};
    return (
      items: _rows(b['data']).map(Message.fromJson).toList(),
      next: meta['next_cursor'] as String?,
      lastId: (meta['last_id'] as num?)?.toInt(),
    );
  }

  Future<void> markRead(String id) async {
    await _dio.post<dynamic>(
      'conversations/$id/read',
      data: <String, dynamic>{},
    );
  }

  Future<List<Recipient>> recipients(String q) async {
    final r = await _dio.get<dynamic>(
      'conversations/recipients',
      queryParameters: {if (q.isNotEmpty) 'q': q},
    );
    return _rows(_body(r)['data'])
        .map((e) => Recipient(_i(e['id']), '${e['pseudo']}'))
        .toList();
  }

  int _i(Object? v) => v is num ? v.toInt() : int.tryParse('$v') ?? 0;

  /// Conversation privée (existante ou nouvelle).
  Future<Conversation> startPrivate(int userId) async {
    final r = await _dio.post<dynamic>(
      'conversations',
      data: {'kind': 'private', 'user_id': userId},
    );
    return Conversation.fromJson(
      Map<String, dynamic>.from(_body(r)['data'] as Map),
    );
  }

  Future<Conversation> block(String id, {required bool on}) async {
    final r = on
        ? await _dio.post<dynamic>('conversations/$id/block')
        : await _dio.delete<dynamic>('conversations/$id/block');
    return Conversation.fromJson(
      Map<String, dynamic>.from(_body(r)['data'] as Map),
    );
  }

  Future<void> reportConversation(
    String id,
    String reason, {
    String? details,
  }) async {
    await _dio.post<dynamic>(
      'conversations/$id/report',
      data: {
        'reason': reason,
        if (details != null && details.isNotEmpty) 'details': details,
      },
    );
  }

  Future<void> hide(String id) async {
    await _dio.post<dynamic>('conversations/$id/leave');
  }

  // --- Groupes --------------------------------------------------------------
  Future<List<Group>> groups({String tab = 'discover', String? q}) async {
    final r = await _dio.get<dynamic>(
      'groups',
      queryParameters: {'tab': tab, 'q': ?q},
    );
    return _rows(_body(r)['data']).map(Group.fromJson).toList();
  }

  Future<Group> group(String id) async {
    final r = await _dio.get<dynamic>('groups/$id');
    return Group.fromJson(Map<String, dynamic>.from(_body(r)['data'] as Map));
  }

  Future<Group> joinGroup(String id) async {
    final r = await _dio.post<dynamic>('groups/$id/join');
    return Group.fromJson(Map<String, dynamic>.from(_body(r)['data'] as Map));
  }

  Future<Group> leaveGroup(String id) async {
    final r = await _dio.post<dynamic>('groups/$id/leave');
    return Group.fromJson(Map<String, dynamic>.from(_body(r)['data'] as Map));
  }

  Future<String> groupConversation(String id) async {
    final r = await _dio.post<dynamic>('groups/$id/conversation');
    return '${(_body(r)['data'] as Map)['id']}';
  }

  Future<List<Map<String, dynamic>>> groupPosts(String id) async {
    final r = await _dio.get<dynamic>('groups/$id/posts');
    return _rows(_body(r)['data']);
  }

  // --- Événements -----------------------------------------------------------
  Future<List<EventItem>> events({
    String filter = 'upcoming',
    String? q,
  }) async {
    final r = await _dio.get<dynamic>(
      'events',
      queryParameters: {'filter': filter, 'q': ?q},
    );
    return _rows(_body(r)['data']).map(EventItem.fromJson).toList();
  }

  Future<EventItem> event(String id) async {
    final r = await _dio.get<dynamic>('events/$id');
    return EventItem.fromJson(
      Map<String, dynamic>.from(_body(r)['data'] as Map),
    );
  }

  Future<EventItem> rsvp(
    String id, {
    required bool going,
    bool reminder = true,
  }) async {
    final r = await _dio.post<dynamic>(
      'events/$id/rsvp',
      data: {'going': going, if (going) 'reminder': reminder},
    );
    return EventItem.fromJson(
      Map<String, dynamic>.from(_body(r)['data'] as Map),
    );
  }

  // --- Assistant ------------------------------------------------------------
  /// Rien n'est enregistré (`persist` jamais envoyé) : l'assistant mobile ne garde aucune conversation.
  Future<AssistantAnswer> ask(String question) async {
    final r = await _dio.post<dynamic>(
      'assistant/question',
      data: {'question': question},
    );
    return AssistantAnswer.fromJson(
      Map<String, dynamic>.from(_body(r)['data'] as Map),
    );
  }
}

final communityApiProvider = Provider<CommunityApi>(
  (ref) => CommunityApi(ref.watch(dioProvider)),
);
