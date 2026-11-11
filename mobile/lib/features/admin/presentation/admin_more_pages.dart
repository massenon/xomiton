import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../../core/util/dates.dart';
import '../../home/data/home_api.dart';
import '../../home/data/home_models.dart';
import '../application/admin_providers.dart';
import '../data/admin_api.dart';
import 'admin_people_pages.dart' show ReasonSheet;
import 'admin_ui.dart';

// --- Gérer (A11) ----------------------------------------------------------------------------------

/// Menu « Gérer » : les tâches d'administration disponibles dans l'appli.
class AdminManagePage extends ConsumerWidget {
  const AdminManagePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final dash = ref.watch(adminDashboardProvider).valueOrNull;
    Widget row(String key, IconData icon, String route, {int? count}) => Padding(
      padding: const EdgeInsets.only(bottom: XmSpace.s2),
      child: LinkRow(
        leading: icon,
        semanticLabel: context.t(key),
        onTap: () => context.go(route),
        child: Row(
          children: [
            Expanded(child: Text(context.t(key), style: theme.textTheme.titleSmall)),
            if (count != null && count > 0) PairBadge(context.t('mobile_admin_count_badge', {'count': count}), tone: PairTone.warning),
          ],
        ),
      ),
    );
    return SafeArea(
      child: ListView(
        key: const ValueKey('admin-manage'),
        padding: const EdgeInsets.all(XmSpace.gutter),
        children: [
          AdminHeader(title: context.t('mobile_admin_manage_title'), subtitle: context.t('mobile_admin_manage_intro')),
          const SizedBox(height: XmSpace.s4),
          row('mobile_admin_manage_reg', Icons.how_to_reg_outlined, '/admin/inscriptions', count: dash?.pendingPeople),
          row('mobile_admin_manage_roles', Icons.group_add_outlined, '/admin/roles'),
          row('mobile_admin_manage_mod', Icons.shield_outlined, '/admin/moderation', count: dash?.pendingModeration),
          row('mobile_admin_manage_maint', Icons.build_outlined, '/admin/maintenance'),
          const SizedBox(height: XmSpace.s3),
          Text(context.t('mobile_admin_manage_web'), style: theme.textTheme.bodySmall),
        ],
      ),
    );
  }
}

// --- Modération (A9, A10) --------------------------------------------------------------------------

/// Contenus signalés à traiter / traités. Les messages privés n'y sont jamais.
class AdminModerationPage extends ConsumerStatefulWidget {
  const AdminModerationPage({super.key});

  @override
  ConsumerState<AdminModerationPage> createState() => _AdminModerationPageState();
}

class _AdminModerationPageState extends ConsumerState<AdminModerationPage> {
  String _tab = 'pending';

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final state = ref.watch(adminReportsProvider(_tab));
    return AdminDetailScaffold(
      title: context.t('mobile_admin_mod_title'),
      child: RefreshIndicator(
        onRefresh: () async => ref.invalidate(adminReportsProvider(_tab)),
        child: ListView(
          key: const ValueKey('admin-moderation'),
          padding: const EdgeInsets.all(XmSpace.gutter),
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            const AdminOfflineNotice(),
            Text(context.t('mobile_admin_mod_intro'), style: theme.textTheme.bodySmall),
            const SizedBox(height: XmSpace.s3),
            Semantics(
              container: true,
              label: context.t('mobile_admin_mod_tabs'),
              child: Wrap(
                spacing: XmSpace.s2,
                children: [
                  for (final (v, k) in [('pending', 'todo'), ('handled', 'done')])
                    ChoiceChip(
                      key: ValueKey('admin-mod-tab-$k'),
                      label: Text(context.t('mobile_admin_mod_tab_$k')),
                      selected: _tab == v,
                      materialTapTargetSize: MaterialTapTargetSize.padded,
                      onSelected: (_) => setState(() => _tab = v),
                    ),
                ],
              ),
            ),
            const SizedBox(height: XmSpace.s3),
            state.when(
              loading: () => const Padding(padding: EdgeInsets.all(XmSpace.s6), child: LoadingView()),
              error: (e, _) => AdminLoadError(error: e, onRetry: () => ref.invalidate(adminReportsProvider(_tab))),
              data: (list) => list.isEmpty
                  ? EmptyView(context.t('mobile_admin_mod_empty'))
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Semantics(
                          liveRegion: true,
                          child: Text(context.t('mobile_admin_mod_count', {'count': list.length}), style: theme.textTheme.titleMedium),
                        ),
                        const SizedBox(height: XmSpace.s2),
                        for (final r in list)
                          Padding(
                            padding: const EdgeInsets.only(bottom: XmSpace.s2),
                            child: LinkRow(
                              key: ValueKey('admin-report-${r.id}'),
                              onTap: () => context.push('/admin/moderation/${r.id}'),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Wrap(
                                    spacing: XmSpace.s2,
                                    runSpacing: XmSpace.s1,
                                    children: [
                                      PairBadge(_kind(context, r.kind), tone: PairTone.violet),
                                      PairBadge(context.t('mobile_admin_mod_reports', {'count': r.reports})),
                                      if (r.hidden) PairBadge(context.t('mobile_admin_mod_hidden'), tone: PairTone.warning),
                                    ],
                                  ),
                                  const SizedBox(height: XmSpace.s1),
                                  Text(r.excerpt ?? '', maxLines: 2, overflow: TextOverflow.ellipsis, style: theme.textTheme.bodyMedium),
                                  if (r.reportedUser != null) Text(r.reportedUser!, style: theme.textTheme.bodySmall),
                                ],
                              ),
                            ),
                          ),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

String _kind(BuildContext c, String kind) {
  final key = 'mobile_admin_mod_kind_$kind';
  return AppStrings.of(c).has(key) ? c.t(key) : kind;
}

/// Contenu signalé (A10) : masquer, conserver ou avertir ; rendre visible un
/// contenu masqué. Chaque décision est confirmée et écrite au journal d'audit.
class AdminReportPage extends ConsumerStatefulWidget {
  const AdminReportPage({super.key, required this.id});
  final String id;

  @override
  ConsumerState<AdminReportPage> createState() => _AdminReportPageState();
}

class _AdminReportPageState extends ConsumerState<AdminReportPage> {
  bool _busy = false;

  void _refresh() {
    ref.invalidate(adminReportProvider(widget.id));
    ref.invalidate(adminReportsProvider('pending'));
    ref.invalidate(adminReportsProvider('handled'));
    ref.invalidate(adminDashboardProvider);
    ref.invalidate(adminDutyProvider);
  }

  Future<void> _decide(String action, {required String doneKey, String? reason}) async {
    setState(() => _busy = true);
    final ok = await runAdmin(context, ref, () => ref.read(adminApiProvider).decide(widget.id, action, reason: reason), done: doneKey);
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok) {
      _refresh();
      context.pop();
    }
  }

  Future<void> _hide() async {
    final ok = await confirmAdmin(
      context,
      title: context.t('mobile_admin_mod_hide_title'),
      text: context.t('mobile_admin_mod_hide_text'),
      yes: context.t('mobile_admin_mod_hide_yes'),
      danger: true,
    );
    if (ok) await _decide('hide', doneKey: 'mobile_admin_mod_done_hide');
  }

  Future<void> _keep() async {
    final ok = await confirmAdmin(
      context,
      title: context.t('mobile_admin_mod_keep_title'),
      text: context.t('mobile_admin_mod_keep_text'),
      yes: context.t('mobile_admin_mod_keep_yes'),
    );
    if (ok) await _decide('keep', doneKey: 'mobile_admin_mod_done_keep');
  }

  Future<void> _warn() async {
    final reason = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (_) => ReasonSheet(
        title: context.t('mobile_admin_mod_warn_title'),
        text: context.t('mobile_admin_mod_warn_text'),
        label: context.t('mobile_admin_reg_reject_reason'),
        yes: context.t('mobile_admin_mod_warn_yes'),
        shortKey: 'mobile_admin_reg_reject_reason_short',
      ),
    );
    if (reason != null && mounted) await _decide('warn', doneKey: 'mobile_admin_mod_done_warn', reason: reason);
  }

  Future<void> _restore() async {
    setState(() => _busy = true);
    final ok = await runAdmin(context, ref, () => ref.read(adminApiProvider).restore(widget.id), done: 'mobile_admin_mod_done_restore');
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok) _refresh();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(adminReportProvider(widget.id));
    final theme = Theme.of(context);
    final offline = ref.watch(adminOfflineProvider);
    return AdminDetailScaffold(
      title: context.t('mobile_admin_mod_detail_title'),
      child: state.when(
        loading: () => const LoadingView(),
        error: (e, _) {
          final api = ApiException.from(e);
          if (api.status == 404 || api.status == 403) return EmptyView(context.t('mobile_admin_mod_gone'));
          return AdminLoadError(error: e, onRetry: () => ref.invalidate(adminReportProvider(widget.id)));
        },
        data: (r) {
          final blocked = _busy || offline;
          return ListView(
            key: const ValueKey('admin-report'),
            padding: const EdgeInsets.all(XmSpace.gutter),
            children: [
              const AdminOfflineNotice(),
              Wrap(
                spacing: XmSpace.s2,
                runSpacing: XmSpace.s2,
                children: [
                  PairBadge(_kind(context, r.kind), tone: PairTone.violet),
                  PairBadge(context.t('mobile_admin_mod_reports', {'count': r.reports})),
                  if (r.hidden) PairBadge(context.t('mobile_admin_mod_hidden'), tone: PairTone.warning),
                ],
              ),
              const SizedBox(height: XmSpace.s3),
              DefinitionCard([
                (context.t('mobile_admin_mod_author'), r.reportedUser ?? ''),
                (context.t('mobile_admin_mod_reason'), r.reason ?? ''),
                (context.t('mobile_admin_mod_content'), r.excerpt ?? ''),
                (context.t('mobile_admin_mod_when'), r.createdAt == null ? '' : formatDateTime(context, r.createdAt!)),
              ]),
              const SizedBox(height: XmSpace.s4),
              if (!r.handled) ...[
                if (r.canHide)
                  Padding(
                    padding: const EdgeInsets.only(bottom: XmSpace.s2),
                    child: FilledButton(
                      key: const ValueKey('admin-mod-hide'),
                      style: FilledButton.styleFrom(
                        minimumSize: const Size.fromHeight(XmSpace.targetMin),
                        backgroundColor: context.xm.danger,
                        foregroundColor: context.xm.onSecondary,
                      ),
                      onPressed: blocked ? null : _hide,
                      child: Text(context.t('mobile_admin_mod_hide')),
                    ),
                  ),
                if (r.canDismiss)
                  Padding(
                    padding: const EdgeInsets.only(bottom: XmSpace.s2),
                    child: OutlinedButton(
                      key: const ValueKey('admin-mod-keep'),
                      style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(XmSpace.targetMin)),
                      onPressed: blocked ? null : _keep,
                      child: Text(context.t('mobile_admin_mod_keep')),
                    ),
                  ),
                if (r.canWarn)
                  Padding(
                    padding: const EdgeInsets.only(bottom: XmSpace.s2),
                    child: OutlinedButton(
                      key: const ValueKey('admin-mod-warn'),
                      style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(XmSpace.targetMin)),
                      onPressed: blocked ? null : _warn,
                      child: Text(context.t('mobile_admin_mod_warn')),
                    ),
                  ),
              ],
              if (r.hidden && r.canRestore)
                OutlinedButton(
                  key: const ValueKey('admin-mod-restore'),
                  style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(XmSpace.targetMin)),
                  onPressed: blocked ? null : _restore,
                  child: Text(context.t('mobile_admin_mod_restore')),
                ),
              const SizedBox(height: XmSpace.s3),
              Text(context.t('mobile_admin_mod_audit'), style: theme.textTheme.bodySmall),
              Text(context.t('mobile_admin_mod_suspend_web'), style: theme.textTheme.bodySmall),
            ],
          );
        },
      ),
    );
  }
}

// --- Maintenance (A5) --------------------------------------------------------------------------------

/// Mode maintenance : activation par confirmation forte (case, mot à saisir,
/// mot de passe `step-up`), désactivation par confirmation + `step-up`.
class AdminMaintenancePage extends ConsumerStatefulWidget {
  const AdminMaintenancePage({super.key});

  @override
  ConsumerState<AdminMaintenancePage> createState() => _AdminMaintenancePageState();
}

class _AdminMaintenancePageState extends ConsumerState<AdminMaintenancePage> {
  final _reason = TextEditingController();
  final _message = TextEditingController();
  final _word = TextEditingController();
  int? _duration = 60;
  bool _ack = false;
  bool _busy = false;

  @override
  void dispose() {
    _reason.dispose();
    _message.dispose();
    _word.dispose();
    super.dispose();
  }

  bool get _valid =>
      _reason.text.trim().length >= 3 &&
      _reason.text.trim().length <= 255 &&
      _message.text.length <= 280 &&
      _ack &&
      _word.text.trim() == 'MAINTENANCE';

  void _done() {
    ref.invalidate(adminMaintenanceProvider);
    ref.invalidate(adminDutyProvider);
    ref.invalidate(adminDashboardProvider);
  }

  Future<void> _enable() async {
    setState(() => _busy = true);
    final ok = await runWithStepUp(
      context,
      ref,
      (t) => ref.read(adminApiProvider).enableMaintenance(
        reason: _reason.text.trim(),
        durationMinutes: _duration,
        message: _message.text.trim(),
        stepUp: t,
      ),
      done: 'mobile_admin_maint_enabled',
      reasonKey: 'mobile_admin_maint_step_up',
    );
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok) {
      _reason.clear();
      _message.clear();
      _word.clear();
      _ack = false;
      _done();
    }
  }

  Future<void> _disable() async {
    final yes = await confirmAdmin(
      context,
      title: context.t('mobile_admin_maint_disable_title'),
      text: context.t('mobile_admin_maint_disable_text'),
      yes: context.t('mobile_admin_maint_disable_yes'),
    );
    if (!yes || !mounted) return;
    setState(() => _busy = true);
    final ok = await runWithStepUp(
      context,
      ref,
      (t) => ref.read(adminApiProvider).disableMaintenance(stepUp: t),
      done: 'mobile_admin_maint_disabled',
      reasonKey: 'mobile_admin_maint_step_up',
    );
    if (!mounted) return;
    setState(() => _busy = false);
    if (ok) _done();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(adminMaintenanceProvider);
    final theme = Theme.of(context);
    final offline = ref.watch(adminOfflineProvider);
    return AdminDetailScaffold(
      title: context.t('mobile_admin_maint_page_title'),
      child: state.when(
        loading: () => const LoadingView(),
        error: (e, _) => AdminLoadError(error: e, onRetry: () => ref.invalidate(adminMaintenanceProvider)),
        data: (m) => ListView(
          key: const ValueKey('admin-maintenance'),
          padding: const EdgeInsets.all(XmSpace.gutter),
          children: [
            const AdminOfflineNotice(),
            Text(context.t('mobile_admin_maint_intro'), style: theme.textTheme.bodySmall),
            const SizedBox(height: XmSpace.s3),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(XmSpace.s4),
                child: Semantics(
                  liveRegion: true,
                  container: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      PairBadge(
                        m.stateLabel ?? context.t(m.active ? 'mobile_admin_maint_ongoing' : 'mobile_admin_maint_inactive'),
                        tone: m.active ? PairTone.warning : PairTone.success,
                      ),
                      const SizedBox(height: XmSpace.s2),
                      Text(
                        context.t(m.active ? 'mobile_admin_maint_ongoing' : 'mobile_admin_maint_inactive'),
                        style: theme.textTheme.titleMedium,
                      ),
                      Text(
                        context.t(m.active ? 'mobile_admin_maint_ongoing_text' : 'mobile_admin_maint_inactive_text'),
                        style: theme.textTheme.bodyMedium,
                      ),
                      if (m.active)
                        Text(
                          m.endsAt == null
                              ? context.t('mobile_admin_maint_ends_unknown')
                              : context.t('mobile_admin_maint_ends', {
                                  'date': DateFormat('d MMM HH:mm', 'fr').format(m.endsAt!.toLocal()),
                                }),
                          style: theme.textTheme.bodySmall,
                        ),
                    ],
                  ),
                ),
              ),
            ),
            const SizedBox(height: XmSpace.s3),
            if (m.active) ...[
              Text(context.t('mobile_admin_maint_disable_text'), style: theme.textTheme.bodyMedium),
              const SizedBox(height: XmSpace.s2),
              FilledButton.icon(
                key: const ValueKey('admin-maint-disable'),
                style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(XmSpace.targetMin)),
                icon: const Icon(Icons.wifi),
                onPressed: _busy || offline ? null : _disable,
                label: Text(context.t('mobile_admin_maint_disable')),
              ),
            ] else ...[
              Text(context.t('mobile_admin_maint_live_text'), style: theme.textTheme.bodyMedium),
              const SizedBox(height: XmSpace.s3),
              XmAlert(
                '${context.t('mobile_admin_maint_confirm_title')} ${context.t('mobile_admin_maint_confirm_text')}',
                icon: Icons.warning_amber_rounded,
                tone: context.xm.warning,
              ),
              const SizedBox(height: XmSpace.s3),
              TextField(
                key: const ValueKey('admin-maint-reason'),
                controller: _reason,
                maxLength: 255,
                onChanged: (_) => setState(() {}),
                decoration: InputDecoration(labelText: context.t('mobile_admin_maint_reason')),
              ),
              DropdownButtonFormField<int?>(
                key: ValueKey('admin-maint-duration-$_duration'),
                initialValue: _duration,
                isExpanded: true,
                decoration: InputDecoration(labelText: context.t('mobile_admin_maint_duration')),
                items: [
                  for (final d in [15, 30, 60, 120, 240])
                    DropdownMenuItem<int?>(value: d, child: Text(context.t('mobile_admin_maint_duration_minutes', {'minutes': d}))),
                  DropdownMenuItem<int?>(value: null, child: Text(context.t('mobile_admin_maint_duration_unknown'))),
                ],
                onChanged: (v) => setState(() => _duration = v),
              ),
              const SizedBox(height: XmSpace.s3),
              TextField(
                key: const ValueKey('admin-maint-message'),
                controller: _message,
                maxLength: 280,
                maxLines: 3,
                minLines: 2,
                onChanged: (_) => setState(() {}),
                decoration: InputDecoration(labelText: context.t('mobile_admin_maint_message')),
              ),
              CheckboxListTile(
                key: const ValueKey('admin-maint-ack'),
                contentPadding: EdgeInsets.zero,
                controlAffinity: ListTileControlAffinity.leading,
                value: _ack,
                onChanged: (v) => setState(() => _ack = v ?? false),
                title: Text(context.t('mobile_admin_maint_ack')),
              ),
              TextField(
                key: const ValueKey('admin-maint-word'),
                controller: _word,
                autocorrect: false,
                textCapitalization: TextCapitalization.characters,
                onChanged: (_) => setState(() {}),
                decoration: InputDecoration(
                  labelText: context.t('mobile_admin_maint_word_label'),
                  helperText: context.t('mobile_admin_maint_prompt', {'word': context.t('mobile_admin_maint_word')}),
                  errorText: _word.text.isNotEmpty && _word.text.trim() != 'MAINTENANCE' ? context.t('mobile_admin_maint_mismatch') : null,
                ),
              ),
              const SizedBox(height: XmSpace.s3),
              FilledButton.icon(
                key: const ValueKey('admin-maint-enable'),
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(XmSpace.targetMin),
                  backgroundColor: context.xm.danger,
                  foregroundColor: context.xm.onSecondary,
                ),
                icon: const Icon(Icons.build_outlined),
                onPressed: _busy || offline || !_valid ? null : _enable,
                label: Text(context.t('mobile_admin_maint_enable_yes')),
              ),
            ],
            const SizedBox(height: XmSpace.s4),
            XmAlert(
              '${context.t('mobile_admin_maint_open_title')} ${context.t('mobile_admin_maint_open_text')}',
              icon: Icons.shield_outlined,
              tone: context.xm.success,
            ),
          ],
        ),
      ),
    );
  }
}

// --- Notifications (A6) ----------------------------------------------------------------------------------

/// Cloche de l'administration, restreinte aux catégories d'astreinte.
class AdminNotificationsPage extends ConsumerWidget {
  const AdminNotificationsPage({super.key});

  static const categories = ['', 'sos', 'alertes', 'moderation', 'systeme', 'securite'];

  String? _route(String category) => switch (category) {
    'sos' || 'alertes' => '/admin/alertes',
    'moderation' => '/admin/moderation',
    _ => null,
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final filter = ref.watch(adminNotifFilterProvider);
    final category = ref.watch(adminNotifCategoryProvider);
    final state = ref.watch(adminNotificationsProvider);
    final theme = Theme.of(context);
    return SafeArea(
      child: RefreshIndicator(
        onRefresh: () async => ref.invalidate(adminNotificationsProvider),
        child: ListView(
          key: const ValueKey('admin-notifications'),
          padding: const EdgeInsets.all(XmSpace.gutter),
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            AdminHeader(title: context.t('mobile_admin_notif_title')),
            const SizedBox(height: XmSpace.s3),
            const AdminOfflineNotice(),
            Row(
              children: [
                Expanded(
                  child: SegmentedButton<String>(
                    segments: [
                      ButtonSegment(value: 'all', label: Text(context.t('mobile_admin_notif_all'))),
                      ButtonSegment(value: 'unread', label: Text(context.t('mobile_admin_notif_unread'))),
                    ],
                    selected: {filter},
                    onSelectionChanged: (s) => ref.read(adminNotifFilterProvider.notifier).state = s.first,
                  ),
                ),
              ],
            ),
            const SizedBox(height: XmSpace.s2),
            Wrap(
              spacing: XmSpace.s2,
              children: [
                for (final c in categories)
                  ChoiceChip(
                    key: ValueKey('admin-notif-cat-${c.isEmpty ? 'all' : c}'),
                    label: Text(context.t('mobile_admin_notif_cat_${c.isEmpty ? 'all' : c}')),
                    selected: category == c,
                    materialTapTargetSize: MaterialTapTargetSize.padded,
                    onSelected: (_) => ref.read(adminNotifCategoryProvider.notifier).state = c,
                  ),
              ],
            ),
            TextButton.icon(
              key: const ValueKey('admin-notif-read-all'),
              style: TextButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
              icon: const Icon(Icons.done_all),
              label: Text(context.t('mobile_admin_notif_mark_all')),
              onPressed: () async {
                final ok = await runAdmin(context, ref, () => ref.read(adminApiProvider).markAllNotificationsRead(), done: 'mobile_admin_notif_marked');
                if (ok) ref.invalidate(adminNotificationsProvider);
              },
            ),
            state.when(
              loading: () => const Padding(padding: EdgeInsets.all(XmSpace.s6), child: LoadingView()),
              error: (e, _) => AdminLoadError(error: e, onRetry: () => ref.invalidate(adminNotificationsProvider)),
              data: (rows) {
                final items = [for (final r in rows) NotificationItem.fromJson(r)];
                if (items.isEmpty) return EmptyView(context.t('mobile_admin_notif_empty'));
                return Column(
                  children: [
                    for (final n in items)
                      Semantics(
                        label: n.read ? null : context.t('mobile_admin_notif_unread_label'),
                        child: ListTile(
                          key: ValueKey('admin-notif-${n.id}'),
                          minTileHeight: 56,
                          contentPadding: EdgeInsets.zero,
                          leading: Icon(
                            n.category == 'sos' ? Icons.sos : Icons.notifications_outlined,
                            color: n.read ? context.xm.textMuted : context.xm.primary,
                          ),
                          title: Text(n.title, style: n.read ? theme.textTheme.bodyMedium : theme.textTheme.labelLarge),
                          subtitle: Text(
                            [
                              if (n.body != null && n.body!.isNotEmpty) n.body!,
                              if (n.createdAt != null) relativeTime(context, n.createdAt!),
                            ].join('\n'),
                          ),
                          trailing: n.read
                              ? null
                              : Container(
                                  width: 10,
                                  height: 10,
                                  decoration: BoxDecoration(color: context.xm.primary, shape: BoxShape.circle),
                                ),
                          onTap: () async {
                            final route = _route(n.category);
                            if (!n.read) {
                              try {
                                await ref.read(homeApiProvider).markRead(n.id);
                              } catch (_) {}
                              ref.invalidate(adminNotificationsProvider);
                            }
                            if (route != null && context.mounted) context.go(route);
                          },
                        ),
                      ),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}
