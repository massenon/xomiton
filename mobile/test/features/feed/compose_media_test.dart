import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/features/feed/data/compose_logic.dart';
import 'package:xomiton/features/feed/data/feed_api.dart';

import '../../support/fakes.dart';
import '../../support/feed_fakes.dart';
import '../../support/harness.dart';
import 'feed_support.dart';

late SpyFeedApi spy;

DioException _http(int status, Map<String, dynamic> body) => DioException(
  requestOptions: RequestOptions(path: 'posts'),
  type: DioExceptionType.badResponse,
  response: Response(requestOptions: RequestOptions(path: 'posts'), statusCode: status, data: body),
);

Future<FakeApi> _open(WidgetTester tester, FakeMediaPicker picker, {Map<String, FakeHandler> routes = const {}}) async {
  final api = apiWith(routes);
  await boot(tester, memberEnv(api, picker: picker, spy: (d, c) => spy = SpyFeedApi(d, c)), '/feed/new');
  containerOf(tester).read(feedApiProvider); // crée l'espion (les fournisseurs sont paresseux)
  return api;
}

String _live(WidgetTester tester) => tester.widget<Text>(find.descendant(of: field('compose-live'), matching: find.byType(Text))).data!;

bool _enabled(WidgetTester tester, String key) {
  final w = tester.widget(field(key));
  if (w is OutlinedButton) return w.onPressed != null;
  if (w is IconButton) return w.onPressed != null;
  if (w is TextButton) return w.onPressed != null;
  if (w is FilledButton) return w.onPressed != null;
  throw StateError('type inattendu ${w.runtimeType}');
}

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('images', () {
    testWidgets('sélection multiple : vignettes, compteur « 3 / 4 », annonce', (tester) async {
      await _open(tester, FakeMediaPicker(images: [img(1), img(2), img(3)]));
      expect(find.text('0 / 4'), findsOneWidget);
      await tapKey(tester, 'compose-add-images');
      expect(find.text('3 / 4'), findsOneWidget);
      expect(field('compose-image-2'), findsOneWidget);
      expect(_live(tester), S.t('mobile_compose_image_added', {'count': 3}));
      expect(find.text(S.t('mobile_compose_image_position', {'n': 2, 'total': 3})), findsOneWidget);
      await disposeApp(tester);
    });

    testWidgets('plafond de 4 : le surplus est refusé et annoncé, le bouton est grisé', (tester) async {
      final picker = FakeMediaPicker(images: [for (var i = 1; i <= 6; i++) img(i)]);
      await _open(tester, picker);
      await tapKey(tester, 'compose-add-images');
      expect(find.text('4 / 4'), findsOneWidget);
      expect(field('compose-image-4'), findsNothing);
      expect(_live(tester), S.t('mobile_compose_images_trimmed', {'max': 4}));
      expect(_enabled(tester, 'compose-add-images'), isFalse);
      expect(find.text(S.t('mobile_compose_images_full', {'max': 4})), findsOneWidget);
      await disposeApp(tester);
    });

    testWidgets('une même image choisie deux fois n’est pas ajoutée deux fois', (tester) async {
      final picker = FakeMediaPicker(images: [img(1), img(2)]);
      await _open(tester, picker);
      await tapKey(tester, 'compose-add-images');
      await tapKey(tester, 'compose-add-images');
      expect(find.text('2 / 4'), findsOneWidget);
      await disposeApp(tester);
    });

    testWidgets('réordonnancement Monter / Descendre : ordre changé, annoncé, limites grisées', (tester) async {
      await _open(tester, FakeMediaPicker(images: [img(1), img(2), img(3)]));
      await tapKey(tester, 'compose-add-images');
      expect(_enabled(tester, 'compose-image-up-0'), isFalse);
      expect(_enabled(tester, 'compose-image-down-2'), isFalse);
      expect(find.byTooltip(S.t('mobile_compose_image_move_up', {'n': 2})), findsOneWidget);
      expect(find.byTooltip(S.t('mobile_compose_image_move_down', {'n': 1})), findsOneWidget);
      await type(tester, 'compose-image-alt-0', 'Première');
      await tapKey(tester, 'compose-image-down-0');
      expect(_live(tester), S.t('mobile_compose_image_moved', {'pos': 2, 'total': 3}));
      // La description suit son image.
      expect(tester.widget<TextField>(field('compose-image-alt-1')).controller!.text, 'Première');
      expect(tester.widget<TextField>(field('compose-image-alt-0')).controller!.text, '');
      await tapKey(tester, 'compose-image-up-2');
      expect(_live(tester), S.t('mobile_compose_image_moved', {'pos': 2, 'total': 3}));
      await disposeApp(tester);
    });

    testWidgets('retrait : compteur et annonce, la description de l’image retirée disparaît', (tester) async {
      await _open(tester, FakeMediaPicker(images: [img(1), img(2)]));
      await tapKey(tester, 'compose-add-images');
      await type(tester, 'compose-image-alt-1', 'Seconde');
      await tapKey(tester, 'compose-image-remove-0');
      expect(find.text('1 / 4'), findsOneWidget);
      expect(_live(tester), S.t('mobile_compose_image_removed', {'count': 1, 'max': 4}));
      expect(tester.widget<TextField>(field('compose-image-alt-0')).controller!.text, 'Seconde');
      await disposeApp(tester);
    });

    testWidgets('envoi multipart : ordre, texte alternatif par image, sans boîte d’envoi', (tester) async {
      final api = await _open(tester, FakeMediaPicker(images: [img(1), img(2)]));
      await tapKey(tester, 'compose-add-images');
      await type(tester, 'compose-image-alt-1', 'Le groupe rassemblé');
      await type(tester, 'compose-body', 'Nos photos de samedi.');
      await tapKey(tester, 'compose-image-up-1');
      spy.result = CreatedPost(post: null, inReview: false);
      await tapKey(tester, 'compose-submit');
      await settle(tester, 6);
      final c = spy.sent.single;
      expect(c.body, 'Nos photos de samedi.');
      expect(c.images.map((i) => i.name), ['photo2.jpg', 'photo1.jpg']);
      expect(c.images.map((i) => i.alt), ['Le groupe rassemblé', '']);
      expect(spy.keys.single, isNotEmpty, reason: 'Idempotency-Key');
      expect(api.calls('POST posts'), isEmpty, reason: 'les fichiers ne passent pas par la boîte d’envoi');
      expect(find.text(S.t('feed_page_title')), findsOneWidget);
      expect(api.calls('GET feed'), hasLength(2), reason: 'le fil est rechargé (publication en ligne)');
      await disposeApp(tester);
    });

    testWidgets('image seule, sans texte : acceptée', (tester) async {
      await _open(tester, FakeMediaPicker(images: [img(1)]));
      await tapKey(tester, 'compose-add-images');
      await tapKey(tester, 'compose-submit');
      await settle(tester, 6);
      expect(spy.sent, hasLength(1));
      expect(spy.sent.single.body, '');
      await disposeApp(tester);
    });

    testWidgets('sondage avec images : envoyé en multipart avec kind=poll', (tester) async {
      await _open(tester, FakeMediaPicker(images: [img(1)]));
      await tester.tap(find.text(S.t('feed_compose_poll')));
      await settle(tester, 3);
      await type(tester, 'compose-question', 'Quel thème pour la causerie ?');
      await type(tester, 'compose-option-0', 'Santé');
      await type(tester, 'compose-option-1', 'Droits');
      await tapKey(tester, 'compose-add-images');
      await tapKey(tester, 'compose-submit');
      await settle(tester, 6);
      final c = spy.sent.single;
      expect(c.poll, isTrue);
      expect(c.sentOptions, ['Santé', 'Droits']);
      expect(c.images, hasLength(1));
      await disposeApp(tester);
    });

    testWidgets('422 images.N : l’erreur apparaît sur l’image concernée, le brouillon reste', (tester) async {
      await _open(tester, FakeMediaPicker(images: [img(1), img(2)]));
      await tapKey(tester, 'compose-add-images');
      await type(tester, 'compose-image-alt-1', 'Ma description');
      spy.failWith = _http(422, {
        'message': 'Image illisible.',
        'code': 'validation_failed',
        'errors': {'images.1': ['Cette image est illisible.'], 'images_alt.0': ['Description trop longue.']},
      });
      await tapKey(tester, 'compose-submit');
      await settle(tester, 6);
      final tile = find.byKey(const Key('compose-image-1'));
      expect(find.descendant(of: tile, matching: find.text('Cette image est illisible.')), findsOneWidget);
      expect(find.descendant(of: find.byKey(const Key('compose-image-0')), matching: find.text('Description trop longue.')), findsOneWidget);
      expect(find.text('2 / 4'), findsOneWidget);
      expect(tester.widget<TextField>(field('compose-image-alt-1')).controller!.text, 'Ma description');
      await disposeApp(tester);
    });

    testWidgets('contrôle local : format ou poids refusé avant tout envoi, sous la bonne image', (tester) async {
      await _open(tester, FakeMediaPicker(images: [img(1), img(2, name: 'doc.pdf'), img(3, size: 6 * 1024 * 1024)]));
      await tapKey(tester, 'compose-add-images');
      await tapKey(tester, 'compose-submit');
      expect(spy.sent, isEmpty);
      expect(find.descendant(of: find.byKey(const Key('compose-image-1')), matching: find.text(S.t('feed_create_photo_type'))), findsOneWidget);
      expect(find.descendant(of: find.byKey(const Key('compose-image-2')), matching: find.text(S.t('feed_toasts_image_too_large', {'max': 5}))), findsOneWidget);
      await disposeApp(tester);
    });

    testWidgets('pas de réseau : message clair, brouillon conservé, rien en file', (tester) async {
      final env = memberEnv(apiWith({}), picker: FakeMediaPicker(images: [img(1)]), spy: (d, c) => spy = SpyFeedApi(d, c), online: false);
      await boot(tester, env, '/feed/new');
      containerOf(tester).read(feedApiProvider);
      await tapKey(tester, 'compose-add-images');
      await type(tester, 'compose-body', 'Mon texte');
      spy.failWith = DioException(requestOptions: RequestOptions(path: 'posts'), type: DioExceptionType.connectionError);
      await tapKey(tester, 'compose-submit');
      await settle(tester, 6);
      expect(find.text(S.t('mobile_compose_media_needs_network')), findsWidgets);
      expect(env.db.outbox.keys, isEmpty, reason: 'l’outbox ne garde que le texte et les sondages sans média');
      expect(find.text('1 / 4'), findsOneWidget);
      expect(tester.widget<TextField>(field('compose-body')).controller!.text, 'Mon texte');
      await disposeApp(tester);
    });

    testWidgets('renvoi sans modification : même Idempotency-Key ; après modification : nouvelle clé', (tester) async {
      await _open(tester, FakeMediaPicker(images: [img(1)]));
      await tapKey(tester, 'compose-add-images');
      await type(tester, 'compose-body', 'Mon texte');
      spy.failWith = DioException(requestOptions: RequestOptions(path: 'posts'), type: DioExceptionType.connectionError);
      await tapKey(tester, 'compose-submit');
      await settle(tester, 6);
      await tapKey(tester, 'compose-submit');
      await settle(tester, 6);
      expect(spy.keys, hasLength(2));
      expect(spy.keys[0], spy.keys[1]);
      await type(tester, 'compose-body', 'Mon texte modifié');
      await tapKey(tester, 'compose-submit');
      await settle(tester, 6);
      expect(spy.keys[2], isNot(spy.keys[0]));
      await disposeApp(tester);
    });
  });

  group('vidéo', () {
    final video = ComposeVideo(path: '/tmp/v.mp4', name: 'atelier.mp4', size: 8 * 1024 * 1024);

    Future<void> pickVideo(WidgetTester tester, {String desc = 'Des femmes assises en cercle.'}) async {
      await tapKey(tester, 'compose-pick-video');
      await type(tester, 'compose-video-desc', desc);
    }

    testWidgets('choix : nom et poids affichés, annonce, avertissement vie privée visible avant l’envoi', (tester) async {
      await _open(tester, FakeMediaPicker(video: video));
      await tapKey(tester, 'compose-pick-video');
      expect(find.text(S.t('mobile_compose_video_selected', {'name': 'atelier.mp4', 'size': '8,0 Mo'})), findsOneWidget);
      expect(_live(tester), S.t('mobile_compose_video_picked'));
      expect(find.text(S.t('feed_video_privacy_notice')), findsOneWidget);
      expect(find.text(S.t('feed_video_review_notice')), findsOneWidget);
      await tapKey(tester, 'compose-video-remove');
      expect(field('compose-video-desc'), findsNothing);
      expect(_live(tester), S.t('mobile_compose_video_removed'));
      await disposeApp(tester);
    });

    testWidgets('exclusivité : vidéo ↔ images, expliquée ; absente d’un sondage', (tester) async {
      final picker = FakeMediaPicker(images: [img(1)], video: video);
      await _open(tester, picker);
      await tapKey(tester, 'compose-add-images');
      expect(_enabled(tester, 'compose-pick-video'), isFalse);
      expect(find.text(S.t('mobile_compose_video_excl_images')), findsOneWidget);
      await tapKey(tester, 'compose-image-remove-0');
      await tapKey(tester, 'compose-pick-video');
      expect(_enabled(tester, 'compose-add-images'), isFalse);
      expect(find.text(S.t('mobile_compose_images_excl_video')), findsOneWidget);
      // Passer en sondage retire la vidéo et le dit.
      await tester.tap(find.text(S.t('feed_compose_poll')));
      await settle(tester, 3);
      expect(field('compose-pick-video'), findsNothing);
      expect(field('compose-video-desc'), findsNothing);
      expect(_live(tester), S.t('mobile_compose_video_removed_for_poll'));
      await tester.tap(find.text(S.t('feed_create_title')).first);
      await settle(tester, 3);
      expect(field('compose-pick-video'), findsOneWidget);
      expect(field('compose-video-info'), findsNothing);
      await disposeApp(tester);
    });

    testWidgets('description obligatoire : erreur sous le champ, aucune confirmation, aucun envoi', (tester) async {
      await _open(tester, FakeMediaPicker(video: video));
      await tapKey(tester, 'compose-pick-video');
      await tapKey(tester, 'compose-submit');
      expect(find.text(S.t('feed_video_description_required')), findsWidgets);
      expect(find.byKey(const Key('privacy-notice')), findsNothing);
      expect(spy.sent, isEmpty);
      await type(tester, 'compose-video-desc', 'Cour');
      await tapKey(tester, 'compose-submit');
      expect(find.text(S.t('feed_video_description_length', {'min': 5, 'max': 500})), findsWidgets);
      await disposeApp(tester);
    });

    testWidgets('taille ou format refusés localement (30 Mo, mp4 / webm)', (tester) async {
      final big = ComposeVideo(path: '/tmp/v.mp4', name: 'gros.mp4', size: 31 * 1024 * 1024);
      await _open(tester, FakeMediaPicker(video: big));
      await pickVideo(tester);
      await tapKey(tester, 'compose-submit');
      expect(find.text(S.t('mobile_compose_video_too_big', {'max': 30})), findsWidgets);
      expect(find.byKey(const Key('privacy-notice')), findsNothing);
      expect(spy.sent, isEmpty);
      await disposeApp(tester);
    });

    testWidgets('avertissement vie privée avant l’envoi : Annuler n’envoie rien, Confirmer envoie', (tester) async {
      await _open(tester, FakeMediaPicker(video: video));
      await pickVideo(tester);
      spy.result = const CreatedPost(inReview: true);
      await tapKey(tester, 'compose-submit');
      await settle(tester, 3);
      expect(find.byKey(const Key('privacy-notice')), findsOneWidget);
      expect(find.text(S.t('feed_video_privacy_notice')), findsWidgets);
      await tester.tap(find.text(S.t('common_cancel')));
      await settle(tester, 3);
      expect(spy.sent, isEmpty);
      expect(field('compose-video-info'), findsOneWidget, reason: 'brouillon conservé');

      await tapKey(tester, 'compose-submit');
      await settle(tester, 3);
      await tester.tap(find.byKey(const Key('privacy-confirm')));
      await settle(tester, 6);
      final c = spy.sent.single;
      expect(c.video!.name, 'atelier.mp4');
      expect(c.videoDescription, 'Des femmes assises en cercle.');
      expect(c.images, isEmpty);
      await disposeApp(tester);
    });

    testWidgets('202 : « vidéo envoyée en relecture », rien n’est inséré dans le fil, le fil n’est pas rechargé', (tester) async {
      final api = await _open(tester, FakeMediaPicker(video: video));
      await pickVideo(tester);
      spy.result = const CreatedPost(inReview: true);
      await tapKey(tester, 'compose-submit');
      await settle(tester, 3);
      await tester.tap(find.byKey(const Key('privacy-confirm')));
      await settle(tester, 8);
      expect(find.text(S.t('feed_video_submitted')), findsOneWidget);
      expect(find.text(S.t('feed_page_title')), findsOneWidget);
      expect(api.calls('GET feed'), hasLength(1), reason: 'pas de rechargement : la vidéo n’est pas dans le fil');
      await disposeApp(tester);
    });

    testWidgets('progression annoncée (25 / 50 %), barre et texte', (tester) async {
      await _open(tester, FakeMediaPicker(video: video));
      await pickVideo(tester);
      spy.gate = Completer<CreatedPost>();
      spy.progress = [10, 30, 60];
      await tapKey(tester, 'compose-submit');
      await settle(tester, 3);
      await tester.tap(find.byKey(const Key('privacy-confirm')));
      await settle(tester, 4);
      expect(field('compose-progress'), findsOneWidget);
      expect(find.text(S.t('mobile_compose_upload_progress', {'percent': 60})), findsWidgets);
      expect(tester.widget<LinearProgressIndicator>(field('compose-progress')).value, 0.6);
      expect(_live(tester), S.t('mobile_compose_upload_progress', {'percent': 50}));
      expect(_enabled(tester, 'compose-submit'), isFalse, reason: 'pas de double envoi');
      spy.gate!.complete(const CreatedPost(inReview: true));
      await settle(tester, 6);
      await disposeApp(tester);
    });

    testWidgets('annulation : l’envoi s’arrête, le brouillon est conservé, message annoncé', (tester) async {
      await _open(tester, FakeMediaPicker(video: video));
      await pickVideo(tester);
      spy.gate = Completer<CreatedPost>();
      spy.progress = [20];
      await tapKey(tester, 'compose-submit');
      await settle(tester, 3);
      await tester.tap(find.byKey(const Key('privacy-confirm')));
      await settle(tester, 4);
      await tapKey(tester, 'compose-cancel-upload');
      await settle(tester, 6);
      expect(_live(tester), S.t('mobile_compose_upload_cancelled'));
      expect(field('compose-progress'), findsNothing);
      expect(field('compose-video-info'), findsOneWidget);
      expect(tester.widget<TextField>(field('compose-video-desc')).controller!.text, 'Des femmes assises en cercle.');
      expect(_enabled(tester, 'compose-submit'), isTrue);
      await disposeApp(tester);
    });

    testWidgets('quota quotidien (429) et pas de réseau : messages clairs, brouillon conservé', (tester) async {
      await _open(tester, FakeMediaPicker(video: video));
      await pickVideo(tester);
      spy.failWith = _http(429, {'message': 'Limite atteinte', 'code': 'too_many_requests', 'retry_after': 3412});
      await tapKey(tester, 'compose-submit');
      await settle(tester, 3);
      await tester.tap(find.byKey(const Key('privacy-confirm')));
      await settle(tester, 6);
      expect(find.text(S.t('errors_api_throttled', {'seconds': 3412})), findsWidgets);
      expect(field('compose-video-info'), findsOneWidget);

      spy.failWith = DioException(requestOptions: RequestOptions(path: 'posts'), type: DioExceptionType.connectionTimeout);
      await tapKey(tester, 'compose-submit');
      await settle(tester, 3);
      await tester.tap(find.byKey(const Key('privacy-confirm')));
      await settle(tester, 6);
      expect(find.text(S.t('mobile_compose_media_needs_network')), findsWidgets);
      await disposeApp(tester);
    });

    testWidgets('422 sur la vidéo et sa description : rattachées aux champs', (tester) async {
      await _open(tester, FakeMediaPicker(video: video));
      await pickVideo(tester);
      spy.failWith = _http(422, {
        'message': 'x',
        'code': 'validation_failed',
        'errors': {'video': ['Format de vidéo refusé.'], 'video_description': ['Description refusée.']},
      });
      await tapKey(tester, 'compose-submit');
      await settle(tester, 3);
      await tester.tap(find.byKey(const Key('privacy-confirm')));
      await settle(tester, 6);
      expect(find.descendant(of: find.byType(Card), matching: find.text('Format de vidéo refusé.')), findsOneWidget);
      expect(find.text('Description refusée.'), findsWidgets);
      await disposeApp(tester);
    });
  });
}
