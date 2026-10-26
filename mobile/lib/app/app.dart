import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/l10n/app_strings.dart';
import '../core/network/app_toast.dart';
import '../core/providers/core_providers.dart';
import 'router.dart';
import 'theme/xm_theme.dart';

final scaffoldMessengerKey = GlobalKey<ScaffoldMessengerState>();

/// Racine : thème clair / sombre selon le système (jetons du design system),
/// routeur go_router, textes depuis l'ARB, toasts des intercepteurs.
class XomitonApp extends ConsumerStatefulWidget {
  const XomitonApp({super.key, required this.strings});
  final AppStrings strings;

  @override
  ConsumerState<XomitonApp> createState() => _XomitonAppState();
}

class _XomitonAppState extends ConsumerState<XomitonApp> {
  StreamSubscription<AppToast>? _sub;

  @override
  void initState() {
    super.initState();
    _sub = ref.read(toastBusProvider).stream.listen(_show);
  }

  void _show(AppToast toast) {
    final text = switch (toast) {
      RateLimitedToast(:final seconds) =>
        widget.strings.t('errors_api_throttled', {'seconds': seconds}),
      SessionClosedToast() => widget.strings.t('auth_api_unauthenticated'),
    };
    scaffoldMessengerKey.currentState
      ?..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(text)));
  }

  @override
  void dispose() {
    _sub?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: widget.strings.t('common_brand'),
      scaffoldMessengerKey: scaffoldMessengerKey,
      routerConfig: ref.watch(routerProvider),
      theme: buildXmTheme(Brightness.light),
      darkTheme: buildXmTheme(Brightness.dark),
      themeMode: ThemeMode.system,
      locale: AppStrings.locale,
      supportedLocales: const [AppStrings.locale],
      localizationsDelegates: [
        AppStringsDelegate(widget.strings),
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
    );
  }
}
