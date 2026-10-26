import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:xomiton/core/providers/core_providers.dart';
import 'package:xomiton/core/storage/secret_store.dart';
import 'package:xomiton/features/security/application/pin_lock.dart';
import 'package:xomiton/features/security/application/pin_service.dart';

import '../support/fakes.dart';
import '../support/harness.dart';

final S = loadStrings();

Map<String, dynamic> settingsBody({String theme = 'auto'}) => {
  'theme': theme,
  'locale': 'fr',
  'language': 'fr',
  'sounds': true,
  'sounds_enabled': true,
  'notifications': true,
};

FakeApi baseApi([Map<String, FakeHandler> extra = const {}]) => FakeApi({
  'GET me': (_) => jsonBody(200, {'data': userJson()}),
  'GET me/settings': (_) => jsonBody(200, {'data': settingsBody()}),
  'PATCH me/settings': (_) => jsonBody(200, {'data': userJson()}),
  'GET me/security': (_) => jsonBody(200, {'data': {'discreet': false, 'mask_previews': true, 'pin_enabled': false, 'neutral_tab_title': false}}),
  'PATCH me/security': (_) => jsonBody(200, {'data': {'discreet': false, 'mask_previews': true, 'pin_enabled': true, 'neutral_tab_title': false}}),
  'POST auth/logout': (_) => jsonBody(200, {'data': {'logged_out': true}}),
  ...extra,
});

TestEnv signedIn(FakeApi api) => TestEnv(
  api: api,
  token: 'tok',
  cachedUser: userJson(),
  extra: [pinIterationsProvider.overrideWithValue(2)],
);

Future<void> pump(WidgetTester tester, TestEnv env, String route) async {
  usePhone(tester);
  addTearDown(tester.view.reset);
  await tester.pumpWidget(await env.app());
  await settle(tester);
  goTo(tester, route);
  await settle(tester, 10);
}

Future<void> tapPin(WidgetTester tester, String pin) async {
  for (final d in pin.split('')) {
    await tester.tap(find.byKey(Key('pin-key-$d')));
    await tester.pump();
  }
  await settle(tester, 10);
}

void main() {
  group('apparence', () {
    testWidgets('le thème sombre est appliqué immédiatement puis envoyé au compte', (tester) async {
      final api = baseApi();
      await pump(tester, signedIn(api), '/parametres');
      MaterialApp app() => tester.widget<MaterialApp>(find.byType(MaterialApp));
      expect(app().themeMode, ThemeMode.system);

      await tester.tap(find.text(S.t('profile_settings_appearance_dark')));
      await tester.pump(); // avant toute réponse du serveur
      expect(app().themeMode, ThemeMode.dark);
      await settle(tester);

      expect((api.calls('PATCH me/settings').last.data as Map)['theme'], 'dark');
      final prefs = await SharedPreferences.getInstance();
      expect(prefs.getString('theme_mode'), 'dark');

      await tester.tap(find.text(S.t('profile_settings_appearance_light')));
      await settle(tester);
      expect(app().themeMode, ThemeMode.light);
      await tester.tap(find.text(S.t('profile_settings_appearance_auto')));
      await settle(tester);
      expect(app().themeMode, ThemeMode.system);
      expect((api.calls('PATCH me/settings').last.data as Map)['theme'], 'system');
      await disposeApp(tester);
    });

    testWidgets('hors ligne : le thème reste appliqué sur l’appareil', (tester) async {
      final api = baseApi({'PATCH me/settings': (o) => throw offlineError(o)});
      await pump(tester, signedIn(api), '/parametres');
      await tester.tap(find.text(S.t('profile_settings_appearance_dark')));
      await settle(tester);
      expect(tester.widget<MaterialApp>(find.byType(MaterialApp)).themeMode, ThemeMode.dark);
      await disposeApp(tester);
    });

    testWidgets('langue : seules les locales de l’API sont activables ; sons envoyés', (tester) async {
      final api = baseApi();
      await pump(tester, signedIn(api), '/parametres');
      expect(tester.widget<RadioListTile<String>>(find.byKey(const Key('locale-fr'))).enabled, isTrue);
      for (final l in ['fon', 'yo', 'en']) {
        expect(tester.widget<RadioListTile<String>>(find.byKey(Key('locale-$l'))).enabled, isFalse);
      }
      await tester.tap(find.byKey(const Key('switch-sounds')));
      await settle(tester);
      expect((api.calls('PATCH me/settings').last.data as Map)['sounds'], false);
      await disposeApp(tester);
    });
  });

  group('sécurité', () {
    testWidgets('activer le PIN : seul pin_enabled part, jamais le code', (tester) async {
      final api = baseApi();
      final env = signedIn(api);
      await pump(tester, env, '/securite');

      await tester.tap(find.byKey(const Key('switch-pin')));
      await settle(tester, 10);
      await tapPin(tester, '4821'); // saisie
      await tapPin(tester, '4821'); // confirmation

      final patch = api.calls('PATCH me/security');
      expect(patch, hasLength(1));
      expect(patch.single.data, {'pin_enabled': true});
      for (final r in api.requests) {
        final seen = '${r.path} ${r.data} ${r.queryParameters} ${r.headers}';
        expect(seen.contains('4821'), isFalse, reason: '${r.method} ${r.path} ne doit pas porter le code');
        expect(seen.contains('pin_hash'), isFalse);
      }
      // Il est haché dans le stockage sécurisé.
      final rec = env.secrets.data[SecretKeys.pinRecord]!;
      expect(rec.contains('4821'), isFalse);
      expect(await PinService(env.secrets, iterations: 2).verify('4821'), isA<PinCheck>().having((c) => c.ok, 'ok', isTrue));
      expect(containerOf(tester).read(pinLockProvider).configured, isTrue);
      await disposeApp(tester);
    });

    testWidgets('désactiver le PIN exige le code actuel', (tester) async {
      final api = baseApi({
        'GET me/security': (_) => jsonBody(200, {'data': {'discreet': false, 'mask_previews': true, 'pin_enabled': true, 'neutral_tab_title': false}}),
        'PATCH me/security': (_) => jsonBody(200, {'data': {'pin_enabled': false}}),
      });
      final env = signedIn(api);
      await PinService(env.secrets, iterations: 2).set('4821');
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await env.app());
      await settle(tester);
      await tapPin(tester, '4821'); // déverrouille
      goTo(tester, '/securite');
      await settle(tester, 10);

      await tester.tap(find.byKey(const Key('switch-pin')));
      await settle(tester, 10);
      await tapPin(tester, '1111'); // mauvais code : reste actif
      expect(env.secrets.data.containsKey(SecretKeys.pinRecord), isTrue);
      expect(api.calls('PATCH me/security'), isEmpty);

      await tester.tap(find.byKey(const Key('switch-pin')));
      await settle(tester, 10);
      await tapPin(tester, '4821');
      expect(env.secrets.data.containsKey(SecretKeys.pinRecord), isFalse);
      expect(api.calls('PATCH me/security').single.data, {'pin_enabled': false});
      await disposeApp(tester);
    });

    testWidgets('mode discret : PATCH me/security {discreet} puis relecture', (tester) async {
      var reads = 0;
      final api = baseApi({
        'GET me/security': (_) {
          reads++;
          return jsonBody(200, {'data': {'discreet': reads > 1, 'mask_previews': true, 'pin_enabled': false, 'neutral_tab_title': reads > 1}});
        },
      });
      await pump(tester, signedIn(api), '/securite');
      await tester.tap(find.byKey(const Key('switch-discreet')));
      await settle(tester);
      expect(api.calls('PATCH me/security').single.data, {'discreet': true});
      expect(tester.widget<SwitchListTile>(find.byKey(const Key('switch-discreet'))).value, isTrue);
      await disposeApp(tester);
    });

    testWidgets('appareils connectés : liste et révocation', (tester) async {
      final api = baseApi({
        'GET me/sessions': (_) => jsonBody(200, {
          'data': [
            {'id': 'token-1', 'kind': 'token', 'name': 'Pixel 7', 'current': true, 'last_active_at': '2026-10-26T09:00:00+01:00'},
            {'id': 'web-abc', 'kind': 'web', 'name': 'Chrome', 'current': false, 'last_active_at': '2026-10-25T09:00:00+01:00'},
          ],
        }),
        'DELETE me/sessions/web-abc': (_) => jsonBody(200, {'data': {'revoked': true}}),
        'DELETE me/sessions': (_) => jsonBody(200, {'data': {'revoked': 1}}),
      });
      await pump(tester, signedIn(api), '/appareils');
      expect(find.text('Pixel 7'), findsOneWidget);
      expect(find.byKey(const Key('revoke-token-1')), findsNothing, reason: 'on ne se révoque pas soi-même ici');
      await tester.tap(find.byKey(const Key('revoke-web-abc')));
      await settle(tester);
      expect(api.calls('DELETE me/sessions/web-abc'), hasLength(1));
      await tester.tap(find.byKey(const Key('revoke-others')));
      await settle(tester);
      expect(api.calls('DELETE me/sessions'), hasLength(1));
      await disposeApp(tester);
    });

    testWidgets('export des données', (tester) async {
      final api = baseApi({'POST me/export': (_) => jsonBody(202, {'data': {'status': 'preparing', 'link_valid_hours': 48}})});
      await pump(tester, signedIn(api), '/securite');
      await tapVisible(tester, find.byKey(const Key('export-data')));
      await settle(tester);
      expect(api.calls('POST me/export'), hasLength(1));
      await disposeApp(tester);
    });

    testWidgets('suppression du compte : confirmation forte (mot + mot de passe), puis tout est effacé', (tester) async {
      final api = baseApi({'DELETE me': (_) => jsonBody(200, {'data': {'deleted': true}})});
      final env = signedIn(api);
      await env.db.privateCache.put('dashboard', '{}');
      await pump(tester, env, '/securite');
      await tapVisible(tester, find.byKey(const Key('delete-account')));
      await settle(tester);

      FilledButton confirm() => tester.widget<FilledButton>(find.byKey(const Key('delete-confirm')));
      expect(confirm().onPressed, isNull);
      await tester.enterText(find.byKey(const Key('delete-word')), 'oui');
      await tester.enterText(find.byKey(const Key('delete-password')), 'Secret123!');
      await tester.pump();
      expect(confirm().onPressed, isNull, reason: 'le mot SUPPRIMER est exigé');
      await tester.enterText(find.byKey(const Key('delete-word')), S.t('profile_settings_delete_confirm_word'));
      await tester.pump();
      await tester.tap(find.byKey(const Key('delete-confirm')));
      await settle(tester, 12);

      expect(api.calls('DELETE me').single.data, {'password': 'Secret123!'});
      expect(find.text(S.t('auth_login_heading')), findsOneWidget);
      expect(env.db.privateCache.get('dashboard'), isNull);
      expect(env.secrets.data.containsKey(SecretKeys.token), isFalse);
      await disposeApp(tester);
    });
  });

  group('déconnexion', () {
    testWidgets('efface tout le stockage privé et retire l’abonnement push', (tester) async {
      final api = baseApi({
        'DELETE me/push-subscriptions': (_) => jsonBody(204, null),
        'DELETE me/devices/dev-uuid-1': (_) => jsonBody(204, null),
      });
      final env = signedIn(api);
      await env.secrets.write(SecretKeys.token, 'tok');
      await env.secrets.write(SecretKeys.deviceToken, 'dev-token');
      await env.secrets.write('push_token', 'fcm-token-1');
      await env.db.privateCache.put('dashboard', '{"x":1}');
      await env.db.drafts.put('post', 'brouillon');
      await env.db.publicCache.put('zones', '[]');
      await pump(tester, env, '/profile');
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(kDeviceUuidPref, 'dev-uuid-1');
      await prefs.setString('push_permission', 'granted');
      await prefs.setBool('pref_mask_previews', true);
      await PinService(env.secrets, iterations: 2).set('4821');

      await tester.tap(find.byKey(const Key('profile-logout')));
      await settle(tester);
      await tester.tap(find.byKey(const Key('logout-confirm')));
      await settle(tester, 12);

      expect(api.calls('DELETE me/push-subscriptions').single.data, {'kind': 'fcm', 'token': 'fcm-token-1'});
      expect(api.calls('DELETE me/devices/dev-uuid-1'), hasLength(1));
      expect(api.calls('POST auth/logout'), hasLength(1));
      expect(env.secrets.data.keys.where((k) => k != SecretKeys.hiveKey), isEmpty, reason: 'jeton, appareil reconnu, PIN, jeton push');
      expect(env.db.privateCache.get('dashboard'), isNull);
      expect(env.db.session.get('user'), isNull);
      expect(env.db.drafts.get('post'), isNull);
      expect(env.db.publicCache.get('zones'), isNotNull, reason: 'contenus publics conservés');
      expect(prefs.getString(kDeviceUuidPref), isNull);
      expect(prefs.getString('push_permission'), isNull);
      expect(prefs.getBool('pref_mask_previews'), isNull);
      expect(find.text(S.t('auth_login_heading')), findsOneWidget);
      await disposeApp(tester);
    });
  });

  group('profil progressif', () {
    Map<String, dynamic> progressive(String next, {bool consent = false}) => {
      'data': {
        'progressive': {
          'groups': [
            {'key': 'pronouns', 'answered': true},
            {'key': 'gender_identity', 'answered': false},
            {'key': 'orientation', 'answered': false},
          ],
          'next': next,
          'done': false,
          'answered': 1,
          'total': 3,
          'sensitive_consent': consent,
        },
        // Déjà renseigné côté serveur : ne doit JAMAIS préremplir le formulaire.
        'gender_identity': 'non_binaire',
      },
    };

    testWidgets('champ sensible : jamais prérempli, consentement explicite requis', (tester) async {
      var next = 'gender_identity';
      final api = baseApi({
        'GET me/profile': (_) => jsonBody(200, progressive(next)),
        'PATCH me/profile': (_) {
          next = 'orientation';
          return jsonBody(200, progressive(next, consent: true));
        },
      });
      await pump(tester, signedIn(api), '/profil-progressif');
      expect(find.text(S.t('profile_progressive_groups_gender_identity_title')), findsOneWidget);
      expect(find.text(S.t('profile_progressive_groups_orientation_title')), findsNothing, reason: 'un groupe à la fois');
      FilledButton save() => tester.widget<FilledButton>(find.byKey(const Key('progressive-save'), skipOffstage: false));
      expect(tester.widget<RadioGroup<String>>(find.byType(RadioGroup<String>)).groupValue, isNull, reason: 'aucune valeur sensible préremplie');
      expect(tester.widget<CheckboxListTile>(find.byKey(const Key('progressive-consent'), skipOffstage: false)).value, isFalse);

      await tapVisible(tester, find.byKey(const Key('progressive-option-non_binaire')));
      await tester.pump();
      expect(save().onPressed, isNull, reason: 'sans consentement, rien ne part');
      await tapVisible(tester, find.byKey(const Key('progressive-consent')));
      await tester.pump();
      await tapVisible(tester, find.byKey(const Key('progressive-save')));
      await settle(tester, 10);

      expect(api.calls('PATCH me/profile').single.data, {'gender_identity': 'non_binaire', 'sensitive_consent': true});
      expect(find.text(S.t('profile_progressive_groups_orientation_title')), findsOneWidget);
      await disposeApp(tester);
    });

    testWidgets('« Je préfère ne pas le dire » marque le groupe traité sans valeur', (tester) async {
      final api = baseApi({
        'GET me/profile': (_) => jsonBody(200, progressive('orientation')),
        'PATCH me/profile': (_) => jsonBody(200, progressive('orientation')),
      });
      await pump(tester, signedIn(api), '/profil-progressif');
      await tapVisible(tester, find.byKey(const Key('progressive-skip')));
      await settle(tester, 10);
      expect(api.calls('PATCH me/profile').single.data, {'answered_groups': ['orientation']});
      await disposeApp(tester);
    });
  });
}
