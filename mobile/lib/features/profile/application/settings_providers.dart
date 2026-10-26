import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/platform/discreet_icon.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/push/push_service.dart';
import '../../auth/application/session_controller.dart';
import '../../security/application/pin_lock.dart';
import '../data/account_api.dart';

const kPrefThemeMode = 'theme_mode';

ThemeMode themeModeFrom(String? v) => switch (v) {
  'light' => ThemeMode.light,
  'dark' => ThemeMode.dark,
  _ => ThemeMode.system,
};

/// Thème de l'application : appliqué IMMÉDIATEMENT, mémorisé sur l'appareil,
/// puis envoyé au compte (`PATCH me/settings {theme}`). Le compte (`auto` =
/// système) l'emporte à l'ouverture de session.
class ThemeModeController extends Notifier<ThemeMode> {
  @override
  ThemeMode build() {
    ref.listen(sessionProvider.select((s) => s.user?.raw['settings'] is Map ? (s.user!.raw['settings'] as Map)['theme'] : null), (prev, now) {
      if (now is String && prev != now) _apply(themeModeFrom(now == 'auto' ? 'system' : now));
    });
    return themeModeFrom(ref.read(sharedPreferencesProvider).getString(kPrefThemeMode));
  }

  void _apply(ThemeMode m) {
    state = m;
    ref.read(sharedPreferencesProvider).setString(kPrefThemeMode, m.name);
  }

  /// Retourne `false` si l'enregistrement sur le compte a échoué (le thème
  /// reste appliqué sur l'appareil).
  Future<bool> set(ThemeMode mode) async {
    _apply(mode);
    if (!ref.read(sessionProvider).authenticated) return true;
    try {
      await ref.read(accountApiProvider).patchSettings({'theme': mode.name});
      return true;
    } catch (_) {
      return false;
    }
  }
}

final themeModeProvider = NotifierProvider<ThemeModeController, ThemeMode>(ThemeModeController.new);

/// Locales proposées : celles de l'API (`config('xomiton.locales')`, aujourd'hui
/// `fr`). L'API n'expose pas la liste : elle est validée à l'écriture (422).
const kSupportedLocales = ['fr'];

class SettingsController extends AsyncNotifier<AccountSettings> {
  @override
  Future<AccountSettings> build() async {
    final s = await ref.read(accountApiProvider).settings();
    await ref.read(sharedPreferencesProvider).setBool(kPrefSounds, s.sounds);
    return s;
  }

  Future<void> _patch(AccountSettings next, Map<String, Object?> body) async {
    final prev = state.value;
    state = AsyncData(next);
    try {
      await ref.read(accountApiProvider).patchSettings(body);
      await ref.read(sharedPreferencesProvider).setBool(kPrefSounds, next.sounds);
    } catch (e) {
      if (prev != null) state = AsyncData(prev);
      rethrow;
    }
  }

  Future<void> setSounds(bool v) => _patch(state.requireValue.copyWith(sounds: v), {'sounds': v});
  Future<void> setNotifications(bool v) => _patch(state.requireValue.copyWith(notifications: v), {'notifications': v});
  Future<void> setLocale(String v) => _patch(state.requireValue.copyWith(locale: v), {'locale': v});
}

final settingsProvider = AsyncNotifierProvider<SettingsController, AccountSettings>(SettingsController.new);

class SecurityController extends AsyncNotifier<SecuritySettings> {
  @override
  Future<SecuritySettings> build() async {
    final api = ref.read(accountApiProvider);
    var s = await api.security();
    // Le PIN est local : le serveur ne connaît que `pin_enabled`. On le recale
    // sur l'état réel de l'appareil.
    final local = await ref.read(pinServiceProvider).isSet();
    if (local != s.pinEnabled) {
      try {
        await api.patchSecurity(pinEnabled: local);
        s = s.copyWith(pinEnabled: local);
      } catch (_) {}
    }
    await ref.read(sharedPreferencesProvider).setBool(kPrefMaskPreviews, s.maskPreviews);
    return s;
  }

  Future<void> _patch({bool? discreet, bool? maskPreviews, bool? pinEnabled}) async {
    final prev = state.requireValue;
    final api = ref.read(accountApiProvider);
    state = AsyncData(prev.copyWith(discreet: discreet, maskPreviews: maskPreviews, pinEnabled: pinEnabled));
    try {
      await api.patchSecurity(discreet: discreet, maskPreviews: maskPreviews, pinEnabled: pinEnabled);
      // Activer le mode discret masque aussi les aperçus : on relit.
      if (discreet != null) state = AsyncData(await api.security());
      final now = state.requireValue;
      await ref.read(sharedPreferencesProvider).setBool(kPrefMaskPreviews, now.maskPreviews);
    } catch (e) {
      state = AsyncData(prev);
      rethrow;
    }
  }

  Future<void> setDiscreet(bool v) async {
    await _patch(discreet: v);
    await ref.read(discreetIconProvider).apply(v);
  }

  Future<void> setMaskPreviews(bool v) => _patch(maskPreviews: v);
  Future<void> setPinEnabled(bool v) => _patch(pinEnabled: v);
}

final securityProvider = AsyncNotifierProvider<SecurityController, SecuritySettings>(SecurityController.new);

final sessionsProvider = FutureProvider.autoDispose<List<ConnectedSession>>(
  (ref) => ref.watch(accountApiProvider).sessions(),
);
