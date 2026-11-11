import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/core_providers.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/storage/api_cache.dart';
import '../../../core/sync/collection_store.dart';
import '../../../core/sync/sync_providers.dart';
import 'home_models.dart';

List<Category> _categories(Object? v) => v is List
    ? v.map(Category.maybe).whereType<Category>().toList()
    : const <Category>[];

/// Lectures de l'accueil mobile (docs/API.md §7 et §11). Toute lecture passe
/// par [ApiCache] : ETag + copie Hive chiffrée, lisible hors connexion.
class HomeApi {
  HomeApi(this._dio, this._cache, [this._common]);
  final Dio _dio;
  final ApiCache _cache;

  /// Collections communes de `sync/pull` (thématiques) : repli hors ligne quand
  /// la lecture directe n'a ni réseau ni copie (P15-23).
  final CollectionStore? _common;

  Future<DashboardData> dashboard() async {
    final r = await _cache.get('dashboard', private: true);
    return DashboardData.fromJson(Map<String, dynamic>.from(r.data as Map), stale: r.stale);
  }

  Future<ThemeList> themes({String? categorie, String? q}) async {
    final CachedJson r;
    try {
      r = await _cache.get('thematiques', query: {'categorie': categorie, 'q': q});
    } on DioException catch (e) {
      final rows = ApiException.from(e).isNetwork ? _common?.rows('thematiques') : null;
      if (rows == null || rows.isEmpty) rethrow;
      final needle = (q ?? '').trim().toLowerCase();
      final list = [
        for (final j in rows)
          if (needle.isEmpty ||
              '${j['title']} ${j['summary'] ?? ''}'.toLowerCase().contains(needle))
            ThemeCardData.fromJson(j),
      ];
      return ThemeList(items: list, categories: const [], total: rows.length, stale: true);
    }
    final items = (r.data as List)
        .map((e) => ThemeCardData.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
    return ThemeList(
      items: items,
      categories: _categories(r.meta['categories']),
      total: (r.meta['total_all'] as num?)?.toInt() ?? items.length,
      stale: r.stale,
    );
  }

  Future<ThemeDetailData> theme(String slug) async {
    final CachedJson r;
    try {
      r = await _cache.get('thematiques/$slug');
    } on DioException catch (e) {
      final row = ApiException.from(e).isNetwork
          ? _common?.rows('thematiques').where((j) => j['slug'] == slug).firstOrNull
          : null;
      if (row == null) rethrow;
      return ThemeDetailData.fromJson(row);
    }
    return ThemeDetailData.fromJson(Map<String, dynamic>.from(r.data as Map));
  }

  Future<PagedResources> resources({String? q, String? categorie, int page = 1}) async {
    final r = await _cache.get('ressources', query: {
      'q': q,
      'categorie': categorie,
      'page': page,
    });
    final items = (r.data as List)
        .map((e) => ResourceCardData.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
    final m = r.meta;
    return PagedResources(
      items: items,
      page: (m['current_page'] as num?)?.toInt() ?? page,
      lastPage: (m['last_page'] as num?)?.toInt() ?? 1,
      total: (m['total'] as num?)?.toInt() ?? items.length,
      categories: _categories(m['categories']),
      stale: r.stale,
    );
  }

  /// Lien signé périssable (10 min) : jamais gardé en cache.
  Future<Uri?> resourceDownloadUrl(int id) async {
    final res = await _dio.get<dynamic>('ressources/$id/download');
    final url = ((res.data as Map)['data'] as Map?)?['url'] as String?;
    return url == null ? null : Uri.tryParse(url);
  }

  Future<NotificationsPage> notifications({required String filter, int page = 1}) async {
    final r = await _cache.get('notifications', private: true, query: {'filter': filter, 'page': page});
    final items = (r.data as List)
        .map((e) => NotificationItem.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
    return NotificationsPage(
      items: items,
      page: (r.meta['current_page'] as num?)?.toInt() ?? page,
      lastPage: (r.meta['last_page'] as num?)?.toInt() ?? 1,
      stale: r.stale,
    );
  }

  Future<int> unreadCount() async {
    final r = await _cache.get('notifications/unread-count', private: true);
    return ((r.data as Map)['unread'] as num?)?.toInt() ?? 0;
  }

  Future<void> markRead(String id) async {
    await _dio.post<dynamic>('notifications/$id/read');
  }

  Future<void> markAllRead() async {
    await _dio.post<dynamic>('notifications/read-all');
  }

  Future<void> deleteNotification(String id) async {
    await _dio.delete<dynamic>('notifications/$id');
  }
}

final homeApiProvider = Provider<HomeApi>(
    (ref) => HomeApi(ref.watch(dioProvider), ref.watch(apiCacheProvider), ref.watch(commonStoreProvider)));
