import '../../../core/auth_spaces/app_space.dart';

/// Compte connecté (vue « moi », docs/API.md §1.5).
///
/// `roles` et `spaces` sont ceux du SERVEUR (rôles cumulables). Rien n'est
/// déduit du client : sans `spaces`, le compte n'a que l'espace Membre.
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
    this.roles = const ['membre'],
    this.permissions = const [],
    this.spaces = const [AppSpace.member],
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

  /// Tous les rôles du compte (`membre` toujours présent).
  final List<String> roles;
  final List<String> permissions;

  /// Espaces proposés par le serveur (`member` toujours présent), ordre stable.
  final List<AppSpace> spaces;
  final Map<String, dynamic> raw;

  bool get isPeer => roles.contains('pair');

  bool hasSpace(AppSpace s) => spaces.contains(s);

  /// Plus d'un espace : le sélecteur d'espace, la pastille et la carte « Mes espaces » apparaissent.
  bool get hasSeveralSpaces => spaces.length > 1;

  bool can(String permission) => permissions.contains(permission);

  factory AppUser.fromJson(Map<String, dynamic> j) {
    final profile = j['profile'];
    final role = (j['role'] as String?) ?? 'membre';
    List<String> strings(Object? v) =>
        v is List ? [for (final e in v) if (e is String) e] : const [];
    final roles = {'membre', ...strings(j['roles']), if (j['roles'] is! List) role};
    final spaces = AppSpace.sorted({
      AppSpace.member,
      for (final e in (j['spaces'] is List ? j['spaces'] as List : const []))
        ?AppSpace.fromApi(e),
    });
    return AppUser(
      id: (j['id'] as num?)?.toInt() ?? 0,
      pseudo: (j['pseudo'] as String?) ?? '',
      role: role,
      status: (j['status'] as String?) ?? 'actif',
      emailVerified: j['email_verified'] == true,
      email: j['email'] as String?,
      avatarUrl: j['avatar_url'] as String?,
      pendingValidation: j['pending_validation'] == true,
      completion: profile is Map ? ((profile['completion'] as num?)?.toInt() ?? 0) : 0,
      roles: roles.toList(),
      permissions: strings(j['permissions']),
      spaces: spaces,
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
          'roles': roles,
          'spaces': [for (final x in spaces) x.apiName],
          'permissions': permissions,
        };
}
