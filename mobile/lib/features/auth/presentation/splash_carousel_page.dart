import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/ui/xm_widgets.dart';
import '../application/onboarding_provider.dart';

/// Une diapositive = un texte validé + une photo. [fit] : `cover` pour les
/// photos haute définition ; `banner` pour les deux visuels basse résolution
/// (visage recadré 480 px) qui s'affichent à la largeur de l'écran, en haut,
/// puis se fondent dans le fond sombre (jamais agrandis ni déformés).
enum SlideFit { cover, banner }

class SplashSlideSpec {
  const SplashSlideSpec(this.image, {this.fit = SlideFit.cover});
  final String image;
  final SlideFit fit;
}

/// Ordre = ordre des six textes `mobile_onboarding_slide_N_*`.
const kSplashSlides = <SplashSlideSpec>[
  SplashSlideSpec('assets/images/splash/paillettes.webp'), // 1 Un espace à toi
  SplashSlideSpec('assets/images/splash/bouche-scotchee.webp', fit: SlideFit.banner), // 2 Ta parole compte
  SplashSlideSpec('assets/images/splash/couple-etreinte.webp'), // 3 En sécurité
  SplashSlideSpec('assets/images/splash/foule-drapeaux.webp'), // 4 Une communauté
  SplashSlideSpec('assets/images/splash/lumiere-doree.webp'), // 5 Réponses fiables
  SplashSlideSpec('assets/images/splash/symbole-venus.webp', fit: SlideFit.banner), // 6 Vie privée
];

/// Splash plein écran : carrousel de six diapositives (une photo chacune) avec dégradé de lisibilité, titre
/// et sous-titre en tu, « Passer », points, puis « Suivant » ; la dernière
/// diapositive propose « Créer un compte » et « Se connecter ». Jamais de
/// défilement automatique : la personne avance elle-même (bouton, point, balayage).
class SplashCarouselPage extends ConsumerStatefulWidget {
  const SplashCarouselPage({super.key});

  @override
  ConsumerState<SplashCarouselPage> createState() => _SplashCarouselPageState();
}

const _splashBg = Color(0xFF0B0D1A);

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
    final last = _index == kSplashSlides.length - 1;
    return Scaffold(
      body: Stack(children: [
        Semantics(
          container: true,
          label: context.t('mobile_onboarding_carousel_label'),
          child: PageView.builder(
            controller: _controller,
            itemCount: kSplashSlides.length,
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
                  for (var i = 0; i < kSplashSlides.length; i++)
                    Semantics(
                      button: true,
                      selected: i == _index,
                      label: context.t('mobile_onboarding_slide_label',
                          {'current': i + 1, 'total': kSplashSlides.length}),
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
    final spec = kSplashSlides[index];
    return Semantics(
      label: context.t('mobile_onboarding_slide_label', {'current': n, 'total': kSplashSlides.length}),
      child: Stack(fit: StackFit.expand, children: [
        const ColoredBox(color: _splashBg),
        if (spec.fit == SlideFit.banner)
          Align(
            alignment: Alignment.topCenter,
            // Fondu du bas de l'image dans le fond : aucun bord franc, quelle que soit la hauteur.
            child: ShaderMask(
              blendMode: BlendMode.dstIn,
              shaderCallback: (r) => const LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [Colors.white, Colors.white, Color(0x00FFFFFF)],
                stops: [0, 0.55, 1],
              ).createShader(r),
              child: Image.asset(
                spec.image,
                width: double.infinity,
                fit: BoxFit.fitWidth,
                semanticLabel: context.t('mobile_onboarding_slide_${n}_alt'),
              ),
            ),
          )
        else
          Image.asset(
            spec.image,
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
