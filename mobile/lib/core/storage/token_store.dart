import 'secret_store.dart';

/// Jeton Sanctum : copie mémoire (lue de façon synchrone par l'intercepteur)
/// + persistance dans le stockage sécurisé.
class TokenStore {
  TokenStore(this._secrets, {String? initial}) : _token = initial;

  final SecretStore _secrets;
  String? _token;

  String? get token => _token;
  bool get hasToken => _token != null && _token!.isNotEmpty;

  static Future<TokenStore> load(SecretStore secrets) async =>
      TokenStore(secrets, initial: await secrets.read(SecretKeys.token));

  Future<void> save(String token) async {
    _token = token;
    await _secrets.write(SecretKeys.token, token);
  }

  Future<void> clear() async {
    _token = null;
    await _secrets.delete(SecretKeys.token);
  }

  /// `device_token` de l'OTP de connexion (appareil reconnu, Q13 b) : il reste
  /// après la déconnexion pour éviter un nouveau code à la reconnexion.
  Future<String?> readDeviceToken() => _secrets.read(SecretKeys.deviceToken);
  Future<void> saveDeviceToken(String value) => _secrets.write(SecretKeys.deviceToken, value);
  Future<void> clearDeviceToken() => _secrets.delete(SecretKeys.deviceToken);
}
