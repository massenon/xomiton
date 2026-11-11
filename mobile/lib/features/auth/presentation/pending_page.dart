import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/ui/xm_widgets.dart';
import '../application/pending_validation.dart';
import '../application/session_controller.dart';

/// « Ton compte attend la validation de l'équipe » : écran d'accueil d'un compte
/// tout juste créé. Tirer pour rafraîchir (ou le bouton, pour qui n'a pas le
/// geste) relit `GET me` ; dès que l'équipe a validé, le routeur ouvre l'accueil.
class PendingPage extends ConsumerStatefulWidget {
  const PendingPage({super.key});

  @override
  ConsumerState<PendingPage> createState() => _PendingPageState();
}

class _PendingPageState extends ConsumerState<PendingPage> {
  bool _checking = false;

  Future<void> _check() async {
    if (_checking) return;
    setState(() => _checking = true);
    await ref.read(sessionProvider.notifier).refresh();
    if (!mounted) return;
    setState(() => _checking = false);
    if (ref.read(pendingValidationProvider)) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(context.t('mobile_pending_still'))));
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final online = ref.watch(isOnlineProvider);
    Widget tile(IconData icon, String key, String route, {bool tab = false}) => ListTile(
          minVerticalPadding: 12,
          leading: Icon(icon),
          title: Text(context.t(key)),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => tab ? context.go(route) : context.push(route),
        );
    return Scaffold(
      appBar: AppBar(title: Text(context.t('mobile_pending_app_bar'))),
      body: RefreshIndicator(
        onRefresh: _check,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(XmSpace.gutter),
          children: [
            Semantics(
              header: true,
              child: Text(context.t('mobile_pending_title'), style: theme.textTheme.headlineSmall),
            ),
            const SizedBox(height: XmSpace.s3),
            XmAlert(context.t('mobile_pending_body'), icon: Icons.hourglass_empty, tone: context.xm.info),
            const SizedBox(height: XmSpace.s4),
            if (!online) ...[
              Text(context.t('mobile_pending_offline'), style: theme.textTheme.bodySmall),
              const SizedBox(height: XmSpace.s3),
            ],
            FilledButton.icon(
              key: const ValueKey('pending-check'),
              style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
              onPressed: _checking ? null : _check,
              icon: _checking
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2.5))
                  : const Icon(Icons.refresh),
              label: Text(context.t('mobile_pending_check')),
            ),
            const SizedBox(height: XmSpace.s2),
            Text(context.t('mobile_pending_pull_hint'), style: theme.textTheme.bodySmall),
            const SizedBox(height: XmSpace.s5),
            Semantics(
              header: true,
              child: Text(context.t('mobile_pending_allowed_title'), style: theme.textTheme.titleMedium),
            ),
            tile(Icons.menu_book_outlined, 'mobile_pending_allowed_read', '/thematiques'),
            tile(Icons.folder_outlined, 'mobile_pending_allowed_resources', '/ressources'),
            tile(Icons.sos_outlined, 'mobile_pending_allowed_sos', '/sos', tab: true),
            tile(Icons.flag_outlined, 'mobile_pending_allowed_report', '/signaler'),
            tile(Icons.phone_in_talk_outlined, 'mobile_pending_allowed_emergency', '/urgences'),
            tile(Icons.person_outline, 'mobile_pending_allowed_profile', '/profile', tab: true),
            const SizedBox(height: XmSpace.s4),
            Semantics(
              header: true,
              child: Text(context.t('mobile_pending_blocked_title'), style: theme.textTheme.titleMedium),
            ),
            const SizedBox(height: XmSpace.s2),
            for (final k in const [
              'mobile_pending_blocked_publish',
              'mobile_pending_blocked_comment',
              'mobile_pending_blocked_messages',
              'mobile_pending_blocked_join',
              'mobile_pending_blocked_collect',
            ])
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 2),
                child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  ExcludeSemantics(child: Icon(Icons.lock_outline, size: 18, color: context.xm.textMuted)),
                  const SizedBox(width: XmSpace.s2),
                  Expanded(child: Text(context.t(k), style: theme.textTheme.bodyMedium)),
                ]),
              ),
            const SizedBox(height: XmSpace.s2),
            Text(context.t('mobile_pending_blocked_note'), style: theme.textTheme.bodySmall),
          ],
        ),
      ),
    );
  }
}
