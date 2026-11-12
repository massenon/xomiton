// P18-09 (Q97) : badges vérifiés par l'administration — lecture de `author.badges`
// (jamais pour un contenu anonyme), affichage à côté du pseudo dans le fil et
// sur le profil (libellés du serveur, aucun classement).
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/features/auth/data/app_user.dart';
import 'package:xomiton/features/feed/data/feed_models.dart';
import 'package:xomiton/features/feed/presentation/verified_badges.dart';

import '../../support/fakes.dart';
import '../../support/harness.dart';

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  test('Author.fromJson lit les badges vérifiés et les ignore pour un contenu anonyme', () {
    final signed = Author.fromJson({
      'id': 3,
      'pseudo': 'Kossi',
      'anonymous': false,
      'badges': [
        {'key': 'pair_animateur', 'label': 'Pair animateur·rice', 'icon': 'award-fill'},
        {'key': '', 'label': 'vide'},
      ],
    });
    expect(signed.badges.map((b) => b.key), ['pair_animateur']);
    expect(signed.badges.single.label, 'Pair animateur·rice');

    final anonymous = Author.fromJson({
      'pseudo': 'Membre anonyme',
      'anonymous': true,
      'badges': [
        {'key': 'membre_verifie', 'label': 'Membre vérifié'},
      ],
    });
    expect(anonymous.badges, isEmpty);
    expect(Author.fromJson({'pseudo': 'Awa'}).badges, isEmpty);
  });

  test('AppUser.fromJson garde les libellés des badges de GET me', () {
    final user = AppUser.fromJson({
      'id': 1,
      'pseudo': 'Awa',
      'badges': [
        {'key': 'membre_verifie', 'label': 'Membre vérifié'},
      ],
    });
    expect(user.badges, ['Membre vérifié']);
    expect(AppUser.fromJson({'id': 2, 'pseudo': 'Bea'}).badges, isEmpty);
  });

  testWidgets('VerifiedBadges affiche chaque libellé et rien sans badge', (tester) async {
    await tester.pumpWidget(const MaterialApp(
      home: Scaffold(
        body: Column(
          children: [
            VerifiedBadges(badges: [VerifiedBadge(key: 'membre_verifie', label: 'Membre vérifié')]),
            VerifiedBadges(badges: []),
          ],
        ),
      ),
    ));
    expect(find.text('Membre vérifié'), findsOneWidget);
    expect(find.byKey(const ValueKey('verified-badge-membre_verifie')), findsOneWidget);
  });

  testWidgets('le fil montre le badge à côté du pseudo de l’auteur·rice', (tester) async {
    final api = FakeApi({
      'GET me': (_) => jsonBody(200, {'data': userJson()}),
      'GET feed': (_) => jsonBody(200, {
        'data': [
          {
            'id': 'P1',
            'author': {
              'id': 3,
              'pseudo': 'Kossi',
              'avatar_url': null,
              'role': 'pair',
              'anonymous': false,
              'badges': [
                {'key': 'pair_animateur', 'label': 'Pair animateur·rice', 'icon': 'award-fill'},
              ],
            },
            'is_mine': false,
            'body': 'Atelier samedi',
            'tag': 'temoignage',
            'tag_label': 'Témoignage',
            'likes_count': 0,
            'comments_count': 0,
            'liked': false,
            'poll': null,
            'published_at': DateTime.now().toIso8601String(),
            'can': {'update': false, 'delete': false, 'report': true, 'comment': true},
          },
        ],
        'meta': {'per_page': 10, 'next_cursor': null},
      }),
    });
    usePhone(tester);
    addTearDown(tester.view.reset);
    await tester.pumpWidget(await TestEnv(api: api, token: 'tok', cachedUser: userJson()).app());
    await settle(tester);
    goTo(tester, '/feed');
    await settle(tester, 10);

    expect(find.text('Atelier samedi'), findsOneWidget);
    expect(find.text('Pair animateur·rice'), findsOneWidget);
    await disposeApp(tester);
  });
}
