import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

import '../../support/fakes.dart';
import '../../support/feed_fakes.dart';
import '../../support/harness.dart';
import 'feed_support.dart';

Map<String, dynamic> _pending([String id = 'V1']) => postJson(
  id,
  body: 'Notre atelier en vidéo.',
  mine: true,
  canComment: false,
  reviewState: 'pending',
  reviewLabel: 'En attente de relecture',
  media: [videoJson()],
);

Map<String, dynamic> _rejected([String id = 'V2']) => postJson(
  id,
  body: 'Ma vidéo refusée',
  mine: true,
  canComment: false,
  reviewState: 'rejected',
  reviewLabel: 'Vidéo refusée',
  reviewReason: 'Vie privée (lieu, visage ou information personnelle visible) — On voit le portail de ta maison.',
);

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  testWidgets('détail d’une vidéo en attente : badge, aide, pas de j’aime ni de commentaires, lecteur sans lecture auto', (tester) async {
    final api = apiWith({'GET posts/V1': (_) => jsonBody(200, {'data': _pending()}), 'GET posts/V1/comments': (_) => jsonBody(200, {'data': <Object>[], 'meta': {}})});
    final engines = EngineLog();
    await boot(tester, memberEnv(api, engines: engines), '/feed/V1');
    expect(find.byKey(const Key('review-label')), findsOneWidget);
    expect(tester.widget<Text>(find.byKey(const Key('review-label'))).data, 'En attente de relecture');
    expect(find.text(S.t('mobile_review_pending_help')), findsOneWidget);
    expect(find.byKey(const Key('review-reason')), findsNothing);
    expect(find.byKey(const Key('review-withdraw')), findsOneWidget);
    expect(find.text(S.t('feed_post_likes', {'count': 2})), findsNothing, reason: 'une vidéo en relecture ne se like pas (404)');
    expect(find.byKey(const Key('video-play')), findsOneWidget);
    expect(engines.engines, isEmpty);
    await disposeApp(tester);
  });

  testWidgets('vidéo refusée : état, motif du serveur, Retirer', (tester) async {
    final api = apiWith({'GET posts/V2': (_) => jsonBody(200, {'data': _rejected()}), 'GET posts/V2/comments': (_) => jsonBody(200, {'data': <Object>[], 'meta': {}})});
    await boot(tester, memberEnv(api), '/feed/V2');
    expect(tester.widget<Text>(find.byKey(const Key('review-label'))).data, 'Vidéo refusée');
    expect(tester.widget<Text>(find.byKey(const Key('review-reason'))).data, contains('portail de ta maison'));
    expect(find.text(S.t('mobile_review_rejected_help')), findsOneWidget);
    expect(find.byKey(const Key('video-play')), findsNothing, reason: 'fichier purgé : media vide');
    await disposeApp(tester);
  });

  testWidgets('Retirer : confirmation puis DELETE posts/{ulid}, annonce, retour', (tester) async {
    final api = apiWith({
      'GET posts/V1': (_) => jsonBody(200, {'data': _pending()}),
      'GET posts/V1/comments': (_) => jsonBody(200, {'data': <Object>[], 'meta': {}}),
      'DELETE posts/V1': (_) => ResponseBody.fromString('', 204),
    });
    await boot(tester, memberEnv(api), '/feed/V1');
    await tester.tap(find.byKey(const Key('review-withdraw')));
    await settle(tester, 3);
    expect(find.text(S.t('mobile_review_remove_confirm_title')), findsOneWidget);
    await tester.tap(find.text(S.t('common_cancel')));
    await settle(tester, 3);
    expect(api.calls('DELETE posts/V1'), isEmpty);
    await tester.tap(find.byKey(const Key('review-withdraw')));
    await settle(tester, 3);
    await tester.tap(find.descendant(of: find.byType(AlertDialog), matching: find.text(S.t('mobile_review_remove'))));
    await settle(tester, 6);
    expect(api.calls('DELETE posts/V1'), hasLength(1));
    expect(find.text(S.t('feed_video_withdrawn')), findsOneWidget);
    await disposeApp(tester);
  });

  testWidgets('l’état de relecture n’apparaît pas chez une autre personne (vidéo publiée)', (tester) async {
    final api = apiWith({
      'GET feed': (_) => jsonBody(200, {
        'data': [postJson('V9', media: [videoJson()], reviewState: 'approved', reviewLabel: 'Vidéo publiée')],
        'meta': {},
      }),
    });
    await boot(tester, memberEnv(api), '/feed');
    expect(find.byKey(const Key('review-label')), findsNothing);
    expect(find.byKey(const Key('video-play')), findsOneWidget);
    expect(find.text(S.t('feed_post_likes', {'count': 2})), findsOneWidget);
    await disposeApp(tester);
  });

  group('Mes vidéos', () {
    testWidgets('accès depuis le Fil (bouton nommé) ; liste GET posts/review avec états', (tester) async {
      final api = apiWith({'GET posts/review': (_) => jsonBody(200, {'data': [_pending(), _rejected()]})});
      await boot(tester, memberEnv(api), '/feed', tall: false);
      expect(find.byTooltip(S.t('mobile_myvideos_title')), findsOneWidget);
      await tester.tap(find.byKey(const Key('feed-my-videos')));
      await settle(tester, 8);
      expect(find.text(S.t('mobile_myvideos_title')), findsOneWidget);
      expect(find.text(S.t('mobile_myvideos_intro')), findsOneWidget);
      expect(api.calls('GET posts/review'), hasLength(1));
      expect(find.text('En attente de relecture'), findsOneWidget);
      await tapVisible(tester, find.text('Notre atelier en vidéo.'));
      await disposeApp(tester);
    });

    testWidgets('Retirer depuis la liste : DELETE puis liste rechargée', (tester) async {
      var removed = false;
      final api = apiWith({
        'GET posts/review': (_) => jsonBody(200, {'data': removed ? [_rejected()] : [_pending(), _rejected()]}),
        'DELETE posts/V1': (_) {
          removed = true;
          return ResponseBody.fromString('', 204);
        },
      });
      await boot(tester, memberEnv(api), '/feed/videos');
      expect(find.byKey(const Key('review-V1')), findsOneWidget);
      expect(find.byKey(const Key('review-V2')), findsOneWidget);
      await tester.tap(find.descendant(of: find.byKey(const Key('review-V1')), matching: find.byKey(const Key('review-withdraw'))));
      await settle(tester, 3);
      await tester.tap(find.descendant(of: find.byType(AlertDialog), matching: find.text(S.t('mobile_review_remove'))));
      await settle(tester, 8);
      expect(api.calls('DELETE posts/V1'), hasLength(1));
      expect(api.calls('GET posts/review'), hasLength(2));
      expect(find.byKey(const Key('review-V1')), findsNothing);
      expect(find.byKey(const Key('review-V2')), findsOneWidget);
      await disposeApp(tester);
    });

    testWidgets('liste vide : message ; erreur : « Réessayer »', (tester) async {
      var fail = true;
      final api = apiWith({
        'GET posts/review': (_) => fail
            ? jsonBody(500, {'message': 'Erreur', 'code': 'server_error'})
            : jsonBody(200, {'data': <Object>[]}),
      });
      await boot(tester, memberEnv(api), '/feed/videos');
      expect(find.text(S.t('common_retry')), findsOneWidget);
      fail = false;
      await tester.tap(find.text(S.t('common_retry')));
      await settle(tester, 6);
      expect(find.text(S.t('mobile_myvideos_empty')), findsOneWidget);
      await disposeApp(tester);
    });
  });
}
