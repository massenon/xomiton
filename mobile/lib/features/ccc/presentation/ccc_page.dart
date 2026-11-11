import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/platform/platform_services.dart';
import '../../../core/ui/xm_widgets.dart';
import '../application/ccc_progress.dart';
import '../application/ccc_providers.dart';
import '../data/ccc_models.dart';
import 'ccc_widgets.dart';

/// Onglets de `/ccc` ; le paramètre `onglet` de l'adresse en est la valeur.
const List<String> kCccTabs = ['manuel', 'images', 'bd', 'lois'];

/// Outils CCC (docs/API.md §19 bis ; web `/ccc`) : Manuel · Boîtes à images ·
/// BD SSR · Lois. Une seule implémentation pour le membre et le pair ; le
/// « mode présentation » (compte pair actif) ajoute le guide de l'animateur·rice.
class CccPage extends ConsumerWidget {
  const CccPage({super.key, this.tab});
  final String? tab;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final catalog = ref.watch(cccCatalogProvider);
    return Scaffold(
      appBar: AppBar(title: Text(context.t('ccc_title'))),
      body: catalog.when(
        loading: () => const LoadingView(),
        error: (e, _) => _isForbidden(e)
            ? _ForbiddenView(onRetry: () => ref.invalidate(cccCatalogProvider))
            : ErrorView(error: e, onRetry: () => ref.invalidate(cccCatalogProvider)),
        data: (c) => _CccTabs(catalog: c, initial: kCccTabs.indexOf(tab ?? '').clamp(0, kCccTabs.length - 1)),
      ),
    );
  }
}

bool _isForbidden(Object e) {
  final a = ApiException.from(e);
  return a.status == 403 && !a.isNetwork;
}

/// 403 `ccc_tools.view` : état distinct de l'erreur réseau.
class _ForbiddenView extends StatelessWidget {
  const _ForbiddenView({required this.onRetry});
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(XmSpace.s5),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Icon(Icons.lock_outline, size: 40, color: context.xm.textMuted),
            const SizedBox(height: XmSpace.s3),
            Text(context.t('mobile_ccc_forbidden'),
                key: const ValueKey('ccc-forbidden'), textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: XmSpace.s4),
            OutlinedButton(
              style: OutlinedButton.styleFrom(minimumSize: const Size(160, 48)),
              onPressed: onRetry,
              child: Text(context.t('common_retry')),
            ),
          ]),
        ),
      );
}

class _CccTabs extends ConsumerWidget {
  const _CccTabs({required this.catalog, required this.initial});
  final CccCatalog catalog;
  final int initial;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final presentation = ref.watch(cccPresentationProvider);
    return DefaultTabController(
      length: kCccTabs.length,
      initialIndex: initial,
      child: Column(
        children: [
          if (presentation)
            Padding(
              padding: const EdgeInsets.fromLTRB(XmSpace.gutter, XmSpace.s2, XmSpace.gutter, 0),
              child: Semantics(
                container: true,
                child: XmAlert(
                  '${context.t('ccc_presentation_badge')} : ${context.t('ccc_presentation_notice')}',
                  icon: Icons.slideshow_outlined,
                  tone: context.xm.info,
                ),
              ),
            ),
          Semantics(
            container: true,
            label: context.t('ccc_tabs_label'),
            child: TabBar(
              isScrollable: true,
              tabAlignment: TabAlignment.start,
              tabs: [
                for (final k in const ['manuel', 'boites_images', 'bd', 'lois'])
                  Tab(height: 48, text: context.t('ccc_tabs_$k')),
              ],
            ),
          ),
          Expanded(
            child: TabBarView(
              children: [
                _ManualTab(modules: catalog.manual),
                _ImagesTab(boxes: catalog.imageBoxes, presentation: presentation),
                _ComicsTab(comics: catalog.comics),
                _LawsTab(count: catalog.lawsCount, url: catalog.lawsUrl),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyTab extends StatelessWidget {
  const _EmptyTab(this.messageKey);
  final String messageKey;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: XmSpace.s5),
        child: Column(children: [
          Icon(Icons.inbox_outlined, size: 36, color: context.xm.textMuted),
          const SizedBox(height: XmSpace.s2),
          Text(context.t(messageKey), textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleSmall),
          const SizedBox(height: XmSpace.s1),
          Text(context.t('ccc_empty_hint'),
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(color: context.xm.textMuted)),
        ]),
      );
}

ListView _tabList(List<Widget> children) => ListView(
      padding: const EdgeInsets.all(XmSpace.gutter),
      children: children,
    );

// ---------------------------------------------------------------- Manuel

class _ManualTab extends ConsumerWidget {
  const _ManualTab({required this.modules});
  final List<ManualModule> modules;

  Future<void> _confirmReset(BuildContext context, WidgetRef ref) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(ctx.t('mobile_ccc_reset_confirm_title')),
        content: Text(ctx.t('mobile_ccc_reset_confirm_body')),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: Text(ctx.t('common_cancel'))),
          FilledButton(onPressed: () => Navigator.of(ctx).pop(true), child: Text(ctx.t('ccc_manual_reset'))),
        ],
      ),
    );
    if (ok != true) return;
    await ref.read(cccProgressProvider.notifier).reset();
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(context.t('ccc_manual_reset_done'))));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final progress = ref.watch(cccProgressProvider);
    final all = totals(progress.seen, modules);
    return _tabList([
      CccHeading(
        context.t('ccc_manual_heading'),
        subtitle: context.t('ccc_manual_intro'),
      ),
      const SizedBox(height: XmSpace.s3),
      if (modules.isEmpty)
        const _EmptyTab('ccc_empty_manuel')
      else ...[
        Text(context.t('ccc_manual_modules_count', {'count': modules.length}), style: Theme.of(context).textTheme.labelLarge),
        const SizedBox(height: XmSpace.s2),
        Card(
          key: const ValueKey('ccc-global-progress'),
          child: Padding(
            padding: const EdgeInsets.all(XmSpace.s4),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(context.t('ccc_manual_progress_global'), style: Theme.of(context).textTheme.titleSmall),
              const SizedBox(height: XmSpace.s2),
              CccProgressBar(
                key: const ValueKey('ccc-global-bar'),
                label: context.t('ccc_manual_progress_global'),
                announce: context.t('ccc_manual_global_announce', {'percent': all.percent}),
                percent: all.percent,
                caption: context.t('mobile_ccc_manual_progress_summary', {'done': all.done, 'total': all.total, 'percent': all.percent}),
              ),
            ]),
          ),
        ),
        const SizedBox(height: XmSpace.s3),
        for (final m in modules) ...[
          _ModuleCard(module: m, seen: progress.seen),
          const SizedBox(height: XmSpace.s3),
        ],
        if (!progress.persisted) XmAlert(context.t('mobile_ccc_storage_unavailable'), icon: Icons.info_outline, tone: context.xm.warning),
        if (all.done > 0)
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              key: const ValueKey('ccc-reset'),
              style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
              onPressed: () => _confirmReset(context, ref),
              icon: const Icon(Icons.restart_alt),
              label: Text(context.t('ccc_manual_reset')),
            ),
          ),
      ],
    ]);
  }
}

class _ModuleCard extends StatelessWidget {
  const _ModuleCard({required this.module, required this.seen});
  final ManualModule module;
  final SeenState seen;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    final done = seenCount(seen, module);
    final total = module.chapters.length;
    final percent = percentOf(done, total);
    final complete = total > 0 && done >= total;
    return Card(
      child: InkWell(
        key: ValueKey('ccc-module-${module.key}'),
        borderRadius: BorderRadius.circular(XmSpace.radiusLg),
        onTap: () => context.push('/ccc/manuel/${module.key}'),
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 56),
          child: Padding(
            padding: const EdgeInsets.all(XmSpace.s4),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Expanded(child: Text(module.title, style: t.titleSmall)),
                const Icon(Icons.chevron_right),
              ]),
              const SizedBox(height: XmSpace.s1),
              Text(
                [
                  if (module.durationMinutes != null) context.t('ccc_manual_duration', {'minutes': module.durationMinutes}),
                  context.t('ccc_manual_chapters_count', {'count': total}),
                ].join(' ${context.t('common_separator')} '),
                style: t.labelMedium?.copyWith(color: context.xm.textMuted),
              ),
              const SizedBox(height: XmSpace.s2),
              CccProgressBar(
                label: context.t('ccc_manual_progress_module', {'title': module.title}),
                announce: context.t('ccc_manual_progress_announce', {'done': done, 'total': total}),
                percent: percent,
                caption: context.t('ccc_manual_progress_count', {'done': done, 'total': total}),
              ),
              if (complete)
                Padding(
                  padding: const EdgeInsets.only(top: XmSpace.s1),
                  child: Row(children: [
                    Icon(Icons.check_circle, size: 18, color: context.xm.success),
                    const SizedBox(width: XmSpace.s1),
                    Text(context.t('ccc_manual_module_done'), style: t.labelMedium?.copyWith(color: context.xm.success)),
                  ]),
                ),
            ]),
          ),
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------- Boîtes à images

class _ImagesTab extends StatelessWidget {
  const _ImagesTab({required this.boxes, required this.presentation});
  final List<PagedTool> boxes;
  final bool presentation;

  @override
  Widget build(BuildContext context) {
    final hasSlides = boxes.any((b) => b.pages.isNotEmpty);
    final narrow = MediaQuery.textScalerOf(context).scale(14) > 18;
    return _tabList([
      CccHeading(context.t('ccc_images_heading'), subtitle: context.t('ccc_images_intro')),
      const SizedBox(height: XmSpace.s3),
      if (boxes.isEmpty)
        const _EmptyTab('ccc_empty_boites_images')
      else ...[
        if (hasSlides)
          FilledButton.icon(
            key: const ValueKey('ccc-start-all'),
            style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
            onPressed: () => context.push('/ccc/animation'),
            icon: const Icon(Icons.fullscreen),
            label: Text(context.t('ccc_images_start')),
          ),
        const SizedBox(height: XmSpace.s3),
        LayoutBuilder(
          builder: (context, c) {
            const gap = XmSpace.s3;
            final cols = (c.maxWidth >= 340 && !narrow) ? 2 : 1;
            final w = (c.maxWidth - gap * (cols - 1)) / cols;
            return Wrap(
              spacing: gap,
              runSpacing: gap,
              children: [
                for (var i = 0; i < boxes.length; i++)
                  SizedBox(width: w, child: _BoxCard(box: boxes[i], number: i + 1, presentation: presentation)),
              ],
            );
          },
        ),
        const SizedBox(height: XmSpace.s4),
        _TipsCard(),
      ],
    ]);
  }
}

class _BoxCard extends StatelessWidget {
  const _BoxCard({required this.box, required this.number, required this.presentation});
  final PagedTool box;
  final int number;
  final bool presentation;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        AspectRatio(
          aspectRatio: 16 / 9,
          child: CccImage(box.cover, alt: box.pages.firstOrNull?.alt ?? box.title),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(XmSpace.s3, XmSpace.s3, XmSpace.s3, 0),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(
              '${context.t('ccc_images_badge', {'number': number})} ${context.t('common_separator')} '
              '${context.t('ccc_images_count', {'count': box.pages.length})}',
              style: t.labelMedium?.copyWith(color: context.xm.textMuted),
            ),
            const SizedBox(height: XmSpace.s1),
            Text(box.title, style: t.titleSmall),
            if (presentation && box.description != null) ...[
              const SizedBox(height: XmSpace.s2),
              Semantics(
                container: true,
                label: '${context.t('ccc_images_guide')}. ${box.description}',
                child: ExcludeSemantics(
                  child: Text(box.description!, key: ValueKey('ccc-guide-${box.key}'), style: t.bodySmall),
                ),
              ),
            ],
          ]),
        ),
        if (box.pages.isNotEmpty)
          Padding(
            padding: const EdgeInsets.all(XmSpace.s2),
            child: Semantics(
              button: true,
              label: context.t('ccc_images_start_one', {'title': box.title}),
              excludeSemantics: true,
              child: TextButton.icon(
                key: ValueKey('ccc-start-${box.key}'),
                style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
                onPressed: () => context.push('/ccc/animation?boite=${box.key}'),
                icon: const Icon(Icons.fullscreen),
                label: Text(context.t('ccc_images_start')),
              ),
            ),
          ),
      ]),
    );
  }
}

class _TipsCard extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(XmSpace.s4),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Semantics(
            header: true,
            child: Row(children: [
              Icon(Icons.lightbulb_outline, color: context.xm.warning),
              const SizedBox(width: XmSpace.s2),
              Expanded(child: Text(context.t('ccc_images_tips_heading'), style: t.titleSmall)),
            ]),
          ),
          const SizedBox(height: XmSpace.s2),
          for (var i = 0; i < 5; i++)
            Padding(
              padding: const EdgeInsets.only(bottom: XmSpace.s1),
              child: Text('${i + 1}. ${context.t('ccc_images_tips_$i')}', style: t.bodyMedium),
            ),
          const SizedBox(height: XmSpace.s2),
          Text(context.t('ccc_images_tips_reminder'), style: t.bodySmall?.copyWith(color: context.xm.textMuted)),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton(
              style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
              onPressed: () => context.push('/urgences'),
              child: Text(context.t('ccc_images_directory')),
            ),
          ),
        ]),
      ),
    );
  }
}

// ---------------------------------------------------------------- BD

class _ComicsTab extends StatelessWidget {
  const _ComicsTab({required this.comics});
  final List<PagedTool> comics;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    return _tabList([
      CccHeading(context.t('ccc_comic_heading'), subtitle: context.t('ccc_comic_intro')),
      const SizedBox(height: XmSpace.s3),
      if (comics.isEmpty)
        const _EmptyTab('ccc_empty_bd')
      else
        for (final c in comics) ...[
          Card(
            clipBehavior: Clip.antiAlias,
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              AspectRatio(
                aspectRatio: 16 / 9,
                child: CccImage(c.cover, alt: c.pages.firstOrNull?.alt ?? c.title),
              ),
              Padding(
                padding: const EdgeInsets.all(XmSpace.s3),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(c.title, style: t.titleSmall),
                  Text(context.t('ccc_comic_pages_count', {'count': c.pages.length}),
                      style: t.labelMedium?.copyWith(color: context.xm.textMuted)),
                  if (c.description != null) ...[
                    const SizedBox(height: XmSpace.s1),
                    Text(c.description!, style: t.bodySmall),
                  ],
                ]),
              ),
              if (c.pages.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.fromLTRB(XmSpace.s2, 0, XmSpace.s2, XmSpace.s2),
                  child: TextButton.icon(
                    key: ValueKey('ccc-read-${c.key}'),
                    style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
                    onPressed: () => context.push('/ccc/bd/${c.key}'),
                    icon: const Icon(Icons.auto_stories_outlined),
                    label: Text(context.t('ccc_comic_read')),
                  ),
                ),
            ]),
          ),
          const SizedBox(height: XmSpace.s3),
        ],
    ]);
  }
}

// ---------------------------------------------------------------- Lois

class _LawsTab extends ConsumerWidget {
  const _LawsTab({required this.count, required this.url});
  final int count;
  final Uri? url;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return _tabList([
      CccHeading(context.t('ccc_laws_heading'), subtitle: context.t('ccc_laws_intro')),
      const SizedBox(height: XmSpace.s3),
      if (count <= 0)
        const _EmptyTab('ccc_empty_lois')
      else
        Card(
          child: Padding(
            padding: const EdgeInsets.all(XmSpace.s4),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Icon(Icons.gavel_outlined, color: context.xm.primary),
                const SizedBox(width: XmSpace.s2),
                Expanded(
                  child: Text(context.t('ccc_laws_count', {'count': count}), style: Theme.of(context).textTheme.titleSmall),
                ),
              ]),
              const SizedBox(height: XmSpace.s3),
              if (url == null)
                XmAlert(context.t('mobile_ccc_laws_unavailable'), icon: Icons.info_outline, tone: context.xm.info)
              else
                FilledButton.icon(
                  key: const ValueKey('ccc-laws-open'),
                  style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
                  onPressed: () async {
                    final ok = await ref.read(linkLauncherProvider).open(url!);
                    if (!ok && context.mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(context.t('mobile_ccc_laws_open_failed'))));
                    }
                  },
                  icon: const Icon(Icons.open_in_new),
                  label: Text(context.t('ccc_laws_open')),
                ),
            ]),
          ),
        ),
    ]);
  }
}
