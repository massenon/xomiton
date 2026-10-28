import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/ui/xm_widgets.dart';
import '../application/onboarding_provider.dart';

/// Photos plein écran (WebP portrait, ~900 px) : écoute, SOS, communauté, ressources.
const _slideImages = [
  'assets/images/splash/ecoute.webp',
  'assets/images/splash/sos.webp',
  'assets/images/splash/communaute.webp',
  'assets/images/splash/ressources.webp',
];

/// Splash plein écran : carrousel de 4 photos avec dégradé de lisibilité, titre
/// et sous-titre en tu, « Passer », points, puis « Suivant » ; la dernière
/// diapositive propose « Créer un compte » et « Se connecter ». Jamais de
/// défilement automatique : la personne avance elle-même (bouton, point, balayage).
class SplashCarouselPage extends ConsumerStatefulWidget {
  const SplashCarouselPage({super.key});

  @override
  ConsumerState<SplashCarouselPage> createState() => _SplashCarouselPageState();
}

final ButtonStyle _lightFilled = FilledButton.styleFrom(
  backgroundColor: Colors.white,
  foregroundColor: XmColors.light.primary, // écran photo : toujours le jeton clair
  minimumSize: const Size.fromHeight(52),
);

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
                key: const ValueKey('splash-skip'),
                onPressed: () => _finish('/login'),
                style: TextButton.styleFrom(
                  foregroundColor: Colors.white,
                  backgroundColor: const Color(0x61080A1E),
                  minimumSize: const Size(48, 48),
                  padding: const EdgeInsets.symmetric(horizontal: XmSpace.s4),
                ),
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
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 17),
                          child: AnimatedContainer(
                            duration: MediaQuery.of(context).disableAnimations
                                ? Duration.zero
                                : const Duration(milliseconds: 150),
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
                if (!last)
                  FilledButton(
                    key: const ValueKey('splash-next'),
                    style: _lightFilled,
                    onPressed: () => _goTo(_index + 1),
                    child: Text(context.t('common_next_item')),
                  )
                else ...[
                  FilledButton(
                    key: const ValueKey('splash-signup'),
                    style: _lightFilled,
                    onPressed: () => _finish('/signup'),
                    child: Text(context.t('auth_login_create_account')),
                  ),
                  const SizedBox(height: XmSpace.s3),
                  OutlinedButton(
                    key: const ValueKey('splash-login'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: Colors.white,
                      minimumSize: const Size.fromHeight(52),
                      side: const BorderSide(color: Colors.white, width: 1.5),
                    ),
                    onPressed: () => _finish('/login'),
                    child: Text(context.t('auth_register_sign_in')),
                  ),
                ],
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
        Image.asset(
          _slideImages[index],
          fit: BoxFit.cover,
          semanticLabel: context.t('mobile_onboarding_slide_${n}_alt'),
        ),
        const DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [Color(0x9E080A1E), Color(0x14080A1E), Color(0x14080A1E), Color(0xC7080A1E), Color(0xF0080A1E)],
              stops: [0, 0.24, 0.4, 0.7, 1],
            ),
          ),
        ),
        Align(
          alignment: Alignment.bottomCenter,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(XmSpace.s5, 0, XmSpace.s5, 280),
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
              Semantics(
                header: true,
                child: Text(context.t('mobile_onboarding_slide_${n}_title'),
                    style: Theme.of(context).textTheme.headlineMedium?.copyWith(color: Colors.white)),
              ),
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
