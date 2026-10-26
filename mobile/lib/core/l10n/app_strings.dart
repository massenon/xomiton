import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart' show rootBundle;
import 'package:flutter/widgets.dart';

import 'icu_format.dart';
import 'pending_strings.dart';

const String kArbAsset = 'lib/l10n/app_fr.arb';

/// Catalogue de textes. Source : `lib/l10n/app_fr.arb`, généré depuis
/// `web/lang/fr` (ne jamais l'éditer). `gen_l10n` est inutilisable ici : l'ARB
/// exporté contient des clés non conformes (« Anonyme », « The_given_… ») que
/// l'outil refuse ; on charge donc le JSON tel quel et on formate les
/// messages ICU nous-mêmes.
class AppStrings {
  AppStrings(this._messages, {this.pending = kPendingStringsFr});

  final Map<String, String> _messages;
  final Map<String, String> pending;

  static const Locale locale = Locale('fr');

  static Future<AppStrings> loadFromAsset([AssetBundle? bundle]) async {
    final raw = await (bundle ?? rootBundle).loadString(kArbAsset);
    return AppStrings.fromArb(raw);
  }

  factory AppStrings.fromArb(String raw, {Map<String, String> pending = kPendingStringsFr}) {
    final json = jsonDecode(raw) as Map<String, dynamic>;
    final m = <String, String>{};
    json.forEach((k, v) {
      if (!k.startsWith('@') && v is String) m[k] = v;
    });
    return AppStrings(m, pending: pending);
  }

  bool has(String key) => _messages.containsKey(key) || pending.containsKey(key);

  /// Texte brut (sans formatage ICU).
  String raw(String key) {
    final v = _messages[key] ?? pending[key];
    assert(v != null, 'Clé de texte inconnue : $key');
    return v ?? key;
  }

  /// Texte formaté : `{nom}` et pluriels ICU.
  String t(String key, [Map<String, Object?> args = const {}]) {
    final v = raw(key);
    return args.isEmpty && !v.contains('{') ? v : formatIcu(v, args);
  }

  static AppStrings of(BuildContext context) =>
      Localizations.of<AppStrings>(context, AppStrings)!;
}

class AppStringsDelegate extends LocalizationsDelegate<AppStrings> {
  const AppStringsDelegate(this.strings);
  final AppStrings strings;

  @override
  bool isSupported(Locale locale) => locale.languageCode == 'fr';

  @override
  Future<AppStrings> load(Locale locale) => SynchronousFuture(strings);

  @override
  bool shouldReload(AppStringsDelegate old) => old.strings != strings;
}

extension AppStringsX on BuildContext {
  /// `context.t('auth_login_heading')`, `context.t('auth_otp_screen_resend_in', {'seconds': 12})`.
  String t(String key, [Map<String, Object?> args = const {}]) =>
      AppStrings.of(this).t(key, args);
}
