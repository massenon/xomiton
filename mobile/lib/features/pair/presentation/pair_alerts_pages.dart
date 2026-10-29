import 'dart:async';

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
import '../application/pair_providers.dart';
import '../data/pair_models.dart';
import 'pair_ui.dart';

/// Alertes routées vers le pair (maquette P2) : liste relue toutes les 10 s
/// (de garde) ou 30 s au premier plan ; accusé direct depuis la liste.
class PairAlertsPage extends ConsumerStatefulWidget {
  const PairAlertsPage({super.key});

  @override
  ConsumerState<PairAlertsPage> createState() => _PairAlertsPageState();
}

class _PairAlertsPageState extends ConsumerState<PairAlertsPage> with WidgetsBindingObserver {
  Timer? _timer;
  int? _pollSeconds;
  String? _announce;
  final Set<String> _busy = {};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && ref.read(isOnlineProvider)) {
      ref.read(peerAlertsProvider.notifier).refresh();
    }
  }

  void _schedule(bool onDuty) {
    if (!ref.read(peerPollEnabledProvider)) return;
    final secs = peerPollSeconds(onDuty: onDuty);
    if (_pollSeconds == secs && _timer != null) return;
    _timer?.cancel();
    _pollSeconds = secs;
    _timer = Timer.periodic(Duration(seconds: secs), (_) {
      if (ref.read(isOnlineProvider)) ref.read(peerAlertsProvider.notifier).refresh();
    });
  }

  Future<void> _ack(PeerAlertSummary a) async {
    setState(() => _busy.add(a.id));
    try {
      await ref.read(peerAlertsProvider.notifier).acknowledge(a.id);
      if (mounted) showSnack(context, context.t('peer_alerts_acknowledged'));
    } catch (e) {
      if (mounted) showSnack(context, apiMessage(context, e));
    } finally {
      if (mounted) setState(() => _busy.remove(a.id));
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(peerAlertsProvider);
    ref.listen<AsyncValue<PeerAlertsPage>>(peerAlertsProvider, (prev, next) {
      final before = prev?.valueOrNull;
      final after = next.valueOrNull;
      if (after == null) return;
      _schedule(after.onDuty ?? false);
      if (before != null) {
        final known = before.items.map((a) => a.id).toSet();
        final fresh = after.items.where((a) => !known.contains(a.id)).length;
        if (fresh > 0) setState(() => _announce = context.t('peer_alerts_announce_new', {'count': fresh}));
      }
    });
    if (state.hasValue && _timer == null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _schedule(state.valueOrNull?.onDuty ?? false);
      });
    }
    final theme = Theme.of(context);
    return SafeArea(
      child: RefreshIndicator(
        onRefresh: () => ref.read(peerAlertsProvider.notifier).refresh(),
        child: ListView(
          key: const ValueKey('pair-alerts'),
          padding: const EdgeInsets.all(XmSpace.gutter),
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            PairHeader(title: context.t('peer_alerts_title')),
            const SizedBox(height: XmSpace.s3),
            state.when(
              loading: () => const Padding(padding: EdgeInsets.all(XmSpace.s6), child: LoadingView()),
              error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(peerAlertsProvider)),
              data: (page) {
                final open = page.open.length;
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            '$open ${context.t('peer_alerts_open_count', {'count': open})}',
                            style: theme.textTheme.titleMedium,
                          ),
                        ),
                        OutlinedButton.icon(
                          key: const ValueKey('alerts-refresh'),
                          style: OutlinedButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
                          onPressed: () => ref.read(peerAlertsProvider.notifier).refresh(),
                          icon: const Icon(Icons.sync, size: 18),
                          label: Text(context.t('peer_alerts_refresh')),
                        ),
                      ],
                    ),
                    const SizedBox(height: XmSpace.s2),
                    Text(
                      (page.onDuty ?? false)
                          ? context.t('peer_alerts_on_duty', {'seconds': peerPollSeconds(onDuty: true)})
                          : context.t('mobile_pair_alerts_poll', {'seconds': peerPollSeconds(onDuty: false)}),
                      style: theme.textTheme.bodySmall,
                    ),
                    if (page.stale) ...[
                      const SizedBox(height: XmSpace.s2),
                      XmAlert(context.t('peer_alerts_stale'), icon: Icons.cloud_off_outlined, tone: context.xm.warning),
                    ],
                    if (_announce != null) ...[
                      const SizedBox(height: XmSpace.s2),
                      XmAlert(_announce!, icon: Icons.notifications_active_outlined, tone: context.xm.primary),
                    ],
                    const SizedBox(height: XmSpace.s3),
                    if (page.items.isEmpty)
                      EmptyView(context.t('peer_alerts_empty'))
                    else
                      for (final a in page.items)
                        Padding(
                          padding: const EdgeInsets.only(bottom: XmSpace.s3),
                          child: _AlertCard(alert: a, busy: _busy.contains(a.id), onAck: () => _ack(a)),
                        ),
                    if (page.hasMore)
                      OutlinedButton(
                        onPressed: () => ref.read(peerAlertsProvider.notifier).loadMore(),
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
  final PeerAlertSummary alert;
  final bool busy;
  final VoidCallback onAck;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final a = alert;
    final type = context.alertTypeLabel(a.type);
    return Semantics(
      container: true,
      label: '$type, ${a.place}',
      child: Card(
        key: ValueKey('alert-${a.id}'),
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
                  PairBadge(
                    a.isNew ? context.alertStatusLabel('nouveau') : context.alertStatusLabel(a.status),
                    tone: statusTone(a.status),
                  ),
                  if (a.priority != 'normale') PairBadge(context.priorityLabel(a.priority), tone: priorityTone(a.priority)),
                ],
              ),
              const SizedBox(height: XmSpace.s2),
              Text(
                a.place.isEmpty ? type : '$type · ${a.place}',
                style: theme.textTheme.titleSmall,
              ),
              const SizedBox(height: XmSpace.s1),
              Wrap(
                spacing: XmSpace.s3,
                children: [
                  if (a.createdAt != null)
                    Text(
                      context.t('peer_alerts_ago', {'time': relativeTime(context, a.createdAt!)}),
                      style: theme.textTheme.bodySmall,
                    ),
                  if (a.categoryLabel != null) Text(a.categoryLabel!, style: theme.textTheme.bodySmall),
                ],
              ),
              const SizedBox(height: XmSpace.s3),
              Wrap(
                spacing: XmSpace.s2,
                runSpacing: XmSpace.s2,
                children: [
                  if (a.isNew)
                    FilledButton(
                      key: ValueKey('ack-${a.id}'),
                      style: FilledButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
                      onPressed: busy ? null : onAck,
                      child: Text(context.t('peer_alerts_acknowledge')),
                    ),
                  OutlinedButton(
                    key: ValueKey('detail-${a.id}'),
                    style: OutlinedButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
                    onPressed: () => context.push('/pair/alertes/${a.id}'),
                    child: Semantics(
                      label: context.t('peer_alerts_detail_for', {'type': type, 'place': a.place}),
                      excludeSemantics: true,
                      child: Text(context.t('peer_alerts_detail')),
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

/// Détail d'une alerte routée (maquette P3) : tout ce qu'il faut pour répondre ;
/// les boutons suivent `can{…}` du serveur.
class PairAlertDetailPage extends ConsumerStatefulWidget {
  const PairAlertDetailPage({super.key, required this.id});
  final String id;

  @override
  ConsumerState<PairAlertDetailPage> createState() => _PairAlertDetailPageState();
}

class _PairAlertDetailPageState extends ConsumerState<PairAlertDetailPage> {
  bool _busy = false;

  Future<void> _run(Future<void> Function() action, String doneKey) async {
    if (!ref.read(isOnlineProvider)) {
      showSnack(context, context.t('peer_alerts_offline'));
      return;
    }
    setState(() => _busy = true);
    try {
      await action();
      if (mounted) showSnack(context, context.t(doneKey));
    } catch (e) {
      if (mounted) showSnack(context, apiMessage(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<bool> _confirm(String textKey, String yesKey) async =>
      await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: Text(ctx.t('mobile_pair_confirm_title')),
          content: Text(ctx.t(textKey)),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(ctx.t('common_cancel'))),
            FilledButton(
              style: FilledButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
              onPressed: () => Navigator.pop(ctx, true),
              child: Text(ctx.t(yesKey)),
            ),
          ],
        ),
      ) ??
      false;

  Future<void> _openMaps(AlertPosition p) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(ctx.t('mobile_pair_maps_title')),
        content: Text(ctx.t('mobile_pair_maps_text')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(ctx.t('common_cancel'))),
          FilledButton(
            style: FilledButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(ctx.t('mobile_pair_maps_yes')),
          ),
        ],
      ),
    );
    if (ok != true) return;
    await ref.read(linkLauncherProvider).open(
      Uri.parse('https://www.google.com/maps/search/?api=1&query=${p.lat},${p.lng}'),
    );
  }

  String _coords(BuildContext context, AlertPosition p) {
    final f = NumberFormat('0.00', 'fr');
    final ns = p.lat >= 0 ? 'N' : 'S';
    final ew = p.lng >= 0 ? 'E' : 'O';
    final acc = p.accuracy == null ? '' : ' · ${context.t('peer_alerts_accuracy', {'meters': p.accuracy!.round()})}';
    return '${f.format(p.lat.abs())}° $ns, ${f.format(p.lng.abs())}° $ew$acc';
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(peerAlertProvider(widget.id));
    return PairDetailScaffold(
      title: context.t('peer_alerts_modal_title'),
      child: state.when(
        loading: () => const LoadingView(),
        error: (e, _) {
          final api = ApiException.from(e);
          if (api.status == 403 || api.status == 404) {
            return EmptyView(context.t('mobile_pair_alert_gone'));
          }
          if (api.isNetwork) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(XmSpace.s5),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(context.t('peer_alerts_offline'), textAlign: TextAlign.center),
                    const SizedBox(height: XmSpace.s4),
                    OutlinedButton(
                      onPressed: () => ref.invalidate(peerAlertProvider(widget.id)),
                      child: Text(context.t('common_retry')),
                    ),
                  ],
                ),
              ),
            );
          }
          return ErrorView(error: e, onRetry: () => ref.invalidate(peerAlertProvider(widget.id)));
        },
        data: (d) => _detail(context, d),
      ),
    );
  }

  Widget _detail(BuildContext context, PeerAlertDetail d) {
    final s = d.summary;
    final theme = Theme.of(context);
    final ctl = ref.read(peerAlertProvider(widget.id).notifier);
    final type = s.typeLabel.isEmpty ? context.alertTypeLabel(s.type) : s.typeLabel;
    final contactLine = d.contact == null || d.contact!.isEmpty
        ? context.t('mobile_pair_alert_no_contact')
        : (s.acknowledged ? d.contact! : context.t('mobile_pair_alert_contact_masked'));
    final tel = s.acknowledged ? _phone(d.contact) : null;
    final rows = <(String, String)>[
      (context.t('peer_alerts_fields_received'), s.createdAt == null ? '' : relativeTime(context, s.createdAt!)),
      (context.t('peer_alerts_fields_place'), s.place),
      (context.t('peer_alerts_fields_category'), s.categoryLabel ?? ''),
      (context.t('peer_alerts_fields_needs'), d.needs.join(', ')),
      (
        context.t('peer_alerts_fields_author'),
        d.anonymous ? context.t('peer_alerts_anonymous') : (d.author ?? context.t('peer_alerts_anonymous')),
      ),
      (
        context.t('peer_alerts_fields_contact'),
        d.channelLabel == null || d.contact == null ? contactLine : '$contactLine (${d.channelLabel})',
      ),
      (context.t('peer_alerts_fields_description'), d.description ?? ''),
    ];
    return ListView(
      key: const ValueKey('pair-alert-detail'),
      padding: const EdgeInsets.all(XmSpace.gutter),
      children: [
        Wrap(
          spacing: XmSpace.s2,
          runSpacing: XmSpace.s2,
          children: [
            PairBadge(type, tone: s.isSos ? PairTone.danger : PairTone.violet),
            PairBadge(context.alertStatusLabel(s.status), tone: statusTone(s.status)),
            if (s.priority != 'normale') PairBadge(context.priorityLabel(s.priority), tone: priorityTone(s.priority)),
            if (d.cancelled) PairBadge(context.t('peer_alerts_cancelled'), tone: PairTone.neutral),
            if (d.safe) PairBadge(context.t('peer_alerts_safe'), tone: PairTone.success),
          ],
        ),
        const SizedBox(height: XmSpace.s3),
        if (s.isNew) ...[
          XmAlert(context.t('mobile_pair_alert_escalation'), icon: Icons.warning_amber_rounded, tone: context.xm.warning),
          const SizedBox(height: XmSpace.s3),
        ],
        DefinitionCard(rows),
        if (tel != null) ...[
          const SizedBox(height: XmSpace.s2),
          OutlinedButton.icon(
            key: const ValueKey('alert-call'),
            icon: const Icon(Icons.call_outlined),
            label: Text(context.t('mobile_pair_alert_call')),
            onPressed: () => ref.read(linkLauncherProvider).open(telUri(tel)),
          ),
        ],
        if (d.position != null) ...[
          const SizedBox(height: XmSpace.s2),
          DefinitionCard([(context.t('peer_alerts_fields_position'), _coords(context, d.position!))]),
          const SizedBox(height: XmSpace.s2),
          OutlinedButton.icon(
            key: const ValueKey('alert-maps'),
            icon: const Icon(Icons.map_outlined),
            label: Text(context.t('mobile_pair_alert_open_maps')),
            onPressed: () => _openMaps(d.position!),
          ),
        ],
        if (d.history.isNotEmpty) ...[
          const SizedBox(height: XmSpace.s4),
          SectionHeading(context.t('peer_alerts_fields_history')),
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
                      title: Text(h.statusLabel),
                      subtitle: h.at == null ? null : Text(formatDateTime(context, h.at!)),
                    ),
                ],
              ),
            ),
          ),
        ],
        const SizedBox(height: XmSpace.s4),
        if (s.acknowledged && d.can.acknowledge == false && s.status == 'nouveau')
          PairBadge(context.t('mobile_pair_alert_ack_done'), tone: PairTone.success, icon: Icons.check),
        if (d.can.acknowledge)
          Padding(
            padding: const EdgeInsets.only(bottom: XmSpace.s2),
            child: FilledButton(
              key: const ValueKey('detail-ack'),
              onPressed: _busy ? null : () => _run(ctl.acknowledge, 'peer_alerts_acknowledged'),
              child: Text(context.t('peer_alerts_acknowledge')),
            ),
          ),
        if (d.can.take && s.status != 'en_cours')
          Padding(
            padding: const EdgeInsets.only(bottom: XmSpace.s2),
            child: (d.can.acknowledge ? OutlinedButton.new : FilledButton.new)(
              key: const ValueKey('detail-take'),
              onPressed: _busy ? null : () => _run(ctl.take, 'peer_alerts_taken'),
              child: Text(context.t('peer_alerts_take')),
            ),
          ),
        if (d.can.statuses.contains('traite') && s.status != 'traite')
          Padding(
            padding: const EdgeInsets.only(bottom: XmSpace.s2),
            child: OutlinedButton(
              key: const ValueKey('detail-treated'),
              onPressed: _busy ? null : () => _run(ctl.markTreated, 'mobile_pair_alert_treated_done'),
              child: Text(context.t('mobile_pair_alert_mark_treated')),
            ),
          ),
        if (d.can.close)
          FilledButton(
            key: const ValueKey('detail-close'),
            style: FilledButton.styleFrom(backgroundColor: theme.colorScheme.error),
            onPressed: _busy
                ? null
                : () async {
                    if (await _confirm('peer_alerts_close_confirm', 'peer_alerts_close_yes')) {
                      await _run(ctl.close, 'peer_alerts_closed');
                    }
                  },
            child: Text(context.t('peer_alerts_close')),
          ),
        if (!ref.watch(isOnlineProvider)) ...[
          const SizedBox(height: XmSpace.s3),
          Text(context.t('peer_alerts_offline'), style: theme.textTheme.bodySmall),
        ],
      ],
    );
  }

  /// Numéro appelable : au moins 8 chiffres (le contact peut être une adresse e-mail).
  String? _phone(String? contact) {
    if (contact == null) return null;
    final digits = contact.replaceAll(RegExp(r'[^\d+]'), '');
    return RegExp(r'\d').allMatches(digits).length >= 8 && !contact.contains('@') ? digits : null;
  }
}
