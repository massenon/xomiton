import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/core_providers.dart';

/// Réglages `me/settings` (noms d'écriture du prototype mobile).
class AccountSettings {
  const AccountSettings({
    this.theme = 'system',
    this.sounds = true,
    this.notifications = true,
    this.locale = 'fr',
  });
  final String theme; // light | dark | system
  final bool sounds;
  final bool notifications;
  final String locale;

  AccountSettings copyWith({String? theme, bool? sounds, bool? notifications, String? locale}) =>
      AccountSettings(
        theme: theme ?? this.theme,
        sounds: sounds ?? this.sounds,
        notifications: notifications ?? this.notifications,
        locale: locale ?? this.locale,
      );

  factory AccountSettings.fromJson(Map<String, dynamic> j) {
    final s = j['settings'] is Map ? Map<String, dynamic>.from(j['settings'] as Map) : j;
    final theme = s['theme'] as String?;
    return AccountSettings(
      theme: theme == 'light' || theme == 'dark' ? theme! : 'system',
      sounds: (s['sounds'] ?? s['sounds_enabled']) != false,
      notifications: s['notifications'] != false,
      locale: (s['locale'] ?? s['language'] ?? 'fr') as String,
    );
  }
}

/// `me/security` : seul `pin_enabled` concerne le PIN (le code reste sur l'appareil).
class SecuritySettings {
  const SecuritySettings({
    this.discreet = false,
    this.maskPreviews = true,
    this.pinEnabled = false,
    this.neutralTabTitle = false,
  });
  final bool discreet;
  final bool maskPreviews;
  final bool pinEnabled;
  final bool neutralTabTitle;

  SecuritySettings copyWith({bool? discreet, bool? maskPreviews, bool? pinEnabled}) =>
      SecuritySettings(
        discreet: discreet ?? this.discreet,
        maskPreviews: maskPreviews ?? this.maskPreviews,
        pinEnabled: pinEnabled ?? this.pinEnabled,
        neutralTabTitle: neutralTabTitle,
      );

  factory SecuritySettings.fromJson(Map<String, dynamic> j) => SecuritySettings(
    discreet: j['discreet'] == true,
    maskPreviews: j['mask_previews'] == true,
    pinEnabled: j['pin_enabled'] == true,
    neutralTabTitle: j['neutral_tab_title'] == true,
  );
}

class ConnectedSession {
  const ConnectedSession({
    required this.id,
    required this.kind,
    required this.name,
    required this.current,
    this.lastActiveAt,
  });
  final String id;
  final String kind;
  final String name;
  final bool current;
  final DateTime? lastActiveAt;

  factory ConnectedSession.fromJson(Map<String, dynamic> j) => ConnectedSession(
    id: '${j['id']}',
    kind: (j['kind'] as String?) ?? 'token',
    name: (j['name'] as String?) ?? '',
    current: j['current'] == true,
    lastActiveAt: DateTime.tryParse('${j['last_active_at']}'),
  );
}

class ProgressiveGroup {
  const ProgressiveGroup(this.key, this.answered);
  final String key;
  final bool answered;
}

/// Profil progressif : groupes, prochain groupe à traiter, consentement.
class Progressive {
  const Progressive({this.groups = const [], this.next, this.done = false, this.sensitiveConsent = false});
  final List<ProgressiveGroup> groups;
  final String? next;
  final bool done;
  final bool sensitiveConsent;

  int get answered => groups.where((g) => g.answered).length;

  factory Progressive.fromJson(Map<String, dynamic>? j) {
    if (j == null) return const Progressive();
    return Progressive(
      groups: ((j['groups'] as List?) ?? const [])
          .map((g) => ProgressiveGroup('${(g as Map)['key']}', g['answered'] == true))
          .toList(),
      next: j['next'] as String?,
      done: j['done'] == true,
      sensitiveConsent: j['sensitive_consent'] == true,
    );
  }
}

/// `GET me/newsletter` (docs/API.md §3 : pas de fréquence dans le schéma).
class NewsletterPrefs {
  const NewsletterPrefs({
    this.subscribed = false,
    this.pendingConfirmation = false,
    this.interests = const {},
    this.options = const [],
  });
  final bool subscribed;
  final bool pendingConfirmation;
  final Set<String> interests;
  final List<({String slug, String label})> options;

  factory NewsletterPrefs.fromJson(Map<String, dynamic> j) => NewsletterPrefs(
    subscribed: j['subscribed'] == true,
    pendingConfirmation: j['pending_confirmation'] == true,
    interests: {for (final e in (j['interests'] as List?) ?? const []) '$e'},
    options: [
      for (final e in (j['available_interests'] as List?) ?? const [])
        if (e is Map) (slug: '${e['slug']}', label: '${e['label']}'),
    ],
  );
}

class AccountApi {
  AccountApi(this._dio);
  final Dio _dio;

  Map<String, dynamic> _data(Response<dynamic> r) =>
      Map<String, dynamic>.from(((r.data as Map?)?['data'] as Map?) ?? const {});

  Future<AccountSettings> settings() async =>
      AccountSettings.fromJson(_data(await _dio.get<dynamic>('me/settings')));

  Future<void> patchSettings(Map<String, Object?> body) =>
      _dio.patch<dynamic>('me/settings', data: body);

  Future<SecuritySettings> security() async =>
      SecuritySettings.fromJson(_data(await _dio.get<dynamic>('me/security')));

  /// `pin_enabled` seulement : le code lui-même n'est JAMAIS envoyé.
  Future<void> patchSecurity({bool? discreet, bool? maskPreviews, bool? pinEnabled}) =>
      _dio.patch<dynamic>('me/security', data: {
        'discreet': ?discreet,
        'mask_previews': ?maskPreviews,
        'pin_enabled': ?pinEnabled,
      });

  Future<List<ConnectedSession>> sessions() async {
    final res = await _dio.get<dynamic>('me/sessions');
    return ((res.data as Map)['data'] as List)
        .map((e) => ConnectedSession.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  Future<void> revokeSession(String id) => _dio.delete<dynamic>('me/sessions/$id');
  Future<void> revokeOthers() => _dio.delete<dynamic>('me/sessions');
  Future<void> requestExport() => _dio.post<dynamic>('me/export');

  Future<void> deleteAccount(String password) =>
      _dio.delete<dynamic>('me', data: {'password': password});

  Future<Progressive> progressive() async {
    final d = _data(await _dio.get<dynamic>('me/profile'));
    return Progressive.fromJson((d['progressive'] as Map?)?.cast<String, dynamic>());
  }

  /// Centres d'intérêt proposés (`available_interests` de `me/newsletter`).
  Future<List<({String slug, String label})>> interestOptions() async {
    final d = _data(await _dio.get<dynamic>('me/newsletter'));
    return ((d['available_interests'] as List?) ?? const [])
        .map((e) => (slug: '${(e as Map)['slug']}', label: '${e['label']}'))
        .toList();
  }

  Future<NewsletterPrefs> newsletter() async =>
      NewsletterPrefs.fromJson(_data(await _dio.get<dynamic>('me/newsletter')));

  Future<NewsletterPrefs> patchNewsletter({bool? subscribed, Set<String>? interests}) async =>
      NewsletterPrefs.fromJson(
        _data(
          await _dio.patch<dynamic>(
            'me/newsletter',
            data: {'subscribed': ?subscribed, 'interests': ?interests?.toList()},
          ),
        ),
      );

  Future<Progressive> patchProfile(Map<String, Object?> body) async {
    final d = _data(await _dio.patch<dynamic>('me/profile', data: body));
    return Progressive.fromJson((d['progressive'] as Map?)?.cast<String, dynamic>());
  }
}

final accountApiProvider = Provider<AccountApi>((ref) => AccountApi(ref.watch(dioProvider)));
