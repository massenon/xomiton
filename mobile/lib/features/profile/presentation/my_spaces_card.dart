import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/auth_spaces/presentation/space_widgets.dart';
import '../../../core/auth_spaces/space_providers.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../auth/application/session_controller.dart';
import '../data/role_requests_api.dart';

/// Carte « Mes espaces » du profil (maquette 26) : compte à plusieurs
/// espaces → « Changer d'espace » ; membre seul → « Demander le rôle pair
/// éducateur·rice » (D-MOB-15), état de la demande en cours ou refusée. Les
/// espaces viennent du serveur ; la décision reste celle d'un·e admin.
class MySpacesCard extends ConsumerWidget {
  const MySpacesCard({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final several = ref.watch(hasSeveralSpacesProvider);
    final user = ref.watch(sessionProvider).user;
    final theme = Theme.of(context);
    final xm = context.xm;
    final roles = ref.watch(spacesProvider);
    return Semantics(
      container: true,
      child: Card(
        key: const ValueKey('my-spaces-card'),
        child: Padding(
          padding: const EdgeInsets.all(XmSpace.s4),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Semantics(header: true, child: Text(context.t('mobile_my_spaces_title'), style: theme.textTheme.titleMedium)),
            const SizedBox(height: XmSpace.s2),
            Text(
              context.t(several ? 'mobile_my_spaces_text' : 'mobile_my_spaces_member_only'),
              style: theme.textTheme.bodySmall,
            ),
            if (several) ...[
              const SizedBox(height: XmSpace.s3),
              Wrap(spacing: XmSpace.s2, runSpacing: XmSpace.s2, children: [
                for (final s in roles)
                  Chip(
                    avatar: Icon(s.icon, size: 16, color: xm.primarySubtleText),
                    label: Text(s.label(context)),
                    backgroundColor: xm.primarySubtle,
                    side: BorderSide.none,
                  ),
              ]),
              const SizedBox(height: XmSpace.s3),
              FilledButton.icon(
                key: const ValueKey('my-spaces-switch'),
                style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
                icon: const Icon(Icons.groups_outlined),
                label: Text(context.t('mobile_my_spaces_switch')),
                onPressed: () => showSpaceSheet(context),
              ),
            ] else if (user != null && !user.isPeer) ...[
              const SizedBox(height: XmSpace.s3),
              const _RoleRequestArea(),
            ],
          ]),
        ),
      ),
    );
  }
}

class _RoleRequestArea extends ConsumerWidget {
  const _RoleRequestArea();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final view = ref.watch(roleRequestsProvider);
    return view.when(
      // Hors ligne ou erreur : la demande reste possible, le serveur tranchera (jamais de blocage muet).
      loading: () => const SizedBox(height: 48),
      error: (_, _) => _requestButton(context, ref),
      data: (v) {
        final pending = v.pending;
        if (pending != null) {
          return XmAlert(context.t('mobile_role_request_pending'), icon: Icons.hourglass_empty, tone: context.xm.info);
        }
        final rejected = v.lastRejected;
        return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          if (rejected != null) ...[
            XmAlert(
              [
                context.t('mobile_role_request_rejected'),
                if ((rejected.decisionReason ?? '').isNotEmpty) rejected.decisionReason!,
              ].join(' '),
              icon: Icons.info_outline,
              tone: context.xm.info,
            ),
            const SizedBox(height: XmSpace.s3),
          ],
          if (v.canRequest) _requestButton(context, ref),
        ]);
      },
    );
  }

  Widget _requestButton(BuildContext context, WidgetRef ref) => OutlinedButton.icon(
        key: const ValueKey('role-request-open'),
        style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48)),
        icon: const Icon(Icons.verified_user_outlined),
        label: Text(context.t('mobile_role_request_cta')),
        onPressed: () async {
          final sent = await showModalBottomSheet<bool>(
            context: context,
            isScrollControlled: true,
            useSafeArea: true,
            showDragHandle: true,
            builder: (_) => const RoleRequestSheet(),
          );
          if (sent == true) ref.invalidate(roleRequestsProvider);
        },
      );
}

/// Feuille de demande du rôle pair : un motif facultatif (≤ 200 caractères), envoi
/// avec une clé d'idempotence fixe pour la feuille (un nouvel essai après coupure
/// réseau ne crée jamais de doublon). Erreurs métier = message du serveur.
class RoleRequestSheet extends ConsumerStatefulWidget {
  const RoleRequestSheet({super.key});

  @override
  ConsumerState<RoleRequestSheet> createState() => _RoleRequestSheetState();
}

class _RoleRequestSheetState extends ConsumerState<RoleRequestSheet> {
  final _reason = TextEditingController();
  final _key = const Uuid().v4();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(roleRequestsApiProvider).request(reason: _reason.text, idempotencyKey: _key);
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(context.t('mobile_role_request_sent'))));
      Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = errorMessage(context, e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: EdgeInsets.fromLTRB(
        XmSpace.gutter,
        0,
        XmSpace.gutter,
        XmSpace.s4 + MediaQuery.of(context).viewInsets.bottom,
      ),
      child: SingleChildScrollView(
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Semantics(header: true, child: Text(context.t('mobile_role_request_title'), style: theme.textTheme.titleLarge)),
          const SizedBox(height: XmSpace.s2),
          Text(context.t('mobile_role_request_text'), style: theme.textTheme.bodySmall),
          const SizedBox(height: XmSpace.s4),
          if (_error != null) ...[XmAlert(_error!), const SizedBox(height: XmSpace.s3)],
          TextField(
            key: const ValueKey('role-request-reason'),
            controller: _reason,
            maxLength: RoleRequestsApi.reasonMax,
            maxLines: 3,
            textInputAction: TextInputAction.newline,
            decoration: InputDecoration(labelText: context.t('mobile_role_request_reason')),
          ),
          const SizedBox(height: XmSpace.s3),
          FilledButton(
            key: const ValueKey('role-request-send'),
            style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
            onPressed: _busy ? null : _send,
            child: _busy
                ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5))
                : Text(context.t('mobile_role_request_send')),
          ),
          TextButton(
            style: TextButton.styleFrom(minimumSize: const Size.fromHeight(48)),
            onPressed: _busy ? null : () => Navigator.of(context).pop(false),
            child: Text(context.t('common_cancel')),
          ),
        ]),
      ),
    );
  }
}
