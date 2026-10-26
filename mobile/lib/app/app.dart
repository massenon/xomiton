import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/l10n/app_strings.dart';
import '../core/network/app_toast.dart';
import '../core/outbox/outbox.dart';
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
  StreamSubscription<OutboxResult>? _outSub;

  /// Envoi abouti depuis la boîte d'envoi : le code de suivi d'un signalement
  /// est attribué par le serveur au rejeu, on le notifie ici (une seule fois).
  void _outboxDone(OutboxResult r) {
    final s = widget.strings;
    final text = switch (r.entry.type) {
      OutboxType.report =>
        '${s.t('offline_outbox_report_sent')} ${r.data['tracking_code_display'] ?? r.data['tracking_code'] ?? ''}',
      OutboxType.sos => s.t('offline_outbox_sos_sent'),
      OutboxType.post => s.t('offline_outbox_post_sent'),
      OutboxType.message => null,
    };
    if (text == null) return;
    scaffoldMessengerKey.currentState
      ?..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(text.trim()),
          duration: const Duration(seconds: 20),
        ),
      );
  }

  @override
  void initState() {
    super.initState();
    _sub = ref.read(toastBusProvider).stream.listen(_show);
    ref.read(outboxRunnerProvider);
    _outSub = ref.read(outboxProvider).results.listen(_outboxDone);
  }

  void _show(AppToast toast) {
    final text = switch (toast) {
      RateLimitedToast(:final seconds) => widget.strings.t(
        'errors_api_throttled',
        {'seconds': seconds},
      ),
      SessionClosedToast() => widget.strings.t('auth_api_unauthenticated'),
    };
    scaffoldMessengerKey.currentState
      ?..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(text)));
  }

  @override
  void dispose() {
    _sub?.cancel();
    _outSub?.cancel();
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
