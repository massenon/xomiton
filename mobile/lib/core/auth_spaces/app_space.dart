/// Les trois espaces de l'application unique (docs/PLAN_MOBILE_APP_UNIQUE.md §5.3, §6).
///
/// Un compte est TOUJOURS membre ; `peer` et `admin` s'y ajoutent selon ce que
/// le SERVEUR déclare dans `GET me` (`spaces`, API §1.5). Le client n'en déduit
/// rien d'autre : c'est de l'ergonomie, chaque route `pair/*` et `admin/*`
/// revérifie permission, policy et scope (un 403 est la seule vérité).
enum AppSpace {
  member('member', '/home'),
  peer('peer', '/pair/accueil'),
  admin('admin', '/admin/astreinte');

  const AppSpace(this.apiName, this.homeRoute);

  /// Valeur de `spaces[]` dans `GET me`.
  final String apiName;

  /// Écran d'arrivée de l'espace.
  final String homeRoute;

  /// Espace d'après une valeur de `spaces[]` ; valeur inconnue : `null`
  /// (ignorée, jamais d'espace inventé).
  static AppSpace? fromApi(Object? value) {
    for (final s in values) {
      if (s.apiName == value) return s;
    }
    return null;
  }

  /// Espace propriétaire d'un chemin : `/pair/**` → pair, `/admin/**` → admin ;
  /// tout le reste (onglets membre, écrans partagés, écrans publics) → `null`.
  static AppSpace? forPath(String path) {
    if (path == '/pair' || path.startsWith('/pair/')) return AppSpace.peer;
    if (path == '/admin' || path.startsWith('/admin/')) return AppSpace.admin;
    return null;
  }

  /// Ordre d'affichage stable : Membre, Pair, Admin.
  static List<AppSpace> sorted(Iterable<AppSpace> spaces) =>
      values.where(spaces.toSet().contains).toList();
}

/// Espaces compilés dans ce build : `--dart-define=SPACES=member,pair,admin`
/// (défaut : les trois ; `pair` et `peer` désignent le même espace). Une variante « membre seul » ou « Équipe » devient un
/// paramètre de build, sans réécriture (plan §8.1, option A réversible).
/// Membre est toujours présent : le SOS et les urgences ne dépendent d'aucun réglage.
const String kBuildSpacesDefine = String.fromEnvironment('SPACES', defaultValue: 'member,peer,admin');

Set<AppSpace> parseBuildSpaces(String define) => {
      AppSpace.member,
      for (final part in define.split(','))
        ?AppSpace.fromApi(part.trim() == 'pair' ? 'peer' : part.trim()),
    };
