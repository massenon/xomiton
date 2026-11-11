import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'session_controller.dart';

/// Le compte connecté attend la validation de l'équipe (`pending_validation`
/// de `GET me`, inscription du 11/11/2026). Lecture du contenu, SOS, alertes,
/// signalement et profil restent ouverts ; publier, commenter, écrire, rejoindre
/// et la collecte attendent la validation. Le serveur applique la même règle
/// (403) : cette garde ne sert qu'à expliquer avant que la personne ne se heurte au refus.
final pendingValidationProvider = Provider<bool>(
  (ref) => ref.watch(sessionProvider.select((s) => s.authenticated && (s.user?.pendingValidation ?? false))),
);
