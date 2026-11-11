import 'dart:convert';

import 'package:dio/dio.dart';
import '../network/api_exception.dart';
import 'local_db.dart';

/// Résultat d'une lecture mise en cache.
class CachedJson {
  const CachedJson(this.json, {this.stale = false, this.fromCache = false});

  /// Corps JSON décodé (enveloppe `{data, meta, links}` complète).
  final dynamic json;

  /// Vrai si le réseau a échoué et que la copie locale est servie telle quelle.
  final bool stale;

  /// Vrai si la copie locale vient d'être revalidée (304) ou servie hors ligne.
  final bool fromCache;

  dynamic get data => (json as Map)['data'];
  Map<String, dynamic> get meta =>
      Map<String, dynamic>.from(((json as Map)['meta'] as Map?) ?? const {});
}

/// Lecture conditionnelle (ETag / If-None-Match) avec copie Hive.
/// Une réponse n'est gardée que si le serveur a posé un `ETag` et n'a pas
/// demandé `no-store` (ex. section « pair » du tableau de bord).
class ApiCache {
  ApiCache({required this.dio, required this.publicBox, required this.privateBox});

  final Dio dio;
  final StringBox publicBox;
  final StringBox privateBox;

  String keyFor(String path, Map<String, dynamic>? query) {
    if (query == null || query.isEmpty) return path;
    final keys = query.keys.where((k) => query[k] != null && '${query[k]}'.isNotEmpty).toList()
      ..sort();
    return keys.isEmpty ? path : '$path?${keys.map((k) => '$k=${query[k]}').join('&')}';
  }

  Future<CachedJson> get(
    String path, {
    Map<String, dynamic>? query,
    bool private = false,
  }) async {
    final box = private ? privateBox : publicBox;
    final key = keyFor(path, query);
    final raw = box.get(key);
    Map<String, dynamic>? entry;
    if (raw != null) {
      try {
        entry = jsonDecode(raw) as Map<String, dynamic>;
      } catch (_) {
        await box.delete(key);
      }
    }
    final etag = entry?['etag'] as String?;
    try {
      final res = await dio.get<dynamic>(
        path,
        queryParameters: query,
        options: Options(
          headers: {'If-None-Match': ?etag},
          validateStatus: (s) => s != null && ((s >= 200 && s < 300) || s == 304),
        ),
      );
      if (res.statusCode == 304 && entry != null) {
        return CachedJson(entry['body'], fromCache: true);
      }
      final newTag = res.headers.value('etag');
      final cc = res.headers.value('cache-control') ?? '';
      if (newTag != null && !cc.contains('no-store')) {
        await box.put(key, jsonEncode({'etag': newTag, 'body': res.data}));
      } else if (entry != null) {
        await box.delete(key);
      }
      return CachedJson(res.data);
    } on DioException catch (e) {
      final api = ApiException.from(e);
      if (entry != null && (api.isNetwork || api.status == 503)) {
        return CachedJson(entry['body'], stale: true, fromCache: true);
      }
      rethrow;
    }
  }
}
