/// Règles du mot de passe (docs/API.md §2 : ≥ 10 caractères, majuscule,
/// minuscule, chiffre ; le symbole ne compte que pour « Excellent »).
class PasswordStrength {
  PasswordStrength(String pw)
      : length = pw.length >= 10,
        cases = RegExp(r'[A-Z]').hasMatch(pw) && RegExp(r'[a-z]').hasMatch(pw),
        digit = RegExp(r'\d').hasMatch(pw),
        symbol = RegExp(r'[^A-Za-z0-9]').hasMatch(pw),
        empty = pw.isEmpty;

  final bool length;
  final bool cases;
  final bool digit;
  final bool symbol;
  final bool empty;

  int get score => empty ? 0 : [length, cases, digit, symbol].where((x) => x).length;

  /// Accepté par l'API.
  bool get meetsPolicy => length && cases && digit;

  /// Clé ARB du libellé (`auth_password_strength_*`).
  String get labelKey {
    if (empty) return 'auth_password_strength_empty';
    if (!length && score <= 1) return 'auth_password_strength_too_short';
    return switch (score) {
      <= 1 => 'auth_password_strength_weak',
      2 => 'auth_password_strength_medium',
      3 => 'auth_password_strength_strong',
      _ => 'auth_password_strength_excellent',
    };
  }
}
