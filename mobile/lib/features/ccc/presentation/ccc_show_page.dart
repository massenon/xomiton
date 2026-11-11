import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/ui/xm_widgets.dart';
import '../application/ccc_providers.dart';
import '../data/ccc_models.dart';
import 'ccc_widgets.dart';

class _Slide {
  const _Slide({required this.title, required this.page, this.guide});
  final String title;
  final String? guide;
  final CccPage page;
}

/// Mode animation : planches d'une boîte à images (ou de toutes), plein écran
/// immersif pour projeter au groupe. Le guide de l'animateur·rice n'est affiché
/// qu'en mode présentation. Barres système masquées à l'ouverture, **restaurées à la sortie**.
/// Navigation : glisser, ou boutons « précédente / suivante » (≥ 48 dp, boucle comme sur le web).
class CccShowPage extends ConsumerStatefulWidget {
  const CccShowPage({super.key, this.boxKey});

  /// Boîte à lire ; `null` : toutes les boîtes publiées, à la suite.
  final String? boxKey;

  @override
  ConsumerState<CccShowPage> createState() => _CccShowPageState();
}

class _CccShowPageState extends ConsumerState<CccShowPage> {
  final _controller = PageController(keepPage: false);
  int _index = 0;

  @override
  void initState() {
    super.initState();
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);
  }

  @override
  void dispose() {
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
    _controller.dispose();
    super.dispose();
  }

  void _close() => context.canPop() ? context.pop() : context.go('/ccc?onglet=images');

  void _step(int delta, int total) {
    if (total == 0) return;
    final next = (_index + delta + total) % total;
    // Les boutons bouclent comme sur le web ; l'animation se contente d'un saut au bout.
    if ((next - _index).abs() == 1) {
      _controller.animateToPage(next, duration: const Duration(milliseconds: 200), curve: Curves.easeOut);
    } else {
      _controller.jumpToPage(next);
    }
  }

  @override
  Widget build(BuildContext context) {
    // Projection : toujours sur fond sombre (jetons sombres du design system).
    const c = XmColors.dark;
    final catalog = ref.watch(cccCatalogProvider);
    final presentation = ref.watch(cccPresentationProvider);
    return Theme(
      data: Theme.of(context).copyWith(extensions: const [c]),
      child: Scaffold(
        backgroundColor: c.bg,
        body: SafeArea(
          child: catalog.when(
            loading: () => const LoadingView(),
            error: (e, _) => Column(children: [
              _closeBar(context, c, null, 0),
              Expanded(child: ErrorView(error: e, onRetry: () => ref.invalidate(cccCatalogProvider))),
            ]),
            data: (cat) => _body(context, cat, presentation, c),
          ),
        ),
      ),
    );
  }

  Widget _closeBar(BuildContext context, XmColors c, String? announce, int total) => Padding(
        padding: const EdgeInsets.fromLTRB(XmSpace.s3, XmSpace.s2, XmSpace.s3, XmSpace.s2),
        child: Row(children: [
          if (announce != null)
            Expanded(
              child: Semantics(
                liveRegion: true,
                label: announce,
                child: ExcludeSemantics(
                  child: Text(
                    context.t('ccc_show_counter', {'current': _index + 1, 'total': total}),
                    key: const ValueKey('ccc-show-counter'),
                    style: Theme.of(context).textTheme.labelLarge?.copyWith(color: c.text),
                  ),
                ),
              ),
            )
          else
            const Spacer(),
          TextButton.icon(
            key: const ValueKey('ccc-show-close'),
            style: TextButton.styleFrom(minimumSize: const Size(48, 48), foregroundColor: c.text),
            onPressed: _close,
            icon: const Icon(Icons.close),
            label: Text(context.t('ccc_show_close')),
          ),
        ]),
      );

  Widget _body(BuildContext context, CccCatalog cat, bool presentation, XmColors c) {
    final boxes = widget.boxKey == null
        ? cat.imageBoxes
        : [?cat.imageBox(widget.boxKey!)];
    final slides = [
      for (final b in boxes)
        for (final p in b.pages) _Slide(title: b.title, guide: b.description, page: p),
    ];
    if (slides.isEmpty) {
      return Column(children: [
        _closeBar(context, c, null, 0),
        Expanded(child: EmptyView(context.t('ccc_empty_boites_images'))),
      ]);
    }
    final total = slides.length;
    final index = _index.clamp(0, total - 1);
    final slide = slides[index];
    final t = Theme.of(context).textTheme;
    return Column(
      children: [
        _closeBar(context, c, context.t('ccc_show_announce', {'current': index + 1, 'total': total, 'title': slide.title}), total),
        Expanded(
          child: PageView.builder(
            key: const ValueKey('ccc-show-pages'),
            controller: _controller,
            itemCount: total,
            onPageChanged: (i) => setState(() => _index = i),
            itemBuilder: (_, i) => Padding(
              padding: const EdgeInsets.symmetric(horizontal: XmSpace.s3),
              child: CccImage(slides[i].page.image, alt: slides[i].page.alt, contain: true),
            ),
          ),
        ),
        ConstrainedBox(
          constraints: BoxConstraints(maxHeight: MediaQuery.sizeOf(context).height * 0.3),
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(XmSpace.s4, XmSpace.s3, XmSpace.s4, 0),
            child: SizedBox(
              width: double.infinity,
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(slide.title, key: const ValueKey('ccc-show-title'), style: t.titleMedium?.copyWith(color: c.text)),
                if (slide.page.caption != null)
                  Padding(
                    padding: const EdgeInsets.only(top: XmSpace.s1),
                    child: Text(slide.page.caption!, key: const ValueKey('ccc-show-caption'), style: t.bodyLarge?.copyWith(color: c.text)),
                  ),
                if (presentation && slide.guide != null)
                  Padding(
                    padding: const EdgeInsets.only(top: XmSpace.s2),
                    child: Semantics(
                      container: true,
                      label: '${context.t('ccc_show_guide')}. ${slide.guide}',
                      child: ExcludeSemantics(
                        child: Text(slide.guide!, key: const ValueKey('ccc-show-guide'), style: t.bodyMedium?.copyWith(color: c.textMuted)),
                      ),
                    ),
                  ),
              ]),
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.all(XmSpace.s3),
          child: Row(children: [
            IconButton.outlined(
              key: const ValueKey('ccc-show-prev'),
              tooltip: context.t('ccc_show_previous'),
              style: IconButton.styleFrom(minimumSize: const Size(56, 56), foregroundColor: c.text, side: BorderSide(color: c.border)),
              onPressed: () => _step(-1, total),
              icon: const Icon(Icons.chevron_left),
            ),
            Expanded(
              child: Text(
                context.t('mobile_ccc_show_hint'),
                textAlign: TextAlign.center,
                style: t.bodySmall?.copyWith(color: c.textMuted),
              ),
            ),
            IconButton.filled(
              key: const ValueKey('ccc-show-next'),
              tooltip: context.t('ccc_show_next'),
              style: IconButton.styleFrom(minimumSize: const Size(56, 56), backgroundColor: c.primary, foregroundColor: c.onPrimary),
              onPressed: () => _step(1, total),
              icon: const Icon(Icons.chevron_right),
            ),
          ]),
        ),
      ],
    );
  }
}
