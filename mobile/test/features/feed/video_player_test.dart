import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

import '../../support/fakes.dart';
import '../../support/feed_fakes.dart';
import 'package:xomiton/features/feed/presentation/media_widgets.dart';

import '../../support/harness.dart';
import 'feed_support.dart';

const _url1 = 'https://x.test/media/fil/V1/video?viewer=12&expires=1&signature=a';

FakeApi _feed(List<Map<String, dynamic>> posts, {Map<String, FakeHandler> extra = const {}}) => apiWith({
  'GET feed': (_) => jsonBody(200, {'data': posts, 'meta': {}}),
  ...extra,
});

Map<String, dynamic> _videoPost(String id, {String url = _url1, String alt = 'Des femmes assises en cercle.'}) =>
    postJson(id, body: 'Notre atelier', media: [videoJson(url: url, alt: alt)], reviewState: 'approved', reviewLabel: 'Vidéo publiée');

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  testWidgets('aucune lecture automatique : ni moteur ni téléchargement avant l’appui ; description visible', (tester) async {
    final engines = EngineLog();
    await boot(tester, memberEnv(_feed([_videoPost('V1')]), engines: engines), '/feed');
    expect(engines.engines, isEmpty);
    expect(find.byKey(const Key('video-play')), findsOneWidget);
    expect(find.byKey(const Key('video-description')), findsOneWidget);
    expect(tester.widget<Text>(find.byKey(const Key('video-description'))).data, 'Des femmes assises en cercle.');
    final h = tester.ensureSemantics();
    expect(find.bySemanticsLabel(RegExp('^${RegExp.escape(S.t('mobile_video_region', {'description': 'Des femmes assises en cercle.'}))}')), findsOneWidget);
    expect(find.byTooltip(S.t('mobile_video_play')), findsOneWidget);
    h.dispose();
    await disposeApp(tester);
  });

  testWidgets('lecture : lien signé tel quel, lecture / pause, durée, statut annoncé', (tester) async {
    final engines = EngineLog();
    await boot(tester, memberEnv(_feed([_videoPost('V1')]), engines: engines), '/feed');
    await tester.tap(find.byKey(const Key('video-play')));
    await settle(tester, 4);
    final e = engines.engines.single;
    expect(e.url.toString(), _url1);
    expect(e.initialized, isTrue);
    expect(e.plays, 1);
    expect(find.byKey(const Key('fake-video-view')), findsOneWidget);
    expect(tester.widget<Text>(find.byKey(const Key('video-status'))).data, S.t('mobile_video_playing'));
    expect(tester.widget<Text>(find.byKey(const Key('video-time'))).data, '0:00 / 1:30');

    // Pause (bouton nommé), puis reprise.
    expect(find.byTooltip(S.t('mobile_video_pause')), findsOneWidget);
    await tester.tap(find.byKey(const Key('video-toggle')));
    await settle(tester, 3);
    expect(e.playing, isFalse);
    expect(tester.widget<Text>(find.byKey(const Key('video-status'))).data, S.t('mobile_video_paused'));
    expect(find.byTooltip(S.t('mobile_video_play')), findsOneWidget);
    await tester.tap(find.byKey(const Key('video-toggle')));
    await settle(tester, 3);
    expect(e.playing, isTrue);

    // Position : curseur nommé, valeur lue en toutes lettres.
    final slider = tester.widget<Slider>(find.byKey(const Key('video-seek')));
    slider.onChanged!(30000);
    await settle(tester, 3);
    expect(e.seeks, [const Duration(seconds: 30)]);
    expect(tester.widget<Text>(find.byKey(const Key('video-time'))).data, '0:30 / 1:30');
    final h = tester.ensureSemantics();
    final node = tester.getSemantics(find.byKey(const Key('video-seek')));
    expect(node.label, S.t('mobile_video_seek'));
    expect(node.value, S.t('mobile_video_position', {'position': '0 min 30 s', 'duration': '1 min 30 s'}));
    h.dispose();
    await disposeApp(tester);
  });

  testWidgets('plein écran : même moteur, description, Fermer ramène à la publication', (tester) async {
    final engines = EngineLog();
    await boot(tester, memberEnv(_feed([_videoPost('V1')]), engines: engines), '/feed');
    await tester.tap(find.byKey(const Key('video-play')));
    await settle(tester, 4);
    await tester.tap(find.byKey(const Key('video-fullscreen')));
    await tester.pumpAndSettle();
    expect(find.byType(VideoFullscreenPage), findsOneWidget);
    expect(find.text('Des femmes assises en cercle.'), findsWidgets);
    expect(engines.engines, hasLength(1), reason: 'pas de second chargement');
    await tester.tap(find.byKey(const Key('video-fullscreen-close')));
    await tester.pumpAndSettle();
    expect(find.byType(VideoFullscreenPage), findsNothing);
    expect(engines.engines.single.disposed, isFalse);
    await disposeApp(tester);
  });

  testWidgets('le contrôleur est libéré quand l’écran est quitté, et la lecture se met en pause en arrière-plan', (tester) async {
    final engines = EngineLog();
    await boot(tester, memberEnv(_feed([_videoPost('V1')]), engines: engines), '/feed');
    await tester.tap(find.byKey(const Key('video-play')));
    await settle(tester, 4);
    final e = engines.engines.single;
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.paused);
    await settle(tester, 2);
    expect(e.playing, isFalse, reason: 'pause automatique quand l’application passe en arrière-plan');
    expect(e.disposed, isFalse);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    await settle(tester, 6);
    await disposeApp(tester); // l'écran est quitté : le contrôleur est libéré
    await tester.pump(const Duration(seconds: 1));
    expect(e.disposed, isTrue);
  });

  testWidgets('une seule vidéo à la fois : lancer la seconde met la première en pause', (tester) async {
    final engines = EngineLog();
    final api = _feed([
      _videoPost('V1'),
      _videoPost('V2', url: 'https://x.test/media/fil/V2/video?viewer=12&expires=1&signature=b'),
    ]);
    await boot(tester, memberEnv(api, engines: engines), '/feed');
    await tester.tap(find.byKey(const Key('video-play')).first);
    await settle(tester, 4);
    await tapVisible(tester, find.byKey(const Key('video-play')));
    await settle(tester, 4);
    expect(engines.engines, hasLength(2));
    expect(engines.engines[0].playing, isFalse);
    expect(engines.engines[1].playing, isTrue);
    await disposeApp(tester);
  });

  testWidgets('lien expiré ou lecture impossible : message, puis « Réessayer » redemande la publication (lien neuf)', (tester) async {
    final engines = EngineLog()..failFirst = 1;
    const fresh = 'https://x.test/media/fil/V1/video?viewer=12&expires=2&signature=NEW';
    final api = _feed([_videoPost('V1')], extra: {
      'GET posts/V1': (_) => jsonBody(200, {'data': _videoPost('V1', url: fresh)}),
    });
    await boot(tester, memberEnv(api, engines: engines), '/feed');
    await tester.tap(find.byKey(const Key('video-play')));
    await settle(tester, 4);
    expect(tester.widget<Text>(find.byKey(const Key('video-status'))).data, S.t('mobile_video_error'));
    expect(engines.engines.single.disposed, isTrue, reason: 'moteur en échec libéré');
    expect(find.byTooltip(S.t('common_retry')), findsOneWidget);
    await tester.tap(find.byKey(const Key('video-play')));
    await settle(tester, 6);
    expect(api.calls('GET posts/V1'), hasLength(1));
    expect(engines.engines.last.url.toString(), fresh);
    expect(engines.engines.last.playing, isTrue);
    await disposeApp(tester);
  });
}
