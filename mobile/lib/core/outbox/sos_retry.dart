/// Renvoi d'un SOS refusé par la validation (422, RG-01 : un SOS part toujours).
/// Port de `web/resources/js/shared/sos-retry.js` : mêmes champs retirés, même
/// clé dérivée. Si le web et le mobile renvoient la même alerte, la clé et le
/// corps sont identiques et le serveur rejoue la réponse (ADR-32).
Map<String, dynamic> stripSosFields(
  Map<String, dynamic> payload,
  List<String> fields,
) {
  final lean = Map<String, dynamic>.from(payload);
  for (final field in fields) {
    final name = field.split('.').first;
    lean.remove(name);
    if (const ['lat', 'lng', 'accuracy'].contains(name)) {
      lean
        ..remove('lat')
        ..remove('lng')
        ..remove('accuracy');
    }
    if (name == 'zone_id') lean.remove('commune_id');
  }
  return lean;
}

int _fnv(String text, int seed) {
  var hash = (0x811c9dc5 ^ seed) & 0xffffffff;
  for (final unit in text.codeUnits) {
    hash ^= unit;
    hash = (hash * 0x01000193) & 0xffffffff;
  }
  return hash;
}

/// UUID (format v4) dérivé de la clé d'origine, identique à `derivedKey` du web.
String derivedSosKey(String key) {
  final source = '${key.toLowerCase()}:stripped';
  final hex = [
    0x9e37,
    0x85eb,
    0xc2b2,
    0x27d4,
  ].map((seed) => _fnv(source, seed).toRadixString(16).padLeft(8, '0')).join();
  final variant = ((int.parse(hex[16], radix: 16) & 0x3) | 0x8).toRadixString(
    16,
  );
  return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-4${hex.substring(13, 16)}-'
      '$variant${hex.substring(17, 20)}-${hex.substring(20, 32)}';
}
