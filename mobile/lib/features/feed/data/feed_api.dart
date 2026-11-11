import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/core_providers.dart';
import '../../../core/storage/api_cache.dart';
import 'feed_models.dart';

/// Fil et sondages (docs/API.md §8). Le fil est lisible hors ligne (copie
/// Hive chiffrée du compte, première page seulement).
class FeedApi {
  FeedApi(this._dio, this._cache);
  final Dio _dio;
  final ApiCache _cache;

  Future<FeedPage> feed({String? cursor}) async {
    final r = await _cache.get(
      'feed',
      query: {'cursor': cursor},
      private: true,
    );
    final items = (r.data as List)
        .whereType<Map>()
        .map((e) => Post.fromJson(Map<String, dynamic>.from(e)))
        .toList();
    return FeedPage(
      items: items,
      nextCursor: r.meta['next_cursor'] as String?,
      stale: r.stale,
    );
  }

  Future<Post> post(String id) async {
    final res = await _dio.get<dynamic>('posts/$id');
    return Post.fromJson(
      Map<String, dynamic>.from((res.data as Map)['data'] as Map),
    );
  }

  Future<({int likes, bool liked})> like(String id, {required bool on}) async {
    final res = on
        ? await _dio.post<dynamic>('posts/$id/like')
        : await _dio.delete<dynamic>('posts/$id/like');
    final d = Map<String, dynamic>.from((res.data as Map)['data'] as Map);
    return (
      likes: (d['likes_count'] as num?)?.toInt() ?? 0,
      liked: d['liked'] == true,
    );
  }

  Future<Poll> vote(String id, List<int> choices) async {
    final res = await _dio.post<dynamic>(
      'posts/$id/poll/vote',
      data: {'choices': choices},
    );
    return Poll.fromJson(
      Map<String, dynamic>.from((res.data as Map)['data'] as Map),
    );
  }

  Future<Poll> removeVote(String id) async {
    final res = await _dio.delete<dynamic>('posts/$id/poll/vote');
    return Poll.fromJson(
      Map<String, dynamic>.from((res.data as Map)['data'] as Map),
    );
  }

  Future<({List<Comment> items, String? next, int? count})> comments(
    String id, {
    String? cursor,
  }) async {
    final res = await _dio.get<dynamic>(
      'posts/$id/comments',
      queryParameters: {'cursor': ?cursor},
    );
    final body = Map<String, dynamic>.from(res.data as Map);
    final meta = body['meta'] is Map
        ? Map<String, dynamic>.from(body['meta'] as Map)
        : <String, dynamic>{};
    return (
      items: (body['data'] as List)
          .whereType<Map>()
          .map((e) => Comment.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      next: meta['next_cursor'] as String?,
      count: (meta['comments_count'] as num?)?.toInt(),
    );
  }

  Future<Comment> addComment(String id, String body, {int? parentId}) async {
    final res = await _dio.post<dynamic>(
      'posts/$id/comments',
      data: {'body': body, 'parent_id': ?parentId},
    );
    return Comment.fromJson(
      Map<String, dynamic>.from((res.data as Map)['data'] as Map),
    );
  }

  Future<void> deleteComment(int id) => _dio.delete<dynamic>('comments/$id');
  Future<void> deletePost(String id) => _dio.delete<dynamic>('posts/$id');

  Future<void> report(
    String id,
    String reason, {
    String? details,
    bool comment = false,
  }) => _dio.post<dynamic>(
    '${comment ? 'comments' : 'posts'}/$id/report',
    data: {
      'reason': reason,
      if (details != null && details.isNotEmpty) 'details': details,
    },
  );
}

final feedApiProvider = Provider<FeedApi>(
  (ref) => FeedApi(ref.watch(dioProvider), ref.watch(apiCacheProvider)),
);
