import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/core_providers.dart';
import '../../auth/application/session_controller.dart';
import '../data/ccc_api.dart';
import '../data/ccc_models.dart';
import 'ccc_progress.dart';

/// Outils publiés. Relus à chaque ouverture (réponse `no-store`) : un outil
/// masqué par l'équipe disparaît.
final cccCatalogProvider = FutureProvider.autoDispose<CccCatalog>((ref) => ref.watch(cccApiProvider).catalog());

/// « Mode présentation » : comme sur le web (`CccPageController`), vrai pour un
/// compte **actif** qui a la permission `peer_space.access` (compte pair, ou
/// administration), même règle que l'espace pair. Affiche seulement le guide de
/// l'animateur·rice ; le serveur n'en dépend pas.
final cccPresentationProvider = Provider<bool>((ref) {
  final user = ref.watch(sessionProvider.select((s) => s.user));
  return user != null && user.status == 'actif' && user.can('peer_space.access');
});

class CccProgressState {
  const CccProgressState({this.seen = const {}, this.persisted = true});
  final SeenState seen;

  /// Faux si l'appareil a refusé d'écrire : la progression vit alors en mémoire seulement.
  final bool persisted;
}

/// Progression du manuel, par compte, dans les préférences de l'appareil.
class CccProgressController extends Notifier<CccProgressState> {
  late String _storageKey;

  @override
  CccProgressState build() {
    final account = ref.watch(sessionProvider.select((s) => s.user?.id));
    _storageKey = cccProgressStorageKey(account);
    try {
      return CccProgressState(seen: decodeSeen(ref.read(sharedPreferencesProvider).getString(_storageKey)));
    } catch (_) {
      return const CccProgressState(persisted: false);
    }
  }

  Future<void> toggle(String moduleKey, int rank, {required bool on}) =>
      _write(toggleSeen(state.seen, moduleKey, rank, on: on));

  Future<void> reset() => _write(const {});

  Future<void> _write(SeenState next) async {
    state = CccProgressState(seen: next, persisted: state.persisted);
    var ok = false;
    try {
      final prefs = ref.read(sharedPreferencesProvider);
      ok = next.isEmpty ? await prefs.remove(_storageKey) : await prefs.setString(_storageKey, encodeSeen(next));
    } catch (_) {
      ok = false;
    }
    if (ok != state.persisted) state = CccProgressState(seen: state.seen, persisted: ok);
  }
}

final cccProgressProvider = NotifierProvider<CccProgressController, CccProgressState>(CccProgressController.new);
