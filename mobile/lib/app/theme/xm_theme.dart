import 'package:flutter/material.dart';

import 'xm_tokens.dart';

/// Polices : Montserrat (titres, fichiers fournis par le design system),
/// Arial pour le corps (repli système : Roboto / SF, comme le simulateur).
const String kHeadingFont = 'Montserrat';
const String kBodyFont = 'Arial';

ThemeData buildXmTheme(Brightness brightness) {
  final c = brightness == Brightness.dark ? XmColors.dark : XmColors.light;
  final scheme = ColorScheme(
    brightness: brightness,
    primary: c.primary,
    onPrimary: c.onPrimary,
    primaryContainer: c.primarySubtle,
    onPrimaryContainer: c.primarySubtleText,
    secondary: c.secondary,
    onSecondary: c.onSecondary,
    error: c.danger,
    onError: c.onSecondary,
    surface: c.surface,
    onSurface: c.text,
    onSurfaceVariant: c.textMuted,
    surfaceContainerHighest: c.surfaceMuted,
    outline: c.border,
    outlineVariant: c.border,
  );
  TextStyle heading(double size, FontWeight w) => TextStyle(
      fontFamily: kHeadingFont, fontSize: size, fontWeight: w, height: 1.2, color: c.text);
  final base = TextStyle(fontFamily: kBodyFont, color: c.text, height: 1.5);
  final text = TextTheme(
    displaySmall: heading(32, FontWeight.w800),
    headlineMedium: heading(26, FontWeight.w800),
    headlineSmall: heading(22, FontWeight.w700),
    titleLarge: heading(20, FontWeight.w700),
    titleMedium: heading(17, FontWeight.w600),
    titleSmall: heading(15, FontWeight.w600),
    bodyLarge: base.copyWith(fontSize: 17),
    bodyMedium: base.copyWith(fontSize: 16),
    bodySmall: base.copyWith(fontSize: 14, color: c.textMuted),
    labelLarge: heading(16, FontWeight.w600),
    labelMedium: base.copyWith(fontSize: 14, fontWeight: FontWeight.w600),
    labelSmall: base.copyWith(fontSize: 13, color: c.textMuted),
  );
  final fieldBorder = OutlineInputBorder(
    borderRadius: BorderRadius.circular(XmSpace.radiusMd),
    borderSide: BorderSide(color: c.border),
  );
  return ThemeData(
    useMaterial3: true,
    brightness: brightness,
    colorScheme: scheme,
    scaffoldBackgroundColor: c.bg,
    canvasColor: c.bg,
    fontFamily: kBodyFont,
    textTheme: text,
    extensions: [c],
    appBarTheme: AppBarTheme(
      backgroundColor: c.bg,
      foregroundColor: c.text,
      elevation: 0,
      scrolledUnderElevation: 0,
      centerTitle: false,
      titleTextStyle: heading(18, FontWeight.w700),
    ),
    cardTheme: CardThemeData(
      color: c.surface,
      elevation: 0,
      margin: EdgeInsets.zero,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(XmSpace.radiusLg),
        side: BorderSide(color: c.border),
      ),
    ),
    dividerTheme: DividerThemeData(color: c.border, space: 1),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: c.primary,
        foregroundColor: c.onPrimary,
        minimumSize: const Size.fromHeight(XmSpace.targetMin + 4),
        textStyle: heading(16, FontWeight.w600),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(XmSpace.radiusMd)),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: c.primary,
        minimumSize: const Size.fromHeight(XmSpace.targetMin + 4),
        side: BorderSide(color: c.primary),
        textStyle: heading(16, FontWeight.w600),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(XmSpace.radiusMd)),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(
        foregroundColor: c.primary,
        minimumSize: const Size(XmSpace.targetMin, XmSpace.targetMin),
        textStyle: base.copyWith(fontWeight: FontWeight.w600, fontSize: 15),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: c.surface,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      border: fieldBorder,
      enabledBorder: fieldBorder,
      focusedBorder: fieldBorder.copyWith(borderSide: BorderSide(color: c.focusRing, width: 2)),
      errorBorder: fieldBorder.copyWith(borderSide: BorderSide(color: c.danger)),
      focusedErrorBorder: fieldBorder.copyWith(borderSide: BorderSide(color: c.danger, width: 2)),
      helperStyle: base.copyWith(fontSize: 13, color: c.textMuted),
      errorStyle: base.copyWith(fontSize: 13, color: c.danger),
    ),
    chipTheme: ChipThemeData(
      backgroundColor: c.surface,
      selectedColor: c.primarySubtle,
      side: BorderSide(color: c.border),
      labelStyle: base.copyWith(fontSize: 14, color: c.text),
      shape: const StadiumBorder(),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: c.surface,
      indicatorColor: c.primarySubtle,
      labelTextStyle: WidgetStatePropertyAll(base.copyWith(fontSize: 12, fontWeight: FontWeight.w600)),
    ),
    snackBarTheme: SnackBarThemeData(
      behavior: SnackBarBehavior.floating,
      backgroundColor: c.text,
      contentTextStyle: base.copyWith(color: c.bg),
    ),
    progressIndicatorTheme: ProgressIndicatorThemeData(color: c.primary),
  );
}
