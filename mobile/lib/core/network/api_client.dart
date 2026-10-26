import 'package:dio/dio.dart';

import '../storage/token_store.dart';
import 'app_toast.dart';
import 'interceptors.dart';

/// URL de base, surchargeable : `--dart-define=API_BASE_URL=https://…/api/v1`.
const String kApiBaseUrl =
    String.fromEnvironment('API_BASE_URL', defaultValue: 'http://10.0.2.2:8000/api/v1');

/// Client Dio `/api/v1` (docs/API.md). L'adaptateur est injectable pour les tests.
Dio buildApiClient({
  required TokenStore tokens,
  required ToastBus toasts,
  required Future<void> Function() onUnauthorized,
  String baseUrl = kApiBaseUrl,
  String? Function()? deviceId,
  HttpClientAdapter? adapter,
  Future<void> Function(Duration)? replayWait,
}) {
  final dio = Dio(BaseOptions(
    baseUrl: baseUrl.endsWith('/') ? baseUrl : '$baseUrl/',
    connectTimeout: const Duration(seconds: 15),
    receiveTimeout: const Duration(seconds: 30),
    sendTimeout: const Duration(seconds: 30),
    contentType: Headers.jsonContentType,
    responseType: ResponseType.json,
  ));
  if (adapter != null) dio.httpClientAdapter = adapter;
  dio.interceptors.addAll([
    RequestHeadersInterceptor(tokens, deviceId: deviceId),
    // Ordre : le rejeu voit l'erreur brute avant la normalisation (toast / 401).
    IdempotentReplayInterceptor(dio, wait: replayWait),
    ApiErrorInterceptor(onUnauthorized: onUnauthorized, toasts: toasts),
  ]);
  return dio;
}
