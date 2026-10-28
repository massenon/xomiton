import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/core/storage/secret_store.dart';

import '../support/harness.dart';

Future<void> pumpApp(WidgetTester tester, TestEnv env) async {
  usePhone(tester);
  addTearDown(tester.view.reset);
  await tester.pumpWidget(await env.app());
  await tester.pumpAndSettle();
}

Finder tr(String text) => find.text(text);

/// Fait défiler jusqu'au widget (animation incluse) puis le touche.
Future<void> tapVisible(WidgetTester tester, Finder f) async {
  await tester.ensureVisible(f);
  await tester.pumpAndSettle();
  await tester.tap(f);
}

Future<void> typeCode(WidgetTester tester, String code) async {
  for (var i = 0; i < code.length; i++) {
    await tester.enterText(find.byKey(ValueKey('otp-$i')), code[i]);
  }
  await tester.pump();
}

Map<String, FakeHandler> dashboardRoutes() => {
      'GET dashboard': (_) => jsonBody(200, {
            'data': {
              'tiles': [
                {'key': 'report', 'route': '/sighub', 'count': null},
                {'key': 'emergency', 'route': '/urgences', 'count': null},
                {'key': 'assistant', 'route': '/assistant', 'count': null},
                {'key': 'learn', 'route': '/thematiques', 'count': 5},
              ],
              'counters': {'notifications': 0, 'messages': 0},
              'profile': {'completion': 43, 'complete': false},
              'featured': {'total': 0, 'items': []},
              'next_event': null,
              'peer': null,
            }
          }),
    };

final S = loadStrings();

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('carrousel', () {
    testWidgets('4 diapositives, aucun défilement automatique', (tester) async {
      await pumpApp(tester, TestEnv(api: FakeApi(), seen: false));
      expect(tr(S.t('mobile_onboarding_slide_1_title')), findsOneWidget);
      // 60 s plus tard, on est toujours sur la première diapositive.
      await tester.pump(const Duration(seconds: 60));
      await tester.pumpAndSettle();
      expect(tr(S.t('mobile_onboarding_slide_1_title')), findsOneWidget);
      expect(find.byType(PageView), findsOneWidget);
      final titles = [S.t('mobile_onboarding_slide_2_title'), S.t('mobile_onboarding_slide_3_title'), S.t('mobile_onboarding_slide_4_title')];
      for (final t in titles) {
        await tester.tap(tr(S.t('common_next_item')));
        await tester.pumpAndSettle();
        expect(tr(t), findsOneWidget);
      }
      expect(tr(S.t('auth_login_create_account')), findsOneWidget);
      expect(tr(S.t('auth_register_sign_in')), findsOneWidget);
      expect(tr(S.t('common_next_item')), findsNothing);
    });

    testWidgets('plein écran : photo cover, « Passer » et points accessibles, « Se connecter » sur la dernière', (tester) async {
      final handle = tester.ensureSemantics();
      await pumpApp(tester, TestEnv(api: FakeApi(), seen: false));
      // 4 photos plein écran, jamais déformées.
      final images = tester.widgetList<Image>(find.byType(Image, skipOffstage: false)).where((i) => i.fit == BoxFit.cover);
      expect(images.length, greaterThanOrEqualTo(1));
      // « Passer » : cible d’au moins 44 px.
      expect(tester.getSize(find.byKey(const ValueKey('splash-skip'))).height, greaterThanOrEqualTo(44));
      // Un point par diapositive, nommé pour les lecteurs d’écran.
      expect(find.bySemanticsLabel(S.t('mobile_onboarding_slide_label', {'current': 3, 'total': 4})), findsWidgets);
      // Balayage vers la gauche : diapositive suivante.
      await tester.drag(find.byType(PageView), const Offset(-400, 0));
      await tester.pumpAndSettle();
      expect(tr(S.t('mobile_onboarding_slide_2_title')), findsOneWidget);
      // Point 4 : dernière diapositive, deux actions.
      await tester.tap(find.byWidgetPredicate((w) => w is Semantics && w.properties.button == true && w.properties.label == S.t('mobile_onboarding_slide_label', {'current': 4, 'total': 4})));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('splash-signup')), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('splash-login')));
      await tester.pumpAndSettle();
      expect(tr(S.t('auth_login_heading')), findsOneWidget);
      handle.dispose();
    });

    testWidgets('« Passer » mène à la connexion et mémorise onboarding.seen', (tester) async {
      final env = TestEnv(api: FakeApi(), seen: false);
      await pumpApp(tester, env);
      await tester.tap(tr(S.t('auth_register_skip')));
      await tester.pumpAndSettle();
      expect(tr(S.t('auth_login_heading')), findsOneWidget);
    });

    testWidgets('« Créer un compte » (dernière diapositive) ouvre l’inscription', (tester) async {
      await pumpApp(tester, TestEnv(api: FakeApi(), seen: false));
      for (var i = 0; i < 3; i++) {
        await tester.tap(tr(S.t('common_next_item')));
        await tester.pumpAndSettle();
      }
      await tester.tap(tr(S.t('auth_login_create_account')));
      await tester.pumpAndSettle();
      expect(tr(S.t('auth_register_account_heading')), findsOneWidget);
      expect(tr(S.t('auth_register_step_of', {'current': 1, 'total': 4})), findsOneWidget);
    });

    testWidgets('onboarding déjà vu : démarrage sur la connexion', (tester) async {
      await pumpApp(tester, TestEnv(api: FakeApi()));
      expect(tr(S.t('auth_login_heading')), findsOneWidget);
    });
  });

  group('connexion', () {
    testWidgets('202 otp_sent → écran OTP → vérification → accueil', (tester) async {
      final api = FakeApi({
        'POST auth/login': (_) => jsonBody(202, {
              'data': {'otp_sent': true, 'purpose': 'login', 'email': 'awa@exemple.bj', 'resend_after': 30, 'expires_in': 600}
            }),
        'POST auth/otp/verify': (_) => jsonBody(200, {
              'data': {'token': '1|abc', 'token_type': 'Bearer', 'user': userJson(), 'device_token': 'dev-123', 'device_token_expires_at': '2026-12-01T00:00:00+01:00'}
            }),
        ...dashboardRoutes(),
      });
      final env = TestEnv(api: api);
      await pumpApp(tester, env);

      await tester.enterText(find.widgetWithText(TextField, S.t('auth_login_identifier')), 'Awa_S');
      await tester.enterText(find.widgetWithText(TextField, S.t('auth_login_password')), 'Secret0Fort1');
      await tester.tap(tr(S.t('auth_login_submit')).last);
      await tester.pumpAndSettle();

      final login = api.calls('POST auth/login').single;
      expect((login.data as Map)['login'], 'Awa_S');
      expect((login.data as Map)['device_name'], isNotEmpty);
      expect(login.data as Map, isNot(contains('device_token')));

      expect(tr(S.t('auth_otp_screen_heading')), findsOneWidget);
      expect(find.textContaining('awa@exemple.bj'), findsWidgets);
      expect(tr(S.t('auth_otp_screen_resend_in', {'seconds': 30})), findsOneWidget);

      await typeCode(tester, '123456');
      await tester.tap(tr(S.t('auth_otp_screen_submit')));
      await tester.pumpAndSettle();

      final verify = api.calls('POST auth/otp/verify').single;
      expect((verify.data as Map)['purpose'], 'login');
      expect((verify.data as Map)['code'], '123456');
      expect(env.secrets.data[SecretKeys.token], '1|abc');
      expect(env.secrets.data[SecretKeys.deviceToken], 'dev-123');
      expect(find.text(S.t('peer_home_greeting', {'name': 'Awa_S'})), findsOneWidget);
    });

    testWidgets('200 direct (appareil reconnu) : pas d’OTP, device_token renvoyé', (tester) async {
      final api = FakeApi({
        'POST auth/login': (_) => jsonBody(200, {
              'data': {'token': '2|xyz', 'token_type': 'Bearer', 'user': userJson(), 'device_token': 'dev-2', 'device_token_expires_at': 'x'}
            }),
        ...dashboardRoutes(),
      });
      final env = TestEnv(api: api);
      env.secrets.data[SecretKeys.deviceToken] = 'dev-1';
      await pumpApp(tester, env);
      await tester.enterText(find.widgetWithText(TextField, S.t('auth_login_identifier')), 'awa@exemple.bj');
      await tester.enterText(find.widgetWithText(TextField, S.t('auth_login_password')), 'Secret0Fort1');
      await tester.tap(tr(S.t('auth_login_submit')).last);
      await tester.pumpAndSettle();
      expect((api.calls('POST auth/login').single.data as Map)['device_token'], 'dev-1');
      expect(tr(S.t('auth_otp_screen_heading')), findsNothing);
      expect(find.text(S.t('peer_home_greeting', {'name': 'Awa_S'})), findsOneWidget);
      expect(env.secrets.data[SecretKeys.deviceToken], 'dev-2');
    });

    testWidgets('identifiants refusés : message indifférencié du serveur, reste sur la connexion', (tester) async {
      final api = FakeApi({
        'POST auth/login': (_) => jsonBody(422, {
              'message': 'Identifiants incorrects. Vérifie ton e-mail et ton mot de passe.',
              'code': 'invalid_credentials',
              'errors': {'login': ['Identifiants incorrects. Vérifie ton e-mail et ton mot de passe.']}
            }),
      });
      await pumpApp(tester, TestEnv(api: api));
      await tester.enterText(find.widgetWithText(TextField, S.t('auth_login_identifier')), 'x');
      await tester.enterText(find.widgetWithText(TextField, S.t('auth_login_password')), 'y');
      await tester.tap(tr(S.t('auth_login_submit')).last);
      await tester.pumpAndSettle();
      expect(find.textContaining('Identifiants incorrects'), findsOneWidget);
      expect(tr(S.t('auth_login_heading')), findsOneWidget);
    });

    testWidgets('champs vides : erreurs locales, aucun appel réseau', (tester) async {
      final api = FakeApi();
      await pumpApp(tester, TestEnv(api: api));
      await tester.tap(tr(S.t('auth_login_submit')).last);
      await tester.pumpAndSettle();
      expect(api.requests, isEmpty);
      expect(find.textContaining('est obligatoire'), findsNWidgets(2));
    });

    testWidgets('429 : toast « Trop de requêtes » avec le délai', (tester) async {
      final api = FakeApi({
        'POST auth/login': (_) => jsonBody(429, {'message': 'Trop de tentatives.', 'code': 'too_many_requests', 'retry_after': 42}),
      });
      await pumpApp(tester, TestEnv(api: api));
      await tester.enterText(find.widgetWithText(TextField, S.t('auth_login_identifier')), 'x');
      await tester.enterText(find.widgetWithText(TextField, S.t('auth_login_password')), 'y');
      await tester.tap(tr(S.t('auth_login_submit')).last);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 300));
      expect(find.textContaining(S.t('errors_api_throttled', {'seconds': 42})), findsOneWidget);
    });

    testWidgets('401 pendant la session : retour à la connexion', (tester) async {
      final api = FakeApi({
        'GET dashboard': (_) => jsonBody(401, {'message': 'Connecte-toi pour continuer.', 'code': 'unauthenticated'}),
        'GET me': (_) => jsonBody(401, {'message': 'Connecte-toi pour continuer.', 'code': 'unauthenticated'}),
      });
      final env = TestEnv(api: api, token: 'vieux', cachedUser: userJson());
      await pumpApp(tester, env);
      expect(tr(S.t('auth_login_heading')), findsOneWidget);
      expect(env.secrets.data.containsKey(SecretKeys.token), isFalse);
    });
  });

  group('inscription en 4 phases', () {
    FakeApi signupApi() => FakeApi({
          'GET zones': (_) => jsonBody(200, {
                'data': [
                  {'id': 1, 'name': 'Littoral', 'slug': 'littoral', 'position': 1, 'communes': [{'id': 3, 'name': 'Cotonou', 'slug': 'cotonou', 'position': 1}]},
                  {'id': 2, 'name': 'Zou', 'slug': 'zou', 'position': 2, 'communes': []},
                ],
                'meta': {'count': 2}
              }, headers: {'ETag': '"z1"'}),
          'POST auth/register': (_) => jsonBody(201, {
                'data': {'token': '3|reg', 'token_type': 'Bearer', 'user': userJson(emailVerified: false), 'email_verification_pending': true}
              }),
          'PATCH me/profile': (_) => jsonBody(200, {'data': userJson(emailVerified: false)}),
          'PATCH me/newsletter': (_) => jsonBody(200, {'data': {}}),
          'POST auth/otp/verify': (_) => jsonBody(200, {'data': {'email_verified': true, 'user': userJson()}}),
          'GET me': (_) => jsonBody(200, {'data': userJson()}),
          ...dashboardRoutes(),
        });

    Future<void> fillPhase1(WidgetTester tester) async {
      await tester.enterText(find.widgetWithText(TextField, S.t('auth_register_account_pseudo')), 'Awa_S');
      await tester.enterText(find.widgetWithText(TextField, S.t('auth_forgot_email')), 'awa@exemple.bj');
      await tester.enterText(find.widgetWithText(TextField, S.t('auth_login_password')), 'Secret0Fort1');
      await tester.pump();
      await tapVisible(tester, find.byKey(const ValueKey('consent-cgu')));
      await tapVisible(tester, find.byKey(const ValueKey('consent-privacy')));
      await tapVisible(tester, find.byKey(const ValueKey('consent-age')));
      await tester.pump();
      await tapVisible(tester, tr(S.t('auth_register_account_submit')));
      await tester.pumpAndSettle();
    }

    testWidgets('phase 1 : consentements non pré-cochés et obligatoires, validation locale', (tester) async {
      final api = signupApi();
      final env = TestEnv(api: api);
      await pumpApp(tester, env);
      await tester.tap(tr(S.t('auth_login_create_account')));
      await tester.pumpAndSettle();
      expect(tr(S.t('auth_register_step_of', {'current': 1, 'total': 4})), findsOneWidget);
      expect(tr(S.t('auth_register_skip')), findsNothing);
      for (final k in ['consent-cgu', 'consent-privacy', 'consent-age']) {
        expect(tester.widget<CheckboxListTile>(find.byKey(ValueKey(k))).value, isFalse);
      }
      await tapVisible(tester, tr(S.t('auth_register_account_submit')));
      await tester.pumpAndSettle();
      expect(api.calls('POST auth/register'), isEmpty);
      expect(find.textContaining('est obligatoire'), findsWidgets);
      expect(find.textContaining(S.t('auth_consent_required')), findsOneWidget);
    });

    testWidgets('parcours complet : « Passer » sur 2 et 3 seulement, puis OTP d’inscription', (tester) async {
      final api = signupApi();
      final env = TestEnv(api: api);
      await pumpApp(tester, env);
      await tester.tap(tr(S.t('auth_login_create_account')));
      await tester.pumpAndSettle();
      await fillPhase1(tester);

      final reg = api.calls('POST auth/register').single.data as Map;
      expect(reg['pseudo'], 'Awa_S');
      expect(reg['cgu'], true);
      expect(reg['privacy'], true);
      expect(env.secrets.data[SecretKeys.token], '3|reg');

      // Phase 2 : facultative, avec « Passer ».
      expect(tr(S.t('auth_register_step_of_optional', {'current': 2, 'total': 4})), findsOneWidget);
      expect(tr(S.t('auth_register_skip')), findsOneWidget);
      // Les départements viennent de GET zones.
      await tester.tap(find.widgetWithText(ChoiceChip, S.t('auth_register_profile_pronouns_options_elle')));
      await tester.pump();
      await tester.tap(find.byType(DropdownButtonFormField<int>).first);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Littoral').last);
      await tester.pumpAndSettle();
      await tester.tap(find.byType(DropdownButtonFormField<int>).last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Cotonou').last);
      await tester.pumpAndSettle();
      await tapVisible(tester, tr(S.t('common_next')));
      await tester.pumpAndSettle();
      final profile = api.calls('PATCH me/profile').single.data as Map;
      expect(profile, {'pronouns': 'elle', 'zone_id': 1, 'commune_id': 3});

      // Phase 3 : « Passer » sans rien envoyer.
      expect(tr(S.t('auth_register_step_of_optional', {'current': 3, 'total': 4})), findsOneWidget);
      await tester.tap(tr(S.t('auth_register_skip')));
      await tester.pumpAndSettle();
      expect(api.calls('PATCH me/profile'), hasLength(1));

      // Phase 4 : deux consentements facultatifs, pas de « Passer ».
      expect(tr(S.t('auth_register_step_of', {'current': 4, 'total': 4})), findsOneWidget);
      expect(tr(S.t('auth_register_skip')), findsNothing);
      await tester.tap(find.byKey(const ValueKey('consent-newsletter')));
      await tester.pump();
      await tapVisible(tester, tr(S.t('auth_register_finish')));
      await tester.pumpAndSettle();
      expect((api.calls('PATCH me/newsletter').single.data as Map)['subscribed'], true);

      // OTP d'inscription, avec « Plus tard ».
      expect(tr(S.t('auth_otp_screen_heading')), findsOneWidget);
      expect(tr(S.t('auth_otp_screen_later')), findsOneWidget);
      await typeCode(tester, '654321');
      await tester.tap(tr(S.t('auth_otp_screen_submit')));
      await tester.pumpAndSettle();
      expect((api.calls('POST auth/otp/verify').single.data as Map)['purpose'], 'signup');
      expect(find.text(S.t('peer_home_greeting', {'name': 'Awa_S'})), findsOneWidget);
    });

    testWidgets('pseudo déjà pris : erreur du serveur sous le champ, on reste en phase 1', (tester) async {
      final api = signupApi();
      api.routes['POST auth/register'] = (_) => jsonBody(422, {
            'message': 'Ce pseudonyme est déjà utilisé.',
            'code': 'validation_failed',
            'errors': {'pseudo': ['Ce pseudonyme est déjà utilisé.']}
          });
      await pumpApp(tester, TestEnv(api: api));
      await tester.tap(tr(S.t('auth_login_create_account')));
      await tester.pumpAndSettle();
      await fillPhase1(tester);
      expect(tr(S.t('auth_register_step_of', {'current': 1, 'total': 4})), findsOneWidget);
      expect(tr('Ce pseudonyme est déjà utilisé.'), findsOneWidget);
    });
  });

  group('mot de passe oublié', () {
    testWidgets('e-mail → code → nouveau mot de passe → connexion', (tester) async {
      final api = FakeApi({
        'POST auth/password/forgot': (_) => jsonBody(202, {'data': {'sent': true, 'message': 'x', 'expires_in': 600}}),
        'POST auth/otp/verify': (_) => jsonBody(200, {'data': {'reset_token': 'rst-1', 'expires_at': 'x'}}),
        'POST auth/password/reset': (_) => jsonBody(200, {'data': {'reset': true}}),
      });
      await pumpApp(tester, TestEnv(api: api));
      await tester.tap(tr(S.t('auth_login_forgot')));
      await tester.pumpAndSettle();
      await tester.enterText(find.widgetWithText(TextField, S.t('auth_forgot_email')), 'awa@exemple.bj');
      await tester.tap(tr(S.t('auth_forgot_submit')));
      await tester.pumpAndSettle();
      expect(api.calls('POST auth/password/forgot'), hasLength(1));
      expect(tr(S.t('auth_otp_screen_heading')), findsOneWidget);
      expect(find.textContaining(S.t('auth_otp_screen_purpose_reset')), findsOneWidget);

      await typeCode(tester, '111222');
      await tester.tap(tr(S.t('auth_otp_screen_submit')));
      await tester.pumpAndSettle();
      expect((api.calls('POST auth/otp/verify').single.data as Map)['purpose'], 'reset');

      expect(tr(S.t('auth_reset_heading')), findsOneWidget);
      await tester.enterText(find.widgetWithText(TextField, S.t('auth_reset_password')), 'Nouveau0Fort1');
      await tester.enterText(find.widgetWithText(TextField, S.t('auth_reset_password_confirmation')), 'Autre0Fort1x');
      await tester.tap(tr(S.t('auth_reset_submit')));
      await tester.pumpAndSettle();
      expect(api.calls('POST auth/password/reset'), isEmpty);
      expect(find.textContaining('confirmation'), findsWidgets);

      await tester.enterText(find.widgetWithText(TextField, S.t('auth_reset_password_confirmation')), 'Nouveau0Fort1');
      await tester.tap(tr(S.t('auth_reset_submit')));
      await tester.pumpAndSettle();
      final reset = api.calls('POST auth/password/reset').single.data as Map;
      expect(reset['reset_token'], 'rst-1');
      expect(reset['email'], 'awa@exemple.bj');
      expect(tr(S.t('auth_login_heading')), findsOneWidget);
    });

    testWidgets('code erroné : message du serveur et cases vidées', (tester) async {
      final api = FakeApi({
        'POST auth/password/forgot': (_) => jsonBody(202, {'data': {'sent': true}}),
        'POST auth/otp/verify': (_) => jsonBody(422, {
              'message': 'Ce code est incorrect ou a expiré. Vérifie le dernier e-mail reçu ou demande un nouveau code.',
              'code': 'otp_invalid',
              'errors': {'code': ['Ce code est incorrect ou a expiré.']}
            }),
      });
      await pumpApp(tester, TestEnv(api: api));
      await tester.tap(tr(S.t('auth_login_forgot')));
      await tester.pumpAndSettle();
      await tester.enterText(find.widgetWithText(TextField, S.t('auth_forgot_email')), 'awa@exemple.bj');
      await tester.tap(tr(S.t('auth_forgot_submit')));
      await tester.pumpAndSettle();
      await typeCode(tester, '000000');
      await tester.tap(tr(S.t('auth_otp_screen_submit')));
      await tester.pumpAndSettle();
      expect(find.textContaining(S.t('auth_otp_invalid')), findsOneWidget);
      expect(tester.widget<TextField>(find.byKey(const ValueKey('otp-0'))).controller!.text, '');
    });
  });

  test('corps JSON des fixtures valides', () {
    expect(jsonEncode(userJson()), contains('Awa_S'));
  });
}
