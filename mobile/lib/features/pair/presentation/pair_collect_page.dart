import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../../core/util/dates.dart';
import '../application/collect_controller.dart';
import '../application/collect_logic.dart';
import '../application/pair_providers.dart';
import '../data/pair_models.dart';
import 'pair_ui.dart';

const _stepKeys = [
  'mobile_pair_col_step_dossier',
  'mobile_pair_col_step_lieu',
  'mobile_pair_col_step_personne',
  'mobile_pair_col_step_faits',
  'mobile_pair_col_step_verification',
];

String _fieldErr(BuildContext c, FieldError e) => e.key == '_raw' ? '${e.args['text']}' : c.t(e.key, e.args);

/// Collecte d'un dossier VBG / VDH par étapes (maquettes P8, P9), utilisable
/// hors connexion. Règles de validation actives du catalogue en cache, brouillon
/// par étape, avertissements de plausibilité non bloquants, envoi par la file
/// chiffrée (un seul envoi, même après un rejeu).
class PairCollectPage extends ConsumerStatefulWidget {
  const PairCollectPage({super.key});

  @override
  ConsumerState<PairCollectPage> createState() => _PairCollectPageState();
}

class _PairCollectPageState extends ConsumerState<PairCollectPage> {
  final _notes = TextEditingController();
  bool _notesInit = false;

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  CollectValidator _validator(ValidationRules rules, CollectLists lists) =>
      CollectValidator(rules: rules, lists: lists, today: DateTime.now());

  @override
  Widget build(BuildContext context) {
    final rulesA = ref.watch(validationRulesProvider);
    final listsA = ref.watch(collectListsProvider);
    final state = ref.watch(collectControllerProvider);
    if (!_notesInit) {
      _notes.text = state.form.notes;
      _notesInit = true;
    }
    return PairDetailScaffold(
      title: context.t('peer_collecte_title'),
      child: Builder(
        builder: (context) {
          if (state.result != null) return _ResultView(result: state.result!, onNotesReset: () => _notes.clear());
          if (rulesA.isLoading || listsA.isLoading) return const LoadingView();
          final rules = rulesA.valueOrNull ?? const ValidationRules();
          final lists = listsA.valueOrNull ?? const CollectLists();
          if (!rules.loaded) {
            // Aucune version des règles sur l'appareil : la collecte attend, rien n'est supposé.
            return EmptyView(context.t('mobile_pair_col_rules_missing'));
          }
          if (lists.zones.isEmpty || lists.violenceTypes.isEmpty) {
            return EmptyView(context.t('mobile_pair_col_lists_missing'));
          }
          return _form(context, state, rules, lists);
        },
      ),
    );
  }

  Widget _form(BuildContext context, CollectState state, ValidationRules rules, CollectLists lists) {
    final ctl = ref.read(collectControllerProvider.notifier);
    final v = _validator(rules, lists);
    final theme = Theme.of(context);
    final step = CollectStep.values[state.step];
    final total = CollectStep.values.length;
    final form = state.form;
    String? err(String f) => state.errors[f] == null ? null : _fieldErr(context, state.errors[f]!);
    final online = ref.watch(isOnlineProvider);
    final isLast = step == CollectStep.verification;

    Widget dropdown<T>({
      required String keyName,
      required String label,
      required T? value,
      required List<DropdownMenuItem<T>> items,
      required void Function(T?) onChanged,
      required String field,
      bool allowNone = false,
    }) => DropdownButtonFormField<T>(
      key: ValueKey('$keyName-${value ?? 'none'}'),
      initialValue: value,
      isExpanded: true,
      decoration: InputDecoration(labelText: label, errorText: err(field)),
      hint: Text(context.t('peer_collecte_choose')),
      items: items,
      onChanged: (x) {
        onChanged(x);
        ctl.clearError(field);
      },
    );

    final fields = <Widget>[];
    switch (step) {
      case CollectStep.dossier:
        fields.addAll([
          XmAlert(
            '${context.t('peer_collecte_no_identity_title')} ${context.t('peer_collecte_no_identity')}',
            icon: Icons.privacy_tip_outlined,
            tone: context.xm.info,
          ),
          const SizedBox(height: XmSpace.s4),
          Semantics(
            container: true,
            label: context.t('peer_collecte_fields_kind'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${context.t('peer_collecte_fields_kind')} *', style: theme.textTheme.labelMedium),
                const SizedBox(height: XmSpace.s2),
                Wrap(
                  spacing: XmSpace.s2,
                  runSpacing: XmSpace.s2,
                  children: [
                    for (final k in kCaseKinds)
                      ChoiceChip(
                        key: ValueKey('col-kind-$k'),
                        label: Text('$k · ${context.caseKindLabel(k)}'),
                        selected: form.kind == k,
                        materialTapTargetSize: MaterialTapTargetSize.padded,
                        onSelected: (_) {
                          ctl.update(form.copyWith(kind: k));
                          ctl.clearError('kind');
                        },
                      ),
                  ],
                ),
                if (err('kind') != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(err('kind')!, style: theme.textTheme.bodySmall?.copyWith(color: context.xm.danger)),
                  ),
              ],
            ),
          ),
          const SizedBox(height: XmSpace.s4),
          PairDateField(
            fieldKey: const ValueKey('col-date'),
            label: '${context.t('peer_collecte_fields_date')} *',
            value: _display(form.occurredOn),
            error: err('occurred_on'),
            onTap: () async {
              final now = DateTime.now();
              final cur = v.parseDay(form.occurredOn) ?? now;
              final d = await showDatePicker(
                context: context,
                initialDate: cur.isAfter(now) ? now : cur,
                firstDate: DateTime(now.year - 30),
                lastDate: rules.isActive('date_not_future') ? now : DateTime(now.year + 1),
              );
              if (d != null) {
                ctl.update(form.copyWith(occurredOn: DateFormat('yyyy-MM-dd').format(d)));
                ctl.clearError('occurred_on');
              }
            },
          ),
        ]);
      case CollectStep.lieu:
        final zone = form.zoneId == null ? null : lists.zones.where((z) => z.id == form.zoneId).firstOrNull;
        fields.addAll([
          dropdown<int>(
            keyName: 'col-zone',
            label: '${context.t('peer_collecte_fields_zone')} *',
            value: form.zoneId,
            field: 'zone_id',
            items: [for (final z in lists.zones) DropdownMenuItem(value: z.id, child: Text(z.name))],
            // Changer de département vide la commune : elle appartient à l'ancien.
            onChanged: (z) => ctl.update(form.copyWith(zoneId: z, clearCommune: true)),
          ),
          const SizedBox(height: XmSpace.s4),
          if (zone == null)
            Text(context.t('mobile_pair_col_commune_first'), style: theme.textTheme.bodySmall)
          else
            dropdown<int>(
              keyName: 'col-commune-${zone.id}',
              label: context.t('peer_collecte_fields_commune'),
              value: form.communeId,
              field: 'commune_id',
              items: [for (final c in zone.communes) DropdownMenuItem(value: c.id, child: Text(c.label))],
              onChanged: (c) => ctl.update(form.copyWith(communeId: c)),
            ),
        ]);
      case CollectStep.personne:
        fields.addAll([
          dropdown<int>(
            keyName: 'col-population',
            label: '${context.t('peer_collecte_fields_population')} *',
            value: form.populationId,
            field: 'population_id',
            items: [for (final p in lists.populations) DropdownMenuItem(value: p.id, child: Text(p.label))],
            onChanged: (p) => ctl.update(form.copyWith(populationId: p)),
          ),
          const SizedBox(height: XmSpace.s4),
          dropdown<String>(
            keyName: 'col-sex',
            label: '${context.t('peer_collecte_fields_sex')} *',
            value: form.sex,
            field: 'sex',
            items: [for (final s in kSexes) DropdownMenuItem(value: s, child: Text(context.sexLabel(s)))],
            onChanged: (s) => ctl.update(form.copyWith(sex: s)),
          ),
          const SizedBox(height: XmSpace.s4),
          dropdown<String>(
            keyName: 'col-age',
            label: '${context.t('peer_collecte_fields_age')} *',
            value: form.ageRange,
            field: 'age_range',
            items: [
              for (final a in rules.ageRanges)
                DropdownMenuItem(value: a.value, child: Text(a.label.isEmpty ? context.ageLabel(a.value) : a.label)),
            ],
            onChanged: (a) => ctl.update(form.copyWith(ageRange: a)),
          ),
        ]);
      case CollectStep.faits:
        final min = rules.notesMinLength;
        fields.addAll([
          dropdown<int>(
            keyName: 'col-violence',
            label: '${context.t('peer_collecte_fields_violence')} *',
            value: form.violenceTypeId,
            field: 'violence_type_id',
            items: [for (final t in lists.violenceTypes) DropdownMenuItem(value: t.id, child: Text(t.label))],
            onChanged: (t) => ctl.update(form.copyWith(violenceTypeId: t)),
          ),
          const SizedBox(height: XmSpace.s4),
          DropdownButtonFormField<String>(
            key: ValueKey('col-referral-${form.referral ?? 'none'}'),
            initialValue: form.referral ?? '',
            isExpanded: true,
            decoration: InputDecoration(labelText: context.t('peer_collecte_fields_referral'), errorText: err('referral')),
            items: [
              DropdownMenuItem(value: '', child: Text(context.t('peer_collecte_no_referral'))),
              for (final r in rules.referrals) DropdownMenuItem(value: r, child: Text(r)),
            ],
            onChanged: (r) => ctl.update(r == null || r.isEmpty ? form.copyWith(clearReferral: true) : form.copyWith(referral: r)),
          ),
          const SizedBox(height: XmSpace.s4),
          TextField(
            key: const ValueKey('col-notes'),
            controller: _notes,
            minLines: 4,
            maxLines: 8,
            onChanged: (t) {
              ctl.update(form.copyWith(notes: t));
              ctl.clearError('notes');
            },
            decoration: InputDecoration(
              labelText: context.t('peer_collecte_fields_notes'),
              hintText: context.t('peer_collecte_notes_placeholder'),
              helperText: rules.isActive('min_len_20') ? context.t('peer_collecte_notes_hint', {'min': min}) : null,
              helperMaxLines: 2,
              errorText: err('notes'),
            ),
          ),
        ]);
      case CollectStep.verification:
        fields.addAll(_review(context, state, rules, lists, v));
    }

    final count = state.errors.length;
    return SingleChildScrollView(
      key: const ValueKey('pair-collect'),
      padding: const EdgeInsets.all(XmSpace.gutter),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(context.t('peer_collecte_intro'), style: theme.textTheme.bodySmall),
          if (_rulesDate(context, rules) != null)
            Padding(
              padding: const EdgeInsets.only(top: 2),
              child: Text(
                context.t('mobile_pair_col_rules_date', {'date': _rulesDate(context, rules)}),
                key: const ValueKey('col-rules-date'),
                style: theme.textTheme.bodySmall,
              ),
            ),
          const SizedBox(height: XmSpace.s3),
          Semantics(
            container: true,
            label: context.t('mobile_pair_col_step', {'n': state.step + 1, 'total': total}),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${context.t('mobile_pair_col_step', {'n': state.step + 1, 'total': total})} · ${context.t(_stepKeys[state.step])}',
                  style: theme.textTheme.titleMedium,
                ),
                const SizedBox(height: XmSpace.s2),
                ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: LinearProgressIndicator(value: (state.step + 1) / total, minHeight: 6),
                ),
              ],
            ),
          ),
          const SizedBox(height: XmSpace.s3),
          if (!form.isEmpty) ...[
            Row(
              children: [
                Expanded(child: Text(context.t('peer_collecte_draft_saved'), style: theme.textTheme.bodySmall)),
                TextButton(
                  key: const ValueKey('col-draft-clear'),
                  onPressed: () async {
                    await ctl.clearDraft();
                    _notes.clear();
                    if (context.mounted) showSnack(context, context.t('mobile_pair_col_draft_cleared'));
                  },
                  child: Text(context.t('peer_collecte_draft_clear')),
                ),
              ],
            ),
            const SizedBox(height: XmSpace.s2),
          ],
          if (count > 0) ...[
            Semantics(liveRegion: true, child: XmAlert(context.t('mobile_pair_col_errors', {'count': count}))),
            const SizedBox(height: XmSpace.s3),
          ],
          ...fields,
          const SizedBox(height: XmSpace.s5),
          Row(
            children: [
              if (state.step > 0)
                Expanded(
                  child: OutlinedButton(
                    key: const ValueKey('col-prev'),
                    onPressed: state.busy ? null : ctl.back,
                    child: Text(context.t('peer_cases_previous')),
                  ),
                ),
              if (state.step > 0) const SizedBox(width: XmSpace.s3),
              Expanded(
                flex: 2,
                child: FilledButton(
                  key: ValueKey(isLast ? 'col-submit' : 'col-next'),
                  onPressed: state.busy
                      ? null
                      : () async {
                          if (isLast) {
                            await ctl.submit(v, summary: _summary(lists, form));
                          } else {
                            ctl.next(v);
                          }
                        },
                  child: Text(
                    isLast
                        ? (online ? context.t('peer_collecte_submit') : context.t('mobile_pair_col_submit_offline'))
                        : context.t('peer_cases_next'),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  /// Résumé affichable dans la file, sans le contenu sensible (jamais les notes).
  Map<String, String> _summary(CollectLists lists, CollectForm f) => {
    if (lists.communeName(f.communeId) != null) 'commune': lists.communeName(f.communeId)!,
    if (lists.violenceLabel(f.violenceTypeId) != null) 'violence': lists.violenceLabel(f.violenceTypeId)!,
  };

  /// Date de la version des règles en cache (`meta.updated_at`), si lisible.
  String? _rulesDate(BuildContext context, ValidationRules rules) {
    final d = DateTime.tryParse(rules.updatedAt ?? '');
    return d == null ? null : formatDateTime(context, d);
  }

  String _display(String? iso) {
    if (iso == null || iso.isEmpty) return '';
    final d = DateTime.tryParse(iso);
    return d == null ? iso : DateFormat('dd/MM/yyyy', 'fr').format(d);
  }

  List<Widget> _review(BuildContext context, CollectState state, ValidationRules rules, CollectLists lists, CollectValidator v) {
    final f = state.form;
    final theme = Theme.of(context);
    final ctl = ref.read(collectControllerProvider.notifier);
    final warnings = v.warnings(f, ref.read(knownCasesProvider));
    String none() => context.t('peer_collecte_none');
    Widget section(CollectStep step, List<(String, String)> rows) => Padding(
      padding: const EdgeInsets.only(bottom: XmSpace.s2),
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(XmSpace.s4),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(child: Semantics(header: true, child: Text(context.t(_stepKeys[step.index]), style: theme.textTheme.titleSmall))),
                  TextButton(
                    key: ValueKey('col-edit-${step.name}'),
                    onPressed: () => ctl.goTo(step.index),
                    child: Text(context.t('mobile_pair_col_edit')),
                  ),
                ],
              ),
              for (final (k, val) in rows)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 2),
                  child: Semantics(
                    container: true,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [Text(k, style: theme.textTheme.bodySmall), Text(val.isEmpty ? none() : val)],
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
    return [
      Semantics(header: true, child: Text(context.t('mobile_pair_col_review_title'), style: theme.textTheme.titleMedium)),
      const SizedBox(height: XmSpace.s1),
      Text(context.t('mobile_pair_col_review_text'), style: theme.textTheme.bodySmall),
      const SizedBox(height: XmSpace.s3),
      if (warnings.isNotEmpty) ...[
        Semantics(
          liveRegion: true,
          child: Card(
            key: const ValueKey('col-warnings'),
            color: context.xm.warning.withValues(alpha: 0.18),
            child: Padding(
              padding: const EdgeInsets.all(XmSpace.s4),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(Icons.warning_amber_rounded, color: context.xm.text),
                      const SizedBox(width: XmSpace.s2),
                      Expanded(child: Text(context.t('mobile_pair_col_warn_title'), style: theme.textTheme.titleSmall)),
                    ],
                  ),
                  const SizedBox(height: XmSpace.s1),
                  Text(context.t('mobile_pair_col_warn_text'), style: theme.textTheme.bodySmall),
                  for (final w in warnings)
                    Padding(padding: const EdgeInsets.only(top: XmSpace.s2), child: Text(context.t(w.key, w.args))),
                ],
              ),
            ),
          ),
        ),
        const SizedBox(height: XmSpace.s3),
      ],
      section(CollectStep.dossier, [
        (context.t('peer_collecte_fields_kind'), f.kind == null ? '' : '${f.kind} · ${context.caseKindLabel(f.kind!)}'),
        (context.t('peer_collecte_fields_date'), _display(f.occurredOn)),
      ]),
      section(CollectStep.lieu, [
        (context.t('peer_collecte_fields_zone'), lists.zoneName(f.zoneId) ?? ''),
        (context.t('peer_collecte_fields_commune'), lists.communeName(f.communeId) ?? ''),
      ]),
      section(CollectStep.personne, [
        (context.t('peer_collecte_fields_population'), lists.populationLabel(f.populationId) ?? ''),
        (context.t('peer_collecte_fields_sex'), f.sex == null ? '' : context.sexLabel(f.sex!)),
        (context.t('peer_collecte_fields_age'), f.ageRange == null ? '' : context.ageLabel(f.ageRange!)),
      ]),
      section(CollectStep.faits, [
        (context.t('peer_collecte_fields_violence'), lists.violenceLabel(f.violenceTypeId) ?? ''),
        (context.t('peer_collecte_fields_referral'), f.referral ?? context.t('peer_collecte_no_referral')),
        (context.t('peer_collecte_fields_notes'), f.notes.trim()),
      ]),
      Text(context.t('peer_collecte_footnote'), style: theme.textTheme.bodySmall),
    ];
  }
}

/// Après l'enregistrement : code attribué, état « Envoyé » ou « En file », ou refus du serveur.
class _ResultView extends ConsumerWidget {
  const _ResultView({required this.result, required this.onNotesReset});
  final CollectResult result;
  final VoidCallback onNotesReset;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final ctl = ref.read(collectControllerProvider.notifier);
    final r = result;
    final sent = r.outcome == CollectOutcome.sent;
    final failed = r.outcome == CollectOutcome.failed;
    return SingleChildScrollView(
      key: ValueKey('collect-result-${r.outcome.name}'),
      padding: const EdgeInsets.all(XmSpace.gutter),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (failed) ...[
            Semantics(header: true, child: Text(context.t('peer_collecte_refused_title'), style: theme.textTheme.titleLarge)),
            const SizedBox(height: XmSpace.s3),
            XmAlert(
              r.errors.isEmpty ? _failureText(context, r.failure) : r.errors.values.map((e) => e.first).join(' '),
            ),
            const SizedBox(height: XmSpace.s4),
            FilledButton(
              key: const ValueKey('col-correct'),
              onPressed: () async {
                await ctl.discardEntry(r.entryUuid);
                if (r.form != null) ctl.reopen(r.form!, errors: r.errors);
              },
              child: Text(context.t('peer_collecte_refused_correct')),
            ),
            const SizedBox(height: XmSpace.s2),
            OutlinedButton(
              onPressed: () async {
                await ctl.discardEntry(r.entryUuid);
                ctl.restart();
                onNotesReset();
              },
              child: Text(context.t('peer_collecte_refused_dismiss')),
            ),
          ] else ...[
            Semantics(
              header: true,
              liveRegion: true,
              child: Text(
                sent ? context.t('mobile_pair_col_sent_title') : context.t('mobile_pair_col_queued_title'),
                style: theme.textTheme.titleLarge,
              ),
            ),
            const SizedBox(height: XmSpace.s3),
            Text(sent
                ? (r.code == null ? context.t('peer_collecte_saved_plain') : context.t('peer_collecte_saved', {'count': 1, 'codes': r.code}))
                : context.t('peer_collecte_queued_offline')),
            const SizedBox(height: XmSpace.s3),
            DefinitionCard([
              (context.t('mobile_pair_col_code'), sent ? (r.code ?? '') : context.t('mobile_pair_col_code_later')),
              (
                context.t('mobile_pair_col_state'),
                sent ? context.t('mobile_sync_state_sent') : context.t('mobile_pair_in_queue'),
              ),
            ]),
            for (final w in r.warnings)
              Padding(
                padding: const EdgeInsets.only(top: XmSpace.s3),
                child: XmAlert('${w['message'] ?? ''}', icon: Icons.warning_amber_rounded, tone: context.xm.warning),
              ),
            const SizedBox(height: XmSpace.s4),
            FilledButton(
              key: const ValueKey('col-again'),
              onPressed: () {
                ctl.restart();
                onNotesReset();
              },
              child: Text(context.t('mobile_pair_col_again')),
            ),
            const SizedBox(height: XmSpace.s2),
            OutlinedButton(
              key: const ValueKey('col-see-cases'),
              onPressed: () {
                ctl.restart();
                context.go('/pair/dossiers');
              },
              child: Text(context.t('peer_collecte_all_cases')),
            ),
            const SizedBox(height: XmSpace.s2),
            OutlinedButton(
              key: const ValueKey('col-see-queue'),
              onPressed: () => context.go('/pair/accueil/synchro'),
              child: Text(context.t('mobile_sync_title')),
            ),
          ],
        ],
      ),
    );
  }

  String _failureText(BuildContext context, String? code) => switch (code) {
    'forbidden' => context.t('sync_items_forbidden'),
    'client_uuid_conflict' => context.t('cases_errors_client_uuid_conflict'),
    'uuid_reused' => context.t('sync_items_uuid_reused'),
    _ => context.t('peer_collecte_refused_generic'),
  };
}
