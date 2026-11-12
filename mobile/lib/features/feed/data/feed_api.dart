import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/core_providers.dart';
import '../../../core/storage/api_cache.dart';
import 'compose_logic.dart';
import 'feed_models.dart';

/// Réponse de `POST posts` avec médias : 201 (en ligne), 202 + `review_state: pending`
/// (vidéo reçue, en relecture : jamais insérée dans le fil) ou 202 d'idempotence
/// (`status: processing`, la requête d'origine court encore).
class CreatedPost {
  const CreatedPost({this.post, required this.inReview, this.processing = false});
  final Post? post;
  final bool inReview;
  final bool processing;
}

String _mediaType(String name) => switch (fileExtension(name)) {
  'jpg' || 'jpeg' => 'image/jpeg',
  'png' => 'image/png',
  'webp' => 'image/webp',
  'mp4' => 'video/mp4',
  'webm' => 'video/webm',
  _ => 'application/octet-stream',
};

/// Corps multipart de `POST posts` (docs/API.md §8.3) : `images[]` et `images_alt[]` alignés,
/// ou `video` + `video_description` ; sondage en champs `poll[...]`. Booléens en `1` / `0`.
Future<FormData> buildPostForm(ComposeInput c) async {
  final form = FormData();
  void f(String k, Object v) => form.fields.add(MapEntry(k, '$v'));
  f('kind', c.poll ? 'poll' : 'post');
  final body = c.body.trim();
  if (body.isNotEmpty) f('body', body);
  f('visibility', c.visibility);
  f('is_anonymous', c.anonymous ? 1 : 0);
  if (c.poll) {
    f('poll[question]', c.question.trim());
    for (final o in c.sentOptions) {
      f('poll[options][]', o);
    }
    f('poll[multiple]', c.multiple ? 1 : 0);
    f('poll[results_before_vote]', c.resultsBeforeVote ? 1 : 0);
    f('poll[duration_days]', c.duration);
  }
  for (final img in c.images) {
    form.files.add(
      MapEntry(
        'images[]',
        await MultipartFile.fromFile(
          img.path,
          filename: img.name,
          contentType: DioMediaType.parse(_mediaType(img.name)),
        ),
      ),
    );
  }
  // Une entrée par image, même vide : `images_alt[1]` décrit `images[1]`.
  if (c.images.any((i) => i.alt.trim().isNotEmpty)) {
    for (final img in c.images) {
      f('images_alt[]', img.alt.trim());
    }
  }
  final v = c.video;
  if (v != null) {
    form.files.add(
      MapEntry(
        'video',
        await MultipartFile.fromFile(
          v.path,
          filename: v.name,
          contentType: DioMediaType.parse(_mediaType(v.name)),
        ),
      ),
    );
    f('video_description', c.videoDescription.trim());
  }
  return form;
}

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

  /// Publication avec images ou vidéo : multipart, **jamais** par la boîte d'envoi (les fichiers
  /// ne sont pas gardés hors ligne). `Idempotency-Key` : un renvoi après coupure réseau ne crée
  /// pas de doublon. Progression (`onProgress`) et annulation (`cancel`) pour les vidéos.
  Future<CreatedPost> createWithMedia(
    ComposeInput c, {
    String? idempotencyKey,
    CancelToken? cancel,
    void Function(int sent, int total)? onProgress,
  }) async {
    final res = await _dio.post<dynamic>(
      'posts',
      data: await buildPostForm(c),
      cancelToken: cancel,
      onSendProgress: onProgress,
      options: Options(
        contentType: Headers.multipartFormDataContentType,
        headers: {'Idempotency-Key': ?idempotencyKey},
        // Une vidéo de 30 Mo sur une connexion lente dépasse largement 30 s.
        sendTimeout: c.video != null
            ? const Duration(minutes: 10)
            : const Duration(minutes: 2),
        receiveTimeout: const Duration(minutes: 2),
        extra: {'no_auto_replay': true},
      ),
    );
    final body = res.data;
    final data = body is Map && body['data'] is Map
        ? Map<String, dynamic>.from(body['data'] as Map)
        : <String, dynamic>{};
    if (res.statusCode == 202 && data['id'] == null) {
      return const CreatedPost(inReview: false, processing: true);
    }
    final post = data['id'] == null ? null : Post.fromJson(data);
    return CreatedPost(
      post: post,
      inReview: res.statusCode == 202 || (post?.inReview ?? false),
    );
  }

  /// `GET posts/review` : mes vidéos en attente de relecture ou refusées (50 au plus).
  Future<List<Post>> myReview() async {
    final res = await _dio.get<dynamic>('posts/review');
    final data = res.data is Map ? (res.data as Map)['data'] : null;
    return (data is List ? data : const [])
        .whereType<Map>()
        .map((e) => Post.fromJson(Map<String, dynamic>.from(e)))
        .toList();
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
