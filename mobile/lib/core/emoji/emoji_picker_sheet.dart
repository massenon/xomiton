import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/semantics.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../app/theme/xm_tokens.dart';
import '../l10n/app_strings.dart';
import '../providers/core_providers.dart';
import 'emoji_data.dart';
import 'emoji_logic.dart';

/// Récents et teinte de peau : préférences de l'appareil, jamais envoyées au serveur.
const String kEmojiRecentsPref = 'xm_emoji_recent_v1';
const String kEmojiTonePref = 'xm_emoji_tone_v1';

List<String> readEmojiRecents(SharedPreferences prefs) {
  try {
    return (prefs.getStringList(kEmojiRecentsPref) ?? const <String>[])
        .where((e) => e.isNotEmpty && e.length <= 32)
        .take(kEmojiRecentsMax)
        .toList();
  } catch (_) {
    return const [];
  }
}

Future<List<String>> pushEmojiRecent(SharedPreferences prefs, String emoji) async {
  final list = [emoji, ...readEmojiRecents(prefs).where((e) => e != emoji)].take(kEmojiRecentsMax).toList();
  try {
    await prefs.setStringList(kEmojiRecentsPref, list);
  } catch (_) {
    // Écriture refusée : la liste vit le temps de l'écran.
  }
  return list;
}

int readEmojiTone(SharedPreferences prefs) {
  try {
    final v = prefs.getInt(kEmojiTonePref) ?? 0;
    return v >= 0 && v < kEmojiTones.length ? v : 0;
  } catch (_) {
    return 0;
  }
}

/// Ouvre le sélecteur (feuille modale) ; l'émoji choisi est inséré dans [controller] à la position du curseur.
Future<void> showEmojiPickerSheet(
  BuildContext context, {
  required TextEditingController controller,
  int? maxLength,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    showDragHandle: true,
    builder: (_) => EmojiPickerSheet(controller: controller, maxLength: maxLength),
  );
}

/// Bouton « Ajouter un émoji » (48 dp) à poser dans un champ de saisie (suffixIcon) ou à côté.
class EmojiButton extends StatelessWidget {
  const EmojiButton({super.key, required this.controller, this.maxLength});

  final TextEditingController controller;
  final int? maxLength;

  @override
  Widget build(BuildContext context) => IconButton(
    key: const ValueKey('emoji-button'),
    tooltip: context.t('emoji_trigger'),
    icon: const Icon(Icons.emoji_emotions_outlined),
    onPressed: () => showEmojiPickerSheet(context, controller: controller, maxLength: maxLength),
  );
}

const String _recents = 'recents';

class _Item {
  const _Item(this.char, this.name, this.label);
  final String char;
  final String name;
  final String label;
}

/// Feuille de sélection : onglets de catégories, recherche française, grille d'émojis (Semantics = nom français,
/// cibles d'au moins 48 dp, agrandies avec la taille de police du système), récents, teinte de peau.
class EmojiPickerSheet extends ConsumerStatefulWidget {
  const EmojiPickerSheet({super.key, required this.controller, this.maxLength});

  final TextEditingController controller;
  final int? maxLength;

  @override
  ConsumerState<EmojiPickerSheet> createState() => _EmojiPickerSheetState();
}

class _EmojiPickerSheetState extends ConsumerState<EmojiPickerSheet> {
  final _query = TextEditingController();
  late List<String> _recentList;
  late int _tone;
  late String _tab;
  String? _limit;

  @override
  void initState() {
    super.initState();
    final prefs = ref.read(sharedPreferencesProvider);
    _recentList = readEmojiRecents(prefs);
    _tone = readEmojiTone(prefs);
    _tab = _recentList.isNotEmpty ? _recents : kEmojiCategories.first.id;
  }

  @override
  void dispose() {
    _query.dispose();
    super.dispose();
  }

  bool get _searching => normalizeEmojiText(_query.text).isNotEmpty;

  _Item _toned(EmojiEntry e) {
    final char = e.tone ? applyEmojiTone(e.char, _tone) : e.char;
    final label = e.tone && _tone > 0
        ? context.t('emoji_with_tone', {
            'name': e.name,
            'tone': context.t('emoji_tones_${kEmojiToneKeys[_tone]}').toLowerCase(),
          })
        : e.name;
    return _Item(char, e.name, label);
  }

  List<_Item> get _items {
    if (_searching) return searchEmojis(_query.text).map(_toned).toList();
    if (_tab == _recents) {
      return [
        for (final c in _recentList)
          _Item(c, emojiNameOf(c), emojiNameOf(c).isEmpty ? c : emojiNameOf(c)),
      ];
    }
    final cat = kEmojiCategories.firstWhere((c) => c.id == _tab, orElse: () => kEmojiCategories.first);
    return cat.emojis.map(_toned).toList();
  }

  Future<void> _choose(_Item item) async {
    final ok = insertEmoji(widget.controller, item.char, maxLength: widget.maxLength);
    if (!ok) {
      final message = context.t('emoji_limit_reached');
      setState(() => _limit = message);
      return;
    }
    final prefs = ref.read(sharedPreferencesProvider);
    final message = context.t('emoji_added', {'name': item.name.isEmpty ? item.char : item.name});
    final view = View.of(context);
    final dir = Directionality.of(context);
    final nav = Navigator.of(context);
    unawaited(SemanticsService.sendAnnouncement(view, message, dir));
    nav.pop();
    await pushEmojiRecent(prefs, item.char);
  }

  Future<void> _setTone(int value) async {
    setState(() => _tone = value);
    try {
      await ref.read(sharedPreferencesProvider).setInt(kEmojiTonePref, value);
    } catch (_) {
      // Voir pushEmojiRecent.
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scaled = MediaQuery.textScalerOf(context).scale(26);
    final cell = scaled + 16 < 48 ? 48.0 : scaled + 16;
    final items = _items;
    final title = _searching
        ? context.t('emoji_results')
        : _tab == _recents
        ? context.t('emoji_recents')
        : kEmojiCategories.firstWhere((c) => c.id == _tab).label;
    final height = MediaQuery.sizeOf(context).height * 0.7;
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SizedBox(
        height: height,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: XmSpace.gutter),
              child: Row(
                children: [
                  Expanded(
                    child: Semantics(
                      header: true,
                      child: Text(context.t('emoji_title'), style: theme.textTheme.titleMedium),
                    ),
                  ),
                  IconButton(
                    tooltip: context.t('emoji_close'),
                    icon: const Icon(Icons.close),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: XmSpace.gutter),
              child: TextField(
                key: const ValueKey('emoji-search'),
                controller: _query,
                onChanged: (_) => setState(() {}),
                textInputAction: TextInputAction.search,
                decoration: InputDecoration(
                  labelText: context.t('emoji_search_label'),
                  hintText: context.t('emoji_search_placeholder'),
                  prefixIcon: const Icon(Icons.search),
                  suffixIcon: _query.text.isEmpty
                      ? null
                      : IconButton(
                          tooltip: context.t('emoji_search_clear'),
                          icon: const Icon(Icons.close),
                          onPressed: () => setState(_query.clear),
                        ),
                ),
              ),
            ),
            if (!_searching)
              Semantics(
                container: true,
                label: context.t('emoji_categories_label'),
                child: SizedBox(
                  height: cell,
                  child: ListView(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: XmSpace.s2),
                    children: [
                      _tabButton(_recents, context.t('emoji_recents'), '🕘', cell),
                      for (final c in kEmojiCategories) _tabButton(c.id, c.label, c.icon, cell),
                    ],
                  ),
                ),
              ),
            Padding(
              padding: const EdgeInsets.fromLTRB(XmSpace.gutter, XmSpace.s2, XmSpace.gutter, 0),
              child: Align(
                alignment: AlignmentDirectional.centerStart,
                child: Semantics(header: true, child: Text(title, style: theme.textTheme.labelLarge)),
              ),
            ),
            if (_limit != null)
              Semantics(
                liveRegion: true,
                child: Padding(
                  padding: const EdgeInsets.all(XmSpace.s2),
                  child: Text(_limit!, style: TextStyle(color: theme.colorScheme.error)),
                ),
              ),
            Expanded(
              child: items.isEmpty
                  ? Padding(
                      padding: const EdgeInsets.all(XmSpace.s5),
                      child: Text(
                        _searching ? context.t('emoji_no_results') : context.t('emoji_recents_empty'),
                        textAlign: TextAlign.center,
                        style: theme.textTheme.bodyMedium,
                      ),
                    )
                  : GridView.builder(
                      key: const ValueKey('emoji-grid'),
                      padding: const EdgeInsets.symmetric(horizontal: XmSpace.s2),
                      gridDelegate: SliverGridDelegateWithMaxCrossAxisExtent(maxCrossAxisExtent: cell, mainAxisExtent: cell),
                      itemCount: items.length,
                      itemBuilder: (_, i) => _cell(items[i]),
                    ),
            ),
            Semantics(
              container: true,
              label: context.t('emoji_tone_label'),
              child: SizedBox(
                height: cell,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: XmSpace.s2),
                  children: [for (var i = 0; i < kEmojiTones.length; i++) _toneButton(i, cell)],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _cell(_Item item) => Semantics(
    button: true,
    label: item.label,
    excludeSemantics: true,
    onTap: () => _choose(item),
    child: InkWell(
      key: ValueKey('emoji-${item.char}'),
      borderRadius: BorderRadius.circular(XmSpace.radiusMd),
      onTap: () => _choose(item),
      child: Center(child: Text(item.char, style: const TextStyle(fontSize: 26))),
    ),
  );

  Widget _tabButton(String id, String label, String icon, double size) {
    final selected = _tab == id;
    final color = Theme.of(context).colorScheme.primary;
    return Semantics(
      button: true,
      selected: selected,
      label: label,
      excludeSemantics: true,
      onTap: () => setState(() => _tab = id),
      child: InkWell(
        key: ValueKey('emoji-tab-$id'),
        onTap: () => setState(() => _tab = id),
        child: Container(
          width: size,
          height: size,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            border: Border(bottom: BorderSide(color: selected ? color : Colors.transparent, width: 3)),
          ),
          child: Text(icon, style: const TextStyle(fontSize: 22)),
        ),
      ),
    );
  }

  Widget _toneButton(int value, double size) {
    final selected = _tone == value;
    final scheme = Theme.of(context).colorScheme;
    final label = context.t('emoji_tones_${kEmojiToneKeys[value]}');
    return Semantics(
      button: true,
      selected: selected,
      label: label,
      excludeSemantics: true,
      onTap: () => _setTone(value),
      child: InkWell(
        key: ValueKey('emoji-tone-$value'),
        borderRadius: BorderRadius.circular(XmSpace.radiusMd),
        onTap: () => _setTone(value),
        child: Container(
          width: size,
          height: size,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(XmSpace.radiusMd),
            border: Border.all(color: selected ? scheme.primary : Colors.transparent, width: 2),
          ),
          child: Text(applyEmojiTone('✋', value), style: const TextStyle(fontSize: 20)),
        ),
      ),
    );
  }
}
