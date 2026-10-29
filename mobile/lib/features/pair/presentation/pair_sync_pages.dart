import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/outbox/outbox.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/sync/sync_engine.dart';
import '../../../core/sync/sync_providers.dart';
import '../../../core/sync/sync_queue.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../../core/util/dates.dart';
import '../application/collect_controller.dart';
import '../application/collect_logic.dart';
import '../application/pair_providers.dart';
import '../data/pair_models.dart';
import 'pair_ui.dart';

/// Ligne d'affichage de la file : un envoi du pair ou un SOS de la boîte Membre.
class _Row {
  const _Row({
    required this.id,
    required this.kindKey,
    required this.detail,
    required this.createdAt,
    required this.state,
    this.entry,
    this.code,
  });
  final String id;
  final String kindKey;
  final String detail;
  final DateTime createdAt;
  final _RowState state;
  final QueueEntry? entry;
  final String? code;
}

enum _RowState { pending, sending, sent, resolved, conflict, failed }

/// File de synchronisation (maquette P10) : ce qui attend, ce qui est parti, ce
/// qui est en conflit. Visible en permanence depuis la pastille d'état.
class PairSyncPage extends ConsumerStatefulWidget {
  const PairSyncPage({super.key});

  @override
  ConsumerState<PairSyncPage> createState() => _PairSyncPageState();
}

class _PairSyncPageState extends ConsumerState<PairSyncPage> {
  String? _message;

  Future<void> _syncNow() async {
    final engine = ref.read(syncEngineProvider);
    final sent = await engine.sync(force: true);
    if (!mounted) {
      return;
    }
    setState(() => _message = sent == 0 ? context.t('mobile_sync_nothing') : context.t('mobile_sync_done', {'count': sent}));
  }

  String _detail(BuildContext context, QueueEntry e) {
    final s = e.summary;
    switch (e.kind) {
      case QueueKind.caseCreate:
        return [
          if (e.payload['kind'] != null) '${e.payload['kind']}',
          if (e.payload['occurred_on'] != null) formatDay(context, '${e.payload['occurred_on']}'),
          if (s['commune'] != null) '${s['commune']}',
        ].join(' · ');
      case QueueKind.caseUpdate:
        final value = '${s['value'] ?? (e.payload['changes'] is Map ? (e.payload['changes'] as Map)['status'] : '')}';
        return '${s['code'] ?? e.caseCode ?? ''} · ${context.caseStatusLabel(value)}';
      case QueueKind.followupCreate:
      case QueueKind.followupDone:
        return '${s['code'] ?? e.caseCode ?? ''} · ${s['step'] ?? ''}';
    }
  }

  String _kindKey(QueueEntry e) => switch (e.kind) {
    QueueKind.caseCreate => 'mobile_sync_kind_case',
    QueueKind.caseUpdate => 'mobile_sync_kind_status',
    QueueKind.followupCreate => 'mobile_sync_kind_followup',
    QueueKind.followupDone => 'mobile_sync_kind_done',
  };

  @override
  Widget build(BuildContext context) {
    final entries = ref.watch(syncQueueEntriesProvider).valueOrNull ?? const <QueueEntry>[];
    final outbox = ref.watch(outboxEntriesProvider).valueOrNull ?? const <OutboxEntry>[];
    final state = ref.watch(syncStateProvider).valueOrNull ?? const SyncState();
    final online = ref.watch(isOnlineProvider);
    final theme = Theme.of(context);

    final rows = <_Row>[
      for (final e in entries)
        _Row(
          id: e.uuid,
          kindKey: _kindKey(e),
          detail: _detail(context, e),
          createdAt: e.createdAt,
          entry: e,
          code: e.resultCode,
          state: switch (e.status) {
            QueueStatus.pending => state.syncing ? _RowState.sending : _RowState.pending,
            QueueStatus.failed => _RowState.failed,
            QueueStatus.conflict => _RowState.conflict,
            QueueStatus.sent => e.resolution != null ? _RowState.resolved : _RowState.sent,
          },
        ),
      for (final o in outbox)
        if (o.type == OutboxType.sos)
          _Row(
            id: o.uuid,
            kindKey: 'mobile_sync_kind_sos',
            detail: '',
            createdAt: o.createdAt,
            state: o.status == OutboxStatus.failed ? _RowState.failed : _RowState.pending,
          ),
    ];
    final conflicts = rows.where((r) => r.state == _RowState.conflict).toList();
    final waiting = rows.where((r) => r.state == _RowState.pending || r.state == _RowState.sending || r.state == _RowState.failed).toList();
    final done = rows.where((r) => r.state == _RowState.sent || r.state == _RowState.resolved).toList()
      ..sort((a, b) => b.createdAt.compareTo(a.createdAt));

    return PairDetailScaffold(
      title: context.t('mobile_sync_title'),
      child: ListView(
        key: const ValueKey('pair-sync'),
        padding: const EdgeInsets.all(XmSpace.gutter),
        children: [
          Card(
            key: const ValueKey('sync-card'),
            child: Padding(
              padding: const EdgeInsets.all(XmSpace.s4),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(online ? Icons.cloud_done_outlined : Icons.cloud_off_outlined),
                      const SizedBox(width: XmSpace.s2),
                      Expanded(
                        child: Text(
                          context.t('peer_status_queue', {'count': waiting.length + conflicts.length}),
                          style: theme.textTheme.titleSmall,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: XmSpace.s2),
                  Text(
                    state.lastSync == null
                        ? context.t('mobile_sync_never')
                        : context.t('mobile_sync_last', {'date': formatDateTime(context, state.lastSync!)}),
                    style: theme.textTheme.bodySmall,
                  ),
                  if (!online) ...[
                    const SizedBox(height: XmSpace.s1),
                    Text(context.t('mobile_sync_now_off'), style: theme.textTheme.bodySmall),
                  ],
                  const SizedBox(height: XmSpace.s3),
                  FilledButton.icon(
                    key: const ValueKey('sync-now'),
                    onPressed: (!online || state.syncing) ? null : _syncNow,
                    icon: state.syncing
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                        : const Icon(Icons.sync, size: 20),
                    label: Text(state.syncing ? context.t('mobile_sync_syncing') : context.t('mobile_sync_now')),
                  ),
                  if (_message != null)
                    Semantics(
                      liveRegion: true,
                      child: Padding(padding: const EdgeInsets.only(top: XmSpace.s2), child: Text(_message!)),
                    ),
                ],
              ),
            ),
          ),
          const SizedBox(height: XmSpace.s4),
          if (rows.isEmpty) EmptyView(context.t('mobile_sync_empty')),
          if (conflicts.isNotEmpty) ...[
            SectionHeading(context.t('mobile_sync_conflicts'), count: '${conflicts.length}'),
            const SizedBox(height: XmSpace.s2),
            for (final r in conflicts) Padding(padding: const EdgeInsets.only(bottom: XmSpace.s2), child: _QueueRow(row: r)),
          ],
          if (waiting.isNotEmpty) ...[
            SectionHeading(context.t('mobile_sync_pending'), count: '${waiting.length}'),
            const SizedBox(height: XmSpace.s2),
            for (final r in waiting) Padding(padding: const EdgeInsets.only(bottom: XmSpace.s2), child: _QueueRow(row: r)),
          ],
          if (done.isNotEmpty) ...[
            SectionHeading(context.t('mobile_sync_sent')),
            const SizedBox(height: XmSpace.s2),
            for (final r in done) Padding(padding: const EdgeInsets.only(bottom: XmSpace.s2), child: _QueueRow(row: r)),
          ],
        ],
      ),
    );
  }
}

class _QueueRow extends ConsumerWidget {
  const _QueueRow({required this.row});
  final _Row row;

  (String, PairTone) _badge(BuildContext c) => switch (row.state) {
    _RowState.pending => (c.t('mobile_sync_state_pending'), PairTone.warning),
    _RowState.sending => (c.t('mobile_sync_state_sending'), PairTone.primary),
    _RowState.sent => (c.t('mobile_sync_state_sent'), PairTone.success),
    _RowState.resolved => (c.t('mobile_sync_state_resolved'), PairTone.success),
    _RowState.conflict => (c.t('mobile_sync_state_conflict'), PairTone.danger),
    _RowState.failed => (c.t('mobile_sync_state_failed'), PairTone.danger),
  };

  String _failureText(BuildContext c, QueueEntry e) {
    if (e.errors.isNotEmpty) {
      return e.errors.values.map((v) => v.first).join(' ');
    }
    return switch (e.failure) {
      'forbidden' => c.t('sync_items_forbidden'),
      'not_found' => c.t('sync_items_not_found'),
      'case_locked' => c.t('cases_errors_locked'),
      'uuid_reused' => c.t('sync_items_uuid_reused'),
      'client_uuid_conflict' => c.t('cases_errors_client_uuid_conflict'),
      'unsupported_type' => c.t('sync_items_unknown_type'),
      _ => c.t('peer_collecte_refused_generic'),
    };
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final (label, tone) = _badge(context);
    final e = row.entry;
    final kind = context.t(row.kindKey);
    return Card(
      key: ValueKey('queue-${row.id}'),
      child: Padding(
        padding: const EdgeInsets.all(XmSpace.s4),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Wrap(
              spacing: XmSpace.s2,
              runSpacing: XmSpace.s2,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                Text(kind, style: theme.textTheme.titleSmall),
                PairBadge(label, tone: tone),
                if (row.code != null && row.code!.isNotEmpty) PairBadge(row.code!, tone: PairTone.primary),
              ],
            ),
            if (row.detail.trim().isNotEmpty) ...[
              const SizedBox(height: 2),
              Text(row.detail, style: theme.textTheme.bodyMedium),
            ],
            const SizedBox(height: 2),
            Text(
              context.t('mobile_sync_created', {'when': relativeTime(context, row.createdAt)}),
              style: theme.textTheme.bodySmall,
            ),
            if (row.state == _RowState.pending || row.state == _RowState.sending) ...[
              const SizedBox(height: XmSpace.s1),
              Text(context.t('mobile_sync_retries'), style: theme.textTheme.bodySmall),
            ],
            if (row.state == _RowState.conflict && e != null && e.conflicts.isNotEmpty) ...[
              const SizedBox(height: XmSpace.s2),
              Text(context.t('mobile_sync_conflict_text'), style: theme.textTheme.bodySmall),
              const SizedBox(height: XmSpace.s2),
              OutlinedButton(
                key: ValueKey('resolve-${e.conflicts.first.id}'),
                onPressed: () => context.push('/pair/accueil/synchro/conflit/${e.conflicts.first.id}'),
                child: Text(context.t('mobile_sync_resolve_open')),
              ),
            ],
            if (row.state == _RowState.failed && e != null) ...[
              const SizedBox(height: XmSpace.s2),
              XmAlert(_failureText(context, e)),
              const SizedBox(height: XmSpace.s2),
              Wrap(
                spacing: XmSpace.s2,
                runSpacing: XmSpace.s2,
                children: [
                  if (e.kind == QueueKind.caseCreate)
                    FilledButton(
                      key: ValueKey('correct-${e.uuid}'),
                      style: FilledButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
                      onPressed: () async {
                        // La fiche refusée repasse dans le formulaire, erreurs sur les champs.
                        final ctl = ref.read(collectControllerProvider.notifier);
                        ctl.reopen(CollectForm.fromMap(e.payload), errors: e.errors);
                        await ref.read(syncQueueProvider).remove(e.uuid);
                        if (context.mounted) {
                          context.go('/pair/dossiers/collecte');
                        }
                      },
                      child: Text(context.t('mobile_sync_correct')),
                    )
                  else
                    FilledButton(
                      key: ValueKey('retry-${e.uuid}'),
                      style: FilledButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
                      onPressed: () async {
                        await ref.read(syncQueueProvider).retry(e.uuid);
                        await ref.read(syncEngineProvider).sync(force: true, pushOnly: true);
                      },
                      child: Text(context.t('mobile_sync_retry')),
                    ),
                  OutlinedButton(
                    key: ValueKey('remove-${e.uuid}'),
                    style: OutlinedButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
                    onPressed: () async {
                      final ok = await showDialog<bool>(
                        context: context,
                        builder: (ctx) => AlertDialog(
                          title: Text(ctx.t('mobile_pair_confirm_title')),
                          content: Text(ctx.t('mobile_sync_remove_confirm')),
                          actions: [
                            TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(ctx.t('common_cancel'))),
                            FilledButton(
                              style: FilledButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
                              onPressed: () => Navigator.pop(ctx, true),
                              child: Text(ctx.t('mobile_sync_remove')),
                            ),
                          ],
                        ),
                      );
                      if (ok == true) {
                        await ref.read(syncQueueProvider).remove(e.uuid);
                      }
                    },
                    child: Text(context.t('mobile_sync_remove')),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Conflit 409 (maquette P11) : **le pair tranche lui-même** — valeur du
/// serveur ou valeur de son appareil. Effet immédiat, sans validation par
/// l'équipe ; l'historique du dossier garde les deux versions et le choix.
class PairConflictPage extends ConsumerStatefulWidget {
  const PairConflictPage({super.key, required this.id});
  final int id;

  @override
  ConsumerState<PairConflictPage> createState() => _PairConflictPageState();
}

class _PairConflictPageState extends ConsumerState<PairConflictPage> {
  String? _choice; // 'serveur' | 'appareil'
  bool _busy = false;
  bool _loaded = false;
  Map<String, dynamic>? _conflict;
  String? _resolvedValue;
  bool _done = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final engine = ref.read(syncEngineProvider);
    Map<String, dynamic>? find(List<Map<String, dynamic>> l) {
      for (final c in l) {
        if (c['id'] == widget.id || '${c['id']}' == '${widget.id}') {
          return c;
        }
      }
      return null;
    }

    var c = find(engine.conflicts());
    if (c == null && ref.read(isOnlineProvider)) {
      try {
        c = find(await engine.refreshConflicts());
      } catch (_) {}
    }
    if (mounted) {
      setState(() {
        _conflict = c;
        _loaded = true;
      });
    }
  }

  String _label(BuildContext context, String field) {
    const map = {
      'sex': 'peer_collecte_fields_sex',
      'age_range': 'peer_collecte_fields_age',
      'occurred_on': 'peer_collecte_fields_date',
      'commune_id': 'peer_collecte_fields_commune',
      'zone_id': 'peer_collecte_fields_zone',
      'population_id': 'peer_collecte_fields_population',
      'violence_type_id': 'peer_collecte_fields_violence',
      'referral': 'peer_collecte_fields_referral',
      'notes': 'peer_collecte_fields_notes',
      'status': 'peer_case_status_title',
    };
    return map[field] == null ? field : context.t(map[field]!);
  }

  String _value(BuildContext context, String field, Object? v, CollectLists lists) {
    if (v == null || '$v'.isEmpty) {
      return context.t('peer_collecte_none');
    }
    switch (field) {
      case 'sex':
        return context.sexLabel('$v');
      case 'age_range':
        return context.ageLabel('$v');
      case 'status':
        return context.caseStatusLabel('$v');
      case 'occurred_on':
        return formatDay(context, '$v');
      case 'commune_id':
        return lists.communeName(int.tryParse('$v')) ?? '$v';
      case 'zone_id':
        return lists.zoneName(int.tryParse('$v')) ?? '$v';
      case 'population_id':
        return lists.populationLabel(int.tryParse('$v')) ?? '$v';
      case 'violence_type_id':
        return lists.violenceLabel(int.tryParse('$v')) ?? '$v';
      default:
        return '$v';
    }
  }

  Future<void> _confirm(BuildContext context, String serverText, String mineText) async {
    final choice = _choice!;
    final text = choice == 'serveur' ? serverText : mineText;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(ctx.t('mobile_conflict_confirm_title', {'value': text})),
        content: Text(choice == 'serveur' ? ctx.t('mobile_conflict_confirm_server') : ctx.t('mobile_conflict_confirm_mine')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(ctx.t('common_cancel'))),
          FilledButton(
            key: const ValueKey('conflict-yes'),
            style: FilledButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(ctx.t('mobile_conflict_yes')),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) {
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(syncEngineProvider).resolveConflict(widget.id, choice);
      if (mounted) {
        setState(() {
          _done = true;
          _resolvedValue = text;
          _busy = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = apiMessage(context, e);
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final lists = ref.watch(collectListsProvider).valueOrNull ?? const CollectLists();
    final online = ref.watch(isOnlineProvider);
    return PairDetailScaffold(
      title: context.t('mobile_conflict_title'),
      child: !_loaded
          ? const LoadingView()
          : _done
          ? _doneView(context)
          : _conflict == null
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(XmSpace.s5),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(context.t('mobile_conflict_not_found'), textAlign: TextAlign.center),
                    const SizedBox(height: XmSpace.s4),
                    OutlinedButton(
                      onPressed: () => context.pop(),
                      child: Text(context.t('mobile_conflict_back_queue')),
                    ),
                  ],
                ),
              ),
            )
          : _body(context, theme, lists, online),
    );
  }

  Widget _doneView(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(XmSpace.s5),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Semantics(
            header: true,
            liveRegion: true,
            child: Text(context.t('mobile_conflict_done_title'), style: Theme.of(context).textTheme.titleLarge),
          ),
          const SizedBox(height: XmSpace.s3),
          Text(context.t('mobile_conflict_done_text', {'value': _resolvedValue ?? ''}), textAlign: TextAlign.center),
          const SizedBox(height: XmSpace.s4),
          FilledButton(
            key: const ValueKey('conflict-back'),
            onPressed: () => context.pop(),
            child: Text(context.t('mobile_conflict_back_queue')),
          ),
        ],
      ),
    ),
  );

  Widget _body(BuildContext context, ThemeData theme, CollectLists lists, bool online) {
    final c = _conflict!;
    final field = '${c['field'] ?? ''}';
    final subject = c['subject'] is Map ? Map<String, dynamic>.from(c['subject'] as Map) : <String, dynamic>{};
    final serverText = _value(context, field, c['server_value'], lists);
    final mineText = _value(context, field, c['device_value'], lists);
    // Valeur de départ : celle que l'appareil avait avant ses deux modifications (`base` de l'envoi).
    String? baseText;
    for (final e in ref.read(syncQueueProvider).list()) {
      if (e.conflicts.any((x) => x.id == widget.id) && e.payload['base'] is Map) {
        baseText = _value(context, field, (e.payload['base'] as Map)[field], lists);
      }
    }
    Widget option(String value, String titleKey, String helpKey, String text) => Card(
      key: ValueKey('option-$value'),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(XmSpace.radiusLg),
        side: BorderSide(color: _choice == value ? context.xm.primary : context.xm.border, width: _choice == value ? 2 : 1),
      ),
      child: RadioListTile<String>(
        value: value,
        enabled: !_busy,
        title: Text(context.t(titleKey), style: theme.textTheme.titleSmall),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 4),
            Text(text, style: theme.textTheme.bodyLarge?.copyWith(fontWeight: FontWeight.w700)),
            const SizedBox(height: 4),
            Text(context.t(helpKey), style: theme.textTheme.bodySmall),
          ],
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: XmSpace.s3, vertical: XmSpace.s2),
      ),
    );
    return ListView(
      key: const ValueKey('pair-conflict'),
      padding: const EdgeInsets.all(XmSpace.gutter),
      children: [
        PairBadge(context.t('mobile_conflict_code'), tone: PairTone.danger),
        const SizedBox(height: XmSpace.s3),
        SectionHeading(context.t('mobile_conflict_what_h')),
        const SizedBox(height: XmSpace.s1),
        Text(context.t('mobile_conflict_what')),
        const SizedBox(height: XmSpace.s3),
        SectionHeading(context.t('mobile_conflict_you_h')),
        const SizedBox(height: XmSpace.s1),
        Text(context.t('mobile_conflict_you')),
        const SizedBox(height: XmSpace.s3),
        DefinitionCard([
          (
            context.t('mobile_conflict_field'),
            [if (subject['reference'] != null) '${subject['reference']}', _label(context, field)].join(' · '),
          ),
          if (baseText != null) (context.t('mobile_conflict_base'), baseText),
        ]),
        const SizedBox(height: XmSpace.s4),
        Semantics(header: true, child: Text(context.t('mobile_conflict_pick'), style: theme.textTheme.titleMedium)),
        const SizedBox(height: XmSpace.s2),
        RadioGroup<String>(
          groupValue: _choice,
          onChanged: (v) => setState(() => _choice = v),
          child: Column(
            children: [
              option('serveur', 'mobile_conflict_server', 'mobile_conflict_server_help', serverText),
              const SizedBox(height: XmSpace.s2),
              option('appareil', 'mobile_conflict_mine', 'mobile_conflict_mine_help', mineText),
            ],
          ),
        ),
        const SizedBox(height: XmSpace.s3),
        Text(context.t('mobile_conflict_history'), style: theme.textTheme.bodySmall),
        const SizedBox(height: XmSpace.s3),
        if (_error != null) ...[XmAlert(_error!), const SizedBox(height: XmSpace.s3)],
        if (!online) ...[
          Text(context.t('mobile_conflict_offline'), style: theme.textTheme.bodySmall),
          const SizedBox(height: XmSpace.s2),
        ],
        if (_choice == null)
          Text(context.t('mobile_conflict_choose_first'), style: theme.textTheme.bodySmall),
        const SizedBox(height: XmSpace.s2),
        FilledButton(
          key: const ValueKey('conflict-confirm'),
          onPressed: (_choice == null || _busy || !online) ? null : () => _confirm(context, serverText, mineText),
          child: Text(context.t('mobile_conflict_confirm')),
        ),
      ],
    );
  }
}
