import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../app/theme/xm_tokens.dart';

/// Décor des écrans connexion et inscription : ruban arc-en-ciel en haut, petites illustrations en bas.
///
/// Purement décoratif : exclu de l'accessibilité (`ExcludeSemantics`), insensible aux touchers
/// (`IgnorePointer`), statique (aucune animation, donc rien à désactiver pour `prefers-reduced-motion`),
/// dessiné par `CustomPainter` (aucun asset). Le décor est placé HORS de la zone de saisie : le ruban au-dessus,
/// l'illustration sous le contenu, masquée quand le clavier est ouvert. Opacité modérée pour garder le contraste AA
/// du texte voisin. À ne jamais utiliser sur SOS, signalement, cas ni tableau de bord (charte §3.5).
class XmAuthDecor extends StatelessWidget {
  const XmAuthDecor({super.key, required this.child});

  final Widget child;

  /// Sous cette hauteur disponible (clavier ouvert, paysage), l'illustration du bas est retirée : le
  /// `Scaffold` réduit déjà le corps quand le clavier apparaît, `viewInsets` y vaut donc zéro.
  static const double compactHeight = 560;

  @override
  Widget build(BuildContext context) {
    final top = MediaQuery.of(context).padding.top;
    return LayoutBuilder(
      builder: (context, box) => Column(
        children: [
          XmRainbowRibbon(height: top + 28),
          Expanded(
            child: MediaQuery.removePadding(
              context: context,
              removeTop: true,
              child: child,
            ),
          ),
          if (box.maxHeight >= compactHeight) const XmRainbowFooter(),
        ],
      ),
    );
  }
}

/// Ruban arc-en-ciel ondulé, six bandes.
class XmRainbowRibbon extends StatelessWidget {
  const XmRainbowRibbon({super.key, this.height = 36});

  final double height;

  @override
  Widget build(BuildContext context) {
    return ExcludeSemantics(
      child: IgnorePointer(
        child: SizedBox(
          key: const ValueKey('xm-rainbow-ribbon'),
          height: height,
          width: double.infinity,
          child: CustomPaint(
            painter: _RibbonPainter(
              dark: Theme.of(context).brightness == Brightness.dark,
            ),
          ),
        ),
      ),
    );
  }
}

/// Petites illustrations (arc-en-ciel, nuage, cœur, étoiles) en pied de page.
class XmRainbowFooter extends StatelessWidget {
  const XmRainbowFooter({super.key, this.height = 56});

  final double height;

  @override
  Widget build(BuildContext context) {
    return ExcludeSemantics(
      child: IgnorePointer(
        child: SizedBox(
          key: const ValueKey('xm-rainbow-footer'),
          height: height,
          width: double.infinity,
          child: CustomPaint(
            painter: _FooterPainter(
              dark: Theme.of(context).brightness == Brightness.dark,
            ),
          ),
        ),
      ),
    );
  }
}

class _RibbonPainter extends CustomPainter {
  _RibbonPainter({required this.dark});

  final bool dark;

  @override
  void paint(Canvas canvas, Size size) {
    final band = size.height / 7.5;
    final opacity = dark ? 0.4 : 0.62;
    for (var i = 0; i < XmRainbow.stripes.length; i++) {
      final y = band * (0.9 + i * 0.85);
      final path = Path()
        ..moveTo(-10, y)
        ..cubicTo(
          size.width * 0.25,
          y + band * 1.4,
          size.width * 0.6,
          y - band * 1.2,
          size.width + 10,
          y + band * 0.8,
        );
      canvas.drawPath(
        path,
        Paint()
          ..style = PaintingStyle.stroke
          ..strokeWidth = band * 1.05
          ..color = XmRainbow.stripes[i].withValues(alpha: opacity),
      );
    }
  }

  @override
  bool shouldRepaint(_RibbonPainter old) => old.dark != dark;
}

class _FooterPainter extends CustomPainter {
  _FooterPainter({required this.dark});

  final bool dark;

  @override
  void paint(Canvas canvas, Size size) {
    final opacity = dark ? 0.35 : 0.55;
    final h = size.height;
    // Petit arc-en-ciel à gauche.
    for (var i = 0; i < XmRainbow.stripes.length; i++) {
      final r = h * 0.9 - i * (h * 0.09);
      canvas.drawArc(
        Rect.fromCircle(center: Offset(h * 1.05, h), radius: r),
        math.pi,
        math.pi,
        false,
        Paint()
          ..style = PaintingStyle.stroke
          ..strokeWidth = h * 0.09
          ..color = XmRainbow.stripes[i].withValues(alpha: opacity),
      );
    }
    // Nuage.
    final cloud = Paint()
      ..color = Colors.white.withValues(alpha: dark ? 0.12 : 0.8);
    final cx = h * 2.3;
    canvas.drawCircle(Offset(cx, h * 0.85), h * 0.14, cloud);
    canvas.drawCircle(Offset(cx + h * 0.2, h * 0.75), h * 0.2, cloud);
    canvas.drawCircle(Offset(cx + h * 0.45, h * 0.86), h * 0.13, cloud);
    // Cœur, étoiles, points : à droite.
    final w = size.width;
    final hx = w - 70, hy = h * 0.4, hs = 11.0;
    final heart = Path()
      ..moveTo(hx, hy + hs * 0.9)
      ..cubicTo(
        hx - hs * 1.4,
        hy + hs * 0.1,
        hx - hs * 0.7,
        hy - hs * 0.9,
        hx,
        hy - hs * 0.2,
      )
      ..cubicTo(
        hx + hs * 0.7,
        hy - hs * 0.9,
        hx + hs * 1.4,
        hy + hs * 0.1,
        hx,
        hy + hs * 0.9,
      );
    canvas.drawPath(
      heart,
      Paint()..color = XmRainbow.stripes[0].withValues(alpha: opacity),
    );
    canvas.drawCircle(
      Offset(w - 26, h * 0.3),
      3,
      Paint()..color = XmRainbow.stripes[5].withValues(alpha: opacity + 0.1),
    );
    canvas.drawCircle(
      Offset(w - 120, h * 0.65),
      3.5,
      Paint()..color = XmRainbow.stripes[4].withValues(alpha: opacity + 0.1),
    );
    canvas.drawCircle(
      Offset(w - 22, h * 0.72),
      4,
      Paint()..color = XmRainbow.stripes[3].withValues(alpha: opacity + 0.1),
    );
    _star(
      canvas,
      Offset(w - 160, h * 0.4),
      7,
      Paint()..color = XmRainbow.stripes[2].withValues(alpha: opacity + 0.15),
    );
  }

  void _star(Canvas canvas, Offset c, double r, Paint paint) {
    final path = Path();
    for (var i = 0; i < 10; i++) {
      final rad = i.isEven ? r : r * 0.45;
      final a = -math.pi / 2 + i * math.pi / 5;
      final p = Offset(c.dx + rad * math.cos(a), c.dy + rad * math.sin(a));
      i == 0 ? path.moveTo(p.dx, p.dy) : path.lineTo(p.dx, p.dy);
    }
    canvas.drawPath(path..close(), paint);
  }

  @override
  bool shouldRepaint(_FooterPainter old) => old.dark != dark;
}
