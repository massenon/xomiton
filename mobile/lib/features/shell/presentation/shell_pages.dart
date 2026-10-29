import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/auth_spaces/app_space.dart';
import '../../../core/auth_spaces/presentation/space_widgets.dart';
import '../../../core/auth_spaces/space_providers.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/ui/xm_widgets.dart';

/// Barre d'onglets Membre : Accueil · Fil · SOS · Messages · Profil (Q38,
/// libellé « SOS », inchangée), plus un onglet « Espace pair » / « Admin » /
/// « Espaces » pour un compte qui a un autre espace (plan §5.3). Le SOS garde
/// l'accent rouge du design system.
class AppShell extends ConsumerWidget {
  const AppShell({super.key, required this.navigationShell});
  final StatefulNavigationShell navigationShell;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final online = ref.watch(isOnlineProvider);
    final others = ref.watch(otherSpacesProvider);
    final xm = context.xm;
    final tabKey = others.length > 1
        ? 'mobile_space_tab_both'
        : others.isEmpty
        ? null
        : (others.first == AppSpace.peer
              ? 'mobile_space_tab_peer'
              : 'mobile_space_tab_admin');
    return SpaceScope(
      space: AppSpace.member,
      child: Scaffold(
        body: Column(
          children: [
            if (!online) SafeArea(bottom: false, child: const OfflineBanner()),
            Expanded(child: navigationShell),
          ],
        ),
        bottomNavigationBar: Semantics(
          container: true,
          label: context.t('mobile_nav_label'),
          child: NavigationBar(
            selectedIndex: navigationShell.currentIndex,
            onDestinationSelected: (i) {
              if (i >= 5) {
                // Onglet des autres espaces : un seul → on y va ; deux → feuille de choix.
                if (others.length == 1) {
                  goToSpace(context, ref, others.first);
                } else {
                  showSpaceSheet(context);
                }
                return;
              }
              navigationShell.goBranch(
                i,
                initialLocation: i == navigationShell.currentIndex,
              );
            },
            destinations: [
              NavigationDestination(
                icon: const Icon(Icons.home_outlined),
                selectedIcon: const Icon(Icons.home),
                label: context.t('menus_mobile_home'),
              ),
              NavigationDestination(
                icon: const Icon(Icons.dynamic_feed_outlined),
                selectedIcon: const Icon(Icons.dynamic_feed),
                label: context.t('menus_mobile_feed'),
              ),
              NavigationDestination(
                icon: Icon(Icons.sos_outlined, color: xm.danger),
                selectedIcon: Icon(Icons.sos, color: xm.danger),
                label: context.t('mobile_tab_sos'),
              ),
              NavigationDestination(
                icon: const Icon(Icons.chat_bubble_outline),
                selectedIcon: const Icon(Icons.chat_bubble),
                label: context.t('menus_mobile_messages'),
              ),
              NavigationDestination(
                icon: const Icon(Icons.person_outline),
                selectedIcon: const Icon(Icons.person),
                label: context.t('menus_mobile_profile'),
              ),
              if (tabKey != null)
                NavigationDestination(
                  key: const ValueKey('tab-other-space'),
                  icon: Badge(
                    smallSize: 8,
                    backgroundColor: xm.accentViolet,
                    child: Icon(
                      others.length > 1 ? Icons.swap_horiz : others.first.icon,
                    ),
                  ),
                  label: context.t(tabKey),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
