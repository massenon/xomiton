import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'harness.dart';

final S = loadStrings();

Map<String, dynamic> referentialsJson({int minAge = 18, bool beninFirst = true, List<String> zoneCountries = const ['BJ']}) => {
      'data': {
        'min_age': minAge,
        'pronouns': [
          {'value': 'elle', 'label': 'Elle'},
          {'value': 'il', 'label': 'Il'},
          {'value': 'iel', 'label': 'Iel'},
          {'value': 'autre', 'label': 'Autre'},
        ],
        'member_types': [
          {'value': 'membre', 'label': 'Membre de la communauté'},
          {'value': 'allie', 'label': 'Personne ressource / Allié·e'},
        ],
        'countries': beninFirst
            ? [
                {'value': 'BJ', 'label': 'Bénin'},
                {'value': 'SN', 'label': 'Sénégal'},
              ]
            : [
                {'value': 'SN', 'label': 'Sénégal'},
                {'value': 'BJ', 'label': 'Bénin'},
              ],
        'zone_countries': zoneCountries,
        'ally_roles': [
          {'value': 'enseignant', 'label': 'Enseignant·e'},
          {'value': 'autre', 'label': 'Autre statut'},
        ],
        'gender_identities': [
          {'value': 'femme', 'label': 'Femme'},
          {'value': 'autre', 'label': 'Autre identité'},
          {'value': 'prefer_not', 'label': 'Je préfère ne pas le dire'},
        ],
        'orientations': [
          {'value': 'lesbienne', 'label': 'Lesbienne'},
          {'value': 'autre', 'label': 'Autre orientation'},
          {'value': 'prefer_not', 'label': 'Je préfère ne pas le dire'},
        ],
      },
    };

Map<String, FakeHandler> signupRoutes({
  Map<String, dynamic>? referentials,
  Map<String, dynamic>? registered,
  Map<String, dynamic>? me,
  int registerStatus = 201,
  Object? registerBody,
}) =>
    {
      'GET referentials/registration': (_) => jsonBody(200, referentials ?? referentialsJson()),
      'GET zones': (_) => jsonBody(200, {
            'data': [
              {
                'id': 1,
                'name': 'Littoral',
                'slug': 'littoral',
                'country_code': 'BJ',
                'position': 1,
                'communes': [
                  {'id': 3, 'name': 'Cotonou', 'slug': 'cotonou', 'position': 1}
                ]
              },
              {
                'id': 2,
                'name': 'Dakar',
                'slug': 'dakar',
                'country_code': 'SN',
                'position': 1,
                'communes': [
                  {'id': 7, 'name': 'Plateau', 'slug': 'plateau', 'position': 1}
                ]
              },
            ],
            'meta': {'count': 2}
          }),
      'POST auth/register': (_) => jsonBody(
            registerStatus,
            registerBody ??
                {
                  'data': {
                    'token': '3|reg',
                    'token_type': 'Bearer',
                    'user': registered ?? userJson(pending: true, emailVerified: false),
                  }
                },
          ),
      'GET me': (_) => jsonBody(200, {'data': me ?? userJson(pending: true, emailVerified: false)}),
    };

Future<void> pumpSignup(WidgetTester tester, FakeApi api, {bool online = true, String? path}) async {
  usePhone(tester);
  addTearDown(tester.view.reset);
  await tester.pumpWidget(await TestEnv(api: api, online: online).app());
  await tester.pumpAndSettle();
  await tester.tap(find.text(S.t('auth_login_create_account')).last);
  await tester.pumpAndSettle();
}

Finder regField(String field) => find.byWidgetPredicate((w) {
      final k = w.key;
      return k is ValueKey<String> && (k.value == 'reg-$field' || k.value.startsWith('reg-$field-'));
    });

Future<void> showField(WidgetTester tester, Finder f) async {
  await tester.ensureVisible(f);
  await tester.pumpAndSettle();
}

Future<void> typeIn(WidgetTester tester, String field, String text) async {
  final f = regField(field);
  await showField(tester, f);
  await tester.enterText(f, text);
  await tester.pump();
}

Future<void> choose(WidgetTester tester, String field, String label) async {
  final f = regField(field);
  await showField(tester, f);
  await tester.tap(f);
  await tester.pumpAndSettle();
  await tester.tap(find.text(label).last);
  await tester.pumpAndSettle();
}

Future<void> tapKey(WidgetTester tester, Key key) async {
  final f = find.byKey(key);
  await showField(tester, f);
  await tester.tap(f);
  await tester.pumpAndSettle();
}

Future<void> next(WidgetTester tester) => tapKey(tester, const ValueKey('reg-next'));
Future<void> submit(WidgetTester tester) => tapKey(tester, const ValueKey('reg-submit'));

Future<void> fillStep1(WidgetTester tester, {String age = '24'}) async {
  await typeIn(tester, 'first_name', 'Awa');
  await typeIn(tester, 'last_name', 'Sossou');
  await typeIn(tester, 'pseudo', 'Awa_S');
  await typeIn(tester, 'email', 'awa@exemple.bj');
  await typeIn(tester, 'age', age);
}

Future<void> fillStep2(WidgetTester tester, {String type = 'membre', String country = 'Bénin'}) async {
  await choose(tester, 'pronouns', 'Elle');
  await choose(tester, 'country_code', country);
  await tapKey(tester, ValueKey('member-type-$type'));
}

Future<void> fillMemberStep3(WidgetTester tester) async {
  await choose(tester, 'orientation', 'Lesbienne');
  await choose(tester, 'gender_identity', 'Femme');
  await tapKey(tester, const ValueKey('consent-sensitive'));
  await fillSecurity(tester);
}

Future<void> fillSecurity(WidgetTester tester) async {
  await typeIn(tester, 'password', 'Secret0Fort1');
  await typeIn(tester, 'password_confirmation', 'Secret0Fort1');
  await tapKey(tester, const ValueKey('consent-cgu'));
  await tapKey(tester, const ValueKey('consent-privacy'));
}

/// Remplit les trois étapes d'un·e membre, sans envoyer.
Future<void> fillAllAsMember(WidgetTester tester) async {
  await fillStep1(tester);
  await next(tester);
  await fillStep2(tester);
  await next(tester);
  await fillMemberStep3(tester);
}

Finder stepOf(int n) => find.text(S.t('auth_register_step_of', {'current': n, 'total': 3}));
