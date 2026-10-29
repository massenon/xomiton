import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/auth_spaces/presentation/space_widgets.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/ui/xm_widgets.dart';
import '../application/admin_providers.dart' show adminPollEnabledProvider;
import '../data/admin_api.dart';

export '../../pair/presentation/pair_ui.dart'
    show PairBadge, PairTone, SectionHeading, LinkRow, DefinitionCard, showSnack, PairLabels, statusTone, priorityTone;

/// En-tête des écrans racine de l'espace Admin : pastille d'espace, titre.
class AdminHeader extends StatelessWidget {
  const AdminHeader({super.key, required this.title, this.subtitle});
  final String title;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Align(alignment: Alignment.centerRight, child: SpaceSwitcherPill()),
        const SizedBox(height: XmSpace.s3),
        Semantics(header: true, child: Text(title, style: theme.textTheme.headlineMedium)),
        if (subtitle != null) ...[
          const SizedBox(height: XmSpace.s1),
          Text(subtitle!, style: theme.textTheme.bodySmall),
        ],
      ],
    );
  }
}

/// Écran de détail de l'espace Admin (retour, barre d'onglets de l'espace conservée).
class AdminDetailScaffold extends StatelessWidget {
  const AdminDetailScaffold({super.key, required this.title, required this.child, this.actions});
  final String title;
  final Widget child;
  final List<Widget>? actions;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(title, maxLines: 1, overflow: TextOverflow.ellipsis), actions: actions),
    body: SafeArea(child: child),
  );
}

/// Rappel permanent hors ligne : les actions d'astreinte demandent le réseau.
class AdminOfflineNotice extends ConsumerWidget {
  const AdminOfflineNotice({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (ref.watch(isOnlineProvider)) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(bottom: XmSpace.s3),
      child: XmAlert(
        context.t('mobile_admin_offline_banner'),
        icon: Icons.cloud_off_outlined,
        tone: context.xm.warning,
      ),
    );
  }
}

/// Texte d'erreur d'une action d'administration : le message du serveur
/// (français), sinon un texte propre au code.
String adminErrorMessage(BuildContext context, Object error) {
  final e = ApiException.from(error);
  if (e.code == 'step_up_required') return context.t('mobile_admin_step_up_expired');
  if (e.code == 'space_disabled' || (e.status == 403 && e.message == null)) {
    return context.t('mobile_admin_forbidden');
  }
  return errorMessage(context, error);
}

/// Exécute une action d'administration : bloquée hors ligne (message clair),
/// erreur affichée en bandeau court. Renvoie `true` si l'action a abouti.
Future<bool> runAdmin(
  BuildContext context,
  WidgetRef ref,
  Future<void> Function() action, {
  String? done,
}) async {
  if (!ref.read(isOnlineProvider)) {
    showSnackText(context, context.t('mobile_admin_offline_blocked'));
    return false;
  }
  try {
    await action();
    if (context.mounted && done != null) showSnackText(context, context.t(done));
    return true;
  } catch (e) {
    if (context.mounted) showSnackText(context, adminErrorMessage(context, e));
    return false;
  }
}

void showSnackText(BuildContext context, String text) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(text)));
}

/// Confirmation d'une action : titre, texte, bouton de validation (≥ 44 px).
Future<bool> confirmAdmin(
  BuildContext context, {
  required String title,
  required String text,
  required String yes,
  bool danger = false,
}) async =>
    await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: Text(text),
        actions: [
          TextButton(
            style: TextButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(ctx.t('common_cancel')),
          ),
          FilledButton(
            key: const ValueKey('admin-confirm-yes'),
            style: FilledButton.styleFrom(
              minimumSize: const Size(0, XmSpace.targetMin),
              backgroundColor: danger ? ctx.xm.danger : null,
              foregroundColor: danger ? ctx.xm.onSecondary : null,
            ),
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(yes),
          ),
        ],
      ),
    ) ??
    false;

/// Demande le mot de passe et renvoie un jeton `step-up` à usage unique, ou
/// `null` si la personne renonce (ou si on est hors ligne). Le mot de passe n'est
/// ni gardé ni journalisé : il ne sert qu'à `POST admin/step-up`.
Future<String?> requestStepUp(BuildContext context, WidgetRef ref, {String? reasonKey}) async {
  if (!ref.read(isOnlineProvider)) {
    showSnackText(context, context.t('mobile_admin_offline_blocked'));
    return null;
  }
  return showDialog<String>(
    context: context,
    barrierDismissible: false,
    builder: (_) => _StepUpDialog(reasonKey: reasonKey),
  );
}

class _StepUpDialog extends ConsumerStatefulWidget {
  const _StepUpDialog({this.reasonKey});
  final String? reasonKey;

  @override
  ConsumerState<_StepUpDialog> createState() => _StepUpDialogState();
}

class _StepUpDialogState extends ConsumerState<_StepUpDialog> {
  final _pw = TextEditingController();
  String? _error;
  bool _busy = false;

  @override
  void dispose() {
    _pw.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_busy) return;
    if (_pw.text.isEmpty) {
      setState(() => _error = context.t('mobile_admin_step_up_wrong'));
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final token = await ref.read(adminApiProvider).stepUp(_pw.text);
      if (mounted) Navigator.pop(context, token);
    } catch (e) {
      final api = ApiException.from(e);
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = api.isValidation
            ? (api.firstError('password') ?? context.t('mobile_admin_step_up_wrong'))
            : adminErrorMessage(context, e);
      });
    }
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(context.t('mobile_admin_step_up_title')),
    content: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(context.t(widget.reasonKey ?? 'mobile_admin_step_up_text')),
          const SizedBox(height: XmSpace.s3),
          PasswordField(
            key: const ValueKey('step-up-password'),
            controller: _pw,
            label: context.t('mobile_admin_step_up_password'),
            errorText: _error,
            textInputAction: TextInputAction.done,
            onSubmitted: (_) => _submit(),
          ),
        ],
      ),
    ),
    actions: [
      TextButton(
        key: const ValueKey('step-up-cancel'),
        style: TextButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
        onPressed: _busy ? null : () => Navigator.pop(context),
        child: Text(context.t('common_cancel')),
      ),
      FilledButton(
        key: const ValueKey('step-up-confirm'),
        style: FilledButton.styleFrom(minimumSize: const Size(0, XmSpace.targetMin)),
        onPressed: _busy ? null : _submit,
        child: Text(context.t('mobile_admin_step_up_confirm')),
      ),
    ],
  );
}

/// Action à fort impact : demande le jeton `step-up`, puis exécute [action] avec
/// lui. Renvoie `true` si l'action a abouti.
Future<bool> runWithStepUp(
  BuildContext context,
  WidgetRef ref,
  Future<void> Function(String stepUp) action, {
  String? done,
  String? reasonKey,
}) async {
  final token = await requestStepUp(context, ref, reasonKey: reasonKey);
  if (token == null || !context.mounted) return false;
  return runAdmin(context, ref, () => action(token), done: done);
}

/// Erreur de réseau ou de droit sur un chargement : retour à une phrase claire.
class AdminLoadError extends StatelessWidget {
  const AdminLoadError({super.key, required this.error, required this.onRetry});
  final Object error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final api = ApiException.from(error);
    if (error is DioException || error is ApiException) {
      if (api.status == 403) return EmptyView(context.t('mobile_admin_forbidden'));
    }
    return ErrorView(error: error, onRetry: onRetry);
  }
}

/// Relecture périodique au premier plan (10 s en garde, 30 s sinon), aussi au
/// retour au premier plan ; coupée si `adminPollEnabledProvider` est faux ou
/// si le téléphone est hors ligne.
mixin AdminPolling<T extends ConsumerStatefulWidget> on ConsumerState<T>, WidgetsBindingObserver {
  Timer? _pollTimer;
  int? _pollSecs;
  Future<void> Function()? _pollTick;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _pollTimer?.cancel();
    super.dispose();
  }

  void schedulePolling(int seconds, Future<void> Function() tick) {
    _pollTick = tick;
    if (!ref.read(adminPollEnabledProvider)) return;
    if (_pollSecs == seconds && _pollTimer != null) return;
    _pollTimer?.cancel();
    _pollSecs = seconds;
    _pollTimer = Timer.periodic(Duration(seconds: seconds), (_) {
      if (ref.read(isOnlineProvider)) _pollTick?.call();
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && ref.read(isOnlineProvider)) _pollTick?.call();
  }
}
