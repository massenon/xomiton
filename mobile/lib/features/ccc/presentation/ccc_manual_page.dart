import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/ui/xm_widgets.dart';
import '../application/ccc_progress.dart';
import '../application/ccc_providers.dart';
import '../data/ccc_models.dart';
import 'ccc_widgets.dart';

/// Détail d'un module du manuel : points à cocher « vu », progression gardée
/// sur l'appareil (jamais envoyée au serveur).
class CccManualPage extends ConsumerWidget {
  const CccManualPage({super.key, required this.moduleKey});
  final String moduleKey;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final catalog = ref.watch(cccCatalogProvider);
    return catalog.when(
      loading: () => Scaffold(appBar: AppBar(), body: const LoadingView()),
      error: (e, _) => Scaffold(
        appBar: AppBar(title: Text(context.t('ccc_title'))),
        body: ErrorView(error: e, onRetry: () => ref.invalidate(cccCatalogProvider)),
      ),
      data: (c) {
        final module = c.module(moduleKey);
        if (module == null) {
          return Scaffold(
            appBar: AppBar(title: Text(context.t('ccc_title'))),
            body: EmptyView(context.t('microcopy_error_not_found')),
          );
        }
        return _ModuleView(module: module);
      },
    );
  }
}

class _ModuleView extends ConsumerWidget {
  const _ModuleView({required this.module});
  final ManualModule module;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = Theme.of(context).textTheme;
    final progress = ref.watch(cccProgressProvider);
    final done = seenCount(progress.seen, module);
    final total = module.chapters.length;
    final percent = percentOf(done, total);
    return Scaffold(
      appBar: AppBar(
        title: Text(context.t('ccc_manual_heading')),
        leading: BackButton(onPressed: () => context.canPop() ? context.pop() : context.go('/ccc')),
      ),
      body: ListView(
        padding: const EdgeInsets.all(XmSpace.gutter),
        children: [
          Semantics(
            header: true,
            label: context.t('ccc_manual_detail_label', {'title': module.title}),
            excludeSemantics: true,
            child: Text(module.title, style: t.headlineSmall),
          ),
          if (module.durationMinutes != null)
            Padding(
              padding: const EdgeInsets.only(top: XmSpace.s1),
              child: Semantics(
                label: context.t('ccc_manual_duration_label', {'minutes': module.durationMinutes}),
                excludeSemantics: true,
                child: Row(children: [
                  Icon(Icons.schedule, size: 18, color: context.xm.textMuted),
                  const SizedBox(width: XmSpace.s1),
                  Text(context.t('ccc_manual_duration', {'minutes': module.durationMinutes}),
                      style: t.labelLarge?.copyWith(color: context.xm.textMuted)),
                ]),
              ),
            ),
          if (module.description != null) ...[
            const SizedBox(height: XmSpace.s3),
            Text(module.description!, style: t.bodyMedium),
          ],
          const SizedBox(height: XmSpace.s4),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(XmSpace.s4),
              child: CccProgressBar(
                key: const ValueKey('ccc-module-progress'),
                label: context.t('ccc_manual_progress_module', {'title': module.title}),
                announce: context.t('ccc_manual_progress_announce', {'done': done, 'total': total}),
                percent: percent,
                caption: context.t('ccc_manual_progress_count', {'done': done, 'total': total}),
              ),
            ),
          ),
          if (total > 0 && done >= total)
            Padding(
              padding: const EdgeInsets.only(top: XmSpace.s2),
              child: Row(children: [
                Icon(Icons.check_circle, color: context.xm.success),
                const SizedBox(width: XmSpace.s2),
                Text(context.t('ccc_manual_module_done'), style: t.labelLarge?.copyWith(color: context.xm.success)),
              ]),
            ),
          const SizedBox(height: XmSpace.s4),
          CccHeading(context.t('ccc_manual_chapters_heading')),
          const SizedBox(height: XmSpace.s2),
          if (module.chapters.isEmpty)
            Text(context.t('ccc_manual_chapters_count', {'count': 0}), style: t.bodyMedium),
          for (var i = 0; i < module.chapters.length; i++)
            _ChapterTile(
              module: module,
              rank: i,
              seen: isSeen(progress.seen, module, i),
            ),
          if (!progress.persisted) ...[
            const SizedBox(height: XmSpace.s3),
            XmAlert(context.t('mobile_ccc_storage_unavailable'), icon: Icons.info_outline, tone: context.xm.warning),
          ],
          const SizedBox(height: XmSpace.s4),
          OutlinedButton.icon(
            style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48)),
            onPressed: () => context.canPop() ? context.pop() : context.go('/ccc'),
            icon: const Icon(Icons.arrow_back),
            label: Text(context.t('ccc_manual_back')),
          ),
        ],
      ),
    );
  }
}

class _ChapterTile extends ConsumerWidget {
  const _ChapterTile({required this.module, required this.rank, required this.seen});
  final ManualModule module;
  final int rank;
  final bool seen;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final chapter = module.chapters[rank];
    void toggle(bool? v) =>
        ref.read(cccProgressProvider.notifier).toggle(module.key, rank, on: v ?? !seen);
    return Semantics(
      container: true,
      checked: seen,
      label: context.t('ccc_manual_chapter_seen', {'chapter': chapter}),
      onTap: () => toggle(!seen),
      excludeSemantics: true,
      child: CheckboxListTile(
        key: ValueKey('ccc-chapter-${module.key}-$rank'),
        value: seen,
        onChanged: toggle,
        controlAffinity: ListTileControlAffinity.leading,
        contentPadding: EdgeInsets.zero,
        title: Text(chapter),
      ),
    );
  }
}
