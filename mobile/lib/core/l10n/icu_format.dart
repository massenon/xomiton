/// Formateur ICU minimal pour les messages de l'ARB généré par
/// `php artisan lang:export` : `{nom}` et `{n, plural, =0{…} =1{…} other{…}}`
/// (seul sous-ensemble utilisé par web/lang/fr).
String formatIcu(String message, Map<String, Object?> args) {
  final out = StringBuffer();
  var i = 0;
  while (i < message.length) {
    final ch = message[i];
    if (ch != '{') {
      out.write(ch);
      i++;
      continue;
    }
    final end = _matchingBrace(message, i);
    if (end < 0) {
      out.write(message.substring(i));
      break;
    }
    out.write(_expand(message.substring(i + 1, end), args));
    i = end + 1;
  }
  return out.toString();
}

int _matchingBrace(String s, int open) {
  var depth = 0;
  for (var i = open; i < s.length; i++) {
    if (s[i] == '{') depth++;
    if (s[i] == '}') {
      depth--;
      if (depth == 0) return i;
    }
  }
  return -1;
}

String _expand(String inner, Map<String, Object?> args) {
  final comma = inner.indexOf(',');
  if (comma < 0) {
    final v = args[inner.trim()];
    return v == null ? '{${inner.trim()}}' : v.toString();
  }
  final name = inner.substring(0, comma).trim();
  final rest = inner.substring(comma + 1);
  final comma2 = rest.indexOf(',');
  final type = (comma2 < 0 ? rest : rest.substring(0, comma2)).trim();
  final cases = comma2 < 0 ? '' : rest.substring(comma2 + 1);
  final value = args[name];
  final parsed = _parseCases(cases);
  String? body;
  if (type == 'plural') {
    final n = value is num ? value : num.tryParse('$value') ?? 0;
    body = parsed['=${n is double && n == n.roundToDouble() ? n.toInt() : n}'];
    body ??= (n == 0 || n == 1) ? (parsed['one'] ?? parsed['other']) : parsed['other'];
    body ??= parsed['other'];
    return formatIcu((body ?? '').replaceAll('#', '$n'), args);
  }
  if (type == 'select') {
    body = parsed['$value'] ?? parsed['other'];
    return formatIcu(body ?? '', args);
  }
  return value?.toString() ?? '';
}

Map<String, String> _parseCases(String s) {
  final map = <String, String>{};
  var i = 0;
  while (i < s.length) {
    while (i < s.length && s[i].trim().isEmpty) {
      i++;
    }
    final start = i;
    while (i < s.length && s[i] != '{') {
      i++;
    }
    if (i >= s.length) break;
    final selector = s.substring(start, i).trim();
    final end = _matchingBrace(s, i);
    if (end < 0) break;
    map[selector] = s.substring(i + 1, end);
    i = end + 1;
  }
  return map;
}
