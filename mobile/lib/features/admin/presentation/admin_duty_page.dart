import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/ui/xm_widgets.dart';
import '../application/admin_providers.dart';
import '../data/admin_models.dart';
import 'admin_ui.dart';

/// Tableau de bord d'astreinte (maquette A1) : bandeau des SOS actifs avec
/// compte à rebours d'escalade, indicateurs, « À traiter », maintenance. Relu
/// toutes les 10 s (de garde) ou 30 s ; rien n'est gardé sur l'appareil.
class AdminDutyPage extends ConsumerStatefulWidget {
  const AdminDutyPage({super.key});

  @override
  ConsumerState<AdminDutyPage> createState() => _AdminDutyPageState();
}

class _AdminDutyPageState extends ConsumerState<AdminDutyPage> with WidgetsBindingObserver, AdminPolling {
  final Set<String> _busy = {};

  Future<void> _ack(AdminAlert a) async {
    setState(() => _busy.add(a.id));
    await runAdmin(context, ref, () => ref.read(adminDutyProvider.notifier).acknowledge(a.id), done: 'mobile_admin_ack_done');
    if (mounted) setState(() => _busy.remove(a.id));
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(adminDutyProvider);
    final poll = state.valueOrNull?.dashboard.pollSeconds;
    if (poll != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) schedulePolling(poll, ref.read(adminDutyProvider.notifier).refresh);
      });
    }
    return SafeArea(
      child: RefreshIndicator(
        onRefresh: () => ref.read(adminDutyProvider.notifier).refresh(),
        child: ListView(
          key: const ValueKey('admin-duty'),
          padding: const EdgeInsets.all(XmSpace.gutter),
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            AdminHeader(title: context.t('mobile_admin_dash_title'), subtitle: context.t('mobile_admin_dash_hello')),
            const SizedBox(height: XmSpace.s3),
            const AdminOfflineNotice(),
            state.when(
              loading: () => const Padding(padding: EdgeInsets.all(XmSpace.s6), child: LoadingView()),
              error: (e, _) => AdminLoadError(error: e, onRetry: () => ref.invalidate(adminDutyProvider)),
              data: _content,
            ),
          ],
        ),
      ),
    );
  }

  Widget _content(AdminDuty duty) {
    final d = duty.dashboard;
    final theme = Theme.of(context);
    final c = d.alerts;
    final todo = <_Todo>[
      if ((c?.fresh ?? 0) > 0) _Todo('mobile_admin_todo_alerts', c!.fresh, '/admin/alertes'),
      if (d.pendingPeople > 0) _Todo('mobile_admin_todo_people', d.pendingPeople, '/admin/inscriptions'),
      if ((d.pendingModeration ?? 0) > 0) _Todo('mobile_admin_todo_moderation', d.pendingModeration!, '/admin/moderation'),
      if ((d.openSyncConflicts ?? 0) > 0) _Todo('mobile_admin_todo_conflicts_web', d.openSyncConflicts!, null),
    ];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Wrap(
          spacing: XmSpace.s2,
          runSpacing: XmSpace.s2,
          children: [
            if (d.onCallMe) PairBadge(context.t('mobile_admin_on_call_me'), tone: PairTone.primary, icon: Icons.shield_outlined),
            PairBadge(context.t('mobile_admin_on_call_team', {'admins': d.onCallAdmins, 'peers': d.onCallPeers})),
          ],
        ),
        if (duty.stale) ...[
          const SizedBox(height: XmSpace.s2),
          XmAlert(context.t('mobile_admin_stale'), icon: Icons.cloud_off_outlined, tone: context.xm.warning),
        ],
        const SizedBox(height: XmSpace.s3),
        if (duty.sos.isNotEmpty) ...[
          _SosBand(
            sos: duty.sos,
            busy: _busy,
            onAck: _ack,
          ),
          const SizedBox(height: XmSpace.s4),
        ],
        if (c != null) ...[
          SectionHeading(context.t('mobile_admin_kpis')),
          const SizedBox(height: XmSpace.s2),
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: XmSpace.s2,
            crossAxisSpacing: XmSpace.s2,
            childAspectRatio: 2.1,
            children: [
              _Kpi(keyName: 'new', label: context.t('mobile_admin_kpi_new'), value: c.fresh, hot: c.fresh > 0, route: '/admin/alertes'),
              _Kpi(keyName: 'sos', label: context.t('mobile_admin_kpi_sos'), value: c.sosActive, hot: c.sosActive > 0, route: '/admin/alertes'),
              _Kpi(keyName: 'critical', label: context.t('mobile_admin_kpi_critical'), value: c.criticalOpen, hot: c.criticalOpen > 0, route: '/admin/alertes'),
              _Kpi(
                keyName: 'people',
                label: context.t('mobile_admin_kpi_people'),
                value: d.pendingPeople,
                hot: false,
                route: '/admin/inscriptions',
              ),
            ],
          ),
          const SizedBox(height: XmSpace.s4),
        ],
        SectionHeading(context.t('mobile_admin_todo')),
        const SizedBox(height: XmSpace.s2),
        if (todo.isEmpty)
          Card(
            child: Padding(
              padding: const EdgeInsets.all(XmSpace.s4),
              child: Text(context.t('mobile_admin_todo_none'), style: theme.textTheme.bodyMedium),
            ),
          )
        else
          for (final t in todo)
            Padding(
              padding: const EdgeInsets.only(bottom: XmSpace.s2),
              child: t.route == null
                  ? Card(
                      child: Padding(
                        padding: const EdgeInsets.all(XmSpace.s4),
                        child: Text(context.t(t.key, {'count': t.count}), style: theme.textTheme.bodyMedium),
                      ),
                    )
                  : LinkRow(
                      semanticLabel: context.t(t.key, {'count': t.count}),
                      onTap: () => context.go(t.route!),
                      child: Text(context.t(t.key, {'count': t.count}), style: theme.textTheme.bodyMedium),
                    ),
            ),
        const SizedBox(height: XmSpace.s3),
        SectionHeading(context.t('mobile_admin_maint_title')),
        const SizedBox(height: XmSpace.s2),
        LinkRow(
          leading: Icons.build_outlined,
          onTap: () => context.go('/admin/maintenance'),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                context.t(d.maintenance.active ? 'mobile_admin_maint_ongoing' : 'mobile_admin_maint_inactive'),
                style: theme.textTheme.titleSmall,
              ),
              Text(
                context.t(d.maintenance.active ? 'mobile_admin_maint_ongoing_text' : 'mobile_admin_maint_inactive_text'),
                style: theme.textTheme.bodySmall,
              ),
            ],
          ),
        ),
        const SizedBox(height: XmSpace.s4),
        Text(context.t('mobile_admin_scope'), style: theme.textTheme.bodySmall),
      ],
    );
  }
}

class _Todo {
  const _Todo(this.key, this.count, this.route);
  final String key;
  final int count;
  final String? route;
}

class _Kpi extends StatelessWidget {
  const _Kpi({required this.keyName, required this.label, required this.value, required this.hot, required this.route});
  final String keyName;
  final String label;
  final int value;
  final bool hot;
  final String route;

  @override
  Widget build(BuildContext context) {
    final xm = context.xm;
    final theme = Theme.of(context);
    return Semantics(
      button: true,
      label: '$label : $value',
      excludeSemantics: true,
      child: Material(
        color: hot ? xm.danger.withValues(alpha: 0.08) : xm.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(XmSpace.radiusLg),
          side: BorderSide(color: hot ? xm.danger : xm.border, width: hot ? 2 : 1),
        ),
        child: InkWell(
          key: ValueKey('admin-kpi-$keyName'),
          borderRadius: BorderRadius.circular(XmSpace.radiusLg),
          onTap: () => context.go(route),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: XmSpace.s3, vertical: XmSpace.s2),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('$value', style: theme.textTheme.headlineSmall),
                Text(label, style: theme.textTheme.bodySmall, maxLines: 2, overflow: TextOverflow.ellipsis),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Bandeau des SOS actifs : lieu, âge, compte à rebours avant l'escalade
/// nationale, accusé de réception direct.
class _SosBand extends StatelessWidget {
  const _SosBand({required this.sos, required this.busy, required this.onAck});
  final List<AdminAlert> sos;
  final Set<String> busy;
  final void Function(AdminAlert) onAck;

  @override
  Widget build(BuildContext context) {
    final xm = context.xm;
    final theme = Theme.of(context);
    return Container(
      key: const ValueKey('admin-sos-band'),
      padding: const EdgeInsets.all(XmSpace.s3),
      decoration: BoxDecoration(
        color: xm.danger.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(XmSpace.radiusLg),
        border: Border.all(color: xm.danger, width: 2),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Semantics(
            header: true,
            liveRegion: true,
            child: Row(
              children: [
                Icon(Icons.sos, color: xm.danger),
                const SizedBox(width: XmSpace.s2),
                Expanded(
                  child: Text(
                    context.t('mobile_admin_sos_banner', {'count': sos.length}),
                    style: theme.textTheme.titleMedium,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: XmSpace.s1),
          Text(context.t('mobile_admin_sos_text'), style: theme.textTheme.bodySmall),
          const SizedBox(height: XmSpace.s3),
          for (final a in sos)
            Padding(
              padding: const EdgeInsets.only(bottom: XmSpace.s2),
              child: _SosRow(alert: a, busy: busy.contains(a.id), onAck: () => onAck(a)),
            ),
        ],
      ),
    );
  }
}

/// Phrase d'état d'escalade d'un SOS (compte à rebours, escaladé, arrêté).
String escalationText(BuildContext context, AdminAlert a) {
  if (a.escalated) return context.t('mobile_admin_escalated');
  if (a.acknowledged) return context.t('mobile_admin_ack_stopped');
  final left = a.escalationMinutesLeft(after: kSosEscalationMinutes);
  if (left == null) return '';
  return context.t('mobile_admin_escalation_in', {'minutes': left});
}

class _SosRow extends StatelessWidget {
  const _SosRow({required this.alert, required this.busy, required this.onAck});
  final AdminAlert alert;
  final bool busy;
  final VoidCallback onAck;

  @override
  Widget build(BuildContext context) {
    final a = alert;
    final theme = Theme.of(context);
    final place = a.place.isEmpty ? context.alertTypeLabel('sos') : a.place;
    final esc = escalationText(context, a);
    return Card(
      key: ValueKey('admin-sos-${a.id}'),
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(XmSpace.s3),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('SOS · $place', style: theme.textTheme.titleSmall),
            if (a.ageMinutes != null)
              Text(context.t('mobile_admin_age_min', {'minutes': a.ageMinutes}), style: theme.textTheme.bodySmall),
            if (esc.isNotEmpty) Text(esc, style: theme.textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: XmSpace.s2),
            Wrap(
              spacing: XmSpace.s2,
              runSpacing: XmSpace.s2,
              children: [
                if (!a.acknowledged)
                  FilledButton(
                    key: ValueKey('admin-sos-ack-${a.id}'),
                    style: FilledButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
                    onPressed: busy ? null : onAck,
                    child: Text(context.t('mobile_admin_acknowledge')),
                  ),
                OutlinedButton(
                  key: ValueKey('admin-sos-open-${a.id}'),
                  style: OutlinedButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
                  onPressed: () => context.go('/admin/alertes/${a.id}'),
                  child: Text(context.t('mobile_admin_detail')),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
