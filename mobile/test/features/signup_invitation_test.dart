import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/features/auth/presentation/signup_page.dart';

import '../support/harness.dart';

final S = loadStrings();

Future<void> pump(WidgetTester tester, FakeApi api) async {
  usePhone(tester);
  addTearDown(tester.view.reset);
  await tester.pumpWidget(await TestEnv(api: api, seen: true).app());
  await tester.pumpAndSettle();
}

/// Amène le widget au milieu de l'écran (loin des bords) avant de le toucher.
Future<void> centerOn(WidgetTester tester, Finder f) async {
  // La liste est paresseuse : un widget construit peut être hors écran, on fait défiler jusqu'à le voir.
  for (var i = 0; i < 12 && tester.getRect(f).bottom > 720; i++) {
    await tester.drag(find.byType(ListView).first, const Offset(0, -150));
    await tester.pumpAndSettle();
  }
}

Future<void> fillAccount(WidgetTester tester, {bool age = true}) async {
  Finder field(String key) => find.widgetWithText(TextField, S.t(key));
  await tester.enterText(field('auth_register_account_pseudo'), 'Awa_S');
  await tester.enterText(field('auth_register_account_email'), 'awa@exemple.bj');
  await tester.enterText(find.byType(TextField).at(2), 'Secret0Fort1');
  for (final k in ['consent-cgu', 'consent-privacy', if (age) 'consent-age']) {
    await centerOn(tester, find.byKey(ValueKey(k)));
    await tester.tap(find.byKey(ValueKey(k)));
    await tester.pump();
  }
  await tester.pump();
}

Future<void> submit(WidgetTester tester) async {
  final b = find.text(S.t('auth_register_account_submit'));
  await centerOn(tester, b);
  await tester.tap(b);
  await tester.pumpAndSettle();
}

Map<String, FakeHandler> registerRoutes({int status = 201, Object? body}) => {
      'POST auth/register': (_) => jsonBody(
            status,
            body ??
                {
                  'data': {'token': '3|reg', 'token_type': 'Bearer', 'user': userJson(), 'email_verification_pending': true}
                },
          ),
      'GET zones': (_) => jsonBody(200, {'data': []}),
    };

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('normalizeInvitation', () {
    test('code seul, lien collé, vide', () {
      expect(normalizeInvitation('  XM-ABCD-EFGH-JKMN '), 'XM-ABCD-EFGH-JKMN');
      expect(normalizeInvitation('https://xomiton.bj/inscription?invitation=XM-ABCD-EFGH-JKMN&signature=abc'), 'XM-ABCD-EFGH-JKMN');
      expect(normalizeInvitation(''), '');
      expect(normalizeInvitation(null), '');
    });
  });

  group('inscription membre seulement', () {
    testWidgets('sans invitation : aucun champ « invitation » envoyé, pas de bandeau', (tester) async {
      final api = FakeApi(registerRoutes());
      await pump(tester, api);
      await tester.tap(find.text(S.t('auth_login_create_account')).last);
      await tester.pumpAndSettle();
      await fillAccount(tester);
      await submit(tester);
      final body = api.calls('POST auth/register').single.data as Map;
      expect(body.containsKey('invitation'), isFalse);
      expect(body['age_confirmed'], true);
      expect(body.containsKey('role'), isFalse, reason: 'un rôle ne se choisit jamais à l’inscription');
      expect(body.containsKey('member_type'), isFalse);
    });

    testWidgets('la case « 15 ans » est obligatoire : sans elle, rien n’est envoyé', (tester) async {
      final api = FakeApi(registerRoutes());
      await pump(tester, api);
      await tester.tap(find.text(S.t('auth_login_create_account')).last);
      await tester.pumpAndSettle();
      await fillAccount(tester, age: false);
      await submit(tester);
      expect(api.calls('POST auth/register'), isEmpty);
      expect(find.text(S.t('auth_age_confirmation_required', {'min_age': kMinRegistrationAge})), findsOneWidget);
    });

    testWidgets('lien /signup/:invitation : code prérempli, bandeau, envoyé avec le même formulaire de membre', (tester) async {
      final api = FakeApi(registerRoutes());
      await pump(tester, api);
      GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/signup/XM-ABCD-EFGH-JKMN');
      await tester.pumpAndSettle();
      expect(find.text('XM-ABCD-EFGH-JKMN'), findsOneWidget);
      expect(find.text(S.t('mobile_signup_invitation_banner')), findsOneWidget);
      await fillAccount(tester);
      await submit(tester);
      final body = api.calls('POST auth/register').single.data as Map;
      expect(body['invitation'], 'XM-ABCD-EFGH-JKMN');
      expect(body['age_confirmed'], true);
      // La phase 2 s'ouvre : le compte est créé, le rôle viendra à la validation.
      expect(find.text(S.t('auth_register_step_of', {'current': 2, 'total': 4})), findsOneWidget);
    });

    testWidgets('lien collé dans le champ : seul le code part au serveur', (tester) async {
      final api = FakeApi(registerRoutes());
      await pump(tester, api);
      await tester.tap(find.text(S.t('auth_login_create_account')).last);
      await tester.pumpAndSettle();
      await tester.enterText(find.byKey(const ValueKey('signup-invitation')), 'https://xomiton.bj/inscription?invitation=XM-WXYZ-1234-ABCD');
      await tester.pump();
      await fillAccount(tester);
      await submit(tester);
      expect((api.calls('POST auth/register').single.data as Map)['invitation'], 'XM-WXYZ-1234-ABCD');
    });

    testWidgets('invitation refusée (422 invitation_invalid) : message sous le champ, aucun compte', (tester) async {
      final api = FakeApi(registerRoutes(
        status: 422,
        body: {
          'message': 'Invitation invalide',
          'code': 'invitation_invalid',
          'errors': {'invitation': ['Ce lien d’invitation n’est plus valable (déjà utilisé, expiré ou annulé). Demande-en un nouveau à l’équipe Xomiton.']},
        },
      ));
      await pump(tester, api);
      GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/signup/XM-VIEUX');
      await tester.pumpAndSettle();
      await fillAccount(tester);
      await submit(tester);
      expect(find.text(S.t('auth_invitation_invalid')), findsOneWidget);
      expect(find.text(S.t('auth_register_step_of', {'current': 1, 'total': 4})), findsOneWidget);
    });

    testWidgets('la phase 2 ne propose plus « pair » : aucun rôle ne s’obtient à l’inscription', (tester) async {
      final api = FakeApi({...registerRoutes(), 'PATCH me/profile': (_) => jsonBody(200, {'data': {}})});
      await pump(tester, api);
      await tester.tap(find.text(S.t('auth_login_create_account')).last);
      await tester.pumpAndSettle();
      await fillAccount(tester);
      await submit(tester);
      expect(find.text(S.t('auth_register_profile_member_types_pair')), findsNothing);
      expect(find.text(S.t('auth_register_profile_member_type')), findsNothing);
    });
  });
}
