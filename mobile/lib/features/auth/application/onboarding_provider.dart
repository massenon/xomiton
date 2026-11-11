import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/core_providers.dart';

const kOnboardingSeenPref = 'onboarding_seen';

/// `onboardingProvider.seen` : le carrousel n'est montré qu'une fois
/// (SharedPreferences).
class OnboardingState {
  const OnboardingState({required this.seen});
  final bool seen;
}

class OnboardingController extends Notifier<OnboardingState> {
  @override
  OnboardingState build() => OnboardingState(
      seen: ref.read(sharedPreferencesProvider).getBool(kOnboardingSeenPref) ?? false);

  Future<void> markSeen() async {
    state = const OnboardingState(seen: true);
    await ref.read(sharedPreferencesProvider).setBool(kOnboardingSeenPref, true);
  }
}

final onboardingProvider =
    NotifierProvider<OnboardingController, OnboardingState>(OnboardingController.new);
