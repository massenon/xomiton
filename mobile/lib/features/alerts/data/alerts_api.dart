import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/core_providers.dart';
import '../../../core/storage/api_cache.dart';
import 'alerts_models.dart';

/// Alertes (docs/API.md §4 et §6). Référentiels et urgences : ETag + copie
/// Hive (lisibles hors ligne). Les écritures passent par la boîte d'envoi.
class AlertsApi {
  AlertsApi(this._dio, this._cache);
  final Dio _dio;
  final ApiCache _cache;

  Future<Referentials> referentials() async {
    final z = await _cache.get('zones');
    final t = await _cache.get('violence-types');
    final n = await _cache.get('needs');
    List<Map<String, dynamic>> rows(CachedJson r) => (r.data as List)
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
    return Referentials(
      zones: rows(z).map(Zone.fromJson).toList(),
      types: rows(t).map(Choice.fromJson).toList(),
      needs: rows(n).map(Choice.fromJson).toList(),
      stale: z.stale || t.stale || n.stale,
    );
  }

  Future<EmergencyData> emergency({int? zoneId}) async {
    final r = await _cache.get('urgences', query: {'zone': zoneId});
    return EmergencyData.fromJson(
      Map<String, dynamic>.from(r.data as Map),
      stale: r.stale,
    );
  }

  /// Suivi par code, sans compte (`throttle:tracking`) ; jamais mis en cache.
  Future<AlertSummary> track(String code) async {
    final res = await _dio.get<dynamic>(
      'signalements/${Uri.encodeComponent(code.trim())}',
    );
    return AlertSummary.fromJson(
      Map<String, dynamic>.from((res.data as Map)['data'] as Map),
    );
  }

  Future<List<AlertSummary>> mine({String? type}) async {
    final res = await _dio.get<dynamic>(
      'signalements/mine',
      queryParameters: {'type': ?type, 'per_page': 50},
    );
    return ((res.data as Map)['data'] as List)
        .whereType<Map>()
        .map((e) => AlertSummary.fromJson(Map<String, dynamic>.from(e)))
        .toList();
  }

  Options _sosOptions(String? token) =>
      Options(headers: {'X-Sos-Token': ?token});

  Future<SosStatus> sosShow(String id, {String? token}) async {
    final res = await _dio.get<dynamic>('sos/$id', options: _sosOptions(token));
    return _status(res);
  }

  Future<SosStatus> sosCancel(String id, {String? token}) async {
    final res = await _dio.post<dynamic>(
      'sos/$id/cancel',
      options: _sosOptions(token),
    );
    return _status(res);
  }

  Future<SosStatus> sosSafe(String id, {String? token}) async {
    final res = await _dio.post<dynamic>(
      'sos/$id/safe',
      options: _sosOptions(token),
    );
    return _status(res);
  }

  SosStatus _status(Response<dynamic> res) {
    final body = Map<String, dynamic>.from(res.data as Map);
    return SosStatus.fromJson(
      Map<String, dynamic>.from(body['data'] as Map),
      meta: body['meta'] is Map
          ? Map<String, dynamic>.from(body['meta'] as Map)
          : null,
    );
  }
}

final alertsApiProvider = Provider<AlertsApi>(
  (ref) => AlertsApi(ref.watch(dioProvider), ref.watch(apiCacheProvider)),
);

final referentialsProvider = FutureProvider.autoDispose<Referentials>(
  (ref) => ref.watch(alertsApiProvider).referentials(),
);

final emergencyProvider = FutureProvider.autoDispose<EmergencyData>(
  (ref) => ref.watch(alertsApiProvider).emergency(),
);
