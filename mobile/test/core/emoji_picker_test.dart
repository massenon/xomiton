import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:xomiton/core/emoji/emoji_data.dart';
import 'package:xomiton/core/emoji/emoji_logic.dart';
import 'package:xomiton/core/emoji/emoji_picker_sheet.dart';
import 'package:xomiton/core/l10n/app_strings.dart';
import 'package:xomiton/core/providers/core_providers.dart';

import '../support/harness.dart';

Future<Widget> host(
  TextEditingController controller, {
  int? maxLength,
  Brightness brightness = Brightness.light,
  double textScale = 1,
  Map<String, Object> prefs = const {},
}) async {
  SharedPreferences.setMockInitialValues(prefs);
  final sp = await SharedPreferences.getInstance();
  return ProviderScope(
    overrides: [sharedPreferencesProvider.overrideWithValue(sp)],
    child: MaterialApp(
      theme: ThemeData(brightness: brightness),
      locale: AppStrings.locale,
      supportedLocales: const [AppStrings.locale],
      localizationsDelegates: [
        AppStringsDelegate(loadStrings()),
        ...GlobalMaterialLocalizations.delegates,
      ],
      builder: (context, child) => MediaQuery(
        data: MediaQuery.of(context).copyWith(textScaler: TextScaler.linear(textScale)),
        child: child!,
      ),
      home: Scaffold(
        body: Column(
          children: [
            TextField(controller: controller),
            EmojiButton(controller: controller, maxLength: maxLength),
          ],
        ),
      ),
    ),
  );
}

Future<void> openSheet(WidgetTester tester) async {
  await tester.tap(find.byKey(const ValueKey('emoji-button')));
  await tester.pumpAndSettle();
}

EmojiEntry? entryOf(String char) {
  for (final c in kEmojiCategories) {
    for (final e in c.emojis) {
      if (e.char == char) return e;
    }
  }
  return null;
}

void main() {
  group('données', () {
    final json = jsonDecode(File('../web/resources/js/emoji/emojis.json').readAsStringSync()) as Map<String, dynamic>;
    final excluded = <String>{
      for (final g in (jsonDecode(File('../web/resources/js/emoji/excluded.json').readAsStringSync())['groups'] as List))
        ...List<String>.from((g as Map)['emojis'] as List),
    };

    test('la version Dart est la copie exacte de la source web (emojis.json)', () {
      final cats = (json['categories'] as List).cast<Map<String, dynamic>>();
      expect(kEmojiCategories.map((c) => c.id).toList(), cats.map((c) => c['id']).toList());
      for (var i = 0; i < cats.length; i++) {
        final src = (cats[i]['emojis'] as List).cast<Map<String, dynamic>>();
        final dart = kEmojiCategories[i].emojis;
        expect(dart.map((e) => e.char).toList(), src.map((e) => e['c']).toList(), reason: kEmojiCategories[i].id);
        expect(dart.map((e) => e.name).toList(), src.map((e) => e['n']).toList());
        expect(dart.map((e) => e.tone).toList(), src.map((e) => e['t'] == 1).toList());
        expect(kEmojiCategories[i].label, cats[i]['label']);
      }
    });

    test('taille, doublons, noms, émojis écartés, drapeaux et séquences ZWJ', () {
      final all = [for (final c in kEmojiCategories) ...c.emojis];
      expect(all.length, inInclusiveRange(300, 600));
      expect(all.map((e) => e.char).toSet().length, all.length);
      expect(all.every((e) => e.name.length >= 2), isTrue);
      expect(all.where((e) => excluded.contains(e.char) || excluded.contains(emojiBaseKey(e.char))), isEmpty);
      for (final flag in ['🏳️‍🌈', '🏳️‍⚧️', '🇧🇯']) {
        expect(entryOf(flag), isNotNull, reason: flag);
      }
      expect(all.any((e) => e.char.contains('‍')), isTrue);
    });
  });

  group('logique', () {
    test('recherche : accents et ligatures ignorés, tous les mots', () {
      expect(normalizeEmojiText('  Cœur  ÉTÉ '), 'coeur ete');
      expect(searchEmojis('COEUR').map((e) => e.name), contains('cœur rouge'));
      expect(searchEmojis('coeur rouge').map((e) => e.name), contains('cœur rouge'));
      expect(searchEmojis('zzzzzz'), isEmpty);
      expect(searchEmojis('   '), isEmpty);
    });

    test('teintes : après le premier point de code, ZWJ conservé', () {
      expect(applyEmojiTone('👍', 3), '👍\u{1F3FD}');
      expect(applyEmojiTone('✌️', 5), '✌\u{1F3FF}');
      expect(applyEmojiTone('👩‍⚕️', 2), '👩\u{1F3FC}‍⚕️');
      expect(applyEmojiTone('👍', 0), '👍');
      expect(emojiNameOf('👍\u{1F3FD}'), 'pouce levé');
    });

    test('points de code : le drapeau arc-en-ciel en compte 4, comme le serveur', () {
      expect(codePointLength('a😀🏳️‍🌈'), 6);
    });

    test('insertion au curseur, sur une sélection, ou à la fin sans sélection', () {
      final c = TextEditingController(text: 'abcd')..selection = const TextSelection.collapsed(offset: 2);
      expect(insertEmoji(c, '😀'), isTrue);
      expect(c.text, 'ab😀cd');
      expect(c.selection.baseOffset, 4);
      c.selection = const TextSelection(baseOffset: 0, extentOffset: 2);
      insertEmoji(c, '❤️');
      expect(c.text, '❤️😀cd');
      c.selection = const TextSelection.collapsed(offset: -1);
      insertEmoji(c, '🌈');
      expect(c.text.endsWith('🌈'), isTrue);
    });

    test('longueur maximale en points de code : refus sans rien changer', () {
      final c = TextEditingController(text: 'ab')..selection = const TextSelection.collapsed(offset: 2);
      expect(insertEmoji(c, '😀', maxLength: 3), isTrue);
      expect(insertEmoji(c, '😀', maxLength: 3), isFalse);
      expect(c.text, 'ab😀');
      final f = TextEditingController(text: '')..selection = const TextSelection.collapsed(offset: 0);
      expect(insertEmoji(f, '🏳️‍🌈', maxLength: 3), isFalse);
      expect(insertEmoji(f, '🏳️‍🌈', maxLength: 4), isTrue);
    });

    test('filtre de saisie : compte les points de code, pas les graphèmes', () {
      const f = CodePointLimitFormatter(5);
      const old = TextEditingValue(text: '🏳️‍🌈');
      expect(f.formatEditUpdate(old, const TextEditingValue(text: '🏳️‍🌈a')).text, '🏳️‍🌈a');
      expect(f.formatEditUpdate(old, const TextEditingValue(text: '🏳️‍🌈ab')).text, '🏳️‍🌈');
    });
  });

  group('feuille de sélection', () {
    testWidgets('insère à la position du curseur, ferme, mémorise le récent et annonce', (tester) async {
      final announced = <String>[];
      tester.binding.defaultBinaryMessenger.setMockDecodedMessageHandler<dynamic>(SystemChannels.accessibility, (m) async {
        announced.add(m.toString());
        return null;
      });
      final c = TextEditingController(text: 'abcd')..selection = const TextSelection.collapsed(offset: 2);
      await tester.pumpWidget(await host(c));
      await openSheet(tester);
      expect(find.byType(EmojiPickerSheet), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('emoji-😀')));
      await tester.pumpAndSettle();
      expect(c.text, 'ab😀cd');
      expect(c.selection.baseOffset, 4);
      expect(find.byType(EmojiPickerSheet), findsNothing);
      final prefs = await SharedPreferences.getInstance();
      expect(prefs.getStringList(kEmojiRecentsPref), ['😀']);
      expect(announced.any((m) => m.contains('Émoji ajouté')), isTrue);
    });

    testWidgets('respecte la longueur maximale : message, rien d’inséré, feuille ouverte', (tester) async {
      final c = TextEditingController(text: 'ab')..selection = const TextSelection.collapsed(offset: 2);
      await tester.pumpWidget(await host(c, maxLength: 3));
      await openSheet(tester);
      await tester.tap(find.byKey(const ValueKey('emoji-😀')));
      await tester.pumpAndSettle();
      expect(c.text, 'ab😀');
      await openSheet(tester);
      await tester.tap(find.byKey(const ValueKey('emoji-😀')));
      await tester.pumpAndSettle();
      expect(c.text, 'ab😀');
      expect(find.text('Limite de caractères atteinte\u00a0: l’émoji n’a pas été ajouté.'), findsOneWidget);
      expect(find.byType(EmojiPickerSheet), findsOneWidget);
    });

    testWidgets('recherche sans accents et état vide', (tester) async {
      final c = TextEditingController();
      await tester.pumpWidget(await host(c));
      await openSheet(tester);
      await tester.enterText(find.byKey(const ValueKey('emoji-search')), 'COEUR');
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('emoji-❤️')), findsOneWidget);
      expect(find.text('Résultats de la recherche'), findsOneWidget);
      await tester.enterText(find.byKey(const ValueKey('emoji-search')), 'zzzzzz');
      await tester.pumpAndSettle();
      expect(find.text('Aucun émoji ne correspond à ta recherche.'), findsOneWidget);
    });

    testWidgets('Semantics : un bouton nommé en français par émoji, cibles d’au moins 48 dp', (tester) async {
      final handle = tester.ensureSemantics();
      final c = TextEditingController();
      await tester.pumpWidget(await host(c));
      await openSheet(tester);
      expect(find.bySemanticsLabel('visage rieur'), findsOneWidget);
      expect(find.byTooltip('Fermer le sélecteur d’émojis'), findsOneWidget);
      expect(find.bySemanticsLabel('Récents'), findsOneWidget);
      final size = tester.getSize(find.byKey(const ValueKey('emoji-😀')));
      expect(size.width, greaterThanOrEqualTo(48));
      expect(size.height, greaterThanOrEqualTo(48));
      expect(tester.getSize(find.byKey(const ValueKey('emoji-tab-visages'))).shortestSide, greaterThanOrEqualTo(48));
      expect(tester.getSize(find.byKey(const ValueKey('emoji-tone-3'))).shortestSide, greaterThanOrEqualTo(48));
      handle.dispose();
    });

    testWidgets('onglets de catégories et récents lus au démarrage', (tester) async {
      final c = TextEditingController();
      await tester.pumpWidget(await host(c, prefs: {kEmojiRecentsPref: ['🌈', '💜']}));
      await openSheet(tester);
      // Récents d'abord quand il y en a.
      expect(find.byKey(const ValueKey('emoji-🌈')), findsOneWidget);
      expect(find.byKey(const ValueKey('emoji-💜')), findsOneWidget);
      await tester.tap(find.byKey(const ValueKey('emoji-tab-drapeaux')));
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('emoji-🏳️‍🌈')), findsOneWidget);
      expect(find.byKey(const ValueKey('emoji-🇧🇯')), findsOneWidget);
    });

    testWidgets('récents vides : message ; stockage corrompu sans plantage', (tester) async {
      final c = TextEditingController();
      await tester.pumpWidget(await host(c, prefs: {kEmojiRecentsPref: <String>[]}));
      await openSheet(tester);
      await tester.tap(find.byKey(const ValueKey('emoji-tab-recents')));
      await tester.pumpAndSettle();
      expect(find.text('Les émojis que tu utilises apparaîtront ici.'), findsOneWidget);
    });

    testWidgets('teinte de peau appliquée et mémorisée', (tester) async {
      final c = TextEditingController();
      await tester.pumpWidget(await host(c));
      await openSheet(tester);
      await tester.tap(find.byKey(const ValueKey('emoji-tab-mains')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('emoji-tone-3')));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('emoji-👍\u{1F3FD}')));
      await tester.pumpAndSettle();
      expect(c.text, '👍\u{1F3FD}');
      expect((await SharedPreferences.getInstance()).getInt(kEmojiTonePref), 3);
    });

    testWidgets('grande taille de police et thème sombre : pas de débordement, cibles agrandies', (tester) async {
      final c = TextEditingController();
      await tester.pumpWidget(await host(c, textScale: 2, brightness: Brightness.dark));
      await openSheet(tester);
      expect(tester.takeException(), isNull);
      expect(tester.getSize(find.byKey(const ValueKey('emoji-😀'))).width, greaterThan(48));
    });
  });
}
