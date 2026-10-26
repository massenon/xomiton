import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/ui/xm_widgets.dart';
import '../application/onboarding_provider.dart';

const _slideImages = [
  'assets/images/hero-04.jpg',
  'assets/images/hero-08.jpg',
  'assets/images/sign-in.jpg',
  'assets/images/hero-09.jpg',
];

/// Splash + carrousel de 4 diapositives. Jamais de défilement automatique :
/// la personne avance elle-même (bouton, point, balayage).
class SplashCarouselPage extends ConsumerStatefulWidget {
  const SplashCarouselPage({super.key});

  @override
  ConsumerState<SplashCarouselPage> createState() => _SplashCarouselPageState();
}

class _SplashCarouselPageState extends ConsumerState<SplashCarouselPage> {
  final _controller = PageController();
  int _index = 0;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _goTo(int i) {
    final reduce = MediaQuery.of(context).disableAnimations;
    if (reduce) {
      _controller.jumpToPage(i);
    } else {
      _controller.animateToPage(i,
          duration: const Duration(milliseconds: 300), curve: Curves.easeOut);
    }
  }

  /// Navigue d'abord, mémorise ensuite : le changement de `seen` ne doit pas
  /// rediriger l'écran quitté.
  void _finish(String to) {
    context.go(to);
    ref.read(onboardingProvider.notifier).markSeen();
  }

  @override
  Widget build(BuildContext context) {
    final last = _index == _slideImages.length - 1;
    return Scaffold(
      body: Stack(children: [
        Semantics(
          container: true,
          label: context.t('mobile_onboarding_carousel_label'),
          child: PageView.builder(
            controller: _controller,
            itemCount: _slideImages.length,
            onPageChanged: (i) => setState(() => _index = i),
            itemBuilder: (context, i) => _Slide(index: i),
          ),
        ),
        SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: XmSpace.gutter, vertical: XmSpace.s2),
            child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              const XmLogo(width: 100, white: true),
              TextButton(
                onPressed: () => _finish('/login'),
                style: TextButton.styleFrom(foregroundColor: Colors.white),
                child: Text(context.t('auth_register_skip')),
              ),
            ]),
          ),
        ),
        Align(
          alignment: Alignment.bottomCenter,
          child: SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(XmSpace.gutter),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                  for (var i = 0; i < _slideImages.length; i++)
                    Semantics(
                      button: true,
                      selected: i == _index,
                      label: context.t('mobile_onboarding_slide_label',
                          {'current': i + 1, 'total': _slideImages.length}),
                      child: InkResponse(
                        onTap: () => _goTo(i),
                        radius: 24,
                        child: Padding(
                          padding: const EdgeInsets.all(14),
                          child: AnimatedContainer(
                            duration: const Duration(milliseconds: 150),
                            width: i == _index ? 24 : 10,
                            height: 10,
                            decoration: BoxDecoration(
                              color: i == _index ? Colors.white : Colors.white54,
                              borderRadius: BorderRadius.circular(5),
                            ),
                          ),
                        ),
                      ),
                    ),
                ]),
                const SizedBox(height: XmSpace.s2),
                FilledButton(
                  onPressed: () => last ? _finish('/signup') : _goTo(_index + 1),
                  child: Text(context.t(last ? 'mobile_onboarding_start' : 'common_next_item')),
                ),
                const SizedBox(height: XmSpace.s3),
                OutlinedButton(
                  style: OutlinedButton.styleFrom(
                    foregroundColor: Colors.white,
                    side: const BorderSide(color: Colors.white),
                  ),
                  onPressed: () => _finish('/login'),
                  child: Text(context.t('mobile_onboarding_have_account')),
                ),
              ]),
            ),
          ),
        ),
      ]),
    );
  }
}

class _Slide extends StatelessWidget {
  const _Slide({required this.index});
  final int index;

  @override
  Widget build(BuildContext context) {
    final n = index + 1;
    return Semantics(
      label: context.t('mobile_onboarding_slide_label', {'current': n, 'total': _slideImages.length}),
      child: Stack(fit: StackFit.expand, children: [
        ExcludeSemantics(child: Image.asset(_slideImages[index], fit: BoxFit.cover)),
        const DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [Color(0x66000000), Color(0x33000000), Color(0xE6000000)],
              stops: [0, 0.4, 1],
            ),
          ),
        ),
        Align(
          alignment: Alignment.bottomCenter,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(XmSpace.s5, 0, XmSpace.s5, 260),
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(context.t('mobile_onboarding_slide_${n}_title'),
                  style: Theme.of(context).textTheme.headlineMedium?.copyWith(color: Colors.white)),
              const SizedBox(height: XmSpace.s3),
              Text(context.t('mobile_onboarding_slide_${n}_body'),
                  style: Theme.of(context).textTheme.bodyLarge?.copyWith(color: Colors.white)),
            ]),
          ),
        ),
      ]),
    );
  }
}
