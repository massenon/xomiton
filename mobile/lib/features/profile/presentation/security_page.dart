import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/platform/discreet_icon.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../../core/util/dates.dart';
import '../../auth/application/session_controller.dart';
import '../../security/application/pin_lock.dart';
import '../../security/presentation/pin_widgets.dart';
import '../application/settings_providers.dart';
import '../data/account_api.dart';

void _toast(BuildContext context, String text) =>
    ScaffoldMessenger.of(context)..hideCurrentSnackBar()..showSnackBar(SnackBar(content: Text(text)));

class SecurityPage extends ConsumerWidget {
  const SecurityPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final sec = ref.watch(securityProvider);
    final lock = ref.watch(pinLockProvider);
    final icon = ref.watch(discreetIconProvider);

    Future<void> guard(Future<void> Function() run) async {
      try {
        await run();
      } catch (e) {
        if (context.mounted) _toast(context, errorMessage(context, e));
      }
    }

    Future<void> togglePin(bool on) async {
      final ctrl = ref.read(pinLockProvider.notifier);
      if (on) {
        final pin = await askNewPin(context);
        if (pin == null) return;
        await ctrl.enable(pin);
        // Seul l'état « activé » part au serveur ; le code reste sur l'appareil.
        await guard(() => ref.read(securityProvider.notifier).setPinEnabled(true));
        if (context.mounted) _toast(context, context.t('mobile_pin_enabled'));
      } else {
        final pin = await askPin(context, 'mobile_pin_current_title');
        if (pin == null) return;
        final res = await ctrl.disable(pin);
        if (!res.ok) {
          if (context.mounted && !res.exhausted) _toast(context, context.t('mobile_pin_wrong', {'count': res.remaining}));
          return;
        }
        await guard(() => ref.read(securityProvider.notifier).setPinEnabled(false));
        if (context.mounted) _toast(context, context.t('mobile_pin_disabled'));
      }
    }

    return Scaffold(
      appBar: AppBar(title: Text(context.t('mobile_security_title'))),
      body: sec.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(securityProvider)),
        data: (s) => ListView(
          padding: const EdgeInsets.all(XmSpace.gutter),
          children: [
            SwitchListTile(
              key: const Key('switch-discreet'),
              contentPadding: EdgeInsets.zero,
              title: Text(context.t('profile_settings_security_discreet_mode')),
              subtitle: Text(context.t(icon.supported ? 'mobile_security_discreet_help' : 'mobile_security_discreet_ios_help')),
              value: s.discreet,
              onChanged: (v) => guard(() => ref.read(securityProvider.notifier).setDiscreet(v)),
            ),
            SwitchListTile(
              key: const Key('switch-mask'),
              contentPadding: EdgeInsets.zero,
              title: Text(context.t('profile_settings_security_mask_notifications')),
              subtitle: Text(context.t('mobile_security_mask_help')),
              value: s.maskPreviews,
              onChanged: (v) => guard(() => ref.read(securityProvider.notifier).setMaskPreviews(v)),
            ),
            SwitchListTile(
              key: const Key('switch-pin'),
              contentPadding: EdgeInsets.zero,
              title: Text(context.t('profile_settings_security_pin_enable')),
              subtitle: Text(context.t('profile_settings_security_pin_help')),
              value: lock.configured,
              onChanged: togglePin,
            ),
            const Divider(),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.phone_android),
              title: Text(context.t('profile_settings_sessions_title')),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => context.push('/appareils'),
            ),
            ListTile(
              key: const Key('export-data'),
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.download_outlined),
              title: Text(context.t('profile_settings_data_submit')),
              subtitle: Text(context.t('profile_settings_data_text')),
              onTap: () async {
                try {
                  await ref.read(accountApiProvider).requestExport();
                  if (context.mounted) _toast(context, context.t('profile_settings_data_preparing', {'hours': 48}));
                } catch (e) {
                  if (context.mounted) _toast(context, errorMessage(context, e));
                }
              },
            ),
            ListTile(
              key: const Key('delete-account'),
              contentPadding: EdgeInsets.zero,
              leading: Icon(Icons.delete_outline, color: context.xm.danger),
              title: Text(context.t('profile_settings_delete_title'), style: TextStyle(color: context.xm.danger)),
              onTap: () => _confirmDelete(context, ref),
            ),
          ],
        ),
      ),
    );
  }

  /// Confirmation forte : mot SUPPRIMER tapé + mot de passe actuel.
  Future<void> _confirmDelete(BuildContext context, WidgetRef ref) async {
    final password = await showDialog<String>(context: context, builder: (_) => const _DeleteDialog());
    if (password == null) return;
    try {
      await ref.read(accountApiProvider).deleteAccount(password);
      await ref.read(sessionProvider.notifier).expire();
      if (context.mounted) _toast(context, context.t('profile_settings_delete_done', {'days': 30}));
    } catch (e) {
      if (context.mounted) _toast(context, errorMessage(context, e));
    }
  }
}

class _DeleteDialog extends StatefulWidget {
  const _DeleteDialog();
  @override
  State<_DeleteDialog> createState() => _DeleteDialogState();
}

class _DeleteDialogState extends State<_DeleteDialog> {
  final _word = TextEditingController();
  final _password = TextEditingController();

  @override
  void dispose() {
    _word.dispose();
    _password.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ok = _word.text.trim() == context.t('profile_settings_delete_confirm_word') && _password.text.isNotEmpty;
    return AlertDialog(
      title: Text(context.t('profile_settings_delete_title')),
      content: SingleChildScrollView(
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(context.t('profile_settings_delete_text', {'days': 30})),
          const SizedBox(height: XmSpace.s3),
          TextField(
            key: const Key('delete-word'),
            controller: _word,
            onChanged: (_) => setState(() {}),
            decoration: InputDecoration(labelText: context.t('profile_settings_delete_confirm_label')),
          ),
          const SizedBox(height: XmSpace.s2),
          TextField(
            key: const Key('delete-password'),
            controller: _password,
            obscureText: true,
            onChanged: (_) => setState(() {}),
            decoration: InputDecoration(
              labelText: context.t('auth_login_password'),
              helperText: context.t('profile_settings_delete_password_help'),
            ),
          ),
        ]),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: Text(context.t('common_cancel'))),
        FilledButton(
          key: const Key('delete-confirm'),
          style: FilledButton.styleFrom(backgroundColor: context.xm.danger),
          onPressed: ok ? () => Navigator.pop(context, _password.text) : null,
          child: Text(context.t('profile_settings_delete_submit')),
        ),
      ],
    );
  }
}

/// Appareils connectés (`me/sessions`) et révocation.
class DevicesPage extends ConsumerWidget {
  const DevicesPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final list = ref.watch(sessionsProvider);
    Future<void> run(Future<void> Function() job, String doneKey) async {
      try {
        await job();
        ref.invalidate(sessionsProvider);
        if (context.mounted) _toast(context, context.t(doneKey));
      } catch (e) {
        if (context.mounted) _toast(context, errorMessage(context, e));
      }
    }

    return Scaffold(
      appBar: AppBar(title: Text(context.t('profile_settings_sessions_title'))),
      body: list.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(sessionsProvider)),
        data: (items) => ListView(
          padding: const EdgeInsets.all(XmSpace.gutter),
          children: [
            Text(context.t('profile_settings_sessions_intro')),
            const SizedBox(height: XmSpace.s3),
            for (final d in items)
              ListTile(
                key: Key('session-${d.id}'),
                contentPadding: EdgeInsets.zero,
                leading: Icon(d.kind == 'web' ? Icons.language : Icons.phone_android),
                title: Text(d.name.isEmpty ? context.t('mobile_device_name_fallback') : d.name, maxLines: 2, overflow: TextOverflow.ellipsis),
                subtitle: Text(d.current
                    ? context.t('profile_settings_sessions_current')
                    : [
                        context.t('profile_settings_sessions_kind_${d.kind == 'web' ? 'web' : 'token'}'),
                        if (d.lastActiveAt != null) context.t('profile_settings_sessions_last_active', {'ago': relativeTime(context, d.lastActiveAt!)}),
                      ].join(' · ')),
                trailing: d.current
                    ? null
                    : TextButton(
                        key: Key('revoke-${d.id}'),
                        onPressed: () => run(() => ref.read(accountApiProvider).revokeSession(d.id), 'profile_settings_toasts_session_revoked'),
                        child: Text(context.t('profile_settings_sessions_revoke')),
                      ),
              ),
            if (items.where((d) => !d.current).isEmpty) EmptyView(context.t('profile_settings_sessions_empty')),
            if (items.where((d) => !d.current).isNotEmpty)
              OutlinedButton(
                key: const Key('revoke-others'),
                onPressed: () => run(() => ref.read(accountApiProvider).revokeOthers(), 'profile_settings_toasts_sessions_revoked'),
                child: Text(context.t('profile_settings_sessions_revoke_all')),
              ),
          ],
        ),
      ),
    );
  }
}
