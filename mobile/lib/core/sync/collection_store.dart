import 'dart:convert';

import '../storage/local_db.dart';

/// Lignes des collections de `sync/pull` (docs/API.md §21.3), gardées en JSON
/// dans un coffre chiffré : `c:<collection>:<id>`. Le coffre de l'espace Pair
/// (clé propre) porte les collections du pair ; le coffre public porte les
/// collections communes (`ns` distinct pour ne jamais croiser le cache d'API).
class CollectionStore {
  CollectionStore(this.box, {this.ns = ''});
  final StringBox box;
  final String ns;

  String _p(String col) => '${ns}c:$col:';
  String _k(String col, Object id) => '${_p(col)}$id';

  List<Map<String, dynamic>> rows(String col) {
    final prefix = _p(col);
    final out = <Map<String, dynamic>>[];
    for (final k in box.keys) {
      if (!k.startsWith(prefix)) continue;
      final m = _decode(box.get(k));
      if (m != null) out.add(m);
    }
    return out;
  }

  Map<String, dynamic>? row(String col, Object id) =>
      _decode(box.get(_k(col, id)));

  Future<void> put(String col, Object id, Map<String, dynamic> row) =>
      box.put(_k(col, id), jsonEncode(row));

  Future<void> remove(String col, Object id) => box.delete(_k(col, id));

  Future<void> clearCollection(String col) async {
    final prefix = _p(col);
    for (final k in box.keys.toList()) {
      if (k.startsWith(prefix)) await box.delete(k);
    }
  }

  // --- valeurs diverses (curseurs, règles, propriétaire…) ---------------------

  Map<String, dynamic>? json(String key) => _decode(box.get('$ns$key'));
  Future<void> putJson(String key, Map<String, dynamic> v) =>
      box.put('$ns$key', jsonEncode(v));
  String? text(String key) => box.get('$ns$key');
  Future<void> putText(String key, String v) => box.put('$ns$key', v);
  Future<void> delete(String key) => box.delete('$ns$key');

  static Map<String, dynamic>? _decode(String? raw) {
    if (raw == null) return null;
    try {
      final v = jsonDecode(raw);
      return v is Map ? Map<String, dynamic>.from(v) : null;
    } catch (_) {
      return null;
    }
  }
}
