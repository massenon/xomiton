import 'package:dio/dio.dart';

/// Erreur d'API normalisée (docs/API.md §1.2) :
/// `{ message, code, errors?, retry_after? }`.
class ApiException implements Exception {
  const ApiException({
    this.status,
    this.code,
    this.message,
    this.errors = const {},
    this.retryAfter,
    this.isNetwork = false,
  });

  final int? status;
  final String? code;

  /// Message français renvoyé par le serveur (déjà au tutoiement).
  final String? message;

  /// Messages par champ (422).
  final Map<String, List<String>> errors;
  final int? retryAfter;

  /// Réseau absent, délai dépassé ou serveur injoignable.
  final bool isNetwork;

  bool get isUnauthenticated => status == 401;
  bool get isRateLimited => status == 429;
  bool get isValidation => status == 422;

  String? firstError(String field) {
    final list = errors[field];
    return (list == null || list.isEmpty) ? null : list.first;
  }

  factory ApiException.from(Object error) {
    if (error is ApiException) return error;
    if (error is DioException) {
      final inner = error.error;
      if (inner is ApiException) return inner;
      return ApiException.fromResponse(error);
    }
    return const ApiException();
  }

  factory ApiException.fromResponse(DioException e) {
    final res = e.response;
    if (res == null) {
      return const ApiException(isNetwork: true);
    }
    final body = res.data;
    String? code;
    String? message;
    final errors = <String, List<String>>{};
    int? retry = int.tryParse(res.headers.value('retry-after') ?? '');
    if (body is Map) {
      code = body['code'] as String?;
      message = body['message'] as String?;
      final raw = body['errors'];
      if (raw is Map) {
        raw.forEach((k, v) {
          errors['$k'] = v is List ? v.map((x) => '$x').toList() : ['$v'];
        });
      }
      final ra = body['retry_after'];
      if (ra is num) retry = ra.toInt();
    }
    return ApiException(
      status: res.statusCode,
      code: code,
      message: message,
      errors: errors,
      retryAfter: retry,
    );
  }

  @override
  String toString() => 'ApiException($status, $code, $message)';
}
