import 'dart:convert';

import '../data/ccc_models.dart';

/// Progression du manuel (cases « point vu »), **sur l'appareil seulement** :
/// jamais envoyée au serveur. Même forme et même clé que le web
/// (`web/resources/js/ccc/progress.ts`) : clé du module → rangs (à partir de 0)
/// des points vus, triés et sans doublon.
typedef SeenState = Map<String, List<int>>;

const String kCccProgressKey = 'xm_ccc_manuel_v1';

/// Clé de stockage par compte (appareil partagé).
String cccProgressStorageKey(int? account) =>
    account == null || account <= 0 ? kCccProgressKey : '$kCccProgressKey:$account';

/// Lecture tolérante : contenu absent, illisible ou corrompu → progression vide.
SeenState decodeSeen(String? raw) {
  if (raw == null || raw.isEmpty) return const {};
  try {
    final json = jsonDecode(raw);
    if (json is! Map) return const {};
    final out = <String, List<int>>{};
    json.forEach((k, v) {
      if (k is! String || v is! List) return;
      final ranks = {for (final r in v) if (r is int && r >= 0) r}.toList()..sort();
      if (ranks.isNotEmpty) out[k] = ranks;
    });
    return out;
  } catch (_) {
    return const {};
  }
}

String encodeSeen(SeenState seen) => jsonEncode(seen);

/// Points vus d'un module : seuls les rangs qui existent encore comptent
/// (un chapitre retiré par l'équipe ne fausse pas la progression).
int seenCount(SeenState seen, ManualModule module) =>
    (seen[module.key] ?? const <int>[]).where((r) => r < module.chapters.length).length;

bool isSeen(SeenState seen, ManualModule module, int rank) => (seen[module.key] ?? const <int>[]).contains(rank);

/// Pourcentage entier de [done] sur [total] (0 si rien à voir).
int percentOf(int done, int total) => total > 0 ? (100 * done / total).round() : 0;

({int done, int total, int percent}) totals(SeenState seen, List<ManualModule> modules) {
  final total = modules.fold<int>(0, (s, m) => s + m.chapters.length);
  final done = modules.fold<int>(0, (s, m) => s + seenCount(seen, m));
  return (done: done, total: total, percent: percentOf(done, total));
}

/// Coche ou décoche un point : nouvel état, l'ancien n'est pas modifié.
SeenState toggleSeen(SeenState seen, String key, int rank, {required bool on}) {
  final ranks = {...(seen[key] ?? const <int>[])};
  if (on) {
    ranks.add(rank);
  } else {
    ranks.remove(rank);
  }
  final next = {...seen};
  if (ranks.isEmpty) {
    next.remove(key);
  } else {
    next[key] = ranks.toList()..sort();
  }
  return next;
}
