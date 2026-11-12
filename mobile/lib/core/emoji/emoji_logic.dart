import 'package:flutter/widgets.dart' show TextEditingController, TextEditingValue, TextSelection;
import 'package:flutter/services.dart';

import 'emoji_data.dart';

/// Logique du sélecteur d'émojis, sans interface (testée seule) : recherche française sans accents, teintes de
/// peau, insertion à la position du curseur, longueur en points de code (règle du serveur : `max:` de Laravel =
/// `mb_strlen`). Même comportement que `web/resources/js/emoji/emoji.ts`.

const List<String> kEmojiTones = ['', '\u{1F3FB}', '\u{1F3FC}', '\u{1F3FD}', '\u{1F3FE}', '\u{1F3FF}'];
const List<String> kEmojiToneKeys = ['default', 'light', 'medium_light', 'medium', 'medium_dark', 'dark'];
const int kEmojiRecentsMax = 24;

/// Minuscules, sans accents ni ligatures : « Cœur » et « coeur » se valent.
String normalizeEmojiText(String text) {
  const from = 'àáâãäåçèéêëìíîïñòóôõöùúûüýÿ';
  const to = 'aaaaaaceeeeiiiinooooouuuuyy';
  final out = StringBuffer();
  for (final r in text.toLowerCase().replaceAll('œ', 'oe').replaceAll('æ', 'ae').replaceAll(RegExp('[’\']'), ' ').runes) {
    final ch = String.fromCharCode(r);
    final at = from.indexOf(ch);
    out.write(at >= 0 ? to[at] : ch);
  }
  return out.toString().replaceAll(RegExp(r'\s+'), ' ').trim();
}

/// Émoji sans teinte ni sélecteur de variante : retrouve l'entrée d'un récent teinté.
String emojiBaseKey(String emoji) => String.fromCharCodes(
  emoji.runes.where((r) => r != 0xFE0F && !(r >= 0x1F3FB && r <= 0x1F3FF)),
);

/// Modificateur après le premier point de code (le sélecteur de variante qui le suit est retiré) : les séquences ZWJ
/// restent valides.
String applyEmojiTone(String emoji, int tone) {
  if (tone <= 0 || tone >= kEmojiTones.length) return emoji;
  final runes = emoji.runes.toList();
  final rest = runes.sublist(1);
  if (rest.isNotEmpty && rest.first == 0xFE0F) rest.removeAt(0);
  return String.fromCharCodes([runes.first, ...kEmojiTones[tone].runes, ...rest]);
}

int codePointLength(String value) => value.runes.length;

class _Indexed {
  _Indexed(this.entry, this.haystack);
  final EmojiEntry entry;
  final String haystack;
}

List<_Indexed>? _index;

List<_Indexed> _build() => _index ??= [
  for (final c in kEmojiCategories)
    for (final e in c.emojis) _Indexed(e, normalizeEmojiText('${e.name} ${e.keywords}')),
];

/// Tous les mots de la requête doivent se retrouver dans le nom ou les mots-clés ; début de mot d'abord.
List<EmojiEntry> searchEmojis(String query) {
  final words = normalizeEmojiText(query).split(' ').where((w) => w.isNotEmpty).toList();
  if (words.isEmpty) return const [];
  final scored = <(EmojiEntry, int)>[];
  for (final item in _build()) {
    var score = 0;
    var ok = true;
    for (final w in words) {
      final at = item.haystack.indexOf(w);
      if (at == -1) {
        ok = false;
        break;
      }
      score += (at == 0 || item.haystack[at - 1] == ' ') ? 0 : 1;
    }
    if (ok) scored.add((item.entry, score));
  }
  scored.sort((a, b) => a.$2.compareTo(b.$2));
  return [for (final s in scored) s.$1];
}

/// Nom français d'un émoji (récent compris), ou chaîne vide s'il n'est pas dans la liste.
final Map<String, String> _names = {
  for (final c in kEmojiCategories)
    for (final e in c.emojis) emojiBaseKey(e.char): e.name,
};
String emojiNameOf(String emoji) => _names[emojiBaseKey(emoji)] ?? '';

/// Insère à la position du curseur (ou remplace la sélection) et place le curseur après l'émoji. Sans sélection
/// valide, ajoute à la fin. Refuse sans rien changer si la longueur en points de code dépasserait [maxLength].
bool insertEmoji(TextEditingController controller, String emoji, {int? maxLength}) {
  final text = controller.text;
  final sel = controller.selection;
  final start = sel.isValid ? sel.start.clamp(0, text.length) : text.length;
  final end = sel.isValid ? sel.end.clamp(start, text.length) : text.length;
  final next = codePointLength(text.substring(0, start)) + codePointLength(emoji) + codePointLength(text.substring(end));
  if (maxLength != null && maxLength > 0 && next > maxLength) return false;
  controller.value = TextEditingValue(
    text: text.replaceRange(start, end, emoji),
    selection: TextSelection.collapsed(offset: start + emoji.length),
  );
  return true;
}

/// Limite en points de code pour la frappe : le `maxLength` de Flutter compte des graphèmes (un drapeau arc-en-ciel
/// = 1), le serveur des points de code (= 4) ; ce filtre aligne les deux.
class CodePointLimitFormatter extends TextInputFormatter {
  const CodePointLimitFormatter(this.max);
  final int max;

  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) {
    if (codePointLength(newValue.text) <= max) return newValue;
    // Ancienne valeur déjà trop longue (collage de départ) : on garde la plus courte des deux ; sinon on refuse.
    return codePointLength(oldValue.text) <= max ? oldValue : newValue;
  }
}
