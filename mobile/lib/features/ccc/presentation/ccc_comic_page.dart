import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/ui/xm_widgets.dart';
import '../application/ccc_providers.dart';
import '../data/ccc_models.dart';
import 'ccc_widgets.dart';

/// Lecteur de BD : une planche à la fois, progression, texte alternatif (`alt`)
/// lu par les lecteurs d'écran, « Terminer » ouvre les questions de discussion.
class CccComicPage extends ConsumerWidget {
  const CccComicPage({super.key, required this.comicKey});
  final String comicKey;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final catalog = ref.watch(cccCatalogProvider);
    return catalog.when(
      loading: () => Scaffold(appBar: AppBar(), body: const LoadingView()),
      error: (e, _) => Scaffold(
        appBar: AppBar(title: Text(context.t('ccc_comic_heading'))),
        body: ErrorView(error: e, onRetry: () => ref.invalidate(cccCatalogProvider)),
      ),
      data: (c) {
        final comic = c.comic(comicKey);
        if (comic == null || comic.pages.isEmpty) {
          return Scaffold(
            appBar: AppBar(title: Text(context.t('ccc_comic_heading'))),
            body: EmptyView(context.t('microcopy_error_not_found')),
          );
        }
        return _Reader(comic: comic);
      },
    );
  }
}

class _Reader extends StatefulWidget {
  const _Reader({required this.comic});
  final PagedTool comic;

  @override
  State<_Reader> createState() => _ReaderState();
}

class _ReaderState extends State<_Reader> {
  final _controller = PageController(keepPage: false);
  int _index = 0;
  bool _finished = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _go(int i) => _controller.animateToPage(i, duration: const Duration(milliseconds: 200), curve: Curves.easeOut);

  void _back() => context.canPop() ? context.pop() : context.go('/ccc?onglet=bd');

  @override
  Widget build(BuildContext context) {
    final comic = widget.comic;
    final total = comic.pages.length;
    final page = comic.pages[_index];
    final t = Theme.of(context).textTheme;
    final counter = context.t('ccc_comic_page_counter', {'current': _index + 1, 'total': total});
    final percent = ((_index + 1) * 100 / total).round();
    final last = _index == total - 1;
    return Scaffold(
      appBar: AppBar(
        title: Text(comic.title),
        leading: BackButton(onPressed: _back),
      ),
      body: SafeArea(
        child: _finished
            ? _Done(onRestart: () => setState(() {
                  _finished = false;
                  _index = 0;
                }), onBack: _back)
            : Semantics(
                container: true,
                explicitChildNodes: true,
                label: context.t('ccc_comic_reader_label', {'title': comic.title}),
                child: Column(children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(XmSpace.gutter, XmSpace.s2, XmSpace.gutter, XmSpace.s2),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Semantics(
                        liveRegion: true,
                        label: context.t('ccc_comic_page_announce', {
                          'current': _index + 1,
                          'total': total,
                          'caption': page.caption ?? page.alt,
                        }),
                        child: ExcludeSemantics(child: Text(counter, key: const ValueKey('ccc-comic-counter'), style: t.labelLarge)),
                      ),
                      const SizedBox(height: XmSpace.s1),
                      Semantics(
                        label: context.t('ccc_comic_progress'),
                        value: counter,
                        child: ExcludeSemantics(
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(XmSpace.radiusSm),
                            child: LinearProgressIndicator(
                              key: const ValueKey('ccc-comic-progress'),
                              value: percent / 100,
                              minHeight: 8,
                              backgroundColor: context.xm.surfaceMuted,
                            ),
                          ),
                        ),
                      ),
                    ]),
                  ),
                  Expanded(
                    child: PageView.builder(
                      key: const ValueKey('ccc-comic-pages'),
                      controller: _controller,
                      itemCount: total,
                      onPageChanged: (i) => setState(() => _index = i),
                      itemBuilder: (_, i) {
                        final p = comic.pages[i];
                        return Padding(
                          padding: const EdgeInsets.symmetric(horizontal: XmSpace.gutter),
                          child: Column(children: [
                            Expanded(child: CccImage(p.image, alt: p.alt, contain: true)),
                            if (p.caption != null)
                              Padding(
                                padding: const EdgeInsets.only(top: XmSpace.s2),
                                child: Text(p.caption!, key: ValueKey('ccc-comic-caption-$i'), style: t.bodyLarge, textAlign: TextAlign.center),
                              ),
                          ]),
                        );
                      },
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.all(XmSpace.s3),
                    child: Row(children: [
                      IconButton.outlined(
                        key: const ValueKey('ccc-comic-prev'),
                        tooltip: context.t('ccc_comic_previous'),
                        style: IconButton.styleFrom(minimumSize: const Size(56, 56)),
                        onPressed: _index == 0 ? null : () => _go(_index - 1),
                        icon: const Icon(Icons.chevron_left),
                      ),
                      Expanded(
                        child: Text(context.t('mobile_ccc_comic_hint'),
                            textAlign: TextAlign.center, style: t.bodySmall?.copyWith(color: context.xm.textMuted)),
                      ),
                      if (last)
                        FilledButton(
                          key: const ValueKey('ccc-comic-finish'),
                          style: FilledButton.styleFrom(minimumSize: const Size(56, 56)),
                          onPressed: () => setState(() => _finished = true),
                          child: Text(context.t('ccc_comic_finish')),
                        )
                      else
                        IconButton.filled(
                          key: const ValueKey('ccc-comic-next'),
                          tooltip: context.t('ccc_comic_next'),
                          style: IconButton.styleFrom(minimumSize: const Size(56, 56)),
                          onPressed: () => _go(_index + 1),
                          icon: const Icon(Icons.chevron_right),
                        ),
                    ]),
                  ),
                ]),
              ),
      ),
    );
  }
}

class _Done extends StatelessWidget {
  const _Done({required this.onRestart, required this.onBack});
  final VoidCallback onRestart;
  final VoidCallback onBack;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    return ListView(
      key: const ValueKey('ccc-comic-done'),
      padding: const EdgeInsets.all(XmSpace.gutter),
      children: [
        Semantics(liveRegion: true, header: true, child: Text(context.t('ccc_comic_done_title'), style: t.headlineSmall)),
        const SizedBox(height: XmSpace.s1),
        Text(context.t('ccc_comic_done'), style: t.bodyLarge),
        const SizedBox(height: XmSpace.s4),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(XmSpace.s4),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Semantics(header: true, child: Text(context.t('ccc_comic_discuss'), style: t.titleSmall)),
              const SizedBox(height: XmSpace.s2),
              for (var i = 0; i < 3; i++)
                Padding(
                  padding: const EdgeInsets.only(bottom: XmSpace.s1),
                  child: Text('• ${context.t('ccc_comic_discuss_items_$i')}', style: t.bodyMedium),
                ),
            ]),
          ),
        ),
        const SizedBox(height: XmSpace.s4),
        FilledButton.icon(
          key: const ValueKey('ccc-comic-restart'),
          style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
          onPressed: onRestart,
          icon: const Icon(Icons.replay),
          label: Text(context.t('ccc_comic_restart')),
        ),
        const SizedBox(height: XmSpace.s2),
        OutlinedButton.icon(
          style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48)),
          onPressed: onBack,
          icon: const Icon(Icons.arrow_back),
          label: Text(context.t('ccc_comic_back')),
        ),
      ],
    );
  }
}
