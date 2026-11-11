import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/auth_spaces/app_space.dart';
import '../../core/auth_spaces/presentation/space_widgets.dart';
import '../../core/auth_spaces/space_providers.dart';
import '../../core/l10n/app_strings.dart';
import '../../core/providers/core_providers.dart';
import '../../core/security/screen_security.dart';
import '../../features/security/application/space_entry.dart';
import '../../core/ui/xm_widgets.dart';
import '../theme/xm_tokens.dart';

/// Un onglet des barres Pair et Admin (plan §5.3). `network` : l'onglet
/// « Réseau » n'est pas une branche, il ramène au fil de l'espace Membre (le
/// pair et l'admin gardent le réseau social dans leur barre).
class SpaceTab {
  const SpaceTab(
    this.key,
    this.labelKey,
    this.icon,
    this.selectedIcon, {
    this.network = false,
    this.danger = false,
  });
  final String key;
  final String labelKey;
  final IconData icon;
  final IconData selectedIcon;
  final bool network;
  final bool danger;
}

const kPeerTabs = [
  SpaceTab('home', 'menus_mobile_home', Icons.home_outlined, Icons.home),
  SpaceTab(
    'alerts',
    'mobile_peer_tab_alerts',
    Icons.notifications_active_outlined,
    Icons.notifications_active,
  ),
  SpaceTab(
    'sos',
    'mobile_tab_sos',
    Icons.sos_outlined,
    Icons.sos,
    danger: true,
  ),
  SpaceTab(
    'cases',
    'mobile_peer_tab_cases',
    Icons.folder_outlined,
    Icons.folder,
  ),
  SpaceTab(
    'network',
    'mobile_tab_network',
    Icons.dynamic_feed_outlined,
    Icons.dynamic_feed,
    network: true,
  ),
];

const kAdminTabs = [
  SpaceTab(
    'duty',
    'mobile_admin_tab_duty',
    Icons.monitor_heart_outlined,
    Icons.monitor_heart,
  ),
  SpaceTab(
    'alerts',
    'mobile_admin_tab_alerts',
    Icons.notifications_active_outlined,
    Icons.notifications_active,
  ),
  SpaceTab(
    'manage',
    'mobile_admin_tab_manage',
    Icons.apps_outlined,
    Icons.apps,
  ),
  SpaceTab(
    'notifications',
    'mobile_admin_tab_notifications',
    Icons.notifications_none,
    Icons.notifications,
  ),
  SpaceTab(
    'network',
    'mobile_tab_network',
    Icons.dynamic_feed_outlined,
    Icons.dynamic_feed,
    network: true,
  ),
];

/// Barre d'onglets d'un espace (Pair ou Admin) : même habillage que la barre
/// Membre, bandeau hors connexion compris, espace déclaré actif à l'affichage.
class SpaceNavShell extends ConsumerWidget {
  const SpaceNavShell({
    super.key,
    required this.space,
    required this.tabs,
    required this.navLabelKey,
    required this.navigationShell,
  });

  final AppSpace space;
  final List<SpaceTab> tabs;
  final String navLabelKey;
  final StatefulNavigationShell navigationShell;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final online = ref.watch(isOnlineProvider);
    final xm = context.xm;
    return SpaceScope(
      space: space,
      onEnter: (r) => r.read(spaceEntryProvider).onEnter(space),
      child: SecureScreen(
        child: Scaffold(
          body: Column(
            children: [
              if (!online)
                const SafeArea(bottom: false, child: OfflineBanner()),
              Expanded(child: navigationShell),
            ],
          ),
          bottomNavigationBar: Semantics(
            container: true,
            label: context.t(navLabelKey),
            child: NavigationBar(
              selectedIndex: navigationShell.currentIndex,
              onDestinationSelected: (i) {
                if (tabs[i].network) {
                  // Réseau : le fil de l'espace Membre (toujours ouvert à un compte).
                  final route = chooseSpace(ref, AppSpace.member);
                  if (route != null) context.go('/feed');
                  return;
                }
                navigationShell.goBranch(
                  i,
                  initialLocation: i == navigationShell.currentIndex,
                );
              },
              destinations: [
                for (final t in tabs)
                  NavigationDestination(
                    key: ValueKey('tab-${space.apiName}-${t.key}'),
                    icon: Icon(t.icon, color: t.danger ? xm.danger : null),
                    selectedIcon: Icon(
                      t.selectedIcon,
                      color: t.danger ? xm.danger : null,
                    ),
                    label: context.t(t.labelKey),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
