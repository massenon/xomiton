import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/application/session_controller.dart';
import '../providers/core_providers.dart';
import 'app_space.dart';

/// Espaces compilés dans ce build (`--dart-define=SPACES`), surchargeable dans les tests.
final buildSpacesProvider = Provider<Set<AppSpace>>((ref) => parseBuildSpaces(kBuildSpacesDefine));

/// Espaces du compte, tels que le serveur les a déclarés dans `GET me`, limités
/// à ceux du build (`sessionProvider` → `spacesProvider` → `activeSpaceProvider`,
/// plan §5.4). Vide sans session ; `member` seul pour un compte simple.
final spacesProvider = Provider<List<AppSpace>>((ref) {
  final fromServer = ref.watch(sessionProvider.select((s) => s.spaces));
  final build = ref.watch(buildSpacesProvider);
  return [for (final s in fromServer) if (build.contains(s)) s];
});

/// Plus d'un espace : sélecteur, pastille d'en-tête, carte « Mes espaces » et
/// onglet « Espace pair » / « Admin » n'existent que dans ce cas.
final hasSeveralSpacesProvider = Provider<bool>((ref) => ref.watch(spacesProvider).length > 1);

/// Espace actif, mémorisé (jamais imposé : toujours validé contre `spacesProvider`).
class ActiveSpaceController extends Notifier<AppSpace> {
  @override
  AppSpace build() {
    final spaces = ref.watch(spacesProvider);
    final saved = AppSpace.fromApi(ref.read(sharedPreferencesProvider).getString(kPrefActiveSpace));
    return (saved != null && spaces.contains(saved)) ? saved : AppSpace.member;
  }

  /// Active [space] s'il fait partie des espaces du compte ; sinon ne fait rien
  /// (`false`) : un espace non déclaré par le serveur n'est jamais atteignable.
  bool select(AppSpace space) {
    if (!ref.read(spacesProvider).contains(space)) return false;
    if (state != space) state = space;
    ref.read(sharedPreferencesProvider).setString(kPrefActiveSpace, space.apiName);
    return true;
  }
}

final activeSpaceProvider = NotifierProvider<ActiveSpaceController, AppSpace>(ActiveSpaceController.new);

/// Un autre espace que Membre est-il ouvert au compte ? (onglet de la barre membre)
final otherSpacesProvider = Provider<List<AppSpace>>(
  (ref) => [for (final s in ref.watch(spacesProvider)) if (s != AppSpace.member) s],
);

/// Choix explicite d'un espace (écran de choix, feuille, onglet) : active,
/// ferme le sélecteur d'après connexion et renvoie la route d'arrivée.
String? chooseSpace(WidgetRef ref, AppSpace space) {
  if (!ref.read(activeSpaceProvider.notifier).select(space)) return null;
  ref.read(sessionProvider.notifier).markSpaceChosen();
  return space.homeRoute;
}
