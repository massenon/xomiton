import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:xomiton/app/theme/xm_tokens.dart';
import 'package:xomiton/core/ui/xm_rainbow_deco.dart';

Widget host(
  Widget child, {
  Brightness brightness = Brightness.light,
  double height = 844,
}) => MaterialApp(
  theme: ThemeData(brightness: brightness),
  home: Center(
    child: SizedBox(
      width: 390,
      height: height,
      child: Scaffold(body: child),
    ),
  ),
);

void main() {
  testWidgets(
    'le décor encadre le contenu sans le recouvrir et reste hors de l\'accessibilité',
    (tester) async {
      final handle = tester.ensureSemantics();
      await tester.pumpWidget(
        host(const XmAuthDecor(child: TextField(key: ValueKey('champ')))),
      );

      expect(find.byKey(const ValueKey('xm-rainbow-ribbon')), findsOneWidget);
      expect(find.byKey(const ValueKey('xm-rainbow-footer')), findsOneWidget);
      // Le champ est entre le ruban (au-dessus) et l'illustration (en dessous), sans chevauchement.
      final ribbon = tester.getRect(
        find.byKey(const ValueKey('xm-rainbow-ribbon')),
      );
      final field = tester.getRect(find.byKey(const ValueKey('champ')));
      final footer = tester.getRect(
        find.byKey(const ValueKey('xm-rainbow-footer')),
      );
      expect(ribbon.bottom, lessThanOrEqualTo(field.top));
      expect(field.bottom, lessThanOrEqualTo(footer.top));
      // Décor : aucun nœud sémantique, aucun toucher intercepté (le champ reste saisissable).
      expect(
        find.descendant(
          of: find.byType(XmAuthDecor),
          matching: find.byType(ExcludeSemantics),
        ),
        findsWidgets,
      );
      await tester.enterText(find.byKey(const ValueKey('champ')), 'awa');
      expect(find.text('awa'), findsOneWidget);
      handle.dispose();
    },
  );

  testWidgets(
    'l\'illustration du bas disparaît quand la place manque (clavier ouvert)',
    (tester) async {
      await tester.pumpWidget(
        host(const XmAuthDecor(child: SizedBox()), height: 400),
      );
      expect(find.byKey(const ValueKey('xm-rainbow-ribbon')), findsOneWidget);
      expect(find.byKey(const ValueKey('xm-rainbow-footer')), findsNothing);
    },
  );

  testWidgets('thème sombre : le décor se dessine sans erreur', (tester) async {
    await tester.pumpWidget(
      host(const XmAuthDecor(child: SizedBox()), brightness: Brightness.dark),
    );
    expect(tester.takeException(), isNull);
  });

  test('palette : six couleurs, une par bande', () {
    expect(XmRainbow.stripes, hasLength(6));
    expect(XmRainbow.stripes.toSet(), hasLength(6));
  });
}
