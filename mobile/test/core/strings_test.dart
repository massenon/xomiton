import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:xomiton/core/l10n/app_strings.dart';
import 'package:xomiton/core/l10n/icu_format.dart';
import 'package:xomiton/core/l10n/pending_strings.dart';
import 'package:xomiton/core/util/html_text.dart';
import 'package:xomiton/core/util/password_strength.dart';

import '../support/harness.dart';

/// Clés d'API (pas des textes) qui ressemblent à des clés de texte.
/// Noms d'API (collections de `sync/pull`, codes d'erreur, clés de tâches) qui ressemblent à des clés de texte.
const _apiKeys = {
  'alerts_to_take',
  'peer_request',
  'peer_pending',
  'disposable_email',
  'repeated_fingerprint',
  'cases_to_contact',
  'validation_rules',
  'directory_contacts',
  'emergency_contacts',
  'case_followups',
  'validation_failed',
};

void main() {
  group('formatIcu', () {
    test('placeholders simples', () {
      expect(formatIcu('Étape {current} sur {total}', {'current': 2, 'total': 4}), 'Étape 2 sur 4');
    });

    test('pluriel avec =0 / =1 / other et accolades imbriquées', () {
      const m = '{count, plural, =0{Aucune sélection} =1{{count} sélectionné} other{{count} sélectionnés}}';
      expect(formatIcu(m, {'count': 0}), 'Aucune sélection');
      expect(formatIcu(m, {'count': 1}), '1 sélectionné');
      expect(formatIcu(m, {'count': 7}), '7 sélectionnés');
    });

    test('argument manquant laissé visible plutôt que perdu', () {
      expect(formatIcu('Salut {name}', {}), 'Salut {name}');
    });
  });

  group('AppStrings sur le vrai ARB', () {
    final s = loadStrings();

    test('textes d’authentification au tutoiement', () {
      expect(s.t('auth_login_submit'), 'Se connecter');
      expect(s.t('auth_otp_screen_resend_in', {'seconds': 12}), 'Renvoyer le code dans 12 s');
      expect(s.t('auth_register_step_of', {'current': 2, 'total': 4}), 'Étape 2 sur 4');
    });

    test('pluriels de l’ARB', () {
      expect(s.t('peer_home_tasks_alerts_to_take', {'count': 1}), '1 alerte nouvelle à traiter');
      expect(s.t('peer_home_tasks_alerts_to_take', {'count': 3}), '3 alertes nouvelles à traiter');
    });

    test('l’ARB prime sur la table des textes en attente', () {
      final custom = AppStrings.fromArb('{"a":"ARB"}', pending: {'a': 'attente', 'b': 'seulement ici'});
      expect(custom.raw('a'), 'ARB');
      expect(custom.raw('b'), 'seulement ici');
      expect(s.has('mobile_onboarding_slide_1_title'), isTrue);
    });
  });

  test('toute clé de texte référencée dans lib/ existe dans l’ARB ou la table en attente', () {
    final s = loadStrings();
    final arb = jsonDecode(File('lib/l10n/app_fr.arb').readAsStringSync()) as Map<String, dynamic>;
    final prefixes = arb.keys
        .where((k) => !k.startsWith('@'))
        .map((k) => k.split('_').first)
        .toSet();
    final literal = RegExp(r"'([a-z][a-z0-9]*(?:_[a-z0-9]+)+)'");
    final missing = <String>{};
    for (final f in Directory('lib').listSync(recursive: true).whereType<File>()) {
      if (!f.path.endsWith('.dart') || f.path.contains('lib/l10n/') || f.path.contains('pending_strings')) continue;
      for (final m in literal.allMatches(f.readAsStringSync())) {
        final key = m.group(1)!;
        if (_apiKeys.contains(key)) continue;
        if (prefixes.contains(key.split('_').first) && !s.has(key)) missing.add(key);
      }
    }
    // Clés construites dynamiquement.
    for (final a in ['login', 'password', 'pseudo', 'email', 'code']) {
      if (!s.has('validation_attributes_$a')) missing.add('validation_attributes_$a');
    }
    for (var i = 1; i <= 6; i++) {
      for (final p in ['title', 'body', 'alt']) {
        if (!s.has('mobile_onboarding_slide_${i}_$p')) missing.add('mobile_onboarding_slide_${i}_$p');
      }
    }
    for (final p in ['login', 'signup', 'reset']) {
      if (!s.has('auth_otp_screen_purpose_$p')) missing.add('auth_otp_screen_purpose_$p');
    }
    for (final p in ['alerts_to_take', 'cases_to_contact']) {
      if (!s.has('peer_home_tasks_$p')) missing.add('peer_home_tasks_$p');
    }
    expect(missing, isEmpty);
  });

  test('table des textes en attente : uniquement des clés « mobile_* »', () {
    expect(kPendingStringsFr.keys.every((k) => k.startsWith('mobile_')), isTrue);
  });

  group('PasswordStrength', () {
    test('politique de l’API : 10 caractères, casse, chiffre', () {
      expect(PasswordStrength('Court1').meetsPolicy, isFalse);
      expect(PasswordStrength('toutenminuscule1').meetsPolicy, isFalse);
      expect(PasswordStrength('SansChiffreIci').meetsPolicy, isFalse);
      expect(PasswordStrength('Assez0Long1').meetsPolicy, isTrue);
    });

    test('libellés', () {
      expect(PasswordStrength('').labelKey, 'auth_password_strength_empty');
      expect(PasswordStrength('abc').labelKey, 'auth_password_strength_too_short');
      expect(PasswordStrength('Assez0Long1!').labelKey, 'auth_password_strength_excellent');
    });
  });

  test('htmlToText garde paragraphes, puces et entités', () {
    expect(htmlToText('<p>Salut&nbsp;toi</p><ul><li>un</li><li>deux &amp; trois</li></ul>'),
        'Salut toi\n\n• un\n• deux & trois');
  });
}
