import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:xomiton/core/auth_spaces/app_space.dart';
import 'package:xomiton/core/auth_spaces/space_providers.dart';

import '../support/fakes.dart';
import '../support/harness.dart';
import '../support/pair_harness.dart';

void main() {
  setUpAll(() => initializeDateFormatting('fr'));

  group('espace pair : SOS et réseau social toujours à portée', () {
    final routes = pairRoutes(extra: {
      'GET pair/alerts': (_) => jsonBody(200, {'data': [alertRow('a1')], 'meta': {'new': 1}}),
      'GET pair/alerts/a1': (_) => jsonBody(200, {'data': {...alertRow('a1'), 'can': {'acknowledge': true, 'take': true, 'close': false, 'statuses': []}}}),
      'GET pair/cases': (_) => jsonBody(200, {'data': [], 'meta': {'current_page': 1, 'last_page': 1, 'total': 0}}),
    });

    testWidgets('le SOS est dans la barre sur tous les écrans pair (accueil, alertes, détail, dossiers, collecte, file, conflit)', (tester) async {
      await openPair(tester, routes: routes);
      for (final path in [
        '/pair/accueil',
        '/pair/alertes',
        '/pair/alertes/a1',
        '/pair/dossiers',
        '/pair/dossiers/collecte',
        '/pair/accueil/synchro',
        '/pair/accueil/synchro/conflit/1',
      ]) {
        goTo(tester, path);
        await tester.pumpAndSettle();
        final sos = find.byKey(const ValueKey('tab-peer-sos'));
        expect(sos, findsOneWidget, reason: path);
        expect(tester.getSize(sos).height, greaterThanOrEqualTo(44), reason: path);
      }
    });

    testWidgets('depuis un écran de détail, le SOS ouvre l’écran SOS sans code (jamais derrière le verrou)', (tester) async {
      await openPair(tester, routes: routes, goTo_: '/pair/alertes/a1');
      await tester.tap(find.byKey(const ValueKey('tab-peer-sos')));
      await tester.pumpAndSettle();
      expect(currentPath(tester), '/pair/sos');
    });

    testWidgets('onglet Réseau : le fil de l’espace Membre, avec la pastille pour revenir au pair', (tester) async {
      await openPair(tester, routes: routes, goTo_: '/pair/dossiers');
      await tester.tap(find.byKey(const ValueKey('tab-peer-network')));
      await tester.pumpAndSettle();
      expect(currentPath(tester), '/feed');
      expect(containerOf(tester).read(activeSpaceProvider), AppSpace.member);
      expect(find.byKey(const ValueKey('tab-other-space')), findsOneWidget, reason: 'barre Membre avec l’onglet « Espace pair »');
      // Un seul autre espace : l'onglet y retourne directement.
      await tester.tap(find.byKey(const ValueKey('tab-other-space')));
      await tester.pumpAndSettle();
      expect(currentPath(tester), '/pair/accueil');
      expect(containerOf(tester).read(activeSpaceProvider), AppSpace.peer);
    });

    testWidgets('l’espace pair garde la pastille d’espace et l’état de synchro en en-tête de chaque onglet', (tester) async {
      await openPair(tester, routes: routes);
      for (final path in ['/pair/accueil', '/pair/alertes', '/pair/dossiers']) {
        goTo(tester, path);
        await tester.pumpAndSettle();
        expect(find.byKey(const ValueKey('sync-chip')), findsOneWidget, reason: path);
        expect(find.byKey(const ValueKey('space-pill')), findsOneWidget, reason: path);
      }
    });
  });
}
