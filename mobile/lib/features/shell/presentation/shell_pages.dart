import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../auth/application/session_controller.dart';

/// Barre d'onglets : Accueil · Fil · SOS · Messages · Profil (Q38, libellé
/// « SOS »). Le SOS garde l'accent rouge du design system.
class AppShell extends ConsumerWidget {
  const AppShell({super.key, required this.navigationShell});
  final StatefulNavigationShell navigationShell;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final online = ref.watch(isOnlineProvider);
    final xm = context.xm;
    return Scaffold(
      body: Column(children: [
        if (!online) SafeArea(bottom: false, child: const OfflineBanner()),
        Expanded(child: navigationShell),
      ]),
      bottomNavigationBar: Semantics(
        container: true,
        label: context.t('mobile_nav_label'),
        child: NavigationBar(
          selectedIndex: navigationShell.currentIndex,
          onDestinationSelected: (i) =>
              navigationShell.goBranch(i, initialLocation: i == navigationShell.currentIndex),
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
          ],
        ),
      ),
    );
  }
}

/// Écran des lots suivants (P15-04 à P15-07) : texte d'attente du catalogue.
class ComingSoonPage extends StatelessWidget {
  const ComingSoonPage({super.key, required this.pageKey, this.showBack = false});
  final String pageKey;
  final bool showBack;

  static const _labels = {
    'feed': 'menus_mobile_feed',
    'sos': 'mobile_tab_sos',
    'messages': 'menus_mobile_messages',
    'profile': 'menus_mobile_profile',
    'report': 'feed_quick_report',
    'emergency': 'feed_quick_emergency',
    'assistant': 'feed_quick_assistant',
  };

  @override
  Widget build(BuildContext context) {
    final label = context.t(_labels[pageKey] ?? 'common_soon');
    return Scaffold(
      appBar: showBack ? AppBar() : null,
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(XmSpace.s5),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Text(context.t('common_coming_soon_title'),
                  style: Theme.of(context).textTheme.titleLarge, textAlign: TextAlign.center),
              const SizedBox(height: XmSpace.s3),
              Text(context.t('common_coming_soon_text', {'page': label}), textAlign: TextAlign.center),
            ]),
          ),
        ),
      ),
    );
  }
}

/// Onglet Profil (P15-07 à venir) : porte pour l'instant la déconnexion.
class ProfileTabPage extends ConsumerWidget {
  const ProfileTabPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(sessionProvider).user;
    return SafeArea(
      child: ListView(padding: const EdgeInsets.all(XmSpace.gutter), children: [
        Text(user?.pseudo ?? context.t('menus_mobile_profile'), style: Theme.of(context).textTheme.headlineMedium),
        const SizedBox(height: XmSpace.s5),
        OutlinedButton.icon(
          icon: const Icon(Icons.logout),
          label: Text(context.t('buttons_logout')),
          onPressed: () async {
            await ref.read(sessionProvider.notifier).signOut();
            if (context.mounted) {
              ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(context.t('auth_logout_done'))));
            }
          },
        ),
      ]),
    );
  }
}
