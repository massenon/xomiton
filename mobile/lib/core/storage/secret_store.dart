import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Stockage sécurisé clé/valeur (Keystore Android, Keychain iOS).
abstract interface class SecretStore {
  Future<String?> read(String key);
  Future<void> write(String key, String value);
  Future<void> delete(String key);
}

class FlutterSecretStore implements SecretStore {
  FlutterSecretStore([FlutterSecureStorage? storage])
      : _s = storage ??
            const FlutterSecureStorage(
              aOptions: AndroidOptions(encryptedSharedPreferences: true),
              iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock_this_device),
            );
  final FlutterSecureStorage _s;

  @override
  Future<String?> read(String key) => _s.read(key: key);
  @override
  Future<void> write(String key, String value) => _s.write(key: key, value: value);
  @override
  Future<void> delete(String key) => _s.delete(key: key);
}

/// Pour les tests.
class MemorySecretStore implements SecretStore {
  final Map<String, String> data = {};
  @override
  Future<String?> read(String key) async => data[key];
  @override
  Future<void> write(String key, String value) async => data[key] = value;
  @override
  Future<void> delete(String key) async => data.remove(key);
}

abstract final class SecretKeys {
  static const token = 'sanctum_token';
  static const deviceToken = 'login_device_token';
  static const hiveKey = 'hive_encryption_key';

  /// Clé du coffre de l'espace Pair (distincte de celle des coffres Membre, P15-12).
  static const hiveKeyPeer = 'hive_encryption_key_peer';

  /// Code PIN local : enregistrement haché (sel + dérivation) et compteur
  /// d'essais. Jamais envoyé au serveur (Q37 b).
  static const pinRecord = 'pin_record';
  static const pinAttempts = 'pin_attempts';
}
