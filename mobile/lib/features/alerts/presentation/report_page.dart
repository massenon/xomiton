import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/platform/platform_services.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../auth/application/session_controller.dart';
import '../application/report_controller.dart';
import '../data/alerts_api.dart';
import '../data/alerts_models.dart';

/// Signalement en six étapes alignées sur Q11 a, même charge utile que le web.
/// Accessible sans compte ; rien ne part avant « Envoyer mon signalement ».
class ReportPage extends ConsumerStatefulWidget {
  const ReportPage({super.key, this.initialType});
  final String? initialType;

  @override
  ConsumerState<ReportPage> createState() => _ReportPageState();
}

class _ReportPageState extends ConsumerState<ReportPage> {
  final _scroll = ScrollController();
  final _description = TextEditingController();
  final _contact = TextEditingController();
  bool _checked = false; // « Continuer » pressé : on montre les erreurs
  bool _draftOffered = true;

  @override
  void initState() {
    super.initState();
    if (widget.initialType == 'need') {
      Future.microtask(
        () => ref.read(reportControllerProvider.notifier).setType('need'),
      );
    }
  }

  @override
  void dispose() {
    _scroll.dispose();
    _description.dispose();
    _contact.dispose();
    super.dispose();
  }

  String _err(BuildContext c, String field, String code) => switch (code) {
    'category' => c.t('alerts_report_fields_category_error'),
    'required' => vRequired(c, 'description'),
    'min' => c.t('alerts_report_fields_description_min', {
      'min': kDescriptionMin,
    }),
    'max' => c.t('validation_max_string', {
      'attribute': c.t('validation_attributes_description'),
      'max': kDescriptionMax,
    }),
    'future' => c.t('alerts_report_fields_date_future'),
    'needs' => c.t('alerts_report_fields_needs_error'),
    'zone_id' => vRequired(c, 'zone_id'),
    'too_many' => c.t('alerts_report_fields_evidence_too_many', {
      'max': kAttachmentsMax,
    }),
    'contact' => vRequired(c, 'contact'),
    'invalid' => c.t('alerts_report_fields_contact_invalid'),
    'preferred_channel' => vRequired(c, 'preferred_channel'),
    _ => c.t('alerts_report_client_errors'),
  };

  @override
  Widget build(BuildContext context) {
    final f = ref.watch(reportControllerProvider);
    final ctrl = ref.read(reportControllerProvider.notifier);
    final online = ref.watch(isOnlineProvider);
    final refs = ref.watch(referentialsProvider);
    if (f.outcome != null) return _ReportDone(outcome: f.outcome!);
    final steps = ReportStep.values;
    final step = steps[f.step];
    final errors = _checked ? f.errors(step) : const <String, String>{};
    final draft = _draftOffered && f.step == 0 && f.description.isEmpty
        ? ctrl.findDraft()
        : null;

    return Scaffold(
      appBar: AppBar(title: Text(context.t('alerts_report_title'))),
      body: SafeArea(
        child: Column(
          children: [
            if (!online) const OfflineBanner(),
            Expanded(
              child: ListView(
                controller: _scroll,
                padding: const EdgeInsets.all(XmSpace.gutter),
                children: [
                  Semantics(
                    header: true,
                    child: Text(
                      context.t('alerts_report_step', {
                        'current': f.step + 1,
                        'total': steps.length,
                      }),
                      style: Theme.of(context).textTheme.labelLarge,
                    ),
                  ),
                  const SizedBox(height: XmSpace.s1),
                  LinearProgressIndicator(value: (f.step + 1) / steps.length),
                  const SizedBox(height: XmSpace.s2),
                  Text(
                    context.t('alerts_report_steps_${step.name}'),
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: XmSpace.s2),
                  Text(
                    context.t('alerts_report_draft_saved'),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  if (draft != null) ...[
                    const SizedBox(height: XmSpace.s3),
                    XmAlert(
                      context.t('alerts_report_draft_found'),
                      icon: Icons.edit_note,
                      tone: context.xm.info,
                    ),
                    Wrap(
                      children: [
                        TextButton(
                          onPressed: () {
                            ctrl.restoreDraft();
                            final restored = ref.read(reportControllerProvider);
                            _description.text = restored.description;
                            setState(() => _draftOffered = false);
                          },
                          child: Text(context.t('alerts_report_draft_resume')),
                        ),
                        TextButton(
                          onPressed: () async {
                            await ctrl.clearDraft();
                            setState(() => _draftOffered = false);
                          },
                          child: Text(context.t('alerts_report_draft_clear')),
                        ),
                      ],
                    ),
                  ],
                  const SizedBox(height: XmSpace.s4),
                  if (f.error != null) ...[
                    XmAlert(
                      f.error == 'error'
                          ? context.t('alerts_report_client_errors')
                          : f.error!,
                    ),
                    const SizedBox(height: XmSpace.s3),
                  ],
                  switch (step) {
                    ReportStep.type => _typeStep(
                      context,
                      f,
                      ctrl,
                      refs.valueOrNull,
                      errors,
                    ),
                    ReportStep.facts => _factsStep(context, f, ctrl, errors),
                    ReportStep.needs => _needsStep(
                      context,
                      f,
                      ctrl,
                      refs.valueOrNull,
                      errors,
                    ),
                    ReportStep.evidence => _evidenceStep(
                      context,
                      f,
                      ctrl,
                      errors,
                    ),
                    ReportStep.contact => _contactStep(
                      context,
                      f,
                      ctrl,
                      errors,
                    ),
                    ReportStep.review => _reviewStep(
                      context,
                      f,
                      ctrl,
                      refs.valueOrNull,
                    ),
                  },
                ],
              ),
            ),
            _footer(context, f, ctrl, step),
          ],
        ),
      ),
    );
  }

  Widget _footer(
    BuildContext context,
    ReportForm f,
    ReportController ctrl,
    ReportStep step,
  ) {
    final last = step == ReportStep.review;
    return Padding(
      padding: const EdgeInsets.all(XmSpace.gutter),
      child: Row(
        children: [
          if (f.step > 0)
            OutlinedButton(
              style: OutlinedButton.styleFrom(minimumSize: const Size(96, 52)),
              onPressed: f.sending ? null : () => ctrl.back(),
              child: Text(context.t('common_back')),
            ),
          const SizedBox(width: XmSpace.s3),
          Expanded(
            child: FilledButton(
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(52),
                backgroundColor: last ? context.xm.info : null,
              ),
              onPressed: f.sending
                  ? null
                  : () async {
                      if (last) {
                        await ctrl.submit();
                        if (_scroll.hasClients &&
                            ref.read(reportControllerProvider).error != null) {
                          _scroll.jumpTo(0);
                        }
                        return;
                      }
                      // État frais : une saisie vient peut-être de changer le formulaire.
                      if (ref
                          .read(reportControllerProvider)
                          .errors(step)
                          .isNotEmpty) {
                        setState(() => _checked = true);
                        return;
                      }
                      setState(() => _checked = false);
                      await ctrl.next();
                    },
              child: Text(
                context.t(last ? 'buttons_help_send_report' : 'common_next'),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _typeStep(
    BuildContext context,
    ReportForm f,
    ReportController ctrl,
    Referentials? refs,
    Map<String, String> errors,
  ) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(context.t('alerts_report_question')),
        const SizedBox(height: XmSpace.s3),
        SegmentedButton<String>(
          segments: [
            ButtonSegment(
              value: 'report',
              label: Text(context.t('alerts_report_tabs_report')),
            ),
            ButtonSegment(
              value: 'need',
              label: Text(context.t('alerts_report_tabs_need')),
            ),
          ],
          selected: {f.type},
          onSelectionChanged: (s) => ctrl.setType(s.first),
        ),
        const SizedBox(height: XmSpace.s2),
        Text(
          context.t(
            f.type == 'need'
                ? 'alerts_report_help_need'
                : 'alerts_report_help_report',
          ),
          style: Theme.of(context).textTheme.bodySmall,
        ),
        if (f.type == 'report') ...[
          const SizedBox(height: XmSpace.s4),
          DropdownButtonFormField<String>(
            isExpanded: true,
            key: ValueKey('cat-${f.category}'),
            initialValue: refs?.types.any((t) => t.slug == f.category) == true
                ? f.category
                : null,
            decoration: InputDecoration(
              labelText: context.t('alerts_report_fields_category'),
              helperText: context.t('alerts_report_fields_category_hint'),
              errorText: errors['category'] == null
                  ? null
                  : _err(context, 'category', errors['category']!),
            ),
            hint: Text(context.t('alerts_report_fields_category_placeholder')),
            items: [
              for (final t in refs?.types ?? const <Choice>[])
                DropdownMenuItem(value: t.slug, child: Text(t.label)),
            ],
            onChanged: (v) => ctrl.update((s) => s.copy(category: v)),
          ),
        ],
        const SizedBox(height: XmSpace.s4),
        Text(
          context.t('alerts_report_sos_hint'),
          style: Theme.of(context).textTheme.bodySmall,
        ),
        TextButton(
          onPressed: () => context.push(
            ref.read(sessionProvider).authenticated ? '/sos' : '/alerte-sos',
          ),
          child: Text(context.t('alerts_report_tabs_sos')),
        ),
      ],
    );
  }

  Widget _factsStep(
    BuildContext context,
    ReportForm f,
    ReportController ctrl,
    Map<String, String> errors,
  ) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        TextField(
          controller: _description,
          minLines: 5,
          maxLines: 10,
          maxLength: kDescriptionMax,
          onChanged: (v) => ctrl.update((s) => s.copy(description: v)),
          decoration: InputDecoration(
            labelText: context.t('alerts_report_fields_description'),
            helperText: context.t('alerts_report_fields_description_help'),
            hintText: context.t(
              'alerts_report_fields_description_placeholder',
              {'min': kDescriptionMin},
            ),
            errorText: errors['description'] == null
                ? null
                : _err(context, 'description', errors['description']!),
          ),
        ),
        const SizedBox(height: XmSpace.s3),
        ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.event_outlined),
          title: Text(context.t('alerts_report_fields_date')),
          subtitle: Text(
            f.occurredOn == null
                ? context.t('alerts_report_fields_date_help')
                : MaterialLocalizations.of(context)
                      .formatMediumDate(f.occurredOn!),
          ),
          trailing: f.occurredOn == null
              ? null
              : IconButton(
                  tooltip: context.t('common_delete'),
                  icon: const Icon(Icons.clear),
                  onPressed: () => ctrl.update((s) => s.copy(occurredOn: null)),
                ),
          onTap: () async {
            final d = await showDatePicker(
              context: context,
              initialDate: f.occurredOn ?? DateTime.now(),
              firstDate: DateTime(2000),
              lastDate: DateTime.now(),
            );
            if (d != null) ctrl.update((s) => s.copy(occurredOn: d));
          },
        ),
        if (errors['occurred_on'] != null)
          XmAlert(_err(context, 'occurred_on', errors['occurred_on']!)),
      ],
    );
  }

  Widget _needsStep(
    BuildContext context,
    ReportForm f,
    ReportController ctrl,
    Referentials? refs,
    Map<String, String> errors,
  ) {
    final zones = refs?.zones ?? const <Zone>[];
    final zone = zones.where((z) => z.id == f.zoneId).firstOrNull;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          '${context.t('alerts_report_fields_needs')} ${context.t(f.type == 'need' ? 'alerts_report_fields_needs_required' : 'alerts_report_fields_needs_optional')}',
          style: Theme.of(context).textTheme.titleMedium,
        ),
        Text(
          context.t('alerts_report_fields_needs_hint'),
          style: Theme.of(context).textTheme.bodySmall,
        ),
        for (final n in refs?.needs ?? const <Choice>[])
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            value: f.needs.contains(n.slug),
            title: Text(n.label),
            onChanged: (v) => ctrl.toggleNeed(n.slug, v == true),
          ),
        if (errors['needs'] != null)
          XmAlert(_err(context, 'needs', errors['needs']!)),
        const SizedBox(height: XmSpace.s4),
        DropdownButtonFormField<int>(
          isExpanded: true,
          key: ValueKey('rz-${f.zoneId}'),
          initialValue: zone?.id,
          decoration: InputDecoration(
            labelText: context.t('alerts_report_fields_zone'),
            errorText: errors['zone_id'] == null
                ? null
                : _err(context, 'zone_id', errors['zone_id']!),
          ),
          hint: Text(context.t('alerts_report_fields_zone_placeholder')),
          items: [
            for (final z in zones)
              DropdownMenuItem(value: z.id, child: Text(z.name)),
          ],
          onChanged: (v) =>
              ctrl.update((s) => s.copy(zoneId: v, communeId: null)),
        ),
        if (zone != null && zone.communes.isNotEmpty) ...[
          const SizedBox(height: XmSpace.s3),
          DropdownButtonFormField<int>(
            isExpanded: true,
            key: ValueKey('rc-${f.zoneId}-${f.communeId}'),
            initialValue: zone.communes.any((c) => c.id == f.communeId)
                ? f.communeId
                : null,
            decoration: InputDecoration(
              labelText: context.t('alerts_report_fields_commune'),
            ),
            hint: Text(context.t('alerts_report_fields_commune_placeholder')),
            items: [
              for (final c in zone.communes)
                DropdownMenuItem(value: c.id, child: Text(c.name)),
            ],
            onChanged: (v) => ctrl.update((s) => s.copy(communeId: v)),
          ),
        ],
        const SizedBox(height: XmSpace.s3),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          value: f.position != null,
          title: Text(context.t('alerts_report_fields_position')),
          subtitle: Text(
            f.position == null
                ? context.t('alerts_report_fields_position_help')
                : context.t('alerts_report_fields_position_attached', {
                    'lat': f.position!.lat.toStringAsFixed(4),
                    'lng': f.position!.lng.toStringAsFixed(4),
                    'accuracy': f.position!.accuracy.round(),
                  }),
          ),
          onChanged: (on) async {
            if (!on) {
              ctrl.update((s) => s.copy(position: null));
              return;
            }
            final p = await ref.read(locationServiceProvider).locate();
            if (!context.mounted) return;
            if (p == null) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text(
                    context.t('alerts_report_fields_position_unavailable'),
                  ),
                ),
              );
            }
            ctrl.update((s) => s.copy(position: p));
          },
        ),
      ],
    );
  }

  Widget _evidenceStep(
    BuildContext context,
    ReportForm f,
    ReportController ctrl,
    Map<String, String> errors,
  ) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          context.t('alerts_report_fields_evidence'),
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: XmSpace.s1),
        XmAlert(
          context.t('alerts_report_fields_evidence_warning'),
          icon: Icons.info_outline,
          tone: context.xm.info,
        ),
        const SizedBox(height: XmSpace.s3),
        OutlinedButton.icon(
          icon: const Icon(Icons.attach_file),
          label: Text(context.t('mobile_report_add_files')),
          onPressed: () async {
            final picked = await ref.read(evidencePickerProvider).pick();
            if (picked.isEmpty) return;
            ctrl.update((s) => s.copy(files: [...s.files, ...picked]));
          },
        ),
        if (f.files.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: XmSpace.s2),
            child: Text(
              context.t('alerts_report_fields_evidence_added', {
                'count': f.files.length,
              }),
            ),
          ),
        for (var i = 0; i < f.files.length; i++)
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.insert_drive_file_outlined),
            title: Text(f.files[i].name, overflow: TextOverflow.ellipsis),
            trailing: IconButton(
              tooltip: context.t('common_delete'),
              icon: const Icon(Icons.close),
              onPressed: () =>
                  ctrl.update((s) => s.copy(files: [...s.files]..removeAt(i))),
            ),
          ),
        if (errors['attachments'] != null)
          XmAlert(_err(context, 'attachments', errors['attachments']!)),
      ],
    );
  }

  Widget _contactStep(
    BuildContext context,
    ReportForm f,
    ReportController ctrl,
    Map<String, String> errors,
  ) {
    final member = ref.watch(sessionProvider).authenticated;
    const channels = ['sms', 'whatsapp', 'call', 'email'];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (member)
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            value: f.anonymous,
            title: Text(context.t('alerts_report_fields_anonymous')),
            subtitle: Text(context.t('alerts_report_fields_anonymous_help')),
            onChanged: (v) => ctrl.update((s) => s.copy(anonymous: v)),
          ),
        Text(
          context.t('alerts_report_fields_contactable'),
          style: Theme.of(context).textTheme.titleMedium,
        ),
        RadioGroup<bool>(
          groupValue: f.contactable,
          onChanged: (v) => ctrl.update((s) => s.copy(contactable: v == true)),
          child: Column(
            children: [
              RadioListTile<bool>(
                contentPadding: EdgeInsets.zero,
                value: false,
                title: Text(context.t('alerts_report_fields_contactable_no')),
              ),
              RadioListTile<bool>(
                contentPadding: EdgeInsets.zero,
                value: true,
                title: Text(context.t('alerts_report_fields_contactable_yes')),
              ),
            ],
          ),
        ),
        if (f.contactable) ...[
          const SizedBox(height: XmSpace.s3),
          TextField(
            controller: _contact,
            keyboardType: TextInputType.emailAddress,
            onChanged: (v) => ctrl.update((s) => s.copy(contact: v)),
            decoration: InputDecoration(
              labelText: context.t('alerts_report_fields_contact'),
              helperText: context.t('alerts_report_fields_contact_hint'),
              hintText: context.t('alerts_report_fields_contact_placeholder'),
              errorText: errors['contact'] == null
                  ? null
                  : _err(context, 'contact', errors['contact']!),
            ),
          ),
          const SizedBox(height: XmSpace.s3),
          Text(
            context.t('alerts_report_fields_preferred_channel'),
            style: Theme.of(context).textTheme.titleSmall,
          ),
          Wrap(
            spacing: XmSpace.s2,
            children: [
              for (final c in channels)
                ChoiceChip(
                  label: Text(context.t('alerts_report_channels_$c')),
                  selected: f.channel == c,
                  onSelected: (_) => ctrl.update((s) => s.copy(channel: c)),
                ),
            ],
          ),
          if (errors['preferred_channel'] != null)
            XmAlert(
              _err(context, 'preferred_channel', errors['preferred_channel']!),
            ),
        ],
      ],
    );
  }

  Widget _reviewStep(
    BuildContext context,
    ReportForm f,
    ReportController ctrl,
    Referentials? refs,
  ) {
    final zone = refs?.zones.where((z) => z.id == f.zoneId).firstOrNull;
    final commune = zone?.communes
        .where((c) => c.id == f.communeId)
        .firstOrNull;
    final type = refs?.types.where((t) => t.slug == f.category).firstOrNull;
    Widget row(String label, String value, int step) => ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(label, style: Theme.of(context).textTheme.labelMedium),
      subtitle: Text(
        value.isEmpty ? context.t('alerts_report_review_empty') : value,
      ),
      trailing: IconButton(
        tooltip: context.t('alerts_report_review_edit_step', {'step': label}),
        icon: const Icon(Icons.edit_outlined),
        onPressed: () => ctrl.goTo(step),
      ),
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          context.t('alerts_report_review_title'),
          style: Theme.of(context).textTheme.titleMedium,
        ),
        Text(context.t('alerts_report_review_text')),
        const Divider(),
        row(
          context.t('alerts_report_steps_type'),
          [
            context.t(
              f.type == 'need'
                  ? 'alerts_report_tabs_need'
                  : 'alerts_report_tabs_report',
            ),
            if (type != null) type.label,
          ].join(' · '),
          0,
        ),
        row(context.t('alerts_report_steps_facts'), f.description.trim(), 1),
        row(
          context.t('alerts_report_review_place'),
          [
            if (zone != null) zone.name,
            if (commune != null) commune.name,
          ].join(' · '),
          2,
        ),
        row(
          context.t('alerts_report_steps_needs'),
          f.needs
              .map(
                (n) =>
                    refs?.needs.where((x) => x.slug == n).firstOrNull?.label ??
                    n,
              )
              .join(', '),
          2,
        ),
        row(
          context.t('alerts_report_review_position_yes'),
          context.t(
            f.position == null
                ? 'alerts_report_review_position_no'
                : 'alerts_report_review_position_yes',
          ),
          2,
        ),
        row(
          context.t('alerts_report_steps_evidence'),
          f.files.isEmpty
              ? context.t('alerts_report_review_no_files')
              : context.t('alerts_report_fields_evidence_added', {
                  'count': f.files.length,
                }),
          3,
        ),
        row(
          context.t('alerts_report_steps_contact'),
          f.contactable
              ? context.t('alerts_report_review_anonymous_no', {
                  'contact': f.contact.trim(),
                })
              : context.t('alerts_report_review_anonymous_yes'),
          4,
        ),
      ],
    );
  }
}

/// Code de suivi (en grand, à copier) ou état « En attente d'envoi » : dans ce
/// cas le code est attribué par le serveur au rejeu, puis notifié ici.
class _ReportDone extends ConsumerWidget {
  const _ReportDone({required this.outcome});
  final ReportOutcome outcome;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    var code = outcome.code;
    if (outcome.queued) {
      final r = ref.watch(outboxResultsProvider).valueOrNull;
      if (r != null && r.entry.uuid == outcome.uuid) {
        code =
            (r.data['tracking_code_display'] ?? r.data['tracking_code'])
                as String?;
      }
    }
    final queued = code == null;
    return Scaffold(
      appBar: AppBar(
        title: Text(context.t('alerts_report_title')),
        automaticallyImplyLeading: false,
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(XmSpace.gutter),
          children: [
            const SizedBox(height: XmSpace.s5),
            Icon(
              queued ? Icons.schedule_send_outlined : Icons.check_circle,
              size: 56,
              color: queued ? context.xm.warning : context.xm.success,
            ),
            const SizedBox(height: XmSpace.s3),
            Semantics(
              liveRegion: true,
              child: Text(
                context.t(
                  queued
                      ? 'mobile_report_waiting_title'
                      : outcome.need
                      ? 'alerts_report_done_need_title'
                      : 'alerts_report_done_title',
                ),
                style: Theme.of(context).textTheme.headlineSmall,
                textAlign: TextAlign.center,
              ),
            ),
            const SizedBox(height: XmSpace.s2),
            Text(
              context.t(
                queued
                    ? 'alerts_report_done_queued'
                    : 'alerts_report_done_text',
              ),
              textAlign: TextAlign.center,
            ),
            if (outcome.duplicate) ...[
              const SizedBox(height: XmSpace.s2),
              Text(
                context.t('alerts_report_done_duplicate'),
                textAlign: TextAlign.center,
              ),
            ],
            if (!queued) ...[
              const SizedBox(height: XmSpace.s5),
              Text(
                context.t('alerts_report_done_tracking'),
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.labelLarge,
              ),
              const SizedBox(height: XmSpace.s2),
              SelectableText(
                code,
                key: const Key('tracking-code'),
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineMedium
                    ?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: XmSpace.s2),
              Text(
                context.t('alerts_report_done_tracking_help'),
                textAlign: TextAlign.center,
              ),
              Text(
                context.t('alerts_report_done_once'),
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: XmSpace.s3),
              OutlinedButton.icon(
                icon: const Icon(Icons.copy),
                label: Text(context.t('alerts_report_done_copy')),
                onPressed: () async {
                  await Clipboard.setData(ClipboardData(text: code!));
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text(context.t('alerts_report_done_copied')),
                      ),
                    );
                  }
                },
              ),
            ],
            const SizedBox(height: XmSpace.s5),
            FilledButton(
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(52),
              ),
              onPressed: () => context.go(
                ref.read(sessionProvider).authenticated ? '/home' : '/login',
              ),
              child: Text(context.t('common_back_home')),
            ),
            TextButton(
              onPressed: () => context.push('/suivi'),
              child: Text(context.t('alerts_report_tracking_title')),
            ),
          ],
        ),
      ),
    );
  }
}
