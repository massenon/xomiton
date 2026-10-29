import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/platform/platform_services.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../../core/util/dates.dart';
import '../application/admin_providers.dart';
import '../data/admin_models.dart';
import 'admin_duty_page.dart' show escalationText;
import 'admin_ui.dart';

const _typeTabs = ['all', 'sos', 'report', 'need'];
const _statusChoices = ['', 'all', 'nouveau', 'pris_en_charge', 'en_cours', 'traite', 'clos'];

/// Alertes & signalements (maquette A2) : onglets de type, statut, recherche
/// dans la liste chargée, accusé direct. Relue toutes les 10 s au premier plan.
class AdminAlertsPage extends ConsumerStatefulWidget {
  const AdminAlertsPage({super.key});

  @override
  ConsumerState<AdminAlertsPage> createState() => _AdminAlertsPageState();
}

class _AdminAlertsPageState extends ConsumerState<AdminAlertsPage> with WidgetsBindingObserver, AdminPolling {
  final Set<String> _busy = {};
  final _search = TextEditingController();

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _ack(AdminAlert a) async {
    setState(() => _busy.add(a.id));
    await runAdmin(context, ref, () => ref.read(adminAlertsProvider.notifier).acknowledge(a.id), done: 'mobile_admin_ack_done');
    if (mounted) setState(() => _busy.remove(a.id));
  }

  String _tabLabel(BuildContext c, String t) => c.t('mobile_admin_tab_type_$t');

  String _statusLabel(BuildContext c, String s) => switch (s) {
    '' => c.t('mobile_admin_status_open'),
    'all' => c.t('mobile_admin_status_all'),
    _ => c.alertStatusLabel(s),
  };

  @override
  Widget build(BuildContext context) {
    final filter = ref.watch(adminAlertFilterProvider);
    final state = ref.watch(adminAlertsProvider);
    if (state.hasValue) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) schedulePolling(kAdminAlertsPollSeconds, ref.read(adminAlertsProvider.notifier).refresh);
      });
    }
    final theme = Theme.of(context);
    return SafeArea(
      child: RefreshIndicator(
        onRefresh: () => ref.read(adminAlertsProvider.notifier).refresh(),
        child: ListView(
          key: const ValueKey('admin-alerts'),
          padding: const EdgeInsets.all(XmSpace.gutter),
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            AdminHeader(title: context.t('mobile_admin_alerts_title')),
            const SizedBox(height: XmSpace.s3),
            const AdminOfflineNotice(),
            Semantics(
              container: true,
              label: context.t('mobile_admin_alerts_types'),
              child: Wrap(
                spacing: XmSpace.s2,
                runSpacing: XmSpace.s2,
                children: [
                  for (final t in _typeTabs)
                    ChoiceChip(
                      key: ValueKey('admin-type-$t'),
                      label: Text(_tabLabel(context, t)),
                      selected: filter.type == t,
                      materialTapTargetSize: MaterialTapTargetSize.padded,
                      onSelected: (_) => ref.read(adminAlertFilterProvider.notifier).state = filter.copyWith(type: t),
                    ),
                ],
              ),
            ),
            const SizedBox(height: XmSpace.s3),
            DropdownButtonFormField<String>(
              key: ValueKey('admin-status-${filter.status}'),
              initialValue: filter.status,
              isExpanded: true,
              decoration: InputDecoration(labelText: context.t('mobile_admin_status_label')),
              items: [
                for (final s in _statusChoices) DropdownMenuItem(value: s, child: Text(_statusLabel(context, s))),
              ],
              onChanged: (v) => ref.read(adminAlertFilterProvider.notifier).state = filter.copyWith(status: v ?? ''),
            ),
            const SizedBox(height: XmSpace.s3),
            TextField(
              key: const ValueKey('admin-alert-search'),
              controller: _search,
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                labelText: context.t('mobile_admin_alerts_search'),
                hintText: context.t('mobile_admin_alerts_search_hint'),
                prefixIcon: const Icon(Icons.search),
              ),
              onChanged: (v) => ref.read(adminAlertFilterProvider.notifier).state = filter.copyWith(query: v),
            ),
            const SizedBox(height: XmSpace.s3),
            state.when(
              loading: () => const Padding(padding: EdgeInsets.all(XmSpace.s6), child: LoadingView()),
              error: (e, _) => AdminLoadError(error: e, onRetry: () => ref.invalidate(adminAlertsProvider)),
              data: (page) {
                final q = filter.query.trim().toLowerCase();
                final items = q.isEmpty
                    ? page.items
                    : [
                        for (final a in page.items)
                          if ('${a.code ?? ''} ${a.place} ${a.zone ?? ''} ${a.commune ?? ''} ${a.routedTo ?? ''}'.toLowerCase().contains(q)) a,
                      ];
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Semantics(
                      liveRegion: true,
                      child: Text(
                        context.t('mobile_admin_alerts_count', {'count': items.length}),
                        style: theme.textTheme.titleMedium,
                      ),
                    ),
                    Text(context.t('mobile_admin_alerts_poll', {'seconds': kAdminAlertsPollSeconds}), style: theme.textTheme.bodySmall),
                    const SizedBox(height: XmSpace.s3),
                    if (items.isEmpty)
                      Column(
                        children: [
                          EmptyView(context.t('mobile_admin_alerts_empty')),
                          if (filter.active)
                            TextButton(
                              key: const ValueKey('admin-alerts-reset'),
                              style: TextButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
                              onPressed: () {
                                _search.clear();
                                ref.read(adminAlertFilterProvider.notifier).state = const AdminAlertFilter();
                              },
                              child: Text(context.t('mobile_admin_alerts_reset')),
                            ),
                        ],
                      )
                    else
                      for (final a in items)
                        Padding(
                          padding: const EdgeInsets.only(bottom: XmSpace.s3),
                          child: _AlertCard(alert: a, busy: _busy.contains(a.id), onAck: () => _ack(a)),
                        ),
                    if (page.hasMore)
                      OutlinedButton(
                        key: const ValueKey('admin-alerts-more'),
                        style: OutlinedButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
                        onPressed: () => ref.read(adminAlertsProvider.notifier).loadMore(),
                        child: Text(context.t('mobile_feed_load_more')),
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

class _AlertCard extends StatelessWidget {
  const _AlertCard({required this.alert, required this.busy, required this.onAck});
  final AdminAlert alert;
  final bool busy;
  final VoidCallback onAck;

  @override
  Widget build(BuildContext context) {
    final a = alert;
    final theme = Theme.of(context);
    final type = context.alertTypeLabel(a.type);
    final esc = escalationText(context, a);
    return Semantics(
      container: true,
      label: '$type, ${a.place}',
      child: Card(
        key: ValueKey('admin-alert-${a.id}'),
        shape: a.isSos && a.isNew
            ? RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(XmSpace.radiusLg),
                side: BorderSide(color: context.xm.danger, width: 2),
              )
            : null,
        child: Padding(
          padding: const EdgeInsets.all(XmSpace.s4),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Wrap(
                spacing: XmSpace.s2,
                runSpacing: XmSpace.s2,
                children: [
                  PairBadge(type, tone: a.isSos ? PairTone.danger : PairTone.violet),
                  PairBadge(context.alertStatusLabel(a.status), tone: statusTone(a.status)),
                  if (a.priority != 'normale') PairBadge(context.priorityLabel(a.priority), tone: priorityTone(a.priority)),
                  if (a.escalated) PairBadge(context.t('mobile_admin_escalated'), tone: PairTone.warning),
                ],
              ),
              const SizedBox(height: XmSpace.s2),
              Text(a.place.isEmpty ? type : '$type · ${a.place}', style: theme.textTheme.titleSmall),
              const SizedBox(height: XmSpace.s1),
              Wrap(
                spacing: XmSpace.s3,
                children: [
                  if (a.ageMinutes != null)
                    Text(context.t('mobile_admin_age_min', {'minutes': a.ageMinutes}), style: theme.textTheme.bodySmall),
                  Text(
                    a.routedTo == null
                        ? context.t('mobile_admin_not_routed')
                        : context.t('mobile_admin_routed', {'name': a.routedTo}),
                    style: theme.textTheme.bodySmall,
                  ),
                ],
              ),
              if (esc.isNotEmpty && a.isSos) Text(esc, style: theme.textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w700)),
              const SizedBox(height: XmSpace.s3),
              Wrap(
                spacing: XmSpace.s2,
                runSpacing: XmSpace.s2,
                children: [
                  if (!a.acknowledged && a.isOpen)
                    FilledButton(
                      key: ValueKey('admin-ack-${a.id}'),
                      style: FilledButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
                      onPressed: busy ? null : onAck,
                      child: Text(context.t('mobile_admin_acknowledge')),
                    ),
                  OutlinedButton(
                    key: ValueKey('admin-detail-${a.id}'),
                    style: OutlinedButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
                    onPressed: () => context.push('/admin/alertes/${a.id}'),
                    child: Semantics(
                      label: context.t('mobile_admin_detail_for', {'type': type, 'place': a.place}),
                      excludeSemantics: true,
                      child: Text(context.t('mobile_admin_detail')),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Détail d'une alerte (maquette A3) : tout ce qu'il faut pour agir ; les
/// boutons suivent `can{…}` du serveur. Position ARRONDIE, avertissement
/// avant Maps, personnes prévenues, historique et notes internes.
class AdminAlertDetailPage extends ConsumerStatefulWidget {
  const AdminAlertDetailPage({super.key, required this.id});
  final String id;

  @override
  ConsumerState<AdminAlertDetailPage> createState() => _AdminAlertDetailPageState();
}

class _AdminAlertDetailPageState extends ConsumerState<AdminAlertDetailPage> {
  bool _busy = false;

  AdminAlertController get _ctl => ref.read(adminAlertProvider(widget.id).notifier);

  Future<void> _do(Future<void> Function() action, String doneKey) async {
    setState(() => _busy = true);
    await runAdmin(context, ref, action, done: doneKey);
    if (mounted) setState(() => _busy = false);
  }

  Future<void> _openMaps(AdminPosition p) async {
    final ok = await confirmAdmin(
      context,
      title: context.t('mobile_admin_maps_title'),
      text: context.t('mobile_admin_maps_text'),
      yes: context.t('mobile_admin_maps_go'),
    );
    if (!ok) return;
    await ref.read(linkLauncherProvider).open(
      Uri.parse('https://www.google.com/maps/search/?api=1&query=${p.lat},${p.lng}'),
    );
  }

  Future<void> _chooseStatus(AdminAlertDetail d) async {
    final options = d.can.statuses;
    final picked = await showModalBottomSheet<String>(
      context: context,
      useSafeArea: true,
      showDragHandle: true,
      builder: (ctx) => SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(XmSpace.gutter, 0, XmSpace.gutter, XmSpace.s4),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Semantics(header: true, child: Text(ctx.t('mobile_admin_status_choose'), style: Theme.of(ctx).textTheme.titleLarge)),
            const SizedBox(height: XmSpace.s2),
            for (final s in options)
              ListTile(
                key: ValueKey('admin-status-opt-$s'),
                minTileHeight: 48,
                title: Text(ctx.alertStatusLabel(s)),
                selected: s == d.summary.status,
                onTap: () => Navigator.pop(ctx, s),
              ),
          ],
        ),
      ),
    );
    if (picked == null || !mounted) return;
    await _do(() => _ctl.setStatus(picked), 'mobile_admin_status_changed');
  }

  Future<void> _addNote() async {
    final body = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (_) => const _NoteSheet(),
    );
    if (body == null || !mounted) return;
    await _do(() => _ctl.addNote(body), 'mobile_admin_note_added');
  }

  Future<void> _reassign(AdminAlertDetail d) async {
    final contact = await showModalBottomSheet<AdminContact>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (ctx) => _ContactPicker(alertId: widget.id),
    );
    if (contact == null || !mounted) return;
    await runWithStepUp(
      context,
      ref,
      (token) => _ctl.reassign(contact.id, token),
      done: 'mobile_admin_reassigned',
      reasonKey: 'mobile_admin_reassign_step_up',
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(adminAlertProvider(widget.id));
    return AdminDetailScaffold(
      title: context.t('mobile_admin_alert_title'),
      child: state.when(
        loading: () => const LoadingView(),
        error: (e, _) {
          final api = ApiException.from(e);
          if (api.status == 403 || api.status == 404) return EmptyView(context.t('mobile_admin_alert_gone'));
          return AdminLoadError(error: e, onRetry: () => ref.invalidate(adminAlertProvider(widget.id)));
        },
        data: _detail,
      ),
    );
  }

  Widget _detail(AdminAlertDetail d) {
    final s = d.summary;
    final theme = Theme.of(context);
    final online = ref.watch(isOnlineProvider);
    final type = context.alertTypeLabel(s.type);
    final f = NumberFormat('0.00', 'fr');
    final rows = <(String, String)>[
      (context.t('mobile_admin_field_code'), s.code ?? ''),
      (context.t('mobile_admin_field_zone'), [s.commune, s.zone].whereType<String>().where((x) => x.isNotEmpty).join(' · ')),
      (context.t('mobile_admin_field_category'), d.category ?? ''),
      (context.t('mobile_admin_field_needs'), d.needs.join(', ')),
      (context.t('mobile_admin_field_description'), d.description ?? ''),
      (
        context.t('mobile_admin_field_author'),
        d.author ?? context.t('mobile_admin_anonymous'),
      ),
      (
        context.t('mobile_admin_field_contact'),
        d.contact == null || d.contact!.isEmpty
            ? context.t('mobile_admin_no_contact')
            : (d.preferredChannel == null ? d.contact! : '${d.contact} (${d.preferredChannel})'),
      ),
      (
        context.t('mobile_admin_field_routed'),
        s.routedTo ?? context.t('mobile_admin_not_routed'),
      ),
    ];
    final esc = escalationText(context, s);
    return ListView(
      key: const ValueKey('admin-alert-detail'),
      padding: const EdgeInsets.all(XmSpace.gutter),
      children: [
        const AdminOfflineNotice(),
        Wrap(
          spacing: XmSpace.s2,
          runSpacing: XmSpace.s2,
          children: [
            PairBadge(type, tone: s.isSos ? PairTone.danger : PairTone.violet),
            PairBadge(context.alertStatusLabel(s.status), tone: statusTone(s.status)),
            if (s.priority != 'normale') PairBadge(context.priorityLabel(s.priority), tone: priorityTone(s.priority)),
            if (s.acknowledged) PairBadge(context.t('mobile_admin_ack_done_badge'), tone: PairTone.success, icon: Icons.check),
          ],
        ),
        const SizedBox(height: XmSpace.s3),
        if (s.isSos && esc.isNotEmpty) ...[
          XmAlert(esc, icon: Icons.warning_amber_rounded, tone: context.xm.warning),
          const SizedBox(height: XmSpace.s3),
        ],
        if (!s.acknowledged && s.isOpen) ...[
          XmAlert(context.t('mobile_admin_take_help'), icon: Icons.info_outline, tone: context.xm.info),
          const SizedBox(height: XmSpace.s3),
        ],
        DefinitionCard(rows),
        const SizedBox(height: XmSpace.s2),
        if (d.position != null) ...[
          DefinitionCard([
            (
              context.t('mobile_admin_field_position'),
              '${f.format(d.position!.lat.abs())}° ${d.position!.lat >= 0 ? 'N' : 'S'}, '
                  '${f.format(d.position!.lng.abs())}° ${d.position!.lng >= 0 ? 'E' : 'O'}'
                  ' · ${context.t('mobile_admin_gps_rounded')}'
                  '${d.position!.precisionM == null ? '' : ' · ${context.t('mobile_admin_accuracy', {'meters': d.position!.precisionM})}'}',
            ),
          ]),
          const SizedBox(height: XmSpace.s2),
          OutlinedButton.icon(
            key: const ValueKey('admin-maps'),
            style: OutlinedButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
            icon: const Icon(Icons.map_outlined),
            label: Text(context.t('mobile_admin_maps')),
            onPressed: () => _openMaps(d.position!),
          ),
          Padding(
            padding: const EdgeInsets.only(top: XmSpace.s1),
            child: Text(context.t('mobile_admin_maps_help'), style: theme.textTheme.bodySmall),
          ),
        ] else
          Text(context.t('mobile_admin_no_gps'), style: theme.textTheme.bodySmall),
        const SizedBox(height: XmSpace.s4),
        SectionHeading(context.t('mobile_admin_notified')),
        const SizedBox(height: XmSpace.s2),
        if (d.notified.isEmpty)
          Card(child: Padding(padding: const EdgeInsets.all(XmSpace.s4), child: Text(context.t('mobile_admin_notified_empty'))))
        else
          Card(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: XmSpace.s3, vertical: XmSpace.s1),
              child: Column(
                children: [
                  for (final n in d.notified)
                    ListTile(
                      key: ValueKey('admin-notified-${n.key}'),
                      contentPadding: EdgeInsets.zero,
                      minTileHeight: 48,
                      title: Text(n.label),
                      subtitle: n.stateLabel == null ? null : Text(n.stateLabel!),
                      trailing: n.phone == null
                          ? null
                          : IconButton(
                              key: ValueKey('admin-call-${n.key}'),
                              tooltip: context.t('mobile_admin_call', {'name': n.label}),
                              constraints: const BoxConstraints(minWidth: XmSpace.targetMin, minHeight: XmSpace.targetMin),
                              icon: const Icon(Icons.call_outlined),
                              onPressed: () => ref.read(linkLauncherProvider).open(telUri(n.phone!)),
                            ),
                    ),
                ],
              ),
            ),
          ),
        const SizedBox(height: XmSpace.s4),
        SectionHeading(context.t('mobile_admin_history')),
        const SizedBox(height: XmSpace.s2),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(XmSpace.s3),
            child: Column(
              children: [
                for (final h in d.history)
                  ListTile(
                    dense: true,
                    leading: const Icon(Icons.check_circle_outline, size: 20),
                    title: Text(h.label),
                    subtitle: h.at == null ? null : Text(formatDateTime(context, h.at!)),
                  ),
                for (final n in d.notes)
                  ListTile(
                    key: ValueKey('admin-note-${n.id}'),
                    dense: true,
                    leading: const Icon(Icons.sticky_note_2_outlined, size: 20),
                    title: Text(n.body),
                    subtitle: Text([n.author, if (n.at != null) formatDateTime(context, n.at!)].where((x) => x.isNotEmpty).join(' · ')),
                  ),
                if (d.history.isEmpty && d.notes.isEmpty) Text(context.t('mobile_admin_history_empty')),
              ],
            ),
          ),
        ),
        const SizedBox(height: XmSpace.s4),
        if (d.can.acknowledge)
          Padding(
            padding: const EdgeInsets.only(bottom: XmSpace.s2),
            child: FilledButton(
              key: const ValueKey('admin-act-ack'),
              style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(XmSpace.targetMin)),
              onPressed: _busy || !online ? null : () => _do(_ctl.acknowledge, 'mobile_admin_ack_done'),
              child: Text(context.t('mobile_admin_acknowledge')),
            ),
          ),
        if (d.can.take && s.status != 'en_cours')
          Padding(
            padding: const EdgeInsets.only(bottom: XmSpace.s2),
            child: (d.can.acknowledge ? OutlinedButton.new : FilledButton.new)(
              key: const ValueKey('admin-act-take'),
              style: ButtonStyle(minimumSize: WidgetStateProperty.all(const Size.fromHeight(XmSpace.targetMin))),
              onPressed: _busy || !online ? null : () => _do(_ctl.take, 'mobile_admin_taken'),
              child: Text(context.t('mobile_admin_take')),
            ),
          ),
        if (d.can.statuses.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(bottom: XmSpace.s2),
            child: OutlinedButton(
              key: const ValueKey('admin-act-status'),
              style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(XmSpace.targetMin)),
              onPressed: _busy || !online ? null : () => _chooseStatus(d),
              child: Text(context.t('mobile_admin_status_change')),
            ),
          ),
        if (d.can.reassign)
          Padding(
            padding: const EdgeInsets.only(bottom: XmSpace.s2),
            child: OutlinedButton(
              key: const ValueKey('admin-act-reassign'),
              style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(XmSpace.targetMin)),
              onPressed: _busy || !online ? null : () => _reassign(d),
              child: Text(context.t('mobile_admin_reassign')),
            ),
          ),
        if (d.can.note)
          Padding(
            padding: const EdgeInsets.only(bottom: XmSpace.s2),
            child: OutlinedButton(
              key: const ValueKey('admin-act-note'),
              style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(XmSpace.targetMin)),
              onPressed: _busy || !online ? null : _addNote,
              child: Text(context.t('mobile_admin_note')),
            ),
          ),
        if (d.can.close)
          FilledButton(
            key: const ValueKey('admin-act-close'),
            style: FilledButton.styleFrom(
              minimumSize: const Size.fromHeight(XmSpace.targetMin),
              backgroundColor: context.xm.danger,
              foregroundColor: context.xm.onSecondary,
            ),
            onPressed: _busy || !online
                ? null
                : () async {
                    final ok = await confirmAdmin(
                      context,
                      title: context.t('mobile_admin_close_title'),
                      text: context.t('mobile_admin_close_text'),
                      yes: context.t('mobile_admin_close_yes'),
                      danger: true,
                    );
                    if (ok) await _do(_ctl.close, 'mobile_admin_closed');
                  },
            child: Text(context.t('mobile_admin_close')),
          ),
      ],
    );
  }
}

/// Annuaire proposé à la réattribution : le département de l'alerte d'abord,
/// « Toutes les zones » à la demande.
class _ContactPicker extends ConsumerStatefulWidget {
  const _ContactPicker({required this.alertId});
  final String alertId;

  @override
  ConsumerState<_ContactPicker> createState() => _ContactPickerState();
}

class _ContactPickerState extends ConsumerState<_ContactPicker> {
  bool _all = false;

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(adminContactsProvider((widget.alertId, _all)));
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(XmSpace.gutter, 0, XmSpace.gutter, XmSpace.s4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Semantics(header: true, child: Text(context.t('mobile_admin_reassign_title'), style: Theme.of(context).textTheme.titleLarge)),
          const SizedBox(height: XmSpace.s1),
          Text(context.t('mobile_admin_reassign_help'), style: Theme.of(context).textTheme.bodySmall),
          SwitchListTile(
            key: const ValueKey('admin-contacts-all'),
            contentPadding: EdgeInsets.zero,
            title: Text(context.t('mobile_admin_reassign_all_zones')),
            value: _all,
            onChanged: (v) => setState(() => _all = v),
          ),
          state.when(
            loading: () => const Padding(padding: EdgeInsets.all(XmSpace.s5), child: LoadingView()),
            error: (e, _) => AdminLoadError(error: e, onRetry: () => ref.invalidate(adminContactsProvider((widget.alertId, _all)))),
            data: (list) => list.isEmpty
                ? EmptyView(context.t('mobile_admin_reassign_empty'))
                : Column(
                    children: [
                      for (final c in list)
                        ListTile(
                          key: ValueKey('admin-contact-${c.id}'),
                          minTileHeight: 52,
                          contentPadding: EdgeInsets.zero,
                          title: Text(c.name),
                          subtitle: Text([c.role, c.zone].whereType<String>().where((x) => x.isNotEmpty).join(' · ')),
                          onTap: () => Navigator.pop(context, c),
                        ),
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}

/// Saisie d'une note interne (le contrôleur vit avec la feuille).
class _NoteSheet extends StatefulWidget {
  const _NoteSheet();

  @override
  State<_NoteSheet> createState() => _NoteSheetState();
}

class _NoteSheetState extends State<_NoteSheet> {
  final _text = TextEditingController();

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext ctx) => Padding(
    padding: EdgeInsets.fromLTRB(XmSpace.gutter, 0, XmSpace.gutter, MediaQuery.viewInsetsOf(ctx).bottom + XmSpace.s4),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Semantics(header: true, child: Text(ctx.t('mobile_admin_note_title'), style: Theme.of(ctx).textTheme.titleLarge)),
        const SizedBox(height: XmSpace.s3),
        TextField(
          key: const ValueKey('admin-note-field'),
          controller: _text,
          maxLines: 4,
          minLines: 3,
          decoration: InputDecoration(labelText: ctx.t('mobile_admin_note_label'), hintText: ctx.t('mobile_admin_note_placeholder')),
        ),
        const SizedBox(height: XmSpace.s3),
        FilledButton(
          key: const ValueKey('admin-note-add'),
          style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(XmSpace.targetMin)),
          onPressed: () {
            final v = _text.text.trim();
            if (v.isEmpty) {
              showSnackText(ctx, ctx.t('mobile_admin_note_empty'));
              return;
            }
            Navigator.pop(ctx, v);
          },
          child: Text(ctx.t('mobile_admin_note_add')),
        ),
      ],
    ),
  );
}
