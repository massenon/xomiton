/// Compte connecté (vue « moi », docs/API.md §1.5).
class AppUser {
  const AppUser({
    required this.id,
    required this.pseudo,
    required this.role,
    required this.status,
    required this.emailVerified,
    this.email,
    this.avatarUrl,
    this.pendingValidation = false,
    this.completion = 0,
    this.raw = const {},
  });

  final int id;
  final String pseudo;
  final String role;
  final String status;
  final bool emailVerified;
  final String? email;
  final String? avatarUrl;
  final bool pendingValidation;
  final int completion;
  final Map<String, dynamic> raw;

  bool get isPeer => role == 'pair';

  factory AppUser.fromJson(Map<String, dynamic> j) {
    final profile = j['profile'];
    return AppUser(
      id: (j['id'] as num?)?.toInt() ?? 0,
      pseudo: (j['pseudo'] as String?) ?? '',
      role: (j['role'] as String?) ?? 'membre',
      status: (j['status'] as String?) ?? 'actif',
      emailVerified: j['email_verified'] == true,
      email: j['email'] as String?,
      avatarUrl: j['avatar_url'] as String?,
      pendingValidation: j['pending_validation'] == true,
      completion: profile is Map ? ((profile['completion'] as num?)?.toInt() ?? 0) : 0,
      raw: j,
    );
  }

  Map<String, dynamic> toJson() => raw.isNotEmpty
      ? raw
      : {
          'id': id,
          'pseudo': pseudo,
          'role': role,
          'status': status,
          'email_verified': emailVerified,
          'email': email,
          'avatar_url': avatarUrl,
          'pending_validation': pendingValidation,
        };
}
