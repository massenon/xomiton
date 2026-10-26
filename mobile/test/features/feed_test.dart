import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/core/providers/core_providers.dart';

import '../support/fakes.dart';
import '../support/harness.dart';

final S = loadStrings();

Map<String, dynamic> post(
  String id,
  String body, {
  bool liked = false,
  int likes = 2,
  Map<String, dynamic>? poll,
}) => {
  'id': id,
  'author': {
    'id': 3,
    'pseudo': 'Kafui',
    'avatar_url': null,
    'role': 'membre',
    'anonymous': false,
  },
  'is_mine': false,
  'body': body,
  'tag': 'temoignage',
  'tag_label': 'Témoignage',
  'likes_count': likes,
  'comments_count': 1,
  'liked': liked,
  'poll': poll,
  'published_at': DateTime.now()
      .subtract(const Duration(hours: 2))
      .toIso8601String(),
  'can': {'update': false, 'delete': false, 'report': true, 'comment': true},
};

Map<String, dynamic> poll({
  bool voted = false,
  List<int> mine = const [],
  int a = 4,
  int b = 6,
}) {
  final pa = ((a * 100) / (a + b)).round();
  return {
    'question': 'Quel thème ?',
    'multiple': false,
    'closed': false,
    'voters_count': a + b,
    'results_visible': voted,
    'has_voted': voted,
    'my_choices': mine,
    'can_vote': true,
    'options': [
      {
        'id': 1,
        'label': 'Santé',
        'votes_count': voted ? a : null,
        'percentage': voted ? pa : null,
        'leading': a > b,
      },
      {
        'id': 2,
        'label': 'Droits',
        'votes_count': voted ? b : null,
        'percentage': voted ? 100 - pa : null,
        'leading': b > a,
      },
    ],
  };
}

TestEnv member(FakeApi api, {bool online = true}) =>
    TestEnv(api: api, token: 'tok', cachedUser: userJson(), online: online);

Future<void> boot(WidgetTester tester, TestEnv env, String route) async {
  usePhone(tester);
  addTearDown(tester.view.reset);
  await tester.pumpWidget(await env.app());
  await settle(tester);
  goTo(tester, route);
  await settle(tester, 10);
}

FakeApi apiWith(Map<String, FakeHandler> extra) => FakeApi({
  'GET me': (_) => jsonBody(200, {'data': userJson()}),
  ...extra,
});

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  testWidgets(
    'liste en curseur : « Charger plus » envoie meta.next_cursor tel quel',
    (tester) async {
      final api = apiWith({
        'GET feed': (o) {
          final cursor = o.queryParameters['cursor'];
          return cursor == null
              ? jsonBody(200, {
                  'data': [post('P1', 'Premier récit')],
                  'meta': {'per_page': 1, 'next_cursor': 'abc=='},
                })
              : jsonBody(200, {
                  'data': [post('P2', 'Deuxième récit')],
                  'meta': {'per_page': 1, 'next_cursor': null},
                });
        },
      });
      await boot(tester, member(api), '/feed');
      expect(find.text('Premier récit'), findsOneWidget);
      await tapVisible(tester, find.text(S.t('mobile_feed_load_more')));
      await settle(tester, 6);
      expect(find.text('Deuxième récit'), findsOneWidget);
      expect(find.text('Premier récit'), findsOneWidget);
      expect(api.calls('GET feed').last.queryParameters['cursor'], 'abc==');
      expect(
        find.text(S.t('mobile_feed_load_more')),
        findsNothing,
        reason: 'plus de curseur : fin de liste',
      );
      await disposeApp(tester);
    },
  );

  testWidgets(
    'j’aime : optimiste, puis retour en arrière si le serveur refuse',
    (tester) async {
      final gate = Completer<void>();
      final api = apiWith({
        'GET feed': (_) => jsonBody(200, {
          'data': [post('P1', 'Récit', likes: 2)],
          'meta': {},
        }),
        'POST posts/P1/like': (_) async {
          await gate.future;
          return jsonBody(200, {
            'data': {'liked': true, 'likes_count': 3, 'changed': true},
          });
        },
      });
      await boot(tester, member(api), '/feed');
      expect(find.text(S.t('feed_post_likes', {'count': 2})), findsOneWidget);
      await tester.tap(find.text(S.t('feed_post_likes', {'count': 2})));
      await tester.pump();
      expect(
        find.text(S.t('feed_post_likes', {'count': 3})),
        findsOneWidget,
        reason: 'affiché avant la réponse',
      );
      gate.complete();
      await settle(tester, 4);
      expect(find.text(S.t('feed_post_likes', {'count': 3})), findsOneWidget);
      await disposeApp(tester);
    },
  );

  testWidgets('j’aime refusé : le compteur revient', (tester) async {
    final api = apiWith({
      'GET feed': (_) => jsonBody(200, {
        'data': [post('P1', 'Récit', likes: 2)],
        'meta': {},
      }),
      'POST posts/P1/like': (_) =>
          jsonBody(403, {'message': 'Refusé', 'code': 'forbidden'}),
    });
    await boot(tester, member(api), '/feed');
    await tester.tap(find.text(S.t('feed_post_likes', {'count': 2})));
    await settle(tester, 4);
    expect(find.text(S.t('feed_post_likes', {'count': 2})), findsOneWidget);
    await disposeApp(tester);
  });

  testWidgets('sondage : vote optimiste, résultats puis retrait du vote', (
    tester,
  ) async {
    final gate = Completer<void>();
    final api = apiWith({
      'GET feed': (_) => jsonBody(200, {
        'data': [post('P1', 'Sondage', poll: poll())],
        'meta': {},
      }),
      'POST posts/P1/poll/vote': (_) async {
        await gate.future;
        return jsonBody(200, {
          'data': poll(voted: true, mine: [1], a: 7, b: 6),
        });
      },
      'DELETE posts/P1/poll/vote': (_) => jsonBody(200, {'data': poll()}),
    });
    await boot(tester, member(api), '/feed');
    expect(
      find.byKey(const Key('poll-pct-1')),
      findsNothing,
      reason: 'pas de résultats avant le vote',
    );
    await tapVisible(tester, find.text('Santé'));
    await tester.pump();
    expect(
      find.text(S.t('feed_poll_remove_vote')),
      findsOneWidget,
      reason: 'vote optimiste, avant la réponse',
    );
    expect(
      find.byKey(const Key('poll-pct-1')),
      findsNothing,
      reason: 'totaux inconnus tant que le serveur n’a pas répondu',
    );
    gate.complete();
    await settle(tester, 4);
    expect(api.calls('POST posts/P1/poll/vote').single.data, {
      'choices': [1],
    });
    expect(
      find.text(S.t('feed_poll_percent', {'value': 54})),
      findsOneWidget,
      reason: 'le serveur fait foi : 7 sur 13',
    );

    await tapVisible(tester, find.text(S.t('feed_poll_remove_vote')));
    await settle(tester, 4);
    expect(api.calls('DELETE posts/P1/poll/vote'), hasLength(1));
    expect(find.byKey(const Key('poll-pct-1')), findsNothing);
    expect(find.text(S.t('feed_poll_remove_vote')), findsNothing);
    await disposeApp(tester);
  });

  testWidgets('vote refusé (sondage clos) : retour à l’état précédent', (
    tester,
  ) async {
    final api = apiWith({
      'GET feed': (_) => jsonBody(200, {
        'data': [post('P1', 'Sondage', poll: poll())],
        'meta': {},
      }),
      'POST posts/P1/poll/vote': (_) =>
          jsonBody(422, {'message': 'Clos', 'code': 'poll_closed'}),
    });
    await boot(tester, member(api), '/feed');
    await tapVisible(tester, find.text('Droits'));
    await settle(tester, 4);
    expect(find.byKey(const Key('poll-pct-2')), findsNothing);
    expect(find.text(S.t('feed_poll_already_closed')), findsOneWidget);
    await disposeApp(tester);
  });

  testWidgets('publication hors ligne : file locale puis envoi sans doublon', (
    tester,
  ) async {
    var online = false;
    final api = apiWith({
      'GET feed': (_) => jsonBody(200, {'data': <Object>[], 'meta': {}}),
      'POST posts': (o) {
        if (!online) throw offlineError(o);
        return jsonBody(201, {'data': post('P9', 'Hors ligne')});
      },
    });
    final env = member(api, online: false);
    await boot(tester, env, '/feed/new');
    await tester.enterText(
      find.byType(TextField).first,
      'Publié depuis le train',
    );
    await tester.pump();
    await tapVisible(tester, find.text(S.t('feed_create_submit')));
    await settle(tester, 8);
    expect(find.text(S.t('microcopy_offline_queued')), findsOneWidget);
    final key = env.db.outbox.keys.single;
    expect(env.db.outbox.get(key)!.contains('"owner":12'), isTrue);

    online = true;
    unawaited(containerOf(tester).read(outboxProvider).flush());
    await settle(tester, 6);
    final calls = api.calls('POST posts');
    expect(calls, hasLength(2));
    expect(calls.map((c) => c.headers['Idempotency-Key']).toSet(), {key});
    expect((calls.first.data as Map)['body'], 'Publié depuis le train');
    expect(env.db.outbox.keys, isEmpty);
    await disposeApp(tester);
  });

  testWidgets('créer un sondage : validation locale puis POST kind=poll', (
    tester,
  ) async {
    final api = apiWith({
      'GET feed': (_) => jsonBody(200, {'data': <Object>[], 'meta': {}}),
      'POST posts': (_) =>
          jsonBody(201, {'data': post('P8', 'Sondage', poll: poll())}),
    });
    await boot(tester, member(api), '/feed/new');
    await tester.tap(find.text(S.t('feed_compose_poll')));
    await settle(tester, 3);
    await tapVisible(tester, find.text(S.t('feed_poll_submit')));
    await settle(tester, 3);
    expect(
      find.text(S.t('feed_poll_question_error', {'min': 5, 'max': 200})),
      findsOneWidget,
    );
    expect(api.calls('POST posts'), isEmpty);
    await tester.enterText(
      find.byType(TextField).at(0),
      'Quel thème pour la causerie ?',
    );
    await tester.enterText(find.byType(TextField).at(1), 'Santé');
    await tester.enterText(find.byType(TextField).at(2), 'Droits');
    await tester.pump();
    await tapVisible(tester, find.text(S.t('feed_poll_submit')));
    await settle(tester, 6);
    final b = api.calls('POST posts').single.data as Map;
    expect(b['kind'], 'poll');
    expect((b['poll'] as Map)['options'], ['Santé', 'Droits']);
    expect((b['poll'] as Map)['question'], 'Quel thème pour la causerie ?');
    await disposeApp(tester);
  });

  testWidgets('commentaires : liste, commentaire publié', (tester) async {
    final api = apiWith({
      'GET feed': (_) => jsonBody(200, {
        'data': [post('P1', 'Récit')],
        'meta': {},
      }),
      'GET posts/P1/comments': (_) => jsonBody(200, {
        'data': [
          {
            'id': 1,
            'parent_id': null,
            'body': 'Bravo à toi',
            'author': {'pseudo': 'Awa'},
            'is_mine': false,
            'can': {'delete': false, 'report': true},
          },
        ],
        'meta': {'comments_count': 1},
      }),
      'POST posts/P1/comments': (_) => jsonBody(201, {
        'data': {
          'id': 2,
          'parent_id': null,
          'body': 'Merci à vous',
          'author': {'pseudo': 'Awa_S'},
          'is_mine': true,
          'can': {'delete': true, 'report': false},
        },
        'meta': {'comments_count': 2},
      }),
    });
    await boot(tester, member(api), '/feed/P1');
    expect(find.text('Bravo à toi'), findsOneWidget);
    await tester.enterText(find.byType(TextField).last, 'Merci à vous');
    await tester.tap(find.byTooltip(S.t('feed_comments_send')));
    await settle(tester, 6);
    expect(find.text('Merci à vous'), findsOneWidget);
    expect(
      (api.calls('POST posts/P1/comments').single.data as Map)['body'],
      'Merci à vous',
    );
    await disposeApp(tester);
  });
}
