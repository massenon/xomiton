import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/auth_spaces/presentation/space_widgets.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/sync/sync_providers.dart';
import '../../../core/sync/sync_queue.dart';
import '../data/pair_api.dart';

/// Ton d'une pastille d'état (couleur + texte : jamais la couleur seule).
enum PairTone { neutral, primary, danger, warning, success, violet }

Color _tone(BuildContext c, PairTone t) {
  final xm = c.xm;
  return switch (t) {
    PairTone.neutral => xm.textMuted,
    PairTone.primary => xm.primary,
    PairTone.danger => xm.danger,
    PairTone.warning => xm.warning,
    PairTone.success => xm.success,
    PairTone.violet => xm.accentViolet,
  };
}

class PairBadge extends StatelessWidget {
  const PairBadge(
    this.label, {
    super.key,
    this.tone = PairTone.neutral,
    this.icon,
  });
  final String label;
  final PairTone tone;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final c = _tone(context, tone);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: c.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: c.withValues(alpha: 0.6)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 14, color: context.xm.text),
            const SizedBox(width: 4),
          ],
          Flexible(
            child: Text(label, style: Theme.of(context).textTheme.labelMedium),
          ),
        ],
      ),
    );
  }
}

/// Libellés d'énumérations : l'ARB (`enums_*`), jamais écrits en dur.
extension PairLabels on BuildContext {
  String _enum(String key, String fallback) {
    final s = AppStrings.of(this);
    return s.has(key) ? s.t(key) : fallback;
  }

  String caseKindLabel(String kind) =>
      _enum('enums_cases_case_kind_$kind', kind);
  String caseStatusLabel(String status) =>
      _enum('enums_cases_case_status_$status', status);
  String outcomeLabel(String o) => _enum('enums_cases_followup_outcome_$o', o);
  String alertStatusLabel(String s) => _enum('enums_alerts_alert_status_$s', s);
  String alertTypeLabel(String s) => _enum('enums_alerts_alert_type_$s', s);
  String priorityLabel(String s) => _enum('enums_alerts_alert_priority_$s', s);
  String sexLabel(String s) => _enum('enums_reference_sex_$s', s);
  String ageLabel(String s) => _enum(ageRangeKey(s), s);
}

PairTone statusTone(String status) => switch (status) {
  'nouveau' => PairTone.danger,
  'pris_en_charge' || 'en_cours' => PairTone.primary,
  'traite' || 'clos' => PairTone.success,
  'ouvert' => PairTone.warning,
  'archive' => PairTone.neutral,
  _ => PairTone.neutral,
};

PairTone priorityTone(String p) => switch (p) {
  'critique' => PairTone.danger,
  'haute' => PairTone.warning,
  _ => PairTone.neutral,
};

String formatDay(BuildContext context, String? iso) {
  if (iso == null || iso.isEmpty) return '';
  final d = DateTime.tryParse(iso);
  if (d == null) return iso;
  return DateFormat(
    AppStrings.of(context).raw('dates_formats_medium'),
    'fr',
  ).format(d);
}

/// Message d'erreur d'API : le texte du serveur (déjà en français, tutoiement),
/// sinon le message générique.
String apiMessage(BuildContext context, Object error) {
  final e = ApiException.from(error);
  if (e.isNetwork) return context.t('peer_alerts_offline');
  return e.message ?? context.t('microcopy_error_title');
}

/// Pastille d'état de synchronisation : réseau + éléments en attente ; ouvre la file.
class SyncStatusChip extends ConsumerWidget {
  const SyncStatusChip({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final online = ref.watch(isOnlineProvider);
    final entries =
        ref.watch(syncQueueEntriesProvider).value ?? const <QueueEntry>[];
    final waiting = entries.where((e) => e.status != QueueStatus.sent).length;
    final conflicts = entries
        .where((e) => e.status == QueueStatus.conflict)
        .length;
    final text = waiting == 0
        ? context.t('mobile_sync_short', {'count': 0})
        : context.t('mobile_sync_short', {'count': waiting});
    final net = online
        ? context.t('peer_status_online')
        : context.t('peer_status_offline');
    final xm = context.xm;
    return Semantics(
      button: true,
      label:
          '$net. ${context.t('peer_status_queue', {'count': waiting})}. ${context.t('mobile_pair_open_sync')}',
      excludeSemantics: true,
      child: Material(
        color: conflicts > 0
            ? xm.warning.withValues(alpha: 0.25)
            : xm.surfaceMuted,
        shape: StadiumBorder(
          side: BorderSide(color: online ? xm.border : xm.warning, width: 1.5),
        ),
        child: InkWell(
          key: const ValueKey('sync-chip'),
          customBorder: const StadiumBorder(),
          onTap: () => context.go('/pair/accueil/synchro'),
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: XmSpace.targetMin),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: XmSpace.s3),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    online
                        ? Icons.cloud_done_outlined
                        : Icons.cloud_off_outlined,
                    size: 18,
                    color: xm.text,
                  ),
                  const SizedBox(width: 6),
                  Flexible(
                    child: Text(
                      '$net · $text',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.labelMedium,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// En-tête des écrans racine de l'espace pair : titre, pastille d'espace, état de synchro.
class PairHeader extends StatelessWidget {
  const PairHeader({super.key, required this.title, this.subtitle});
  final String title;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Wrap(
          spacing: XmSpace.s2,
          runSpacing: XmSpace.s2,
          alignment: WrapAlignment.end,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: const [SyncStatusChip(), SpaceSwitcherPill()],
        ),
        const SizedBox(height: XmSpace.s3),
        Semantics(
          header: true,
          child: Text(title, style: theme.textTheme.headlineMedium),
        ),
        if (subtitle != null) ...[
          const SizedBox(height: XmSpace.s1),
          Text(subtitle!, style: theme.textTheme.bodySmall),
        ],
      ],
    );
  }
}

/// Écran de détail de l'espace pair : barre d'application (retour) dans la
/// barre d'onglets de l'espace, qui reste visible (SOS toujours à portée).
class PairDetailScaffold extends StatelessWidget {
  const PairDetailScaffold({
    super.key,
    required this.title,
    required this.child,
    this.actions,
    this.backLabel,
  });
  final String title;
  final Widget child;
  final List<Widget>? actions;
  final String? backLabel;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(title, maxLines: 1, overflow: TextOverflow.ellipsis),
      actions: [
        const Padding(
          padding: EdgeInsets.only(right: XmSpace.s2),
          child: SyncStatusChip(),
        ),
        ...?actions,
      ],
    ),
    body: SafeArea(child: child),
  );
}

class SectionHeading extends StatelessWidget {
  const SectionHeading(this.title, {super.key, this.count});
  final String title;
  final String? count;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: XmSpace.s2),
    child: Row(
      children: [
        Expanded(
          child: Semantics(
            header: true,
            child: Text(title, style: Theme.of(context).textTheme.titleMedium),
          ),
        ),
        if (count != null) PairBadge(count!),
      ],
    ),
  );
}

/// Ligne cliquable (≥ 48 px), chevron à droite.
class LinkRow extends StatelessWidget {
  const LinkRow({
    super.key,
    required this.child,
    required this.onTap,
    this.leading,
    this.semanticLabel,
  });
  final Widget child;
  final VoidCallback onTap;
  final IconData? leading;
  final String? semanticLabel;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: semanticLabel,
    child: Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(XmSpace.radiusLg),
        onTap: onTap,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 56),
          child: Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: XmSpace.s4,
              vertical: XmSpace.s3,
            ),
            child: Row(
              children: [
                if (leading != null) ...[
                  Icon(leading, color: context.xm.primary),
                  const SizedBox(width: XmSpace.s3),
                ],
                Expanded(child: child),
                const Icon(Icons.chevron_right),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}

/// Liste de définitions (libellé / valeur) dans une carte.
class DefinitionCard extends StatelessWidget {
  const DefinitionCard(this.rows, {super.key});
  final List<(String, String)> rows;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    return Card(
      child: Padding(
        padding: const EdgeInsets.symmetric(
          horizontal: XmSpace.s4,
          vertical: XmSpace.s2,
        ),
        child: Column(
          children: [
            for (final (k, v) in rows)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: XmSpace.s2),
                child: Semantics(
                  container: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Align(
                        alignment: Alignment.centerLeft,
                        child: Text(k, style: t.bodySmall),
                      ),
                      Align(
                        alignment: Alignment.centerLeft,
                        child: Text(v.isEmpty ? '—' : v, style: t.bodyMedium),
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

void showSnack(BuildContext context, String text) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(text)));
}
