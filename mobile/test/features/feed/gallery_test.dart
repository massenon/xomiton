import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/app/theme/xm_theme.dart';
import 'package:xomiton/features/feed/data/feed_models.dart';
import 'package:xomiton/features/feed/presentation/media_widgets.dart';

import '../../support/fakes.dart';
import '../../support/harness.dart';
import 'feed_support.dart';

PostMedia _m(int pos, {String? alt}) => PostMedia.fromJson(imageJson(pos, alt: alt));

Widget _host(Widget child) => MaterialApp(
  theme: buildXmTheme(Brightness.light),
  localizationsDelegates: [
    loadedDelegate(),
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  supportedLocales: const [Locale('fr')],
  home: Scaffold(body: SingleChildScrollView(child: child)),
);

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  testWidgets('1 image : plein cadre, libellé = texte alternatif, bouton « Ouvrir en plein écran »', (tester) async {
    final handle = tester.ensureSemantics();
    await tester.pumpWidget(_host(PostGallery(images: [_m(0, alt: 'Atelier en plein air')])));
    expect(find.byKey(const Key('gallery-tile-0')), findsOneWidget);
    expect(find.byKey(const Key('gallery-tile-1')), findsNothing);
    final node = tester.getSemantics(find.byKey(const Key('gallery-tile-0')));
    expect(node.label, 'Atelier en plein air');
    expect(node.flagsCollection.isImage, isTrue);
    expect(node.flagsCollection.isButton, isTrue);
    final size = tester.getSize(find.byKey(const Key('gallery-tile-0')));
    expect(size.width, greaterThan(300), reason: 'plein cadre');
    handle.dispose();
  });

  testWidgets('2 à 4 images : grille ; sans alt, libellé de position et d’absence de description', (tester) async {
    final handle = tester.ensureSemantics();
    for (final n in [2, 3, 4]) {
      await tester.pumpWidget(_host(PostGallery(images: [for (var i = 0; i < n; i++) _m(i, alt: i == 1 ? null : 'Image décrite $i')])));
      for (var i = 0; i < n; i++) {
        expect(find.byKey(Key('gallery-tile-$i')), findsOneWidget, reason: '$n images');
      }
    }
    final node = tester.getSemantics(find.byKey(const Key('gallery-tile-1')));
    expect(node.label, S.t('mobile_gallery_image_alt_missing', {'n': 2, 'total': 4}));
    // Grille : la 1re et la 2e vignette sont sur la même ligne.
    expect(tester.getTopLeft(find.byKey(const Key('gallery-tile-0'))).dy, tester.getTopLeft(find.byKey(const Key('gallery-tile-1'))).dy);
    expect(tester.getTopLeft(find.byKey(const Key('gallery-tile-2'))).dy, greaterThan(tester.getTopLeft(find.byKey(const Key('gallery-tile-0'))).dy));
    handle.dispose();
  });

  testWidgets('plein écran : compteur « 2 / 4 », boutons, alt, fermeture', (tester) async {
    final handle = tester.ensureSemantics();
    final images = [for (var i = 0; i < 4; i++) _m(i, alt: 'Alt $i')];
    await tester.pumpWidget(_host(PostGallery(images: images)));
    await tester.tap(find.byKey(const Key('gallery-tile-1')));
    await tester.pumpAndSettle();
    expect(find.byType(ImageViewerPage), findsOneWidget);
    expect(find.text('2 / 4'), findsOneWidget);
    expect(find.byKey(const Key('viewer-alt')), findsOneWidget);
    expect(tester.widget<Text>(find.byKey(const Key('viewer-alt'))).data, 'Alt 1');
    // Compteur annoncé (région live) avec une phrase complète.
    expect(tester.getSemantics(find.byKey(const Key('viewer-counter'))).label, S.t('mobile_gallery_viewer_title', {'n': 2, 'total': 4}));

    await tester.tap(find.byTooltip(S.t('mobile_gallery_next')));
    await tester.pumpAndSettle();
    expect(find.text('3 / 4'), findsOneWidget);
    expect(tester.widget<Text>(find.byKey(const Key('viewer-alt'))).data, 'Alt 2');
    await tester.tap(find.byTooltip(S.t('mobile_gallery_prev')));
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip(S.t('mobile_gallery_prev')));
    await tester.pumpAndSettle();
    expect(find.text('1 / 4'), findsOneWidget);
    expect(tester.widget<IconButton>(find.widgetWithIcon(IconButton, Icons.chevron_left)).onPressed, isNull, reason: 'début de liste');

    // Glissement.
    await tester.fling(find.byType(PageView), const Offset(-400, 0), 1000);
    await tester.pumpAndSettle();
    expect(find.text('2 / 4'), findsOneWidget);

    await tester.tap(find.byTooltip(S.t('common_close')));
    await tester.pumpAndSettle();
    expect(find.byType(ImageViewerPage), findsNothing);
    handle.dispose();
  });

  testWidgets('plein écran d’une seule image : pas de boutons Précédente / Suivante', (tester) async {
    await tester.pumpWidget(_host(PostGallery(images: [_m(0, alt: 'Seule')])));
    await tester.tap(find.byKey(const Key('gallery-tile-0')));
    await tester.pumpAndSettle();
    expect(find.text('1 / 1'), findsOneWidget);
    expect(find.byTooltip(S.t('mobile_gallery_next')), findsNothing);
    expect(find.byTooltip(S.t('common_close')), findsOneWidget);
  });

  testWidgets('fil : la galerie apparaît dans la carte, dans l’ordre des positions', (tester) async {
    final api = apiWith({
      'GET feed': (_) => jsonBody(200, {
        'data': [postJson('P1', media: [imageJson(1, alt: 'Deux'), imageJson(0, alt: 'Un')])],
        'meta': {},
      }),
    });
    await boot(tester, memberEnv(api), '/feed');
    expect(find.byKey(const Key('gallery-tile-0')), findsOneWidget);
    expect(find.byKey(const Key('gallery-tile-1')), findsOneWidget);
    final h = tester.ensureSemantics();
    expect(tester.getSemantics(find.byKey(const Key('gallery-tile-0'))).label, 'Un');
    h.dispose();
    await disposeApp(tester);
  });
}
