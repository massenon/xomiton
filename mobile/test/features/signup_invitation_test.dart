import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/features/auth/presentation/signup_page.dart';

import '../support/harness.dart';
import '../support/signup_support.dart';

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('normalizeInvitation', () {
    test('code seul, lien collé, vide', () {
      expect(normalizeInvitation('  XM-ABCD-EFGH-JKMN '), 'XM-ABCD-EFGH-JKMN');
      expect(normalizeInvitation('https://xomiton.app/inscription?invitation=XM-ABCD-EFGH-JKMN&signature=abc'), 'XM-ABCD-EFGH-JKMN');
      expect(normalizeInvitation(''), '');
      expect(normalizeInvitation(null), '');
    });
  });

  group('inscription avec ou sans invitation (même formulaire en 3 étapes)', () {
    testWidgets('sans invitation : aucun champ « invitation » envoyé, pas de bandeau', (tester) async {
      final api = FakeApi(signupRoutes());
      await pumpSignup(tester, api);
      expect(find.text(S.t('mobile_signup_invitation_banner')), findsNothing);
      await fillAllAsMember(tester);
      await submit(tester);
      final body = api.calls('POST auth/register').single.data as Map;
      expect(body.containsKey('invitation'), isFalse);
      expect(body.containsKey('role'), isFalse, reason: 'un rôle ne se choisit jamais à l’inscription');
      expect(body.containsKey('age_confirmed'), isFalse, reason: 'l’âge est saisi (entier), plus de case');
    });

    testWidgets('lien /signup/:invitation : code prérempli, bandeau, envoyé avec le même formulaire', (tester) async {
      final api = FakeApi(signupRoutes());
      usePhone(tester);
      addTearDown(tester.view.reset);
      await tester.pumpWidget(await TestEnv(api: api).app());
      await tester.pumpAndSettle();
      GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/signup/XM-ABCD-EFGH-JKMN');
      await tester.pumpAndSettle();
      expect(find.text('XM-ABCD-EFGH-JKMN'), findsOneWidget);
      expect(find.text(S.t('mobile_signup_invitation_banner')), findsOneWidget);
      await fillAllAsMember(tester);
      await submit(tester);
      final body = api.calls('POST auth/register').single.data as Map;
      expect(body['invitation'], 'XM-ABCD-EFGH-JKMN');
      expect(body['member_type'], 'membre');
    });

    testWidgets('lien collé dans le champ : seul le code part au serveur', (tester) async {
      final api = FakeApi(signupRoutes());
      await pumpSignup(tester, api);
      await tester.enterText(find.byKey(const ValueKey('signup-invitation')), 'https://xomiton.app/inscription?invitation=XM-WXYZ-1234-ABCD');
      await tester.pump();
      await fillAllAsMember(tester);
      await submit(tester);
      expect((api.calls('POST auth/register').single.data as Map)['invitation'], 'XM-WXYZ-1234-ABCD');
    });

    testWidgets('invitation refusée (422) : message sous le champ, étape 1 rouverte, aucun compte', (tester) async {
      final api = FakeApi(signupRoutes(
        registerStatus: 422,
        registerBody: {
          'message': 'Invitation invalide',
          'code': 'invitation_invalid',
          'errors': {'invitation': ['Ce lien d’invitation n’est plus valable. Demande-en un nouveau à l’équipe Xomiton.']},
        },
      ));
      usePhone(tester);
      addTearDown(tester.view.reset);
      final env = TestEnv(api: api);
      await tester.pumpWidget(await env.app());
      await tester.pumpAndSettle();
      GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/signup/XM-VIEUX');
      await tester.pumpAndSettle();
      await fillAllAsMember(tester);
      await submit(tester);
      expect(stepOf(1), findsOneWidget);
      expect(find.textContaining('n’est plus valable'), findsWidgets);
    });

    testWidgets('l’étape 2 ne propose que les types du serveur : aucun rôle « pair »', (tester) async {
      final api = FakeApi(signupRoutes());
      await pumpSignup(tester, api);
      await fillStep1(tester);
      await next(tester);
      expect(find.text(S.t('auth_register_profile_member_types_pair')), findsNothing);
      expect(find.text('Membre de la communauté'), findsOneWidget);
      expect(find.text('Personne ressource / Allié·e'), findsOneWidget);
    });
  });
}
