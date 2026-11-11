import 'dart:convert';
import 'dart:math';
import 'dart:typed_data';

import 'package:crypto/crypto.dart';

import '../../../core/storage/secret_store.dart';

/// Résultat d'une vérification de code.
class PinCheck {
  const PinCheck({required this.ok, required this.remaining});
  final bool ok;

  /// Essais restants (0 = épuisés : reconnexion mot de passe + OTP exigée).
  final int remaining;
  bool get exhausted => !ok && remaining <= 0;
}

/// Code PIN de verrouillage de l'appareil (Q37 b).
///
/// Le code ne quitte JAMAIS le téléphone : seul `pin_enabled` est synchronisé
/// avec `PATCH me/security`. Il est stocké dans `flutter_secure_storage` sous
/// forme d'un enregistrement `{sel, itérations, empreinte}` (PBKDF2-HMAC-SHA256),
/// comparé à temps constant. Le compteur d'essais est persistant (relancer
/// l'application ne le remet pas à zéro).
class PinService {
  PinService(
    this._secrets, {
    this.iterations = 60000,
    this.maxAttempts = 5,
    Random? random,
  }) : _random = random ?? Random.secure();

  final SecretStore _secrets;
  final int iterations;
  final int maxAttempts;
  final Random _random;

  static final _format = RegExp(r'^\d{4}$');
  static bool isValid(String pin) => _format.hasMatch(pin);

  Future<bool> isSet() async => (await _secrets.read(SecretKeys.pinRecord)) != null;

  Future<int> attempts() async =>
      int.tryParse(await _secrets.read(SecretKeys.pinAttempts) ?? '') ?? 0;

  Future<void> set(String pin) async {
    if (!isValid(pin)) throw ArgumentError('Le code doit comporter 4 chiffres.');
    final salt = Uint8List.fromList(List<int>.generate(16, (_) => _random.nextInt(256)));
    final hash = derive(pin, salt, iterations);
    await _secrets.write(
      SecretKeys.pinRecord,
      jsonEncode({
        'v': 1,
        'salt': base64Url.encode(salt),
        'iter': iterations,
        'hash': base64Url.encode(hash),
      }),
    );
    await _secrets.write(SecretKeys.pinAttempts, '0');
  }

  Future<void> clear() async {
    await _secrets.delete(SecretKeys.pinRecord);
    await _secrets.delete(SecretKeys.pinAttempts);
  }

  Future<PinCheck> verify(String pin) async {
    final raw = await _secrets.read(SecretKeys.pinRecord);
    if (raw == null) return const PinCheck(ok: true, remaining: 0);
    final used = await attempts();
    if (used >= maxAttempts) return const PinCheck(ok: false, remaining: 0);
    // L'essai est compté AVANT la comparaison : tuer l'application entre les deux ne le fait pas gagner.
    await _secrets.write(SecretKeys.pinAttempts, '${used + 1}');
    final rec = jsonDecode(raw) as Map<String, dynamic>;
    final expected = base64Url.decode(rec['hash'] as String);
    final actual = isValid(pin)
        ? derive(pin, base64Url.decode(rec['salt'] as String), (rec['iter'] as num).toInt())
        : Uint8List(expected.length);
    final ok = constantTimeEquals(expected, actual) && isValid(pin);
    if (ok) {
      await _secrets.write(SecretKeys.pinAttempts, '0');
      return const PinCheck(ok: true, remaining: 0);
    }
    return PinCheck(ok: false, remaining: maxAttempts - used - 1);
  }

  /// PBKDF2-HMAC-SHA256, 32 octets (un seul bloc).
  static Uint8List derive(String pin, List<int> salt, int iterations) {
    final hmac = Hmac(sha256, utf8.encode(pin));
    var u = hmac.convert([...salt, 0, 0, 0, 1]).bytes;
    final t = List<int>.from(u);
    for (var i = 1; i < iterations; i++) {
      u = hmac.convert(u).bytes;
      for (var j = 0; j < t.length; j++) {
        t[j] ^= u[j];
      }
    }
    return Uint8List.fromList(t);
  }

  /// Comparaison sans sortie anticipée.
  static bool constantTimeEquals(List<int> a, List<int> b) {
    var diff = a.length ^ b.length;
    final n = min(a.length, b.length);
    for (var i = 0; i < n; i++) {
      diff |= a[i] ^ b[i];
    }
    return diff == 0;
  }
}
