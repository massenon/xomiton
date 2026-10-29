import 'dart:async';

import 'package:dio/dio.dart';
import 'package:uuid/uuid.dart';

import '../storage/token_store.dart';
import 'api_exception.dart';
import 'app_toast.dart';
import 'idempotency.dart';

const _uuid = Uuid();

/// `Authorization: Bearer`, `Accept`, `X-Device-Id` et `Idempotency-Key`.
///
/// La clé d'idempotence est posée **dans les en-têtes de la requête** : un
/// rejeu (`dio.fetch(requestOptions)`) renvoie donc la même clé et le même
/// corps. L'appelant qui a une boîte d'envoi fournit l'uuid de l'entrée
/// (en-tête `Idempotency-Key` déjà présent) ; sinon on en génère un.
class RequestHeadersInterceptor extends Interceptor {
  RequestHeadersInterceptor(this.tokens, {this.deviceId});

  final TokenStore tokens;
  final String? Function()? deviceId;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    options.headers.putIfAbsent('Accept', () => 'application/json');
    options.headers.putIfAbsent('Accept-Language', () => 'fr');
    final t = tokens.token;
    if (t != null &&
        t.isNotEmpty &&
        options.extra['anonymous'] != true &&
        !options.headers.containsKey('Authorization')) {
      options.headers['Authorization'] = 'Bearer $t';
    }
    final d = deviceId?.call();
    if (d != null && d.isNotEmpty) {
      options.headers.putIfAbsent('X-Device-Id', () => d);
    }
    if (needsIdempotencyKey(options.method, options.path)) {
      options.headers.putIfAbsent('Idempotency-Key', () => _uuid.v4());
    }
    handler.next(options);
  }
}

/// Rejeu des POST idempotents (signalement, SOS, lot de synchro) avec **la
/// même clé** : coupure réseau, 5xx, 429 `idempotency_in_progress`, ou 202
/// `processing` (corps `{data:{status:'processing', retry_after}}`).
class IdempotentReplayInterceptor extends Interceptor {
  IdempotentReplayInterceptor(
    this.dio, {
    this.maxAttempts = 4,
    Future<void> Function(Duration)? wait,
  }) : _wait = wait ?? Future<void>.delayed;

  final Dio dio;
  final int maxAttempts;
  final Future<void> Function(Duration) _wait;

  static const _attemptKey = 'replay_attempt';

  /// `no_auto_replay` : corps multipart (non rejouable tel quel) — la boîte
  /// d'envoi le reconstruit et rejoue avec la même clé.
  bool _applies(RequestOptions o) =>
      o.extra['no_auto_replay'] != true &&
      needsIdempotencyKey(o.method, o.path);

  Duration _delay(int attempt, int? retryAfter) => Duration(
    seconds: retryAfter ?? (attempt <= 1 ? 1 : (attempt * 2)).clamp(1, 8),
  );

  @override
  void onResponse(Response response, ResponseInterceptorHandler handler) async {
    final o = response.requestOptions;
    final body = response.data;
    if (response.statusCode == 202 &&
        _applies(o) &&
        body is Map &&
        body['data'] is Map &&
        (body['data'] as Map)['status'] == 'processing') {
      final attempt = (o.extra[_attemptKey] as int? ?? 0) + 1;
      if (attempt < maxAttempts) {
        o.extra[_attemptKey] = attempt;
        final ra = (body['data'] as Map)['retry_after'];
        await _wait(_delay(attempt, ra is num ? ra.toInt() : null));
        try {
          return handler.resolve(await dio.fetch<dynamic>(o));
        } on DioException catch (e) {
          return handler.reject(e);
        }
      }
    }
    handler.next(response);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) async {
    final o = err.requestOptions;
    if (!_applies(o)) return handler.next(err);
    final status = err.response?.statusCode;
    final code = err.response?.data is Map
        ? (err.response!.data as Map)['code']
        : null;
    final network =
        err.response == null &&
        err.type != DioExceptionType.cancel &&
        err.type != DioExceptionType.badCertificate;
    final retryable =
        network ||
        (status != null && status >= 500) ||
        (status == 429 && code == 'idempotency_in_progress');
    final attempt = (o.extra[_attemptKey] as int? ?? 0) + 1;
    if (!retryable || attempt >= maxAttempts) return handler.next(err);
    o.extra[_attemptKey] = attempt;
    final ra = int.tryParse(err.response?.headers.value('retry-after') ?? '');
    await _wait(_delay(attempt, ra));
    try {
      handler.resolve(await dio.fetch<dynamic>(o));
    } on DioException catch (e) {
      handler.reject(e);
    }
  }
}

/// Route d'un espace réservé (`pair/*`, `admin/*`) : un 403 `forbidden` y
/// signifie que le droit a disparu côté serveur.
bool isSpaceApiPath(String path) {
  final p = path.replaceAll(RegExp(r'^/+'), '').replaceFirst(RegExp(r'^(api/)?v1/'), '');
  return p.startsWith('pair/') || p.startsWith('admin/');
}

bool _isDeviceRegistration(String path) {
  final p = path.replaceAll(RegExp(r'^/+'), '').replaceFirst(RegExp(r'^(api/)?v1/'), '');
  return p == 'me/devices';
}

/// Note chaque contact réussi avec le serveur (date de la réponse) : sert à
/// l'expiration des données Pair hors contact (D-MOB-7).
class ContactInterceptor extends Interceptor {
  ContactInterceptor(this.onContact);
  final void Function(String? dateHeader) onContact;

  @override
  void onResponse(Response response, ResponseInterceptorHandler handler) {
    final status = response.statusCode ?? 0;
    if (status >= 200 && status < 500) onContact(response.headers.value('date'));
    handler.next(response);
  }

  /// Une réponse d'erreur (4xx, 5xx) prouve aussi que le serveur a répondu.
  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    final res = err.response;
    if (res != null) onContact(res.headers.value('date'));
    handler.next(err);
  }
}

/// Normalise les erreurs en [ApiException] ; 401 → déconnexion ; 429 → toast ;
/// 403 `forbidden` sur `pair/*` ou `admin/*` → relecture de `GET me` (le
/// serveur seul décide des espaces : plan §6.1).
class ApiErrorInterceptor extends Interceptor {
  ApiErrorInterceptor({
    required this.onUnauthorized,
    required this.toasts,
    this.onSpaceForbidden,
    this.onDeviceRevoked,
  });

  final Future<void> Function() onUnauthorized;
  final ToastBus toasts;
  final Future<void> Function()? onSpaceForbidden;

  /// 403 `device_revoked` : purge totale (sauf sur `me/devices`, où l'application
  /// génère elle-même un nouvel identifiant d'appareil).
  final Future<void> Function()? onDeviceRevoked;

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    if (err.error is ApiException) return handler.next(err);
    final api = ApiException.fromResponse(err);
    if (api.status == 401 &&
        err.requestOptions.headers.containsKey('Authorization')) {
      toasts.emit(const SessionClosedToast());
      unawaited(onUnauthorized());
    } else if (api.status == 429 && api.code != 'idempotency_in_progress') {
      toasts.emit(RateLimitedToast(api.retryAfter ?? 60));
    } else if (api.status == 403 &&
        api.code == 'device_revoked' &&
        err.requestOptions.headers.containsKey('Authorization') &&
        !_isDeviceRegistration(err.requestOptions.path) &&
        onDeviceRevoked != null) {
      toasts.emit(const DeviceRevokedToast());
      unawaited(onDeviceRevoked!());
    } else if (api.status == 403 &&
        api.code == 'forbidden' &&
        isSpaceApiPath(err.requestOptions.path) &&
        onSpaceForbidden != null) {
      unawaited(onSpaceForbidden!());
    }
    handler.next(
      DioException(
        requestOptions: err.requestOptions,
        response: err.response,
        type: err.type,
        error: api,
        message: api.message,
      ),
    );
  }
}
