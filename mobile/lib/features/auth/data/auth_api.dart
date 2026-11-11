import 'dart:io' show Platform;

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/core_providers.dart';
import '../../../core/storage/api_cache.dart';
import '../../../core/storage/token_store.dart';

enum OtpPurpose { login, signup, reset }

sealed class LoginResult {
  const LoginResult();
}

/// 202 : un code est requis (appareil inconnu ou rôle administrateur, Q13 b).
class LoginOtpRequired extends LoginResult {
  const LoginOtpRequired({required this.email, required this.resendAfter, required this.expiresIn});
  final String email;
  final int resendAfter;
  final int expiresIn;
}

/// 200 : appareil reconnu, jeton délivré directement.
class LoginSuccess extends LoginResult {
  const LoginSuccess({required this.token, required this.user});
  final String token;
  final Map<String, dynamic>? user;
}

class OtpVerified {
  const OtpVerified({this.token, this.user, this.resetToken, this.emailVerified = false});
  final String? token;
  final Map<String, dynamic>? user;
  final String? resetToken;
  final bool emailVerified;
}

class RegisterResult {
  const RegisterResult({required this.token, required this.user});
  final String token;
  final Map<String, dynamic>? user;
}

class OtpSent {
  const OtpSent({required this.resendAfter, required this.expiresIn});
  final int resendAfter;
  final int expiresIn;
}

String defaultDeviceName() {
  try {
    if (Platform.isAndroid) return 'Android';
    if (Platform.isIOS) return 'iOS';
  } catch (_) {}
  return 'Appareil';
}

/// Appels `/api/v1/auth/*` (docs/API.md §2). Les erreurs sortent en
/// `DioException` portant une `ApiException`.
class AuthApi {
  AuthApi(this._dio, this._tokens, this._cache, {String Function()? deviceName})
      : _deviceName = deviceName ?? defaultDeviceName;

  final Dio _dio;
  final TokenStore _tokens;
  final ApiCache _cache;
  final String Function() _deviceName;

  Map<String, dynamic> _data(Response<dynamic> r) =>
      Map<String, dynamic>.from(((r.data as Map)['data'] as Map?) ?? const {});

  Future<LoginResult> login(String login, String password) async {
    final deviceToken = await _tokens.readDeviceToken();
    final res = await _dio.post<dynamic>('auth/login', data: {
      'login': login.trim(),
      'password': password,
      'device_name': _deviceName(),
      'device_token': ?deviceToken,
    });
    final d = _data(res);
    if (res.statusCode == 202 || d['otp_sent'] == true) {
      return LoginOtpRequired(
        email: (d['email'] as String?) ?? login.trim(),
        resendAfter: (d['resend_after'] as num?)?.toInt() ?? 30,
        expiresIn: (d['expires_in'] as num?)?.toInt() ?? 600,
      );
    }
    await _storeDeviceToken(d);
    return LoginSuccess(
        token: d['token'] as String, user: (d['user'] as Map?)?.cast<String, dynamic>());
  }

  Future<void> _storeDeviceToken(Map<String, dynamic> d) async {
    final dt = d['device_token'];
    if (dt is String && dt.isNotEmpty) await _tokens.saveDeviceToken(dt);
  }

  Future<OtpSent> sendOtp(OtpPurpose purpose, String email) async {
    final res = await _dio.post<dynamic>('auth/otp/send', data: {
      'purpose': purpose.name,
      'email': email,
    });
    final d = _data(res);
    return OtpSent(
      resendAfter: (d['resend_after'] as num?)?.toInt() ?? 30,
      expiresIn: (d['expires_in'] as num?)?.toInt() ?? 600,
    );
  }

  Future<OtpVerified> verifyOtp(OtpPurpose purpose, String email, String code) async {
    final res = await _dio.post<dynamic>('auth/otp/verify', data: {
      'purpose': purpose.name,
      'email': email,
      'code': code,
      if (purpose == OtpPurpose.login) 'device_name': _deviceName(),
    });
    final d = _data(res);
    switch (purpose) {
      case OtpPurpose.login:
        await _storeDeviceToken(d);
        return OtpVerified(
            token: d['token'] as String?, user: (d['user'] as Map?)?.cast<String, dynamic>());
      case OtpPurpose.signup:
        return OtpVerified(
            emailVerified: d['email_verified'] == true,
            user: (d['user'] as Map?)?.cast<String, dynamic>());
      case OtpPurpose.reset:
        return OtpVerified(resetToken: d['reset_token'] as String?);
    }
  }

  /// Phase 1 : crée le compte et l'ouvre (Q12 c, D4). Les phases 2 à 4 passent
  /// ensuite par `PATCH me/profile` et `PATCH me/newsletter`.
  Future<RegisterResult> register({
    required String pseudo,
    required String email,
    required String password,
    required bool cgu,
    required bool privacy,
    required bool ageConfirmed,
    String? invitation,
  }) async {
    final res = await _dio.post<dynamic>('auth/register', data: {
      'pseudo': pseudo.trim(),
      'email': email.trim(),
      'password': password,
      'cgu': cgu,
      'privacy': privacy,
      'age_confirmed': ageConfirmed,
      if (invitation != null && invitation.trim().isNotEmpty) 'invitation': invitation.trim(),
      'device_name': _deviceName(),
    });
    final d = _data(res);
    return RegisterResult(
        token: d['token'] as String, user: (d['user'] as Map?)?.cast<String, dynamic>());
  }

  Future<void> forgotPassword(String email) async {
    await _dio.post<dynamic>('auth/password/forgot', data: {'email': email.trim()});
  }

  Future<void> resetPassword({
    required String email,
    required String resetToken,
    required String password,
    required String confirmation,
  }) async {
    await _dio.post<dynamic>('auth/password/reset', data: {
      'email': email,
      'reset_token': resetToken,
      'password': password,
      'password_confirmation': confirmation,
    });
  }

  /// Phases 2 et 3 (profil, sécurité) et consentement aux données sensibles.
  Future<Map<String, dynamic>> patchProfile(Map<String, dynamic> body) async {
    final res = await _dio.patch<dynamic>('me/profile', data: body);
    return _data(res);
  }

  Future<void> patchNewsletter({required bool subscribed}) async {
    await _dio.patch<dynamic>('me/newsletter', data: {'subscribed': subscribed});
  }

  /// `GET zones` (cache Hive + ETag) : départements et communes, jamais codés en dur.
  Future<List<Zone>> zones() async {
    final r = await _cache.get('zones');
    return (r.data as List)
        .map((e) => Zone.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }
}

class Commune {
  const Commune(this.id, this.name);
  final int id;
  final String name;
}

class Zone {
  const Zone(this.id, this.name, this.communes);
  final int id;
  final String name;
  final List<Commune> communes;

  factory Zone.fromJson(Map<String, dynamic> j) => Zone(
        (j['id'] as num).toInt(),
        j['name'] as String,
        ((j['communes'] as List?) ?? const [])
            .map((c) => Commune((c['id'] as num).toInt(), c['name'] as String))
            .toList(),
      );
}

final authApiProvider = Provider<AuthApi>((ref) => AuthApi(
      ref.watch(dioProvider),
      ref.watch(tokenStoreProvider),
      ref.watch(apiCacheProvider),
    ));

final zonesProvider = FutureProvider<List<Zone>>((ref) => ref.watch(authApiProvider).zones());

