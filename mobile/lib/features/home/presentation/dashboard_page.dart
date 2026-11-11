import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/auth_spaces/presentation/space_widgets.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../../core/util/dates.dart';
import '../../auth/application/session_controller.dart';
import '../application/home_providers.dart';
import '../data/home_models.dart';
import 'theme_card.dart';

class DashboardPage extends ConsumerWidget {
  const DashboardPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dash = ref.watch(dashboardProvider);
    return SafeArea(
      bottom: false,
      child: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(dashboardProvider);
          await ref.read(dashboardProvider.future).catchError((_) => _empty);
        },
        child: dash.when(
          loading: () => const LoadingView(),
          error: (e, _) => dash.hasValue
              ? const SizedBox.shrink()
              : ListView(
                  children: [
                    SizedBox(
                      height: 360,
                      child: ErrorView(
                        error: e,
                        onRetry: () => ref.invalidate(dashboardProvider),
                      ),
                    ),
                  ],
                ),
          data: (d) => _Body(data: d),
        ),
      ),
    );
  }
}

final _empty = DashboardData.fromJson(const {});

class _Body extends ConsumerWidget {
  const _Body({required this.data});
  final DashboardData data;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(sessionProvider).user;
    final unread = ref.watch(unreadCountProvider).value ?? data.notifications;
    final theme = Theme.of(context);
    final peer = data.peer;
    return ListView(
      padding: const EdgeInsets.all(XmSpace.gutter),
      children: [
        // Pastille « Membre ▾ » : seulement pour un compte à plusieurs espaces.
        const Align(alignment: Alignment.centerRight, child: SpaceSwitcherPill()),
        Row(
          children: [
            Expanded(
              child: Semantics(
                header: true,
                child: Text(
                  context.t('peer_home_greeting', {'name': user?.pseudo ?? ''}),
                  style: theme.textTheme.headlineMedium,
                ),
              ),
            ),
            _Bell(unread: unread),
          ],
        ),
        if (data.stale) ...[
          const SizedBox(height: XmSpace.s2),
          Text(
            context.t('mobile_stale_notice'),
            style: theme.textTheme.labelSmall,
          ),
        ],
        if (user != null && user.pendingValidation) ...[
          const SizedBox(height: XmSpace.s3),
          XmAlert(
            context.t('auth_pending'),
            icon: Icons.hourglass_empty,
            tone: context.xm.info,
          ),
        ],
        if (user != null && !user.emailVerified) ...[
          const SizedBox(height: XmSpace.s3),
          _VerifyEmailCard(email: user.email),
        ],
        const SizedBox(height: XmSpace.s4),
        _Tiles(tiles: data.tiles),
        if (!data.profileComplete && data.completion < 100) ...[
          const SizedBox(height: XmSpace.s4),
          _CompletionCard(percent: data.completion),
        ],
        if (peer != null) ...[
          const SizedBox(height: XmSpace.s4),
          _PeerCard(peer: peer),
        ],
        if (data.featured.isNotEmpty) ...[
          const SizedBox(height: XmSpace.s5),
          Row(
            children: [
              Expanded(
                child: Text(
                  context.t('feed_featured_title'),
                  style: theme.textTheme.titleLarge,
                ),
              ),
              TextButton(
                onPressed: () => context.go('/thematiques'),
                child: Text(context.t('feed_featured_see_all')),
              ),
            ],
          ),
          const SizedBox(height: XmSpace.s2),
          SizedBox(
            height: 210,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: data.featured.length,
              separatorBuilder: (_, _) => const SizedBox(width: XmSpace.s3),
              itemBuilder: (context, i) => SizedBox(
                width: 220,
                child: ThemeCard(theme: data.featured[i], compact: true),
              ),
            ),
          ),
        ],
        if (data.nextEvent != null) ...[
          const SizedBox(height: XmSpace.s5),
          _EventCard(event: data.nextEvent!),
        ],
      ],
    );
  }
}

class _Bell extends StatelessWidget {
  const _Bell({required this.unread});
  final int unread;

  @override
  Widget build(BuildContext context) {
    final label = unread > 0
        ? '${context.t('components_bell_label')}, $unread ${context.t('components_bell_unread', {'count': unread})}'
        : context.t('components_bell_label');
    return Semantics(
      button: true,
      label: label,
      excludeSemantics: true,
      child: InkResponse(
        onTap: () => context.push('/notifications'),
        radius: 28,
        child: SizedBox(
          width: 48,
          height: 48,
          child: Stack(
            alignment: Alignment.center,
            children: [
              const Icon(Icons.notifications_outlined, size: 28),
              if (unread > 0)
                Positioned(
                  top: 6,
                  right: 4,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 5,
                      vertical: 1,
                    ),
                    decoration: BoxDecoration(
                      color: context.xm.danger,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      unread > 99 ? '99+' : '$unread',
                      style: TextStyle(
                        color: context.xm.onSecondary,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _VerifyEmailCard extends StatelessWidget {
  const _VerifyEmailCard({this.email});
  final String? email;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(XmSpace.s4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.t('auth_verify_banner_title'),
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: XmSpace.s1),
          Text(
            context.t('auth_verify_banner_text'),
            style: Theme.of(context).textTheme.bodySmall,
          ),
          if (email != null)
            TextButton(
              onPressed: () => context.push(
                '/otp?purpose=signup&email=${Uri.encodeQueryComponent(email!)}',
              ),
              child: Text(context.t('auth_verify_banner_enter_code')),
            ),
        ],
      ),
    ),
  );
}

class _Tiles extends StatelessWidget {
  const _Tiles({required this.tiles});
  final List<DashboardTile> tiles;

  static const _meta = {
    'report': (Icons.flag_outlined, 'feed_quick_report'),
    'emergency': (Icons.phone_outlined, 'feed_quick_emergency'),
    'assistant': (Icons.smart_toy_outlined, 'feed_quick_assistant'),
    'learn': (Icons.menu_book_outlined, 'pages_thematiques_title'),
  };

  @override
  Widget build(BuildContext context) {
    final known = tiles.where((t) => _meta.containsKey(t.key)).toList();
    Widget tile(DashboardTile t) => _Tile(
      icon: _meta[t.key]!.$1,
      label: context.t(_meta[t.key]!.$2),
      subtitle: t.key == 'learn' && t.count != null
          ? context.t('mobile_tile_learn_count', {'count': t.count})
          : null,
      onTap: () => context.push(switch (t.key) {
        'learn' => '/thematiques',
        'report' => '/signaler',
        'emergency' => '/urgences',
        'assistant' => '/assistant',
        _ => '/thematiques',
      }),
    );
    // Deux colonnes, hauteur suivant le contenu (texte agrandi, libellés longs).
    return Column(
      children: [
        for (var i = 0; i < known.length; i += 2)
          Padding(
            padding: EdgeInsets.only(
              bottom: i + 2 < known.length ? XmSpace.s3 : 0,
            ),
            child: IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Expanded(child: tile(known[i])),
                  const SizedBox(width: XmSpace.s3),
                  Expanded(
                    child: i + 1 < known.length
                        ? tile(known[i + 1])
                        : const SizedBox.shrink(),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

class _Tile extends StatelessWidget {
  const _Tile({
    required this.icon,
    required this.label,
    required this.onTap,
    this.subtitle,
  });
  final IconData icon;
  final String label;
  final String? subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Card(
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(XmSpace.s4),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: context.xm.primary, size: 28),
            const SizedBox(height: XmSpace.s2),
            Text(label, style: Theme.of(context).textTheme.titleSmall),
            if (subtitle != null)
              Text(subtitle!, style: Theme.of(context).textTheme.labelSmall),
          ],
        ),
      ),
    ),
  );
}

class _CompletionCard extends StatelessWidget {
  const _CompletionCard({required this.percent});
  final int percent;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(XmSpace.s4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  context.t('profile_completion', {'percent': percent}),
                  style: Theme.of(context).textTheme.titleSmall,
                ),
              ),
              TextButton(
                onPressed: () => context.push('/profil-progressif'),
                child: Text(context.t('buttons_community_complete_profile')),
              ),
            ],
          ),
          const SizedBox(height: XmSpace.s2),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: percent.clamp(0, 100) / 100,
              minHeight: 8,
              backgroundColor: context.xm.border,
            ),
          ),
        ],
      ),
    ),
  );
}

class _PeerCard extends StatelessWidget {
  const _PeerCard({required this.peer});
  final PeerSection peer;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    Widget kpi(String key, int v) => Expanded(
      child: Column(
        children: [
          Text('$v', style: t.headlineSmall),
          Text(
            context.t(key),
            style: t.labelSmall,
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(XmSpace.s4),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    context.t('peer_home_tasks_title'),
                    style: t.titleMedium,
                  ),
                ),
                Chip(label: Text(context.t('peer_home_badge'))),
              ],
            ),
            const SizedBox(height: XmSpace.s3),
            Semantics(
              label: context.t('peer_home_kpi_label'),
              child: Row(
                children: [
                  kpi('peer_home_kpi_collected', peer.kpi.collected),
                  kpi('peer_home_kpi_this_month', peer.kpi.thisMonth),
                  kpi('peer_home_kpi_in_progress', peer.kpi.inProgress),
                  kpi('peer_home_kpi_closed', peer.kpi.closed),
                ],
              ),
            ),
            for (final task in peer.tasks)
              if (task.key == 'alerts_to_take' ||
                  task.key == 'cases_to_contact')
                Padding(
                  padding: const EdgeInsets.only(top: XmSpace.s2),
                  child: Text(
                    context.t('peer_home_tasks_${task.key}', {
                      'count': task.count,
                    }),
                  ),
                ),
          ],
        ),
      ),
    );
  }
}

class _EventCard extends StatelessWidget {
  const _EventCard({required this.event});
  final NextEvent event;

  @override
  Widget build(BuildContext context) => Card(
    child: ListTile(
      leading: Icon(Icons.event_outlined, color: context.xm.primary),
      title: Text(event.title),
      subtitle: Text(
        [
          context.t('mobile_event_next'),
          if (event.startsAt != null) formatDateTime(context, event.startsAt!),
          if (event.isOnline) context.t('common_online'),
        ].join(' ${context.t('common_separator')} '),
      ),
    ),
  );
}
