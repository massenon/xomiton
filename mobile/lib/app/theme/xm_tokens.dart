import 'package:flutter/material.dart';

/// Jetons de design Xomiton (design-system/tokens/design-tokens.json, v1.1.0).
/// Source unique : toute couleur de l'interface vient d'ici, jamais d'un
/// hexadécimal dans un widget.
@immutable
class XmColors extends ThemeExtension<XmColors> {
  const XmColors({
    required this.primary,
    required this.primaryHover,
    required this.primarySubtle,
    required this.primarySubtleText,
    required this.onPrimary,
    required this.secondary,
    required this.onSecondary,
    required this.danger,
    required this.success,
    required this.warning,
    required this.onWarning,
    required this.info,
    required this.accentViolet,
    required this.bg,
    required this.surface,
    required this.surfaceMuted,
    required this.border,
    required this.text,
    required this.textMuted,
    required this.focusRing,
  });

  final Color primary;
  final Color primaryHover;
  final Color primarySubtle;
  final Color primarySubtleText;
  final Color onPrimary;
  final Color secondary;
  final Color onSecondary;
  final Color danger;
  final Color success;
  final Color warning;
  final Color onWarning;
  final Color info;
  final Color accentViolet;
  final Color bg;
  final Color surface;
  final Color surfaceMuted;
  final Color border;
  final Color text;
  final Color textMuted;
  final Color focusRing;

  static const light = XmColors(
    primary: Color(0xFF0433C3),
    primaryHover: Color(0xFF032BA6),
    primarySubtle: Color(0xFFEBEFFA),
    primarySubtleText: Color(0xFF021C6B),
    onPrimary: Color(0xFFFFFFFF),
    secondary: Color(0xFFE30E0E),
    onSecondary: Color(0xFFFFFFFF),
    danger: Color(0xFFE30E0E),
    success: Color(0xFF0B7A4B),
    warning: Color(0xFFF0A800),
    onWarning: Color(0xFF1B1630),
    info: Color(0xFF1864AB),
    accentViolet: Color(0xFF6F2C91),
    bg: Color(0xFFFBF7FA),
    surface: Color(0xFFFFFFFF),
    surfaceMuted: Color(0xFFF3EDF4),
    border: Color(0xFFE4DCE6),
    text: Color(0xFF1B1630),
    textMuted: Color(0xFF5B5670),
    focusRing: Color(0xFF0433C3),
  );

  static const dark = XmColors(
    primary: Color(0xFF7C97FF),
    primaryHover: Color(0xFF9DB1FF),
    primarySubtle: Color(0xFF1B2347),
    primarySubtleText: Color(0xFF9DB1FF),
    onPrimary: Color(0xFF0E1020),
    secondary: Color(0xFFFF6B6B),
    onSecondary: Color(0xFF0E1020),
    danger: Color(0xFFFF6B6B),
    success: Color(0xFF4DD39D),
    warning: Color(0xFFFFC94D),
    onWarning: Color(0xFF0E1020),
    info: Color(0xFF7AB2FF),
    accentViolet: Color(0xFFC79BE6),
    bg: Color(0xFF0E1020),
    surface: Color(0xFF171A2E),
    surfaceMuted: Color(0xFF1F2340),
    border: Color(0xFF2A2E48),
    text: Color(0xFFEDEAF7),
    textMuted: Color(0xFFA9A5C0),
    focusRing: Color(0xFF7C97FF),
  );

  @override
  XmColors copyWith() => this;

  @override
  XmColors lerp(ThemeExtension<XmColors>? other, double t) =>
      other is XmColors && t >= 0.5 ? other : this;
}

/// Espacement, formes et tailles (jetons `space`, `radius`, `target-min`).
abstract final class XmSpace {
  static const double s1 = 4;
  static const double s2 = 8;
  static const double s3 = 12;
  static const double s4 = 16;
  static const double s5 = 24;
  static const double s6 = 32;
  static const double s7 = 48;
  static const double radiusSm = 6;
  static const double radiusMd = 8;
  static const double radiusLg = 16;
  static const double targetMin = 44;
  static const double gutter = 16;
}

extension XmThemeX on BuildContext {
  XmColors get xm => Theme.of(this).extension<XmColors>()!;
}
