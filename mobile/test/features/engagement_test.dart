import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/features/engagement/presentation/contribute_pages.dart';

import '../support/harness.dart';

final S = loadStrings();

/// P18-10 à P18-14 dans l'application : annonce globale (bannière de l'accueil,
/// fermeture envoyée au serveur), proposer une ressource, boîte à idées, liste
/// des forums (archivé signalé), type d'un événement, ressource à la une.
Map<String, dynamic> dashboard() => {
  'user': {'id': 12, 'pseudo': 'Awa_S'},
  'tiles': const [],
  'counters': {'notifications': 0, 'messages': 0},
  'profile': {'completion': 43, 'complete': false},
  'featured': {'total': 0, 'items': const []},
  'next_event': null,
  'peer': null,
};

FakeApi apiWith(Map<String, FakeHandler> extra) => FakeApi({
  'GET me': (_) => jsonBody(200, {'data': userJson()}),
  'GET dashboard': (_) => jsonBody(200, {'data': dashboard()}),
  ...extra,
});

Future<void> pumpApp(WidgetTester tester, FakeApi api, {List<Override> extra = const []}) async {
  usePhone(tester);
  addTearDown(tester.view.reset);
  final env = TestEnv(api: api, token: 'tok', cachedUser: userJson(), extra: extra);
  await tester.pumpWidget(await env.app());
  await tester.pumpAndSettle();
}

/// Fait défiler la liste de l'écran jusqu'au widget (les listes construisent à la demande).
Future<void> reveal(WidgetTester tester, Finder f) async {
  await tester.dragUntilVisible(f, find.byType(ListView).last, const Offset(0, -200));
  await tester.pumpAndSettle();
}

Future<void> open(WidgetTester tester, String route) async {
  GoRouter.of(tester.element(find.byType(Scaffold).first)).push(route);
  await tester.pumpAndSettle();
}

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  testWidgets('annonce globale : bannière sur l’accueil, lien interne, fermeture mémorisée', (tester) async {
    final api = apiWith({
      'GET announcements/active': (_) => jsonBody(200, {
        'data': {'id': 5, 'title': 'Atelier samedi', 'message': 'Rejoins-nous.', 'level': 'important', 'link': '/evenements'},
      }),
      'POST announcements/5/dismiss': (_) => ResponseBody.fromString('', 204),
    });
    await pumpApp(tester, api);

    expect(find.byKey(const Key('announcement-5')), findsOneWidget);
    expect(find.text('Atelier samedi'), findsOneWidget);
    expect(find.text(S.t('community_announcement_open')), findsOneWidget);

    await tester.tap(find.byKey(const Key('announcement-close')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('announcement-5')), findsNothing);
    expect(api.calls('POST announcements/5/dismiss'), hasLength(1));
  });

  testWidgets('sans annonce (ou erreur), l’accueil s’affiche sans bannière', (tester) async {
    await pumpApp(tester, apiWith({'GET announcements/active': (_) => jsonBody(200, {'data': null})}));
    expect(find.byKey(const Key('announcement-close')), findsNothing);
    expect(find.text(S.t('peer_home_greeting', {'name': 'Awa_S'})), findsOneWidget);
  });

  testWidgets('proposer une ressource : envoi multipart, erreurs du serveur sous les champs', (tester) async {
    var attempts = 0;
    final api = apiWith({
      'GET resource-proposals/options': (_) => jsonBody(200, {
        'data': {
          'kinds': [
            {'value': 'lien', 'label': 'Lien externe'},
            {'value': 'pdf', 'label': 'Document PDF'},
          ],
          'categories': [
            {'value': 3, 'label': 'Santé'},
          ],
          'limits': {'title_max': 150, 'summary_min': 20, 'summary_max': 500, 'file_max_kb': 10240},
        },
      }),
      'POST resource-proposals': (_) {
        attempts++;
        return attempts == 1
            ? jsonBody(422, {
                'message': 'Données invalides.',
                'errors': {
                  'summary': ['Le résumé est trop court.'],
                },
              })
            : jsonBody(201, {
                'data': {'id': 1, 'status': 'proposee'},
              });
      },
    });
    await pumpApp(tester, api);
    await open(tester, '/proposer-une-ressource');

    await tester.enterText(find.byKey(const Key('proposal-title')), 'Guide des démarches');
    await tester.enterText(find.byKey(const Key('proposal-summary')), 'Court');
    await tester.enterText(find.byKey(const Key('proposal-link')), 'https://exemple.org');
    await reveal(tester, find.byKey(const Key('proposal-submit')));
    await tester.tap(find.byKey(const Key('proposal-submit')));
    await tester.pumpAndSettle();
    expect(find.text('Le résumé est trop court.'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('proposal-summary')), 'Un guide utile pour toutes les démarches.');
    await reveal(tester, find.byKey(const Key('proposal-submit')));
    await tester.tap(find.byKey(const Key('proposal-submit')));
    await tester.pumpAndSettle();

    final sent = api.calls('POST resource-proposals').last.data as FormData;
    final fields = {for (final f in sent.fields) f.key: f.value};
    expect(fields['title'], 'Guide des démarches');
    expect(fields['kind'], 'lien');
    expect(fields['external_url'], 'https://exemple.org');
    expect(sent.files, isEmpty);
    expect(find.byKey(const Key('contribute-sent')), findsOneWidget);
  });

  testWidgets('proposer un PDF : le fichier choisi part avec le formulaire', (tester) async {
    final api = apiWith({
      'GET resource-proposals/options': (_) => jsonBody(200, {
        'data': {
          'kinds': [
            {'value': 'pdf', 'label': 'Document PDF'},
          ],
          'categories': const [],
          'limits': const {},
        },
      }),
      'POST resource-proposals': (_) => jsonBody(201, {
        'data': {'id': 2, 'status': 'proposee'},
      }),
    });
    final tmp = await tester.runAsync(() async {
      final dir = await Directory.systemTemp.createTemp('xm-pdf');
      final file = File('${dir.path}/guide.pdf')..writeAsStringSync('%PDF-1.4\n%%EOF\n');
      return file.path;
    });
    await pumpApp(
      tester,
      api,
      extra: [pdfPickerProvider.overrideWithValue(() async => (path: tmp!, name: 'guide.pdf'))],
    );
    await open(tester, '/proposer-une-ressource');

    expect(find.byKey(const Key('proposal-link')), findsNothing);
    await tester.enterText(find.byKey(const Key('proposal-title')), 'Guide PDF');
    await tester.enterText(find.byKey(const Key('proposal-summary')), 'Un PDF complet sur les démarches.');
    await reveal(tester, find.byKey(const Key('proposal-file')));
    await tester.tap(find.byKey(const Key('proposal-file')));
    await tester.pumpAndSettle();
    expect(find.text('guide.pdf'), findsOneWidget);
    await reveal(tester, find.byKey(const Key('proposal-submit')));
    await tester.tap(find.byKey(const Key('proposal-submit')));
    await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 50)));
    await tester.pumpAndSettle();

    final sent = api.calls('POST resource-proposals').single.data as FormData;
    expect(sent.files.single.key, 'file');
    expect(sent.files.single.value.filename, 'guide.pdf');
  });

  testWidgets('boîte à idées : catégorie, message et anonymat envoyés', (tester) async {
    final api = apiWith({
      'GET suggestions/options': (_) => jsonBody(200, {
        'data': {
          'categories': [
            {'value': 'fonctionnalite', 'label': 'Fonctionnalité'},
            {'value': 'securite', 'label': 'Sécurité'},
          ],
          'limits': {'message_min': 10, 'message_max': 1000},
        },
      }),
      'POST suggestions': (_) => jsonBody(201, {
        'data': {'id': 3, 'status': 'nouvelle'},
      }),
    });
    await pumpApp(tester, api);
    await open(tester, '/boite-a-idees');

    await tester.enterText(find.byKey(const Key('suggestion-message')), 'Un bouton pour quitter vite.');
    await tester.tap(find.byKey(const Key('suggestion-anonymous')));
    await tester.pumpAndSettle();
    await reveal(tester, find.byKey(const Key('suggestion-submit')));
    await tester.tap(find.byKey(const Key('suggestion-submit')));
    await tester.pumpAndSettle();

    expect(api.calls('POST suggestions').single.data, {
      'category': 'fonctionnalite',
      'message': 'Un bouton pour quitter vite.',
      'is_anonymous': true,
    });
    expect(find.byKey(const Key('contribute-sent')), findsOneWidget);
  });

  testWidgets('forums : ordre du serveur, forum archivé signalé', (tester) async {
    final api = apiWith({
      'GET forums': (_) => jsonBody(200, {
        'data': [
          {'key': 'f2', 'name': 'Droits & démarches', 'description': 'Questions juridiques.', 'topics_count': 3, 'is_archived': false},
          {'key': 'f1', 'name': 'Parler librement', 'description': '', 'topics_count': 0, 'is_archived': true},
        ],
      }),
    });
    await pumpApp(tester, api);
    await open(tester, '/forums');

    expect(tester.getTopLeft(find.byKey(const Key('forum-f2'))).dy, lessThan(tester.getTopLeft(find.byKey(const Key('forum-f1'))).dy));
    expect(find.textContaining(S.t('community_forums_archived')), findsOneWidget);
    expect(find.byIcon(Icons.archive_outlined), findsOneWidget);
  });

  testWidgets('événement : type affiché et attente d’approbation', (tester) async {
    final api = apiWith({
      'GET events/01EVT': (_) => jsonBody(200, {
        'data': {
          'id': '01EVT',
          'title': 'Cercle du samedi',
          'type': 'groupe_parole',
          'type_label': 'Groupe de parole',
          'is_pending': true,
          'is_past': false,
          'going_count': 0,
        },
      }),
    });
    await pumpApp(tester, api);
    await open(tester, '/evenements/01EVT');

    expect(find.byKey(const Key('event-type')), findsOneWidget);
    expect(find.text('Groupe de parole'), findsOneWidget);
    expect(find.text(S.t('community_events_pending_badge')), findsOneWidget);
  });
}
