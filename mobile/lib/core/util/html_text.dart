/// Convertit le HTML nettoyé des fiches (`body`, `sections[].body`) en texte
/// lisible : paragraphes, listes à puces, entités courantes. Pas de rendu
/// riche en v1 (le serveur ne renvoie que des balises de texte).
String htmlToText(String? html) {
  if (html == null || html.isEmpty) return '';
  var s = html
      .replaceAll(RegExp(r'<\s*br\s*/?>', caseSensitive: false), '\n')
      .replaceAll(RegExp(r'</\s*(p|div|h[1-6]|ul|ol|blockquote)\s*>', caseSensitive: false), '\n\n')
      .replaceAll(RegExp(r'<\s*li[^>]*>', caseSensitive: false), '• ')
      .replaceAll(RegExp(r'</\s*li\s*>', caseSensitive: false), '\n')
      .replaceAll(RegExp(r'<[^>]+>'), '');
  const entities = {
    '&nbsp;': ' ',
    '&amp;': '&',
    '&lt;': '<',
    '&gt;': '>',
    '&quot;': '"',
    '&#39;': "'",
    '&apos;': "'",
    '&rsquo;': '’',
    '&lsquo;': '‘',
    '&laquo;': '«',
    '&raquo;': '»',
    '&hellip;': '…',
  };
  entities.forEach((k, v) => s = s.replaceAll(k, v));
  s = s.replaceAllMapped(RegExp(r'&#(\d+);'), (m) => String.fromCharCode(int.parse(m[1]!)));
  return s.replaceAll(RegExp(r'[ \t]+\n'), '\n').replaceAll(RegExp(r'\n{3,}'), '\n\n').trim();
}
