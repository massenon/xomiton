import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../storage/token_store.dart';
import 'app_toast.dart';
import 'interceptors.dart';

/// URL de base, surchargeable : `--dart-define=API_BASE_URL=https://…/api/v1`.
const String kApiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://10.0.2.2:8000/api/v1',
);

/// En production (build release), l'API n'est joignable qu'en HTTPS : sinon
/// l'application refuse de démarrer plutôt que d'envoyer jeton et récits en clair.
void assertSecureBaseUrl(String baseUrl, {bool? release}) {
  final uri = Uri.tryParse(baseUrl);
  if ((release ?? kReleaseMode) &&
      (uri == null || uri.scheme != 'https' || uri.host.isEmpty)) {
    throw StateError('API_BASE_URL doit être une URL https en production.');
  }
}

/// Client Dio `/api/v1` (docs/API.md). L'adaptateur est injectable pour les tests.
Dio buildApiClient({
  required TokenStore tokens,
  required ToastBus toasts,
  required Future<void> Function() onUnauthorized,
  Future<void> Function()? onSpaceForbidden,
  Future<void> Function()? onDeviceRevoked,
  bool Function()? deviceRegistered,
  void Function(Map<dynamic, dynamic> body)? onMaintenance,
  void Function(String? dateHeader)? onContact,
  String baseUrl = kApiBaseUrl,
  String? Function()? deviceId,
  HttpClientAdapter? adapter,
  Future<void> Function(Duration)? replayWait,
}) {
  assertSecureBaseUrl(baseUrl);
  final dio = Dio(
    BaseOptions(
      baseUrl: baseUrl.endsWith('/') ? baseUrl : '$baseUrl/',
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 30),
      sendTimeout: const Duration(seconds: 30),
      contentType: Headers.jsonContentType,
      responseType: ResponseType.json,
    ),
  );
  if (adapter != null) dio.httpClientAdapter = adapter;
  dio.interceptors.addAll([
    RequestHeadersInterceptor(tokens, deviceId: deviceId),
    if (onContact != null) ContactInterceptor(onContact),
    // Ordre : le rejeu voit l'erreur brute avant la normalisation (toast / 401).
    IdempotentReplayInterceptor(dio, wait: replayWait),
    ApiErrorInterceptor(
      onUnauthorized: onUnauthorized,
      toasts: toasts,
      onSpaceForbidden: onSpaceForbidden,
      onDeviceRevoked: onDeviceRevoked,
      deviceRegistered: deviceRegistered,
      onMaintenance: onMaintenance,
    ),
  ]);
  return dio;
}
