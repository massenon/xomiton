// ignore_for_file: curly_braces_in_flow_control_structures
import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/sync/sync_providers.dart';
import '../../../core/sync/sync_queue.dart';
import '../../../core/ui/xm_widgets.dart';
import '../application/pair_providers.dart';
import '../data/pair_models.dart';
import 'pair_ui.dart';

String _caseKind(BuildContext c, PeerCase x) =>
    x.kindLabel ?? c.caseKindLabel(x.kind);
String _caseStatus(BuildContext c, PeerCase x) =>
    x.statusLabel ?? c.caseStatusLabel(x.status);

/// Mes dossiers (maquette P4) : recherche par code (le serveur ne cherche que
/// dans le code, ADR-33), filtres de nature et de statut, lecture hors ligne
/// depuis la copie chiffrée. Les notes ne sont jamais dans la liste.
class PairCasesPage extends ConsumerStatefulWidget {
  const PairCasesPage({super.key});

  @override
  ConsumerState<PairCasesPage> createState() => _PairCasesPageState();
}

class _PairCasesPageState extends ConsumerState<PairCasesPage> {
  final _search = TextEditingController();
  Timer? _debounce;
  CasesQuery _query = const CasesQuery();

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  void _onSearch(String v) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      if (mounted)
        setState(
          () => _query = CasesQuery(
            q: v,
            status: _query.status,
            kind: _query.kind,
          ),
        );
    });
  }

  void _reset() {
    _search.clear();
    setState(() => _query = const CasesQuery());
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(peerCasesProvider(_query));
    final queued = _query.filtered
        ? const <PeerCase>[]
        : ref.watch(queuedCasesProvider);
    final theme = Theme.of(context);
    Widget chip(String label, bool selected, VoidCallback onTap, {Key? key}) =>
        Semantics(
          selected: selected,
          button: true,
          child: ChoiceChip(
            key: key,
            label: Text(label),
            selected: selected,
            onSelected: (_) => onTap(),
            materialTapTargetSize: MaterialTapTargetSize.padded,
            visualDensity: VisualDensity.standard,
          ),
        );
    final kinds = [
      ('', context.t('peer_cases_all_kinds')),
      ('VBG', 'VBG'),
      ('VDH', 'VDH'),
    ];
    final statuses = [
      ('', context.t('peer_cases_all_statuses')),
      for (final s in ['ouvert', 'en_cours', 'clos', 'archive'])
        (s, context.caseStatusLabel(s)),
    ];
    return SafeArea(
      child: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(peerCasesProvider(_query));
          await ref.read(syncEngineProvider).sync(force: true);
        },
        child: ListView(
          key: const ValueKey('pair-cases'),
          padding: const EdgeInsets.all(XmSpace.gutter),
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            PairHeader(
              title: context.t('peer_cases_heading'),
              subtitle: context.t('peer_cases_intro'),
            ),
            const SizedBox(height: XmSpace.s4),
            TextField(
              key: const ValueKey('cases-search'),
              controller: _search,
              maxLength: 12,
              textCapitalization: TextCapitalization.characters,
              inputFormatters: [
                FilteringTextInputFormatter.allow(RegExp(r'[A-Za-z0-9\- ]')),
              ],
              decoration: InputDecoration(
                labelText: context.t('peer_cases_search'),
                hintText: context.t('peer_cases_search_placeholder'),
                prefixIcon: const Icon(Icons.search),
                counterText: '',
              ),
              onChanged: _onSearch,
            ),
            const SizedBox(height: XmSpace.s3),
            Semantics(
              container: true,
              label: context.t('peer_cases_filters_label'),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Wrap(
                    spacing: XmSpace.s2,
                    children: [
                      for (final (v, l) in kinds)
                        chip(
                          l,
                          _query.kind == v,
                          () => setState(
                            () => _query = CasesQuery(
                              q: _query.q,
                              status: _query.status,
                              kind: v,
                            ),
                          ),
                          key: ValueKey('kind-${v.isEmpty ? 'all' : v}'),
                        ),
                    ],
                  ),
                  Wrap(
                    spacing: XmSpace.s2,
                    children: [
                      for (final (v, l) in statuses)
                        chip(
                          l,
                          _query.status == v,
                          () => setState(
                            () => _query = CasesQuery(
                              q: _query.q,
                              status: v,
                              kind: _query.kind,
                            ),
                          ),
                          key: ValueKey('status-${v.isEmpty ? 'all' : v}'),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: XmSpace.s3),
            state.when(
              loading: () => const Padding(
                padding: EdgeInsets.all(XmSpace.s6),
                child: LoadingView(),
              ),
              error: (e, _) => ErrorView(
                error: e,
                onRetry: () => ref.invalidate(peerCasesProvider(_query)),
              ),
              data: (page) {
                final total = page.total ?? page.items.length;
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (page.stale) ...[
                      Text(
                        context.t('mobile_stale_notice'),
                        style: theme.textTheme.bodySmall,
                      ),
                      const SizedBox(height: XmSpace.s2),
                    ],
                    Row(
                      children: [
                        Expanded(
                          child: Semantics(
                            liveRegion: true,
                            child: Text(
                              context.t('peer_cases_results', {
                                'count': total + queued.length,
                              }),
                              style: theme.textTheme.titleMedium,
                            ),
                          ),
                        ),
                        if (_query.filtered)
                          TextButton(
                            onPressed: _reset,
                            child: Text(context.t('peer_cases_reset')),
                          ),
                      ],
                    ),
                    const SizedBox(height: XmSpace.s2),
                    for (final c in queued)
                      Padding(
                        padding: const EdgeInsets.only(bottom: XmSpace.s2),
                        child: _CaseRow(c: c),
                      ),
                    if (page.items.isEmpty && queued.isEmpty)
                      Padding(
                        padding: const EdgeInsets.all(XmSpace.s5),
                        child: Column(
                          children: [
                            Text(
                              _query.filtered
                                  ? context.t('peer_cases_empty_filtered')
                                  : context.t('peer_cases_empty'),
                              style: theme.textTheme.titleSmall,
                              textAlign: TextAlign.center,
                            ),
                            if (!_query.filtered) ...[
                              const SizedBox(height: XmSpace.s1),
                              Text(
                                context.t('peer_cases_empty_text'),
                                textAlign: TextAlign.center,
                              ),
                            ],
                          ],
                        ),
                      ),
                    for (final c in page.items)
                      Padding(
                        padding: const EdgeInsets.only(bottom: XmSpace.s2),
                        child: _CaseRow(c: c),
                      ),
                    if (page.hasMore)
                      OutlinedButton(
                        onPressed: () => ref
                            .read(peerCasesProvider(_query).notifier)
                            .loadMore(),
                        child: Text(context.t('mobile_feed_load_more')),
                      ),
                  ],
                );
              },
            ),
            const SizedBox(height: XmSpace.s3),
            FilledButton.icon(
              key: const ValueKey('cases-new'),
              onPressed: () => context.push('/pair/dossiers/collecte'),
              icon: const Icon(Icons.add, size: 20),
              label: Text(context.t('peer_cases_new')),
            ),
          ],
        ),
      ),
    );
  }
}

class _CaseRow extends StatelessWidget {
  const _CaseRow({required this.c});
  final PeerCase c;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final steps = context.t('mobile_pair_case_steps', {
      'count': c.followupsCount,
    });
    final line = [
      if (c.occurredOn != null) formatDay(context, c.occurredOn),
      if (c.commune != null) c.commune!,
      if (c.violence != null) c.violence!,
      if (!c.queued) steps,
    ].join(' · ');
    final title = c.queued
        ? context.t('mobile_pair_case_queued_title')
        : c.code;
    return LinkRow(
      key: ValueKey(c.queued ? 'case-q-${c.queuedUuid}' : 'case-${c.code}'),
      semanticLabel:
          '$title, ${_caseStatus(context, c)}, ${context.t('peer_cases_open')}',
      onTap: () => c.queued
          ? context.go('/pair/accueil/synchro')
          : context.push('/pair/dossiers/${Uri.encodeComponent(c.code)}'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 6,
            runSpacing: 6,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              Text(title, style: theme.textTheme.titleSmall),
              PairBadge(c.kind, tone: PairTone.primary),
              if (c.queued)
                PairBadge(
                  context.t('mobile_pair_in_queue'),
                  tone: PairTone.warning,
                  icon: Icons.schedule,
                )
              else
                PairBadge(_caseStatus(context, c), tone: statusTone(c.status)),
            ],
          ),
          if (line.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(line, style: theme.textTheme.bodySmall),
          ],
        ],
      ),
    );
  }
}

/// Fiche d'un dossier (maquette P5) : cinq sections, statut, frise de suivi.
class PairCasePage extends ConsumerStatefulWidget {
  const PairCasePage({super.key, required this.code});
  final String code;

  @override
  ConsumerState<PairCasePage> createState() => _PairCasePageState();
}

class _PairCasePageState extends ConsumerState<PairCasePage> {
  String? _newStatus;
  bool _busy = false;

  Future<void> _guard(
    Future<ActionOutcome> Function() action,
    String doneKey,
  ) async {
    setState(() => _busy = true);
    try {
      final r = await action();
      if (!mounted) return;
      showSnack(
        context,
        r == ActionOutcome.queued
            ? context.t('mobile_pair_followup_queued')
            : context.t(doneKey),
      );
    } catch (e) {
      if (mounted) showSnack(context, apiMessage(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(peerCaseProvider(widget.code));
    return PairDetailScaffold(
      title: widget.code,
      child: state.when(
        loading: () => const LoadingView(),
        error: (e, _) {
          final api = ApiException.from(e);
          if (api.status == 404 || api.status == 403) {
            // Dossier d'un autre pair, code inconnu ou illisible : le même 404 neutre.
            return EmptyView(context.t('mobile_pair_case_gone'));
          }
          return ErrorView(
            error: e,
            onRetry: () => ref.invalidate(peerCaseProvider(widget.code)),
          );
        },
        data: _content,
      ),
    );
  }

  Widget _content(PeerCase c) {
    final theme = Theme.of(context);
    Widget section(
      String titleKey,
      List<(String, String?)> rows, {
      bool open = false,
    }) => Padding(
      padding: const EdgeInsets.only(bottom: XmSpace.s2),
      child: Card(
        child: Theme(
          data: theme.copyWith(dividerColor: Colors.transparent),
          child: ExpansionTile(
            key: PageStorageKey('sheet-$titleKey'),
            initiallyExpanded: open,
            tilePadding: const EdgeInsets.symmetric(horizontal: XmSpace.s4),
            title: Semantics(
              header: true,
              child: Text(
                context.t(titleKey),
                style: theme.textTheme.titleSmall,
              ),
            ),
            childrenPadding: const EdgeInsets.fromLTRB(
              XmSpace.s4,
              0,
              XmSpace.s4,
              XmSpace.s3,
            ),
            expandedCrossAxisAlignment: CrossAxisAlignment.start,
            children: [
              for (final (k, val) in rows)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: XmSpace.s1),
                  child: Semantics(
                    container: true,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(k, style: theme.textTheme.bodySmall),
                        Text(
                          (val == null || val.isEmpty)
                              ? context.t('peer_sheet_empty')
                              : val,
                          style: (val == null || val.isEmpty)
                              ? theme.textTheme.bodySmall?.copyWith(
                                  fontStyle: FontStyle.italic,
                                )
                              : theme.textTheme.bodyMedium,
                        ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
    final zone = [
      if (c.commune != null) c.commune!,
      if (c.zone != null) '(${c.zone})',
    ].join(' ');
    final statuses = [
      for (final s in ['ouvert', 'en_cours', 'clos'])
        if (s != c.status) s,
    ];
    final fu = c.followups;
    return SingleChildScrollView(
      key: const ValueKey('pair-case'),
      padding: const EdgeInsets.all(XmSpace.gutter),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Wrap(
            spacing: XmSpace.s2,
            runSpacing: XmSpace.s2,
            children: [
              PairBadge(_caseKind(context, c), tone: PairTone.primary),
              PairBadge(_caseStatus(context, c), tone: statusTone(c.status)),
              if (c.occurredOn != null)
                PairBadge(
                  context.t('peer_case_received', {
                    'date': formatDay(context, c.occurredOn),
                  }),
                ),
              if (c.ageRange != null)
                PairBadge(
                  context.t('peer_case_age', {
                    'range': c.ageRangeLabel ?? c.ageRange!,
                  }),
                ),
              if (c.anonymized)
                PairBadge(
                  context.t('peer_case_anonymized'),
                  icon: Icons.lock_outline,
                ),
            ],
          ),
          const SizedBox(height: XmSpace.s3),
          XmAlert(
            context.t('peer_sheet_not_filled'),
            icon: Icons.info_outline,
            tone: context.xm.info,
          ),
          const SizedBox(height: XmSpace.s3),
          section('peer_sheet_sections_identification', [
            (context.t('peer_sheet_fields_pseudo'), c.code),
            (
              context.t('peer_sheet_fields_sex'),
              c.sex == null ? null : (c.sexLabel ?? context.sexLabel(c.sex!)),
            ),
            (
              context.t('peer_sheet_fields_age'),
              c.ageRange == null
                  ? null
                  : (c.ageRangeLabel ?? context.ageLabel(c.ageRange!)),
            ),
            (context.t('peer_sheet_fields_population'), c.population),
            (context.t('peer_sheet_fields_zone'), zone),
          ], open: true),
          section('peer_sheet_sections_facts', [
            (
              context.t('peer_sheet_fields_date'),
              formatDay(context, c.occurredOn),
            ),
            (context.t('peer_sheet_fields_place'), null),
            (context.t('peer_sheet_fields_type'), c.violence),
            (context.t('peer_sheet_fields_description'), c.notes),
          ], open: true),
          section('peer_sheet_sections_services', [
            (context.t('peer_sheet_fields_description'), null),
          ]),
          section('peer_sheet_sections_orientation', [
            (
              context.t('peer_sheet_fields_referral'),
              (c.referral == null || c.referral!.isEmpty)
                  ? context.t('peer_sheet_no_referral')
                  : c.referral,
            ),
          ]),
          section('peer_sheet_sections_consent', [
            (context.t('peer_sheet_fields_consent'), null),
          ]),
          const SizedBox(height: XmSpace.s2),
          if (c.locked)
            XmAlert(
              context.t('peer_case_locked'),
              icon: Icons.lock_outline,
              tone: context.xm.textMuted,
            )
          else if (c.can.update)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(XmSpace.s4),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Semantics(
                      header: true,
                      child: Text(
                        context.t('peer_case_status_title'),
                        style: theme.textTheme.titleSmall,
                      ),
                    ),
                    const SizedBox(height: XmSpace.s2),
                    DropdownButtonFormField<String>(
                      key: const ValueKey('case-status'),
                      initialValue: _newStatus,
                      isExpanded: true,
                      decoration: InputDecoration(
                        labelText: context.t('peer_case_status_label'),
                      ),
                      hint: Text(context.t('peer_case_status_choose')),
                      items: [
                        for (final s in statuses)
                          DropdownMenuItem(
                            value: s,
                            child: Text(context.caseStatusLabel(s)),
                          ),
                      ],
                      onChanged: (s) => setState(() => _newStatus = s),
                    ),
                    const SizedBox(height: XmSpace.s3),
                    OutlinedButton(
                      key: const ValueKey('case-status-submit'),
                      onPressed: _busy || _newStatus == null
                          ? null
                          : () async {
                              final target = _newStatus!;
                              await _guard(
                                () => ref
                                    .read(pairActionsProvider)
                                    .changeStatus(c, target),
                                'peer_case_status_saved',
                              );
                              if (mounted) setState(() => _newStatus = null);
                            },
                      child: Text(context.t('peer_case_status_submit')),
                    ),
                  ],
                ),
              ),
            ),
          const SizedBox(height: XmSpace.s4),
          SectionHeading(context.t('peer_case_timeline')),
          const SizedBox(height: XmSpace.s2),
          if (fu.isEmpty)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(XmSpace.s4),
                child: Text(context.t('peer_case_timeline_empty')),
              ),
            )
          else
            for (final f in fu)
              Padding(
                padding: const EdgeInsets.only(bottom: XmSpace.s2),
                child: _FollowupCard(
                  f: f,
                  canMark:
                      !c.locked &&
                      c.can.update &&
                      f.pending &&
                      !f.queued &&
                      f.id > 0,
                  busy: _busy,
                  onDone: () => _guard(
                    () => ref.read(pairActionsProvider).markDone(c, f),
                    'peer_case_followup_done',
                  ),
                ),
              ),
          const SizedBox(height: XmSpace.s3),
          if (!c.locked && c.can.addFollowup)
            FilledButton.icon(
              key: const ValueKey('case-add-followup'),
              onPressed: () => context.push(
                '/pair/dossiers/${Uri.encodeComponent(c.code)}/suivi',
              ),
              icon: const Icon(Icons.add, size: 20),
              label: Text(context.t('peer_case_add_title')),
            ),
        ],
      ),
    );
  }
}

class _FollowupCard extends StatelessWidget {
  const _FollowupCard({
    required this.f,
    required this.canMark,
    required this.busy,
    required this.onDone,
  });
  final FollowupItem f;
  final bool canMark;
  final bool busy;
  final VoidCallback onDone;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final outcome = f.outcomeLabel.isEmpty
        ? context.outcomeLabel(f.outcome)
        : f.outcomeLabel;
    return Card(
      key: ValueKey(f.queued ? 'fu-q-${f.queuedUuid}' : 'fu-${f.id}'),
      child: Padding(
        padding: const EdgeInsets.all(XmSpace.s4),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(formatDay(context, f.date), style: theme.textTheme.bodySmall),
            const SizedBox(height: 2),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                Text(f.step, style: theme.textTheme.titleSmall),
                PairBadge(
                  outcome,
                  tone: f.outcome == 'realise'
                      ? PairTone.success
                      : PairTone.neutral,
                ),
                if (f.queued)
                  PairBadge(
                    context.t('mobile_pair_in_queue'),
                    tone: PairTone.warning,
                    icon: Icons.schedule,
                  ),
                if (f.overdue)
                  PairBadge(
                    context.t('mobile_pair_overdue'),
                    tone: PairTone.danger,
                  ),
              ],
            ),
            if (f.comment != null && f.comment!.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(f.comment!, style: theme.textTheme.bodySmall),
            ],
            if (f.nextOn != null && f.nextOn!.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(
                '${context.t('mobile_pair_next_followup')} : ${formatDay(context, f.nextOn)}',
                style: theme.textTheme.bodySmall,
              ),
            ],
            if (canMark)
              TextButton(
                key: ValueKey('fu-done-${f.id}'),
                onPressed: busy ? null : onDone,
                child: Text(context.t('peer_case_mark_done')),
              ),
          ],
        ),
      ),
    );
  }
}

/// Ajouter une étape de suivi (maquette P6). Écrite dans la file chiffrée puis
/// envoyée tout de suite si le réseau est là ; erreurs du serveur par champ.
class PairFollowupFormPage extends ConsumerStatefulWidget {
  const PairFollowupFormPage({super.key, required this.code});
  final String code;

  @override
  ConsumerState<PairFollowupFormPage> createState() =>
      _PairFollowupFormPageState();
}

class _PairFollowupFormPageState extends ConsumerState<PairFollowupFormPage> {
  static const int maxComment = 1000;
  final _comment = TextEditingController();
  final _stepText = TextEditingController();
  String? _step;
  DateTime _date = DateTime.now();
  String _outcome = 'planifie';
  DateTime? _next;
  Map<String, String> _errors = {};
  bool _busy = false;

  @override
  void dispose() {
    _comment.dispose();
    _stepText.dispose();
    super.dispose();
  }

  String _fmt(DateTime d) => DateFormat('dd/MM/yyyy', 'fr').format(d);
  String _iso(DateTime d) => DateFormat('yyyy-MM-dd').format(d);

  Future<void> _pick({required bool next}) async {
    final now = DateTime.now();
    final d = await showDatePicker(
      context: context,
      initialDate: next ? (_next ?? _date) : _date,
      firstDate: next
          ? DateTime(_date.year, _date.month, _date.day)
          : DateTime(now.year - 5),
      lastDate: next ? DateTime(now.year + 2) : now,
    );
    if (d == null) return;
    setState(() => next ? _next = d : _date = d);
  }

  Future<void> _submit(List<String> steps) async {
    final errors = <String, String>{};
    final step = steps.isEmpty ? _stepText.text.trim() : (_step ?? '');
    if (step.isEmpty)
      errors['step'] = context.t('peer_collecte_errors_required');
    if (_next != null &&
        _next!.isBefore(DateTime(_date.year, _date.month, _date.day))) {
      errors['next'] = context.t('peer_case_errors_next_before');
    }
    if (_comment.text.length > maxComment) {
      errors['comment'] = context.t('peer_case_errors_comment_max', {
        'max': maxComment,
      });
    }
    if (errors.isNotEmpty) {
      setState(() => _errors = errors);
      return;
    }
    setState(() {
      _errors = {};
      _busy = true;
    });
    final payload = <String, dynamic>{
      'date': _iso(_date),
      'step': step,
      'outcome': _outcome,
      if (_next != null) 'next_on': _iso(_next!),
      if (_comment.text.trim().isNotEmpty) 'comment': _comment.text.trim(),
    };
    try {
      final e = await ref
          .read(pairActionsProvider)
          .addFollowup(widget.code, payload);
      if (!mounted) return;
      if (e.status == QueueStatus.failed) {
        // Refus du serveur : l'entrée reste modifiable, les erreurs sont sur les champs.
        await ref.read(syncQueueProvider).remove(e.uuid);
        setState(() {
          _busy = false;
          _errors = {
            if (e.errors['step'] != null) 'step': e.errors['step']!.first,
            if (e.errors['date'] != null) 'date': e.errors['date']!.first,
            if (e.errors['next_on'] != null) 'next': e.errors['next_on']!.first,
            if (e.errors['comment'] != null)
              'comment': e.errors['comment']!.first,
            if (e.errors.isEmpty) 'form': _failureText(e.failure),
          };
        });
        return;
      }
      showSnack(
        context,
        e.status == QueueStatus.sent
            ? context.t('peer_case_followup_saved')
            : context.t('mobile_pair_followup_queued'),
      );
      context.pop();
    } catch (err) {
      if (mounted) {
        setState(() => _busy = false);
        showSnack(context, apiMessage(context, err));
      }
    }
  }

  String _failureText(String? code) => switch (code) {
    'case_locked' => context.t('cases_errors_locked'),
    'not_found' => context.t('mobile_pair_case_gone'),
    _ => context.t('peer_collecte_refused_generic'),
  };

  @override
  Widget build(BuildContext context) {
    final rules = ref.watch(validationRulesProvider).valueOrNull;
    final steps = rules?.followupSteps ?? const <String>[];
    final theme = Theme.of(context);
    String? err(String k) => _errors[k];
    final count = _errors.keys.where((k) => k != 'form').length;
    return PairDetailScaffold(
      title: context.t('peer_case_add_title'),
      child: ListView(
        key: const ValueKey('pair-followup-form'),
        padding: const EdgeInsets.all(XmSpace.gutter),
        children: [
          Text(
            '${widget.code} · ${context.t('peer_collecte_no_identity_title')}',
            style: theme.textTheme.bodySmall,
          ),
          const SizedBox(height: XmSpace.s3),
          if (count > 0 || _errors.containsKey('form')) ...[
            Semantics(
              liveRegion: true,
              child: XmAlert(
                _errors['form'] ??
                    context.t('mobile_pair_form_errors', {'count': count}),
              ),
            ),
            const SizedBox(height: XmSpace.s3),
          ],
          if (steps.isEmpty)
            TextField(
              key: const ValueKey('fu-step-text'),
              controller: _stepText,
              decoration: InputDecoration(
                labelText: '${context.t('peer_case_fields_step')} *',
                errorText: err('step'),
              ),
            )
          else
            DropdownButtonFormField<String>(
              key: const ValueKey('fu-step'),
              initialValue: _step,
              isExpanded: true,
              decoration: InputDecoration(
                labelText: '${context.t('peer_case_fields_step')} *',
                errorText: err('step'),
              ),
              hint: Text(context.t('peer_collecte_choose')),
              items: [
                for (final s in steps)
                  DropdownMenuItem(value: s, child: Text(s)),
              ],
              onChanged: (s) => setState(() => _step = s),
            ),
          const SizedBox(height: XmSpace.s3),
          PairDateField(
            fieldKey: const ValueKey('fu-date'),
            label: '${context.t('peer_case_fields_date')} *',
            value: _fmt(_date),
            error: err('date'),
            onTap: () => _pick(next: false),
          ),
          const SizedBox(height: XmSpace.s3),
          DropdownButtonFormField<String>(
            key: const ValueKey('fu-outcome'),
            initialValue: _outcome,
            isExpanded: true,
            decoration: InputDecoration(
              labelText: context.t('peer_case_fields_outcome'),
            ),
            items: [
              for (final o in ['planifie', 'realise', 'reporte', 'annule'])
                DropdownMenuItem(
                  value: o,
                  child: Text(context.outcomeLabel(o)),
                ),
            ],
            onChanged: (o) => setState(() => _outcome = o ?? 'planifie'),
          ),
          const SizedBox(height: XmSpace.s3),
          PairDateField(
            fieldKey: const ValueKey('fu-next'),
            label:
                '${context.t('peer_case_fields_next')} (${context.t('mobile_pair_optional')})',
            value: _next == null ? '' : _fmt(_next!),
            error: err('next'),
            onTap: () => _pick(next: true),
            onClear: _next == null ? null : () => setState(() => _next = null),
          ),
          const SizedBox(height: XmSpace.s3),
          TextField(
            key: const ValueKey('fu-comment'),
            controller: _comment,
            minLines: 3,
            maxLines: 6,
            onChanged: (_) => setState(() {}),
            decoration: InputDecoration(
              labelText:
                  '${context.t('peer_case_fields_comment')} (${context.t('mobile_pair_optional')})',
              errorText: err('comment'),
              helperText: context.t('mobile_pair_comment_count', {
                'count': _comment.text.length,
                'max': maxComment,
              }),
            ),
          ),
          const SizedBox(height: XmSpace.s4),
          FilledButton(
            key: const ValueKey('fu-submit'),
            onPressed: _busy ? null : () => _submit(steps),
            child: Text(context.t('peer_case_add_submit')),
          ),
        ],
      ),
    );
  }
}
