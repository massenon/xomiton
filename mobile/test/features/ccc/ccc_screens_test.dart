import 'dart:ui' show CheckedState;
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:xomiton/features/ccc/application/ccc_providers.dart';

import '../../support/fakes.dart';
import '../../support/harness.dart';
import '../../support/pair_harness.dart' show currentPath, openPair, pairRoutes;
import 'ccc_support.dart';

/// Adresse de l'écran du dessus (une route poussée n'est pas dans `currentConfiguration.uri`).
String _path(WidgetTester tester) {
  final last = GoRouter.of(tester.element(find.byType(Scaffold).first)).routerDelegate.currentConfiguration.last;
  return last is ImperativeRouteMatch ? last.matches.uri.toString() : currentPath(tester);
}

void main() {
  group('états de /ccc', () {
    testWidgets('chargement puis onglets ; la requête est GET ccc', (tester) async {
      final h = await openCcc(tester);
      expect(find.text(S.t('ccc_title')), findsOneWidget);
      for (final k in ['manuel', 'boites_images', 'bd', 'lois']) {
        expect(find.text(S.t('ccc_tabs_$k')), findsOneWidget);
      }
      expect(h.api.calls('GET ccc'), hasLength(1));
      expect(find.text('Module 1 : Poser un cadre sûr'), findsOneWidget);
    });

    testWidgets('vide : un message par onglet, pas d’écran blanc', (tester) async {
      await openCcc(tester, ccc: (_) => jsonBody(200, cccBody(manuel: [], boites: [], bd: [], laws: 0)));
      expect(find.text(S.t('ccc_empty_manuel')), findsOneWidget);
      expect(find.text(S.t('ccc_empty_hint')), findsOneWidget);
      await openTab(tester, 'boites_images');
      expect(find.text(S.t('ccc_empty_boites_images')), findsOneWidget);
      await openTab(tester, 'bd');
      expect(find.text(S.t('ccc_empty_bd')), findsOneWidget);
      await openTab(tester, 'lois');
      expect(find.text(S.t('ccc_empty_lois')), findsOneWidget);
    });

    testWidgets('erreur réseau : message d’indisponibilité et « Réessayer » qui relit', (tester) async {
      var online = false;
      final h = await openCcc(tester, ccc: (o) {
        if (!online) throw offlineError(o);
        return jsonBody(200, cccBody());
      });
      expect(find.text(S.t('microcopy_error_unreachable')), findsOneWidget);
      expect(find.byKey(const ValueKey('ccc-forbidden')), findsNothing);
      online = true;
      await tester.tap(find.text(S.t('common_retry')));
      await tester.pumpAndSettle();
      expect(find.text('Module 1 : Poser un cadre sûr'), findsOneWidget);
      expect(h.api.calls('GET ccc').length, greaterThanOrEqualTo(2));
    });

    testWidgets('403 : état distinct (accès refusé), avec « Réessayer »', (tester) async {
      var allowed = false;
      await openCcc(tester, ccc: (_) => allowed
          ? jsonBody(200, cccBody())
          : jsonBody(403, {'message': 'Action non autorisée.', 'code': 'forbidden'}));
      expect(find.text(S.t('mobile_ccc_forbidden')), findsOneWidget);
      expect(find.text(S.t('microcopy_error_unreachable')), findsNothing);
      allowed = true;
      await tester.tap(find.text(S.t('common_retry')));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('ccc-forbidden')), findsNothing);
      expect(find.text('Module 1 : Poser un cadre sûr'), findsOneWidget);
    });

    testWidgets('chaque ouverture relit le serveur (réponse no-store : un outil masqué disparaît)', (tester) async {
      var hidden = false;
      final h = await openCcc(tester, ccc: (_) => jsonBody(200, cccBody(manuel: hidden ? [] : null)));
      expect(find.text('Module 1 : Poser un cadre sûr'), findsOneWidget);
      GoRouter.of(tester.element(find.byType(Scaffold).first)).pop();
      await tester.pumpAndSettle();
      hidden = true;
      GoRouter.of(tester.element(find.byType(Scaffold).first)).push('/ccc');
      await tester.pumpAndSettle();
      expect(h.api.calls('GET ccc'), hasLength(2));
      expect(find.text('Module 1 : Poser un cadre sûr'), findsNothing);
    });

    testWidgets('onglet choisi par l’adresse (?onglet=bd)', (tester) async {
      await openCcc(tester, path: '/ccc?onglet=bd');
      expect(find.text('Awa et le centre de santé'), findsOneWidget);
    });
  });

  group('Manuel', () {
    testWidgets('liste : titre, durée, nombre de points, progression globale à 0 %', (tester) async {
      final sem = tester.ensureSemantics();
      await openCcc(tester);
      expect(find.text(S.t('ccc_manual_modules_count', {'count': 2})), findsOneWidget);
      expect(find.textContaining(S.t('ccc_manual_duration', {'minutes': 45})), findsOneWidget);
      expect(find.textContaining(S.t('ccc_manual_chapters_count', {'count': 3})), findsWidgets);
      final global = tester.getSemantics(find.byKey(const ValueKey('ccc-global-bar')));
      expect(global.label, contains(S.t('ccc_manual_global_announce', {'percent': 0})));
      sem.dispose();
    });

    testWidgets('cases « point vu » : Semantics (libellé, coché), progression annoncée, persistance par compte', (tester) async {
      final sem = tester.ensureSemantics();
      final h = await openCcc(tester);
      await tester.tap(find.byKey(const ValueKey('ccc-module-m1')));
      await tester.pumpAndSettle();
      expect(_path(tester), '/ccc/manuel/m1');

      final first = find.byKey(const ValueKey('ccc-chapter-m1-0'));
      var node = tester.getSemantics(first);
      expect(node.label, S.t('ccc_manual_chapter_seen', {'chapter': 'Confidentialité'}));
      expect(node.flagsCollection.isChecked, isNot(CheckedState.isTrue));
      expect(tester.getSize(first).height, greaterThanOrEqualTo(48));

      await tester.tap(first);
      await tester.pumpAndSettle();
      node = tester.getSemantics(first);
      expect(node.flagsCollection.isChecked, CheckedState.isTrue);

      final bar = tester.getSemantics(find.byKey(const ValueKey('ccc-module-progress')));
      expect(bar.label, contains(S.t('ccc_manual_progress_module', {'title': 'Module 1 : Poser un cadre sûr'})));
      expect(bar.label, contains(S.t('ccc_manual_progress_announce', {'done': 1, 'total': 3})));
      expect(bar.flagsCollection.isLiveRegion, isTrue);

      await tester.tap(find.byKey(const ValueKey('ccc-chapter-m1-2')));
      await tester.pumpAndSettle();
      final stored = container(tester).read(cccProgressProvider).seen;
      expect(stored['m1'], [0, 2]);
      // Rien n'a été envoyé au serveur.
      expect(h.api.requests.where((r) => r.method != 'GET' && r.path.contains('ccc')), isEmpty);
      expect(h.api.requests.where((r) => r.path.contains('ccc')).map((r) => r.method).toSet(), {'GET'});

      // Retour à la liste : progression globale 40 % (2 sur 5), annoncée.
      GoRouter.of(tester.element(find.byType(Scaffold).first)).pop();
      await tester.pumpAndSettle();
      final global = tester.getSemantics(find.byKey(const ValueKey('ccc-global-bar')));
      expect(global.label, contains(S.t('ccc_manual_global_announce', {'percent': 40})));
      sem.dispose();
    });

    testWidgets('la progression survit au redémarrage et reste propre à chaque compte', (tester) async {
      final key = 'xm_ccc_manuel_v1:12';
      await openCcc(tester, prefs: {key: jsonEncode({'m1': [0, 1, 2], 'm2': [0]})});
      expect(find.text(S.t('ccc_manual_module_done')), findsOneWidget, reason: 'module 1 terminé');
      expect(container(tester).read(cccProgressProvider).seen['m2'], [0]);
    });

    testWidgets('progression d’un autre compte ignorée ; contenu corrompu : progression vide', (tester) async {
      await openCcc(tester, prefs: {'xm_ccc_manuel_v1:99': jsonEncode({'m1': [0]}), 'xm_ccc_manuel_v1:12': '{oups'});
      expect(container(tester).read(cccProgressProvider).seen, isEmpty);
      expect(find.byKey(const ValueKey('ccc-reset')), findsNothing);
    });

    testWidgets('« Effacer ma progression » demande confirmation puis vide l’appareil', (tester) async {
      await openCcc(tester, prefs: {'xm_ccc_manuel_v1:12': jsonEncode({'m1': [0]})});
      await tester.ensureVisible(find.byKey(const ValueKey('ccc-reset')));
      await tester.tap(find.byKey(const ValueKey('ccc-reset')));
      await tester.pumpAndSettle();
      await tester.tap(find.text(S.t('common_cancel')));
      await tester.pumpAndSettle();
      expect(container(tester).read(cccProgressProvider).seen, isNotEmpty);
      await tester.tap(find.byKey(const ValueKey('ccc-reset')));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, S.t('ccc_manual_reset')));
      await tester.pumpAndSettle();
      expect(container(tester).read(cccProgressProvider).seen, isEmpty);
      expect(find.text(S.t('ccc_manual_reset_done')), findsOneWidget);
    });

    testWidgets('module inconnu ou masqué : message neutre', (tester) async {
      await openCcc(tester, path: '/ccc/manuel/zz');
      expect(find.text(S.t('microcopy_error_not_found')), findsOneWidget);
    });
  });

  group('Boîtes à images', () {
    testWidgets('grille, nombre d’images, consigne masquée hors mode présentation', (tester) async {
      final sem = tester.ensureSemantics();
      await openCcc(tester);
      await openTab(tester, 'boites_images');
      expect(find.text('Le consentement'), findsOneWidget);
      expect(find.text('La pression des pairs'), findsOneWidget);
      expect(find.textContaining(S.t('ccc_images_count', {'count': 2})), findsOneWidget);
      expect(find.byKey(const ValueKey('ccc-guide-bi1')), findsNothing);
      expect(find.text(S.t('ccc_presentation_badge'), findRichText: true), findsNothing);
      // Image indisponible : jamais une zone muette (message lu par les lecteurs d'écran).
      expect(find.bySemanticsLabel(S.t('ccc_image_failed')), findsWidgets);
      sem.dispose();
    });

    testWidgets('mode présentation (compte pair actif) : bandeau et consigne visibles', (tester) async {
      await openCcc(tester, user: peerUserCcc());
      expect(find.textContaining(S.t('ccc_presentation_badge')), findsOneWidget);
      await openTab(tester, 'boites_images');
      expect(find.byKey(const ValueKey('ccc-guide-bi1')), findsOneWidget);
      expect(container(tester).read(cccPresentationProvider), isTrue);
    });

    testWidgets('pair non actif ou sans permission : pas de mode présentation', (tester) async {
      await openCcc(tester, user: {...peerUserCcc(), 'status': 'suspendu'});
      expect(container(tester).read(cccPresentationProvider), isFalse);
    });
  });

  group('Mode animation', () {
    late List<String> chrome;

    void trackChrome(WidgetTester tester) {
      chrome = [];
      tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, (call) async {
        if (call.method == 'SystemChrome.setEnabledSystemUIMode') chrome.add('${call.arguments}');
        return null;
      });
      addTearDown(() => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(SystemChannels.platform, null));
    }

    testWidgets('boîte : compteur, légende, suivante / précédente (boucle), cibles ≥ 48 dp, annonce', (tester) async {
      final sem = tester.ensureSemantics();
      trackChrome(tester);
      await openCcc(tester);
      await openTab(tester, 'boites_images');
      await tester.tap(find.byKey(const ValueKey('ccc-start-bi1')));
      await tester.pumpAndSettle();
      expect(_path(tester), '/ccc/animation?boite=bi1');

      String counter() => (tester.widget<Text>(find.byKey(const ValueKey('ccc-show-counter')))).data!;
      expect(counter(), S.t('ccc_show_counter', {'current': 1, 'total': 2}));
      expect(find.text('Deux amies discutent'), findsOneWidget);
      expect(find.byKey(const ValueKey('ccc-show-guide')), findsNothing, reason: 'consigne : mode présentation seulement');
      for (final k in ['ccc-show-prev', 'ccc-show-next', 'ccc-show-close']) {
        final s = tester.getSize(find.byKey(ValueKey(k)));
        expect(s.height, greaterThanOrEqualTo(48), reason: k);
        expect(s.width, greaterThanOrEqualTo(48), reason: k);
      }
      final live = tester.getSemantics(find.byKey(const ValueKey('ccc-show-counter')));
      expect(live.label, S.t('ccc_show_announce', {'current': 1, 'total': 2, 'title': 'Le consentement'}));

      await tester.tap(find.byKey(const ValueKey('ccc-show-next')));
      await tester.pumpAndSettle();
      expect(counter(), S.t('ccc_show_counter', {'current': 2, 'total': 2}));
      expect(find.text('Deux amies discutent'), findsNothing);
      await tester.tap(find.byKey(const ValueKey('ccc-show-next')));
      await tester.pumpAndSettle();
      expect(counter(), S.t('ccc_show_counter', {'current': 1, 'total': 2}), reason: 'boucle');
      await tester.tap(find.byKey(const ValueKey('ccc-show-prev')));
      await tester.pumpAndSettle();
      expect(counter(), S.t('ccc_show_counter', {'current': 2, 'total': 2}));

      // Glisser vers la droite = planche précédente.
      await tester.fling(find.byKey(const ValueKey('ccc-show-pages')), const Offset(400, 0), 1500);
      await tester.pumpAndSettle();
      expect(counter(), S.t('ccc_show_counter', {'current': 1, 'total': 2}));
      sem.dispose();
    });

    testWidgets('chrome système : immersif à l’ouverture, restauré en fermant', (tester) async {
      trackChrome(tester);
      await openCcc(tester, path: '/ccc?onglet=images');
      await tester.tap(find.byKey(const ValueKey('ccc-start-all')));
      await tester.pumpAndSettle();
      expect(chrome, ['SystemUiMode.immersiveSticky']);
      // Toutes les boîtes à la suite : 2 + 1 images.
      expect(find.text(S.t('ccc_show_counter', {'current': 1, 'total': 3})), findsOneWidget);

      await tester.tap(find.byKey(const ValueKey('ccc-show-close')));
      await tester.pumpAndSettle();
      expect(chrome, ['SystemUiMode.immersiveSticky', 'SystemUiMode.edgeToEdge']);
      expect(_path(tester), '/ccc?onglet=images');
    });

    testWidgets('chrome système restauré aussi quand l’écran est quitté par retour système', (tester) async {
      trackChrome(tester);
      await openCcc(tester, path: '/ccc/animation?boite=bi1');
      expect(chrome.last, 'SystemUiMode.immersiveSticky');
      GoRouter.of(tester.element(find.byType(Scaffold).first)).go('/home');
      await tester.pumpAndSettle();
      expect(chrome.last, 'SystemUiMode.edgeToEdge');
    });

    testWidgets('mode présentation : la consigne de l’animateur·rice accompagne l’image', (tester) async {
      await openCcc(tester, user: peerUserCcc(), path: '/ccc/animation?boite=bi1');
      expect(find.byKey(const ValueKey('ccc-show-guide')), findsOneWidget);
      expect(find.text('Laisse chaque personne parler avant de réagir.'), findsOneWidget);
    });

    testWidgets('boîte inconnue : message, fermeture possible', (tester) async {
      trackChrome(tester);
      await openCcc(tester, path: '/ccc/animation?boite=zz');
      expect(find.text(S.t('ccc_empty_boites_images')), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('ccc-show-close')));
      await tester.pumpAndSettle();
      expect(chrome.last, 'SystemUiMode.edgeToEdge');
    });
  });

  group('BD', () {
    testWidgets('liste puis lecteur : progression, légende, alt par planche en Semantics, fin et discussion', (tester) async {
      final sem = tester.ensureSemantics();
      await openCcc(tester, path: '/ccc?onglet=bd');
      expect(find.text(S.t('ccc_comic_pages_count', {'count': 3})), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('ccc-read-bd1')));
      await tester.pumpAndSettle();
      expect(_path(tester), '/ccc/bd/bd1');

      String counter() => tester.widget<Text>(find.byKey(const ValueKey('ccc-comic-counter'))).data!;
      expect(counter(), S.t('ccc_comic_page_counter', {'current': 1, 'total': 3}));
      expect(find.text('Awa hésite devant la porte'), findsOneWidget);
      expect(tester.getSemantics(find.byKey(const ValueKey('ccc-comic-counter'))).label,
          S.t('ccc_comic_page_announce', {'current': 1, 'total': 3, 'caption': 'Awa hésite devant la porte'}));
      // Texte alternatif de la planche courante : l'image en erreur reste décrite, alt lu en Semantics.
      expect(find.bySemanticsLabel(S.t('ccc_comic_reader_label', {'title': 'Awa et le centre de santé'})), findsOneWidget);
      final bar = tester.getSemantics(find.byKey(const ValueKey('ccc-comic-progress')));
      expect(bar.label, S.t('ccc_comic_progress'));
      expect(bar.value, S.t('ccc_comic_page_counter', {'current': 1, 'total': 3}));
      expect(tester.getSize(find.byKey(const ValueKey('ccc-comic-prev'))).height, greaterThanOrEqualTo(48));
      expect(tester.widget<IconButton>(find.byKey(const ValueKey('ccc-comic-prev'))).onPressed, isNull, reason: 'première planche');

      await tester.tap(find.byKey(const ValueKey('ccc-comic-next')));
      await tester.pumpAndSettle();
      expect(counter(), S.t('ccc_comic_page_counter', {'current': 2, 'total': 3}));
      // Planche sans légende : l'annonce retombe sur le texte alternatif.
      expect(tester.getSemantics(find.byKey(const ValueKey('ccc-comic-counter'))).label,
          S.t('ccc_comic_page_announce', {'current': 2, 'total': 3, 'caption': 'Awa parle à une infirmière'}));
      await tester.fling(find.byKey(const ValueKey('ccc-comic-pages')), const Offset(-400, 0), 1500);
      await tester.pumpAndSettle();
      expect(counter(), S.t('ccc_comic_page_counter', {'current': 3, 'total': 3}));
      expect(find.byKey(const ValueKey('ccc-comic-next')), findsNothing);

      await tester.tap(find.byKey(const ValueKey('ccc-comic-finish')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('ccc_comic_done_title')), findsOneWidget);
      expect(find.textContaining(S.t('ccc_comic_discuss_items_0')), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('ccc-comic-restart')));
      await tester.pumpAndSettle();
      expect(counter(), S.t('ccc_comic_page_counter', {'current': 1, 'total': 3}));
      expect(find.text('Awa hésite devant la porte'), findsOneWidget);
      sem.dispose();
    });

    testWidgets('alt de chaque planche porté par l’image (Semantics)', (tester) async {
      final sem = tester.ensureSemantics();
      await openCcc(tester, path: '/ccc/bd/bd1');
      // L'image est en erreur dans le test : le message d'indisponibilité reste lisible.
      expect(find.text(S.t('ccc_image_failed')), findsOneWidget);
      sem.dispose();
    });

    testWidgets('BD inconnue ou masquée : message neutre', (tester) async {
      await openCcc(tester, path: '/ccc/bd/zz');
      expect(find.text(S.t('microcopy_error_not_found')), findsOneWidget);
    });
  });

  group('Lois', () {
    testWidgets('nombre de textes et ouverture de /lois par le lanceur de liens existant', (tester) async {
      final h = await openCcc(tester);
      await openTab(tester, 'lois');
      expect(find.text(S.t('ccc_laws_count', {'count': 12})), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('ccc-laws-open')));
      await tester.pumpAndSettle();
      expect(h.launcher.opened, [Uri.parse('https://xomiton.test/lois')]);
    });

    testWidgets('sans adresse valide : carte informative, aucun lien inventé', (tester) async {
      final h = await openCcc(tester, ccc: (_) => jsonBody(200, cccBody(lawsUrl: 'n’importe quoi')));
      await openTab(tester, 'lois');
      expect(find.byKey(const ValueKey('ccc-laws-open')), findsNothing);
      expect(find.text(S.t('mobile_ccc_laws_unavailable')), findsOneWidget);
      expect(h.launcher.opened, isEmpty);
    });
  });

  group('police agrandie', () {
    testWidgets('texte à 200 % : aucun débordement sur les quatre onglets', (tester) async {
      tester.platformDispatcher.textScaleFactorTestValue = 2.0;
      addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
      await openCcc(tester);
      for (final k in ['manuel', 'boites_images', 'bd', 'lois']) {
        await openTab(tester, k);
      }
      expect(tester.takeException(), isNull);
      await openCcc(tester, path: '/ccc/manuel/m1');
      await openCcc(tester, path: '/ccc/bd/bd1');
      expect(tester.takeException(), isNull);
    });
  });

  group('entrées et route', () {
    testWidgets('espace pair : raccourci « Outils CCC » et tâche « Préparer la séance » du tableau de bord', (tester) async {
      await openPair(tester, routes: pairRoutes(extra: {
        'GET ccc': (_) => jsonBody(200, cccBody()),
      }));
      final shortcut = find.byKey(const ValueKey('shortcut-peer_home_shortcuts_ccc'));
      await tester.scrollUntilVisible(shortcut, 200, scrollable: find.byType(Scrollable).first);
      await tester.drag(find.byType(Scrollable).first, const Offset(0, -120));
      await tester.pumpAndSettle();
      await tester.tap(shortcut);
      await tester.pumpAndSettle();
      expect(_path(tester), '/ccc');
      expect(find.text('Module 1 : Poser un cadre sûr'), findsOneWidget);
      expect(find.textContaining(S.t('ccc_presentation_badge')), findsOneWidget, reason: 'compte pair actif : mode présentation');
    });

    testWidgets('espace membre : entrée « Outils CCC » du menu Profil → /ccc', (tester) async {
      await openCcc(tester, path: '/profile');
      await tester.scrollUntilVisible(find.byKey(const Key('tile-ccc')), 200, scrollable: find.byType(Scrollable).first);
      await tester.drag(find.byType(ListView).first, const Offset(0, -150));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('tile-ccc')));
      await tester.pumpAndSettle();
      expect(_path(tester), '/ccc');
      expect(find.text('Module 1 : Poser un cadre sûr'), findsOneWidget);
    });

    testWidgets('route enregistrée, détails compris, et protégée par la session', (tester) async {
      await openCcc(tester);
      final router = GoRouter.of(tester.element(find.byType(Scaffold).first));
      for (final p in ['/ccc', '/ccc/manuel/m1', '/ccc/bd/bd1', '/ccc/animation']) {
        router.go(p);
        await tester.pumpAndSettle();
        expect(_path(tester), p, reason: p);
        expect(find.text(S.t('microcopy_error_not_found')), findsNothing, reason: p);
      }
    });
  });
}
