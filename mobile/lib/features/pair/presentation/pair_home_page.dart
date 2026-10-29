// ignore_for_file: curly_braces_in_flow_control_structures
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/sync/sync_providers.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../auth/application/session_controller.dart';
import '../application/pair_providers.dart';
import 'pair_ui.dart';

/// Accueil de l'espace pair (maquette P1) : chiffres de ses dossiers, tâches du
/// jour, raccourcis. Le SOS reste dans la barre d'onglets.
class PairHomePage extends ConsumerWidget {
  const PairHomePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final home = ref.watch(peerHomeProvider);
    final name = ref.watch(sessionProvider.select((s) => s.user?.pseudo)) ?? '';
    final theme = Theme.of(context);
    return SafeArea(
      child: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(peerHomeProvider);
          if (ref.read(isOnlineProvider))
            await ref.read(syncEngineProvider).sync(force: true);
        },
        child: ListView(
          key: const ValueKey('pair-home'),
          padding: const EdgeInsets.all(XmSpace.gutter),
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            PairHeader(title: context.t('peer_home_title')),
            const SizedBox(height: XmSpace.s3),
            Wrap(
              spacing: XmSpace.s2,
              runSpacing: XmSpace.s2,
              children: [
                PairBadge(context.t('peer_home_badge'), tone: PairTone.violet),
                if (home.valueOrNull?.onDuty ?? false)
                  PairBadge(
                    context.t('mobile_pair_duty'),
                    tone: PairTone.success,
                    icon: Icons.verified_user_outlined,
                  ),
              ],
            ),
            const SizedBox(height: XmSpace.s3),
            Text(
              context.t('peer_home_greeting', {'name': name}),
              style: theme.textTheme.titleLarge,
            ),
            const SizedBox(height: XmSpace.s1),
            Text(
              context.t('peer_home_intro'),
              style: theme.textTheme.bodySmall,
            ),
            const SizedBox(height: XmSpace.s4),
            home.when(
              loading: () => const Padding(
                padding: EdgeInsets.all(XmSpace.s6),
                child: LoadingView(),
              ),
              error: (e, _) => ErrorView(
                error: e,
                onRetry: () => ref.invalidate(peerHomeProvider),
              ),
              data: (h) => _Content(home: h),
            ),
            const SizedBox(height: XmSpace.s4),
            const _Shortcuts(),
          ],
        ),
      ),
    );
  }
}

class _Content extends StatelessWidget {
  const _Content({required this.home});
  final PeerHome home;

  @override
  Widget build(BuildContext context) {
    final kpi = home.section.kpi;
    final theme = Theme.of(context);
    int count(String key) {
      for (final t in home.section.tasks) {
        if (t.key == key) return t.count;
      }
      return 0;
    }

    final toTake = count('alerts_to_take');
    final toContact = count('cases_to_contact');
    Widget tile(String labelKey, int v) => Expanded(
      child: Semantics(
        container: true,
        label: '${context.t(labelKey)} : $v',
        excludeSemantics: true,
        child: Card(
          child: Padding(
            padding: const EdgeInsets.symmetric(
              vertical: XmSpace.s3,
              horizontal: XmSpace.s2,
            ),
            child: Column(
              children: [
                Text('$v', style: theme.textTheme.headlineSmall),
                Text(
                  context.t(labelKey),
                  style: theme.textTheme.labelSmall,
                  textAlign: TextAlign.center,
                ),
              ],
            ),
          ),
        ),
      ),
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (home.stale) ...[
          XmAlert(
            context.t('mobile_pair_home_stale'),
            icon: Icons.cloud_off_outlined,
            tone: context.xm.warning,
          ),
          const SizedBox(height: XmSpace.s3),
        ],
        SectionHeading(context.t('peer_home_kpi_label')),
        const SizedBox(height: XmSpace.s2),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            tile('peer_home_kpi_collected', kpi.collected),
            const SizedBox(width: XmSpace.s2),
            tile('peer_home_kpi_this_month', kpi.thisMonth),
          ],
        ),
        const SizedBox(height: XmSpace.s2),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            tile('peer_home_kpi_in_progress', kpi.inProgress),
            const SizedBox(width: XmSpace.s2),
            tile('peer_home_kpi_closed', kpi.closed),
          ],
        ),
        const SizedBox(height: XmSpace.s4),
        SectionHeading(context.t('peer_home_tasks_title')),
        const SizedBox(height: XmSpace.s2),
        LinkRow(
          key: const ValueKey('task-alerts'),
          leading: Icons.notifications_active_outlined,
          onTap: () => context.go('/pair/alertes'),
          child: Text(
            toTake == 0
                ? context.t('mobile_pair_tasks_alerts_none')
                : context.t('peer_home_tasks_alerts_to_take', {
                    'count': toTake,
                  }),
            style: theme.textTheme.titleSmall,
          ),
        ),
        const SizedBox(height: XmSpace.s2),
        LinkRow(
          key: const ValueKey('task-cases'),
          leading: Icons.folder_outlined,
          onTap: () => context.go('/pair/dossiers'),
          child: Text(
            toContact == 0
                ? context.t('mobile_pair_tasks_cases_none')
                : context.t('peer_home_tasks_cases_to_contact', {
                    'count': toContact,
                  }),
            style: theme.textTheme.titleSmall,
          ),
        ),
        const SizedBox(height: XmSpace.s2),
        LinkRow(
          leading: Icons.groups_outlined,
          onTap: () => context.push('/ressources'),
          child: Text(
            context.t('peer_home_tasks_session'),
            style: theme.textTheme.titleSmall,
          ),
        ),
      ],
    );
  }
}

class _Shortcuts extends StatelessWidget {
  const _Shortcuts();

  @override
  Widget build(BuildContext context) {
    final items = <(String, IconData, String)>[
      (
        'peer_home_shortcuts_collecte',
        Icons.note_add_outlined,
        '/pair/dossiers/collecte',
      ),
      ('peer_home_shortcuts_cases', Icons.folder_outlined, '/pair/dossiers'),
      ('peer_home_shortcuts_report', Icons.campaign_outlined, '/signaler'),
      ('peer_home_shortcuts_directory', Icons.menu_book_outlined, '/urgences'),
      (
        'peer_home_shortcuts_resources',
        Icons.library_books_outlined,
        '/ressources',
      ),
    ];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SectionHeading(context.t('peer_home_shortcuts_label')),
        const SizedBox(height: XmSpace.s2),
        GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          crossAxisSpacing: XmSpace.s2,
          mainAxisSpacing: XmSpace.s2,
          childAspectRatio: 2.3,
          children: [
            for (final (key, icon, route) in items)
              Semantics(
                button: true,
                child: Card(
                  child: InkWell(
                    key: ValueKey('shortcut-$key'),
                    borderRadius: BorderRadius.circular(XmSpace.radiusLg),
                    onTap: () => route.startsWith('/pair/')
                        ? context.go(route)
                        : context.push(route),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: XmSpace.s3,
                      ),
                      child: Row(
                        children: [
                          Icon(icon, color: context.xm.primary),
                          const SizedBox(width: XmSpace.s2),
                          Expanded(
                            child: Text(
                              context.t(key),
                              style: Theme.of(context).textTheme.labelLarge,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ],
    );
  }
}
