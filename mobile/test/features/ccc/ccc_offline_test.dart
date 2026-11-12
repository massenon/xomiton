import 'dart:ui' show Tristate;
import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/intl.dart';
import 'package:xomiton/core/providers/core_providers.dart';
import 'package:xomiton/features/auth/application/session_controller.dart';
import 'package:xomiton/features/ccc/application/ccc_offline.dart';
import 'package:xomiton/features/ccc/application/ccc_progress.dart';
import 'package:xomiton/features/ccc/application/ccc_providers.dart';
import 'package:xomiton/features/ccc/data/ccc_image_store.dart';
import 'package:xomiton/features/ccc/data/ccc_repository.dart';

import '../../support/fakes.dart';
import '../../support/harness.dart';
import 'ccc_support.dart';

/// Lecture hors ligne des outils CCC : bandeau, bouton « Actualiser », indicateur par
/// outil, téléchargement des images (liveRegion), mode animation sans réseau.
void main() {
  FakeHandler down() => (o) => throw offlineError(o);
  DateTime ago(int days) => DateTime.now().subtract(Duration(days: days));
  final allImages = bodyImages(cccBody());
  final box1 = {
    'https://xomiton.test/storage/ccc/bi1-cover.jpg',
    'https://xomiton.test/storage/ccc/bi1-1.jpg',
    'https://xomiton.test/storage/ccc/bi1-2.jpg',
  };

  group('copie servie sans réseau', () {
    testWidgets('bandeau « Hors ligne », date de dernière synchro, contenu lisible', (tester) async {
      final at = ago(3);
      await openCcc(tester, ccc: down(), copyAt: at);
      final date = DateFormat('d MMM y', 'fr').format(at.toLocal());
      expect(find.byKey(const ValueKey('ccc-offline-banner')), findsOneWidget);
      expect(find.textContaining(S.t('ccc_offline_status')), findsOneWidget);
      expect(find.textContaining(S.t('ccc_offline_saved_on', {'date': date})), findsOneWidget);
      expect(find.text(S.t('ccc_offline_notice')), findsOneWidget);
      expect(find.text('Module 1 : Poser un cadre sûr'), findsOneWidget);
      expect(find.text(S.t('microcopy_error_unreachable')), findsNothing);
    });

    testWidgets('bandeau annoncé aux lecteurs d’écran : état en zone live (texte + icône), bouton « Actualiser » à part et nommé', (tester) async {
      final sem = tester.ensureSemantics();
      await openCcc(tester, ccc: down(), copyAt: ago(1));
      final status = tester.getSemantics(find.byKey(const ValueKey('ccc-offline-status')));
      expect(status.flagsCollection.isLiveRegion, isTrue);
      expect(status.label, contains(S.t('ccc_offline_status')));
      expect(status.label, contains(S.t('ccc_offline_notice')));
      expect(find.descendant(of: find.byKey(const ValueKey('ccc-offline-banner')), matching: find.byIcon(Icons.cloud_off_outlined)), findsOneWidget);
      final button = tester.getSemantics(find.byKey(const ValueKey('ccc-offline-refresh')));
      expect(button.label, S.t('ccc_offline_refresh_label'));
      expect(button.flagsCollection.isButton, isTrue);
      expect(button.flagsCollection.isEnabled, Tristate.isTrue);
      sem.dispose();
    });

    testWidgets('pas de bandeau quand le réseau répond', (tester) async {
      await openCcc(tester);
      expect(find.byKey(const ValueKey('ccc-offline-banner')), findsNothing);
    });

    testWidgets('« Actualiser » : réseau revenu, contenu remplacé (outil masqué disparu) et bandeau retiré', (tester) async {
      var online = false;
      var hidden = false;
      final h = await openCcc(tester, copyAt: ago(2), ccc: (o) {
        if (!online) throw offlineError(o);
        return jsonBody(200, cccBody(manuel: hidden ? [] : null), headers: {'Cache-Control': 'no-store, private'});
      });
      expect(find.text('Module 1 : Poser un cadre sûr'), findsOneWidget);
      // Toujours sans réseau : « Actualiser » garde la copie et le bandeau.
      await tester.tap(find.byKey(const ValueKey('ccc-offline-refresh')));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('ccc-offline-banner')), findsOneWidget);
      expect(find.text('Module 1 : Poser un cadre sûr'), findsOneWidget);
      online = true;
      hidden = true;
      await tester.tap(find.byKey(const ValueKey('ccc-offline-refresh')));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('ccc-offline-banner')), findsNothing);
      expect(find.text('Module 1 : Poser un cadre sûr'), findsNothing);
      expect(find.text(S.t('ccc_empty_manuel')), findsOneWidget);
      expect(h.api.calls('GET ccc').length, greaterThanOrEqualTo(3));
      // La copie a été remplacée : sans réseau, l'outil masqué ne revient pas.
      final stored = jsonDecode(h.env.db.privateCache.get(cccCopyKey(12))!) as Map;
      expect(((stored['body'] as Map)['data'] as Map)['manuel'], isEmpty);
    });

    testWidgets('aucune copie : l’erreur « Réessayer » reste inchangée', (tester) async {
      await openCcc(tester, ccc: down());
      expect(find.text(S.t('microcopy_error_unreachable')), findsOneWidget);
      expect(find.text(S.t('common_retry')), findsOneWidget);
      expect(find.byKey(const ValueKey('ccc-offline-banner')), findsNothing);
    });

    testWidgets('copie de plus de 30 jours : non servie, message clair', (tester) async {
      await openCcc(tester, ccc: down(), copyAt: ago(31));
      expect(find.byKey(const ValueKey('ccc-copy-expired')), findsOneWidget);
      expect(find.text(S.t('ccc_offline_expired', {'days': 30})), findsOneWidget);
      expect(find.text('Module 1 : Poser un cadre sûr'), findsNothing);
      expect(find.text(S.t('common_retry')), findsOneWidget);
    });

    testWidgets('403 : la copie n’est pas servie et elle est purgée', (tester) async {
      final h = await openCcc(tester, copyAt: ago(1), ccc: (_) => jsonBody(403, {'message': 'Action non autorisée.', 'code': 'forbidden'}));
      expect(find.text(S.t('mobile_ccc_forbidden')), findsOneWidget);
      expect(find.text('Module 1 : Poser un cadre sûr'), findsNothing);
      expect(h.env.db.privateCache.get(cccCopyKey(12)), isNull);
    });

    testWidgets('503 (maintenance de l’API) : la copie est servie', (tester) async {
      await openCcc(tester, copyAt: ago(1), ccc: (_) => jsonBody(503, {'message': 'x', 'code': 'x'}));
      expect(find.byKey(const ValueKey('ccc-offline-banner')), findsOneWidget);
    });
  });

  group('images hors ligne', () {
    testWidgets('indicateur « Disponible hors ligne » : seulement l’outil dont toutes les images sont sur disque, texte + icône', (tester) async {
      final sem = tester.ensureSemantics();
      await openCcc(tester, ccc: down(), copyAt: ago(1), savedImages: box1);
      await openTab(tester, 'boites_images');
      final badge = find.byKey(const ValueKey('ccc-offline-bi1'));
      expect(badge, findsOneWidget);
      expect(find.descendant(of: badge, matching: find.text(S.t('ccc_offline_tool_available'))), findsOneWidget);
      expect(find.descendant(of: badge, matching: find.byIcon(Icons.download_done)), findsOneWidget);
      expect(find.byKey(const ValueKey('ccc-offline-bi2')), findsNothing);
      sem.dispose();
    });

    testWidgets('une seule image manquante : pas d’indicateur', (tester) async {
      await openCcc(tester, ccc: down(), copyAt: ago(1), savedImages: box1.take(2));
      await openTab(tester, 'boites_images');
      expect(find.byKey(const ValueKey('ccc-offline-bi1')), findsNothing);
    });

    testWidgets('BD : indicateur quand couverture et planches sont enregistrées', (tester) async {
      await openCcc(tester, ccc: down(), copyAt: ago(1), savedImages: allImages);
      await openTab(tester, 'bd');
      expect(find.byKey(const ValueKey('ccc-offline-bd1')), findsOneWidget);
    });

    testWidgets('image enregistrée : lue depuis le disque, sinon depuis le réseau', (tester) async {
      await openCcc(tester, ccc: down(), copyAt: ago(1), savedImages: ['https://xomiton.test/storage/ccc/bi1-cover.jpg']);
      await openTab(tester, 'boites_images');
      final images = tester.widgetList<Image>(find.byType(Image)).map((i) => i.image).toList();
      expect(images.whereType<FileImage>(), hasLength(1));
      expect(images.whereType<FileImage>().single.file.path, endsWith(CccImageStore.nameFor('https://xomiton.test/storage/ccc/bi1-cover.jpg')));
      expect(images.whereType<NetworkImage>(), isNotEmpty, reason: 'les autres images restent en ligne');
    });

    testWidgets('hors ligne : pas de bouton de téléchargement, message clair', (tester) async {
      await openCcc(tester, ccc: down(), copyAt: ago(1));
      await openTab(tester, 'boites_images');
      expect(find.byKey(const ValueKey('ccc-download')), findsNothing);
      expect(find.text(S.t('ccc_offline_download_needs_network')), findsOneWidget);
    });

    testWidgets('bouton « Télécharger pour lire hors ligne » : jamais automatique, progression, puis indicateurs', (tester) async {
      final server = FakeImageServer();
      final h = await openCcc(tester, imageApi: server);
      await openTab(tester, 'boites_images');
      expect(server.urls, isEmpty, reason: 'aucun téléchargement silencieux');
      expect(find.text(S.t('ccc_offline_download_hint')), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('ccc-download')));
      await tester.pumpAndSettle();
      expect(server.urls.toSet(), allImages);
      expect(h.env.imageDisk.files, hasLength(allImages.length));
      expect(find.byKey(const ValueKey('ccc-download-done')), findsOneWidget);
      expect(find.text(S.t('ccc_offline_download_done')), findsOneWidget);
      expect(find.byKey(const ValueKey('ccc-offline-bi1')), findsOneWidget);
      expect(find.byKey(const ValueKey('ccc-offline-bi2')), findsOneWidget);
    });

    testWidgets('progression annoncée en zone live, annulable, sans rien perdre', (tester) async {
      final sem = tester.ensureSemantics();
      final gate = Completer<void>();
      var served = 0;
      final server = FakeImageServer(handler: (url) async {
        if (++served == 2) await gate.future;
        return imageBody([1, 2, 3]);
      });
      await openCcc(tester, imageApi: server);
      await openTab(tester, 'boites_images');
      await tester.tap(find.byKey(const ValueKey('ccc-download')));
      for (var i = 0; i < 5; i++) {
        await tester.pump(const Duration(milliseconds: 20));
      }
      final bar = find.byKey(const ValueKey('ccc-download-progress'));
      expect(bar, findsOneWidget);
      final node = tester.getSemantics(bar);
      expect(node.flagsCollection.isLiveRegion, isTrue);
      expect(node.label, contains(S.t('ccc_offline_download_cta')));
      expect(node.label, contains(S.t('ccc_offline_download_progress', {'done': 0, 'total': allImages.length})), reason: 'annonce par quarts');
      expect(find.text(S.t('ccc_offline_download_progress', {'done': 1, 'total': allImages.length})), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('ccc-download-cancel')));
      gate.complete();
      await tester.pumpAndSettle();
      expect(find.text(S.t('ccc_offline_download_cancelled')), findsOneWidget);
      expect(served, lessThan(allImages.length), reason: 'arrêt après l’annulation');
      expect(find.byKey(const ValueKey('ccc-download')), findsOneWidget, reason: 'on peut reprendre');
      sem.dispose();
    });

    testWidgets('échecs : nombre d’images non enregistrées annoncé, « Télécharger » reste proposé', (tester) async {
      final server = FakeImageServer(handler: (url) => imageBody([1], status: 404));
      await openCcc(tester, imageApi: server);
      await openTab(tester, 'boites_images');
      await tester.tap(find.byKey(const ValueKey('ccc-download')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('ccc_offline_download_failed', {'count': allImages.length})), findsOneWidget);
      expect(find.byKey(const ValueKey('ccc-download')), findsOneWidget);
    });

    testWidgets('une synchro réussie retire du disque les images qui ne sont plus publiées', (tester) async {
      final orphan = 'https://xomiton.test/storage/ccc/retiree.jpg';
      final h = await openCcc(tester, savedImages: [...allImages, orphan]);
      await tester.pumpAndSettle();
      expect(h.env.imageDisk.files.keys, isNot(contains(CccImageStore.nameFor(orphan))));
      expect(h.env.imageDisk.files, hasLength(allImages.length));
    });

    testWidgets('appareil sans dossier utilisable : aucune offre de téléchargement, images lues en ligne', (tester) async {
      await openCcc(tester, diskAvailable: false);
      await openTab(tester, 'boites_images');
      expect(container(tester).read(cccImagesProvider).supported, isFalse);
      expect(find.byKey(const ValueKey('ccc-download-card')), findsNothing);
    });
  });

  group('mode animation et manuel sans réseau', () {
    testWidgets('mode animation : images, légende, boutons et retour au début, tout depuis la copie', (tester) async {
      await openCcc(tester, ccc: down(), copyAt: ago(5), savedImages: allImages, path: '/ccc/animation?boite=bi1');
      expect(find.byKey(const ValueKey('ccc-show-title')), findsOneWidget);
      expect(find.text('Le consentement'), findsOneWidget);
      expect(find.text('Deux amies discutent'), findsOneWidget);
      expect(find.text(S.t('ccc_show_counter', {'current': 1, 'total': 2})), findsOneWidget);
      final shown = tester.widgetList<Image>(find.byType(Image)).map((i) => i.image);
      expect(shown.whereType<FileImage>(), isNotEmpty, reason: 'image lue depuis le disque');
      await tester.tap(find.byKey(const ValueKey('ccc-show-next')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('ccc_show_counter', {'current': 2, 'total': 2})), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('ccc-show-next')));
      await tester.pumpAndSettle();
      expect(find.text(S.t('ccc_show_counter', {'current': 1, 'total': 2})), findsOneWidget);
    });

    testWidgets('mode animation sans copie ni réseau : erreur « Réessayer », fermeture possible', (tester) async {
      await openCcc(tester, ccc: down(), path: '/ccc/animation');
      expect(find.text(S.t('common_retry')), findsOneWidget);
      expect(find.byKey(const ValueKey('ccc-show-close')), findsOneWidget);
    });

    testWidgets('lecteur de BD hors ligne depuis la copie', (tester) async {
      await openCcc(tester, ccc: down(), copyAt: ago(5), savedImages: allImages, path: '/ccc/bd/bd1');
      expect(find.text('Awa et le centre de santé'), findsWidgets);
      expect(find.text(S.t('ccc_comic_page_counter', {'current': 1, 'total': 3})), findsOneWidget);
    });

    testWidgets('cases « point vu » du manuel : cochées et gardées sur l’appareil sans réseau', (tester) async {
      await openCcc(tester, ccc: down(), copyAt: ago(2), path: '/ccc/manuel/m1');
      await tester.tap(find.byKey(const ValueKey('ccc-chapter-m1-0')));
      await tester.pumpAndSettle();
      expect(container(tester).read(cccProgressProvider).seen, {'m1': [0]});
      final saved = decodeSeen(container(tester).read(sharedPreferencesProvider).getString(cccProgressStorageKey(12)));
      expect(saved, {'m1': [0]});
    });
  });

  group('purge', () {
    testWidgets('déconnexion : copie et images enregistrées effacées', (tester) async {
      final h = await openCcc(tester, copyAt: ago(1), savedImages: allImages);
      await tester.pumpAndSettle();
      expect(h.env.db.privateCache.get(cccCopyKey(12)), isNotNull);
      await container(tester).read(sessionProvider.notifier).expire();
      await tester.pumpAndSettle();
      expect(h.env.db.privateCache.get(cccCopyKey(12)), isNull);
      expect(h.env.imageDisk.files, isEmpty);
    });
  });
}
