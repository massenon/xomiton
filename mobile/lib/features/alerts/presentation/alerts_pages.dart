import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/outbox/outbox.dart';
import '../../../core/platform/platform_services.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../../core/util/dates.dart';
import '../data/alerts_api.dart';
import '../data/alerts_models.dart';
import 'sos_page.dart';

/// Contacts d'urgence (Q47 : tu). Numéros et fiches viennent de l'API, avec
/// copie Hive : appel direct possible hors ligne.
class EmergencyPage extends ConsumerWidget {
  const EmergencyPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(emergencyProvider);
    final online = ref.watch(isOnlineProvider);
    return Scaffold(
      appBar: AppBar(title: Text(context.t('alerts_emergency_title'))),
      body: SafeArea(
        child: Column(
          children: [
            if (!online) const OfflineBanner(),
            Expanded(
              child: state.when(
                loading: () => const LoadingView(),
                error: (e, _) => ErrorView(
                  error: e,
                  onRetry: () => ref.invalidate(emergencyProvider),
                ),
                data: (d) => ListView(
                  padding: const EdgeInsets.all(XmSpace.gutter),
                  children: [
                    Text(context.t('alerts_emergency_intro')),
                    if (d.stale) ...[
                      const SizedBox(height: XmSpace.s2),
                      Text(
                        context.t('mobile_stale_notice'),
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                    const SizedBox(height: XmSpace.s4),
                    for (final s in d.services)
                      Padding(
                        padding: const EdgeInsets.only(bottom: XmSpace.s2),
                        child: CallButton(
                          label: '${s.label} · ${s.number}',
                          number: s.number,
                        ),
                      ),
                    const SizedBox(height: XmSpace.s4),
                    Text(
                      context.t('alerts_emergency_directory_title'),
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    if (d.contacts.isEmpty)
                      Padding(
                        padding: const EdgeInsets.only(top: XmSpace.s2),
                        child: Text(
                          context.t('alerts_emergency_directory_empty'),
                        ),
                      ),
                    for (final c in d.contacts)
                      Card(
                        child: ListTile(
                          title: Text(c.name),
                          subtitle: Text(
                            [
                              if (c.roleLabel != null) c.roleLabel!,
                              if (c.org != null) c.org!,
                              if (c.zone != null) c.zone!,
                              if (c.availability != null)
                                context.t('alerts_emergency_availability', {
                                  'hours': c.availability,
                                }),
                            ].join(' · '),
                          ),
                          trailing: c.phone == null
                              ? null
                              : IconButton(
                                  tooltip: c.phone,
                                  icon: const Icon(Icons.call_outlined),
                                  onPressed: () => ref
                                      .read(linkLauncherProvider)
                                      .open(
                                        Uri(
                                          scheme: 'tel',
                                          path: c.phone!.replaceAll(
                                            RegExp(r'[^\d+]'),
                                            '',
                                          ),
                                        ),
                                      ),
                                ),
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Suivi par code, sans compte : aucune autre information n'est demandée.
class TrackingPage extends ConsumerStatefulWidget {
  const TrackingPage({super.key});

  @override
  ConsumerState<TrackingPage> createState() => _TrackingPageState();
}

class _TrackingPageState extends ConsumerState<TrackingPage> {
  final _code = TextEditingController();
  AlertSummary? _result;
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _lookup() async {
    if (_code.text.trim().isEmpty) {
      setState(() => _error = context.t('alerts_report_tracking_required'));
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
      _result = null;
    });
    try {
      final r = await ref.read(alertsApiProvider).track(_code.text);
      if (mounted) setState(() => _result = r);
    } catch (e) {
      final api = ApiException.from(e);
      if (mounted) {
        setState(
          () => _error = api.status == 404
              ? context.t('alerts_report_tracking_not_found', {'days': 90})
              : errorMessage(context, e),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final r = _result;
    return Scaffold(
      appBar: AppBar(title: Text(context.t('alerts_report_tracking_title'))),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(XmSpace.gutter),
          children: [
            Text(context.t('alerts_report_tracking_intro')),
            const SizedBox(height: XmSpace.s4),
            TextField(
              controller: _code,
              textCapitalization: TextCapitalization.characters,
              autocorrect: false,
              enableSuggestions: false,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => _lookup(),
              decoration: InputDecoration(
                labelText: context.t('alerts_report_tracking_code'),
                hintText: context.t('alerts_report_tracking_code_placeholder'),
                helperText: context.t('alerts_report_tracking_no_code'),
              ),
            ),
            const SizedBox(height: XmSpace.s3),
            FilledButton(
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(52),
              ),
              onPressed: _busy ? null : _lookup,
              child: Text(context.t('alerts_report_tracking_submit')),
            ),
            if (_error != null) ...[
              const SizedBox(height: XmSpace.s3),
              XmAlert(_error!),
            ],
            if (r != null) ...[
              const SizedBox(height: XmSpace.s5),
              Text(
                context.t('alerts_report_tracking_result_title', {
                  'code': r.codeDisplay ?? '',
                }),
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: XmSpace.s2),
              Text(
                context.t('alerts_report_tracking_status', {
                  'status': r.statusLabel ?? r.status,
                }),
                key: const Key('tracking-status'),
              ),
              if (r.statusText != null) Text(r.statusText!),
              if (r.routedRole != null)
                Text(context.t('alerts_report_tracking_handled_by')),
              if (r.createdAt != null)
                Text(
                  context.t('alerts_report_tracking_received_on', {
                    'date': formatDateTime(context, r.createdAt!),
                  }),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              if (r.history.isNotEmpty) ...[
                const SizedBox(height: XmSpace.s3),
                Text(
                  context.t('alerts_report_tracking_history'),
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                for (final h in r.history)
                  ListTile(
                    dense: true,
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.circle, size: 10),
                    title: Text(h.label ?? h.status),
                    subtitle: h.at == null
                        ? null
                        : Text(formatDateTime(context, h.at!)),
                  ),
              ],
            ],
          ],
        ),
      ),
    );
  }
}

final myAlertsProvider = FutureProvider.autoDispose<List<AlertSummary>>(
  (ref) => ref.watch(alertsApiProvider).mine(),
);

/// « Mes signalements » : alertes non anonymes du compte + file d'attente locale.
class MyReportsPage extends ConsumerWidget {
  const MyReportsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(myAlertsProvider);
    final queue =
        ref.watch(outboxEntriesProvider).valueOrNull ?? const <OutboxEntry>[];
    return Scaffold(
      appBar: AppBar(title: Text(context.t('mobile_reports_mine_title'))),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () async => ref.invalidate(myAlertsProvider),
          child: ListView(
            padding: const EdgeInsets.all(XmSpace.gutter),
            children: [
              if (queue.isNotEmpty) OutboxQueueCard(entries: queue),
              state.when(
                loading: () => const Padding(
                  padding: EdgeInsets.all(XmSpace.s5),
                  child: LoadingView(),
                ),
                error: (e, _) => ErrorView(
                  error: e,
                  onRetry: () => ref.invalidate(myAlertsProvider),
                ),
                data: (items) => items.isEmpty
                    ? EmptyView(context.t('mobile_reports_mine_empty'))
                    : Column(
                        children: [
                          for (final a in items)
                            Card(
                              child: ListTile(
                                title: Text(a.codeDisplay ?? ''),
                                subtitle: Text(
                                  [
                                    a.statusLabel ?? a.status,
                                    if (a.createdAt != null)
                                      formatDateTime(context, a.createdAt!),
                                  ].join(' · '),
                                ),
                                leading: Icon(
                                  a.type == 'sos'
                                      ? Icons.sos
                                      : Icons.flag_outlined,
                                ),
                              ),
                            ),
                        ],
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Éléments en attente d'envoi (le SOS part en premier) ; un refus du serveur
/// laisse l'élément visible avec « Réessayer » / « Supprimer ».
class OutboxQueueCard extends ConsumerWidget {
  const OutboxQueueCard({super.key, required this.entries});
  final List<OutboxEntry> entries;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final svc = ref.read(outboxProvider);
    String label(OutboxEntry e) => context.t(switch (e.type) {
      OutboxType.sos => 'offline_outbox_sos_failed_title',
      OutboxType.report => 'alerts_report_done_pending_title',
      OutboxType.message => 'microcopy_offline_pending_message',
      OutboxType.post => 'offline_outbox_post_failed_title',
    });
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(XmSpace.s3),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              context.t('microcopy_offline_queue_title'),
              style: Theme.of(context).textTheme.titleSmall,
            ),
            Text(
              context.t('microcopy_offline_queue_hint'),
              style: Theme.of(context).textTheme.bodySmall,
            ),
            for (final e in entries)
              ListTile(
                key: Key('outbox-${e.uuid}'),
                dense: true,
                contentPadding: EdgeInsets.zero,
                leading: Icon(
                  e.isPending
                      ? Icons.schedule_send_outlined
                      : Icons.error_outline,
                ),
                title: Text(
                  e.isPending
                      ? context.t('microcopy_offline_pending_message')
                      : label(e),
                ),
                subtitle: Text(formatDateTime(context, e.createdAt)),
                trailing: e.isPending
                    ? null
                    : Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          IconButton(
                            tooltip: context.t('common_retry'),
                            icon: const Icon(Icons.refresh),
                            onPressed: () async {
                              await svc.retry(e.uuid);
                              svc.flush();
                            },
                          ),
                          IconButton(
                            tooltip: context.t('common_delete'),
                            icon: const Icon(Icons.delete_outline),
                            onPressed: () => svc.remove(e.uuid),
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
