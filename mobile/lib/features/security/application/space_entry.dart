import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/auth_spaces/app_space.dart';
import '../../../core/network/app_toast.dart';
import '../../../core/providers/core_providers.dart';
import '../../auth/application/session_controller.dart';
import '../../profile/application/settings_providers.dart';

/// Premier accès à l'espace Pair ou Admin : le mode discret (icône et nom
/// « Notes », aperçus masqués) est activé par défaut (D-MOB-8, plan §7.4). La
/// personne est prévenue et peut le désactiver ensuite ; son choix n'est plus
/// jamais écrasé. En cas d'échec (hors ligne), on réessaie à l'entrée suivante.
class SpaceEntry {
  SpaceEntry(this._ref);
  final Ref _ref;

  Future<void> onEnter(AppSpace space) async {
    if (space == AppSpace.member) return;
    final prefs = _ref.read(sharedPreferencesProvider);
    if (prefs.getBool(kPrefDiscreetDefaultApplied) == true) return;
    try {
      final current = await _ref.read(securityProvider.future);
      if (!current.discreet) {
        await _ref.read(securityProvider.notifier).setDiscreet(true);
        _ref.read(toastBusProvider).emit(const DiscreetDefaultToast());
      }
      await prefs.setBool(kPrefDiscreetDefaultApplied, true);
    } catch (_) {
      // Réessayé au prochain accès : on repart d'une lecture neuve.
      _ref.invalidate(securityProvider);
    }
  }
}

final spaceEntryProvider = Provider<SpaceEntry>(SpaceEntry.new);
