import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/core/storage/secret_store.dart';

import '../support/harness.dart';
import '../support/signup_support.dart';

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('étape 1 « Informations de base »', () {
    testWidgets('indicateur « Étape 1 sur 3 », champs obligatoires, aucune requête d’inscription', (tester) async {
      final api = FakeApi(signupRoutes());
      await pumpSignup(tester, api);
      expect(stepOf(1), findsOneWidget);
      expect(find.text(S.t('mobile_register_step_1')), findsWidgets);
      await next(tester);
      expect(stepOf(1), findsOneWidget);
      expect(find.text(S.t('mobile_register_err_required')), findsNWidgets(5));
      expect(find.text(S.t('mobile_register_fix_errors')), findsOneWidget);
      expect(api.calls('POST auth/register'), isEmpty);
    });

    testWidgets('âge sous le minimum DU SERVEUR : message avec ce minimum', (tester) async {
      final api = FakeApi(signupRoutes(referentials: referentialsJson(minAge: 18)));
      await pumpSignup(tester, api);
      expect(find.textContaining(S.t('mobile_register_age_hint', {'min_age': 18})), findsOneWidget);
      await fillStep1(tester, age: '17');
      await next(tester);
      expect(stepOf(1), findsOneWidget);
      expect(find.text(S.t('mobile_register_err_age_low', {'min_age': 18})), findsOneWidget);
    });

    testWidgets('pseudo, e-mail et âge non numérique', (tester) async {
      final api = FakeApi(signupRoutes());
      await pumpSignup(tester, api);
      await fillStep1(tester, age: 'abc');
      await typeIn(tester, 'pseudo', 'a b');
      await typeIn(tester, 'email', 'pas-un-email');
      await next(tester);
      expect(find.text(S.t('mobile_register_err_pseudo_format')), findsOneWidget);
      expect(find.text(S.t('mobile_register_err_email')), findsOneWidget);
      expect(find.text(S.t('mobile_register_err_age_number')), findsOneWidget);
    });

    testWidgets('étape valide : passe à l’étape 2 sans appel réseau d’inscription', (tester) async {
      final api = FakeApi(signupRoutes());
      await pumpSignup(tester, api);
      await fillStep1(tester);
      await next(tester);
      expect(stepOf(2), findsOneWidget);
      expect(api.calls('POST auth/register'), isEmpty);
    });
  });

  group('étape 2 « Profil & identité »', () {
    Future<void> toStep2(WidgetTester tester, FakeApi api) async {
      await pumpSignup(tester, api);
      await fillStep1(tester);
      await next(tester);
    }

    testWidgets('pronoms, pays et « Tu es… » obligatoires', (tester) async {
      final api = FakeApi(signupRoutes());
      await toStep2(tester, api);
      await next(tester);
      expect(stepOf(2), findsOneWidget);
      expect(find.text(S.t('mobile_register_err_required')), findsNWidgets(3));
    });

    testWidgets('zone et commune en cascade pour le pays choisi s’il a des zones actives (ici le Bénin seul)', (tester) async {
      final api = FakeApi(signupRoutes());
      await toStep2(tester, api);
      expect(find.text(S.t('mobile_register_zone')), findsNothing);
      await choose(tester, 'country_code', 'Sénégal');
      expect(find.text(S.t('mobile_register_zone')), findsNothing);
      await choose(tester, 'country_code', 'Bénin');
      expect(find.text(S.t('mobile_register_zone')), findsOneWidget);
      expect(find.text(S.t('mobile_register_commune')), findsOneWidget);
      await tester.tap(find.byType(DropdownButtonFormField<int>).first);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Littoral').last);
      await tester.pumpAndSettle();
      await tester.tap(find.byType(DropdownButtonFormField<int>).last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Cotonou').last);
      await tester.pumpAndSettle();
      // Repasser à un autre pays efface la cascade.
      await choose(tester, 'country_code', 'Sénégal');
      expect(find.text(S.t('mobile_register_zone')), findsNothing);
    });

    testWidgets('un pays hors Bénin avec des zones actives propose SES zones puis communes', (tester) async {
      final api = FakeApi(signupRoutes(referentials: referentialsJson(zoneCountries: const ['BJ', 'SN'])));
      await toStep2(tester, api);
      await choose(tester, 'country_code', 'Sénégal');
      expect(find.text(S.t('mobile_register_zone')), findsOneWidget);
      await tester.tap(find.byType(DropdownButtonFormField<int>).first);
      await tester.pumpAndSettle();
      // Seules les zones du pays choisi sont listées : pas « Littoral », qui est béninoise.
      expect(find.text('Littoral'), findsNothing);
      await tester.tap(find.text('Dakar').last);
      await tester.pumpAndSettle();
      await tester.tap(find.byType(DropdownButtonFormField<int>).last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Plateau').last);
      await tester.pumpAndSettle();
      // Changer de pays oublie la zone et la commune déjà choisies.
      await choose(tester, 'country_code', 'Bénin');
      await tester.tap(find.byType(DropdownButtonFormField<int>).first);
      await tester.pumpAndSettle();
      expect(find.text('Dakar'), findsNothing);
      expect(find.text('Littoral'), findsWidgets);
    });

    testWidgets('le Bénin est en premier dans la liste des pays, même si le serveur l’envoie plus loin', (tester) async {
      final api = FakeApi(signupRoutes(referentials: referentialsJson(beninFirst: false)));
      await toStep2(tester, api);
      await tester.tap(regField('country_code'));
      await tester.pumpAndSettle();
      final benin = tester.getTopLeft(find.text('Bénin').last).dy;
      final senegal = tester.getTopLeft(find.text('Sénégal').last).dy;
      expect(benin, lessThan(senegal));
    });

    testWidgets('revenir en arrière garde ce qui est saisi', (tester) async {
      final api = FakeApi(signupRoutes());
      await toStep2(tester, api);
      await fillStep2(tester);
      await next(tester);
      expect(stepOf(3), findsOneWidget);
      await tapKey(tester, const ValueKey('reg-previous'));
      expect(stepOf(2), findsOneWidget);
      expect(find.text('Elle'), findsWidgets);
      expect(find.text('Bénin'), findsWidgets);
      expect(tester.widget<RadioListTile<String>>(find.byKey(const ValueKey('member-type-membre'))).value, 'membre');
      await tapKey(tester, const ValueKey('reg-previous'));
      expect(stepOf(1), findsOneWidget);
      expect(tester.widget<TextField>(regField('first_name')).controller!.text, 'Awa');
      expect(tester.widget<TextField>(regField('age')).controller!.text, '24');
      expect(api.calls('POST auth/register'), isEmpty);
    });
  });

  group('étape 3 « Détails & sécurité »', () {
    Future<void> toStep3(WidgetTester tester, FakeApi api, {String type = 'membre'}) async {
      await pumpSignup(tester, api);
      await fillStep1(tester);
      await next(tester);
      await fillStep2(tester, type: type);
      await next(tester);
    }

    testWidgets('membre : orientation, identité de genre et consentement ; pas de statut d’allié·e', (tester) async {
      final api = FakeApi(signupRoutes());
      await toStep3(tester, api);
      expect(stepOf(3), findsOneWidget);
      expect(regField('orientation'), findsOneWidget);
      expect(regField('gender_identity'), findsOneWidget);
      expect(find.byKey(const ValueKey('consent-sensitive')), findsOneWidget);
      expect(regField('ally_role'), findsNothing);
    });

    testWidgets('allié·e : statut seulement', (tester) async {
      final api = FakeApi(signupRoutes());
      await toStep3(tester, api, type: 'allie');
      expect(regField('ally_role'), findsOneWidget);
      expect(regField('orientation'), findsNothing);
      expect(find.byKey(const ValueKey('consent-sensitive')), findsNothing);
    });

    testWidgets('« autre » ouvre un champ de précision obligatoire (membre)', (tester) async {
      final api = FakeApi(signupRoutes());
      await toStep3(tester, api);
      expect(regField('orientation_other'), findsNothing);
      await choose(tester, 'orientation', 'Autre orientation');
      await choose(tester, 'gender_identity', 'Autre identité');
      expect(regField('orientation_other'), findsOneWidget);
      expect(regField('gender_identity_other'), findsOneWidget);
      await fillSecurity(tester);
      await tapKey(tester, const ValueKey('consent-sensitive'));
      await submit(tester);
      expect(api.calls('POST auth/register'), isEmpty);
      expect(find.text(S.t('mobile_register_err_required')), findsNWidgets(2));
      // Une fois précisé, l'étape passe.
      await typeIn(tester, 'orientation_other', 'Pansexuelle');
      await typeIn(tester, 'gender_identity_other', 'Non-binaire');
      await submit(tester);
      expect(api.calls('POST auth/register'), hasLength(1));
      final body = api.calls('POST auth/register').single.data as Map;
      expect(body['orientation_other'], 'Pansexuelle');
      expect(body['gender_identity_other'], 'Non-binaire');
    });

    testWidgets('« autre » ouvre un champ de précision obligatoire (allié·e)', (tester) async {
      final api = FakeApi(signupRoutes());
      await toStep3(tester, api, type: 'allie');
      await choose(tester, 'ally_role', 'Autre statut');
      expect(regField('ally_role_other'), findsOneWidget);
      await fillSecurity(tester);
      await submit(tester);
      expect(api.calls('POST auth/register'), isEmpty);
      await typeIn(tester, 'ally_role_other', 'Bénévole');
      await submit(tester);
      final body = api.calls('POST auth/register').single.data as Map;
      expect(body['ally_role_other'], 'Bénévole');
      expect(body['member_type'], 'allie');
      expect(body.containsKey('orientation'), isFalse);
      expect(body.containsKey('sensitive_data'), isFalse);
    });

    testWidgets('mot de passe : jauge, confirmation, cases obligatoires non pré-cochées', (tester) async {
      final api = FakeApi(signupRoutes());
      await toStep3(tester, api);
      for (final k in ['consent-cgu', 'consent-privacy', 'consent-sensitive']) {
        await showField(tester, find.byKey(ValueKey(k)));
        expect(tester.widget<CheckboxListTile>(find.byKey(ValueKey(k))).value, isFalse);
      }
      await typeIn(tester, 'password', 'court');
      expect(find.text(S.t('auth_password_meter_label') + S.t('auth_password_strength_too_short')), findsOneWidget);
      await typeIn(tester, 'password_confirmation', 'autre');
      await submit(tester);
      expect(api.calls('POST auth/register'), isEmpty);
      expect(find.text(S.t('mobile_register_password_hint')), findsWidgets);
      expect(find.text(S.t('mobile_register_err_mismatch')), findsOneWidget);
      expect(find.text(S.t('mobile_register_err_consent')), findsWidgets);
    });
  });

  group('envoi unique', () {
    testWidgets('aucune requête d’inscription avant le bouton final ; un seul POST avec le contrat complet', (tester) async {
      final api = FakeApi(signupRoutes());
      await pumpSignup(tester, api);
      await fillAllAsMember(tester);
      expect(api.requests.where((r) => r.method == 'POST'), isEmpty, reason: 'aucun POST avant « Créer mon compte »');
      await submit(tester);
      final regs = api.calls('POST auth/register');
      expect(regs, hasLength(1));
      final b = regs.single.data as Map;
      expect(b['first_name'], 'Awa');
      expect(b['last_name'], 'Sossou');
      expect(b['pseudo'], 'Awa_S');
      expect(b['email'], 'awa@exemple.bj');
      expect(b['age'], 24);
      expect(b['pronouns'], 'elle');
      expect(b['country_code'], 'BJ');
      expect(b['member_type'], 'membre');
      expect(b['gender_identity'], 'femme');
      expect(b['orientation'], 'lesbienne');
      expect(b['sensitive_data'], true);
      expect(b['password'], 'Secret0Fort1');
      expect(b['password_confirmation'], 'Secret0Fort1');
      expect(b['cgu'], true);
      expect(b['privacy'], true);
      expect(b.containsKey('ally_role'), isFalse);
      expect(b.containsKey('invitation'), isFalse);
      expect(b.containsKey('role'), isFalse, reason: 'un rôle ne se choisit jamais à l’inscription');
    });

    testWidgets('succès : session ouverte, jeton stocké, écran « en attente de validation »', (tester) async {
      final api = FakeApi(signupRoutes());
      usePhone(tester);
      addTearDown(tester.view.reset);
      final env = TestEnv(api: api);
      await tester.pumpWidget(await env.app());
      await tester.pumpAndSettle();
      await tester.tap(find.text(S.t('auth_login_create_account')).last);
      await tester.pumpAndSettle();
      await fillAllAsMember(tester);
      await submit(tester);
      expect(env.secrets.data[SecretKeys.token], '3|reg');
      expect(find.text(S.t('mobile_pending_title')), findsOneWidget);
      // Seules les options permises sont proposées.
      for (final k in [
        'mobile_pending_allowed_read',
        'mobile_pending_allowed_sos',
        'mobile_pending_allowed_report',
        'mobile_pending_allowed_profile',
        'mobile_pending_blocked_publish',
        'mobile_pending_blocked_comment',
        'mobile_pending_blocked_messages',
        'mobile_pending_blocked_join',
        'mobile_pending_blocked_collect',
      ]) {
        await tester.scrollUntilVisible(find.text(S.t(k)), 120, scrollable: find.byType(Scrollable).first);
        expect(find.text(S.t(k)), findsOneWidget);
      }
    });

    testWidgets('sans réseau à l’envoi : message clair, rien n’est envoyé, données conservées', (tester) async {
      final api = FakeApi(signupRoutes());
      await pumpSignup(tester, api, online: false);
      await fillAllAsMember(tester);
      await submit(tester);
      expect(api.calls('POST auth/register'), isEmpty);
      await tester.ensureVisible(find.text(S.t('mobile_register_offline')));
      expect(find.text(S.t('mobile_register_offline')), findsOneWidget);
      expect(stepOf(3), findsOneWidget);
      expect(tester.widget<CheckboxListTile>(find.byKey(const ValueKey('consent-cgu'))).value, isTrue);
      expect(tester.widget<TextField>(find.descendant(of: regField('password'), matching: find.byType(TextField))).controller!.text, 'Secret0Fort1');
    });

    testWidgets('réseau coupé pendant l’envoi : même message, données conservées', (tester) async {
      final api = FakeApi({
        ...signupRoutes(),
        'POST auth/register': (_) => throw Exception('hors ligne'),
      });
      await pumpSignup(tester, api);
      await fillAllAsMember(tester);
      await submit(tester);
      expect(api.calls('POST auth/register'), hasLength(1));
      expect(stepOf(3), findsOneWidget);
      expect(tester.widget<TextField>(find.descendant(of: regField('password'), matching: find.byType(TextField))).controller!.text, 'Secret0Fort1');
    });

    testWidgets('422 sur un champ de l’étape 1 : étape 1 rouverte, erreur sous le champ', (tester) async {
      final api = FakeApi(signupRoutes(
        registerStatus: 422,
        registerBody: {
          'message': 'Ce pseudonyme est déjà utilisé.',
          'code': 'validation_failed',
          'errors': {
            'pseudo': ['Ce pseudonyme est déjà utilisé.'],
            'cgu': ['Les conditions doivent être acceptées.'],
          },
        },
      ));
      await pumpSignup(tester, api);
      await fillAllAsMember(tester);
      await submit(tester);
      expect(api.calls('POST auth/register'), hasLength(1));
      expect(stepOf(1), findsOneWidget);
      expect(find.text('Ce pseudonyme est déjà utilisé.'), findsWidgets);
      // L'erreur de l'étape 3 attend la personne en bas.
      await next(tester);
      await next(tester);
      await tester.ensureVisible(find.byKey(const ValueKey('consent-cgu')));
      expect(find.text('Les conditions doivent être acceptées.'), findsOneWidget);
    });

    testWidgets('422 sur l’étape 2 : étape 2 rouverte', (tester) async {
      final api = FakeApi(signupRoutes(
        registerStatus: 422,
        registerBody: {
          'message': 'Invalide',
          'code': 'validation_failed',
          'errors': {
            'country_code': ['Ce pays n’est pas proposé.']
          },
        },
      ));
      await pumpSignup(tester, api);
      await fillAllAsMember(tester);
      await submit(tester);
      expect(stepOf(2), findsOneWidget);
      expect(find.text('Ce pays n’est pas proposé.'), findsOneWidget);
    });

    testWidgets('422 sur l’étape 3 : on reste à l’étape 3, erreur sous le champ', (tester) async {
      final api = FakeApi(signupRoutes(
        registerStatus: 422,
        registerBody: {
          'message': 'Invalide',
          'code': 'validation_failed',
          'errors': {
            'password': ['Ce mot de passe est trop courant.']
          },
        },
      ));
      await pumpSignup(tester, api);
      await fillAllAsMember(tester);
      await submit(tester);
      expect(stepOf(3), findsOneWidget);
      expect(find.text('Ce mot de passe est trop courant.'), findsOneWidget);
    });

    testWidgets('le mot de passe n’apparaît dans aucune sortie de journal', (tester) async {
      final lines = <String>[];
      final old = debugPrint;
      debugPrint = (String? m, {int? wrapWidth}) => lines.add(m ?? '');
      try {
        final api = FakeApi(signupRoutes());
        await pumpSignup(tester, api);
        await fillAllAsMember(tester);
        await submit(tester);
      } finally {
        debugPrint = old;
      }
      expect(lines.where((l) => l.contains('Secret0Fort1') || l.contains('awa@exemple.bj')), isEmpty);
      expect(jsonEncode(lines).contains('Secret0Fort1'), isFalse);
    });
  });

  testWidgets('référentiels indisponibles : message d’erreur et nouvel essai', (tester) async {
    final api = FakeApi({...signupRoutes(), 'GET referentials/registration': (_) => jsonBody(500, {'message': 'x'})});
    await pumpSignup(tester, api);
    expect(find.text(S.t('common_retry')), findsOneWidget);
    expect(api.calls('POST auth/register'), isEmpty);
  });
}
