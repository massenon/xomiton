import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../auth/application/session_controller.dart';
import '../application/settings_providers.dart';

void _toast(BuildContext context, String text) =>
    ScaffoldMessenger.of(context)..hideCurrentSnackBar()..showSnackBar(SnackBar(content: Text(text)));

/// Déconnexion avec confirmation : efface tout le stockage privé.
Future<void> confirmAndSignOut(BuildContext context, WidgetRef ref) async {
  final ok = await showDialog<bool>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text(ctx.t('buttons_logout')),
      content: Text(ctx.t('mobile_logout_confirm')),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(ctx.t('common_cancel'))),
        FilledButton(key: const Key('logout-confirm'), onPressed: () => Navigator.pop(ctx, true), child: Text(ctx.t('buttons_logout'))),
      ],
    ),
  );
  if (ok != true) return;
  await ref.read(sessionProvider.notifier).signOut();
  if (context.mounted) _toast(context, context.t('auth_logout_done'));
}

class SettingsPage extends ConsumerWidget {
  const SettingsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final mode = ref.watch(themeModeProvider);
    final settings = ref.watch(settingsProvider);

    Future<void> guard(Future<void> Function() run) async {
      try {
        await run();
      } catch (e) {
        if (context.mounted) _toast(context, errorMessage(context, e));
      }
    }

    return Scaffold(
      appBar: AppBar(title: Text(context.t('profile_settings_title'))),
      body: ListView(
        padding: const EdgeInsets.all(XmSpace.gutter),
        children: [
          Text(context.t('profile_settings_appearance_title'), style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: XmSpace.s2),
          SegmentedButton<ThemeMode>(
            key: const Key('theme-selector'),
            showSelectedIcon: false,
            segments: [
              ButtonSegment(value: ThemeMode.light, label: Text(context.t('profile_settings_appearance_light'))),
              ButtonSegment(value: ThemeMode.dark, label: Text(context.t('profile_settings_appearance_dark'))),
              ButtonSegment(value: ThemeMode.system, label: Text(context.t('profile_settings_appearance_auto'))),
            ],
            selected: {mode},
            onSelectionChanged: (s) async {
              final saved = await ref.read(themeModeProvider.notifier).set(s.first);
              if (context.mounted) {
                _toast(context, context.t(saved ? 'profile_settings_appearance_applied' : 'microcopy_error_generic'));
              }
            },
          ),
          const SizedBox(height: XmSpace.s4),
          settings.when(
            loading: () => const Padding(padding: EdgeInsets.all(XmSpace.s5), child: LoadingView()),
            error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(settingsProvider)),
            data: (s) => Column(children: [
              SwitchListTile(
                key: const Key('switch-notifications'),
                contentPadding: EdgeInsets.zero,
                title: Text(context.t('mobile_settings_notifications')),
                subtitle: Text(context.t('mobile_settings_notifications_help')),
                value: s.notifications,
                onChanged: (v) => guard(() => ref.read(settingsProvider.notifier).setNotifications(v)),
              ),
              SwitchListTile(
                key: const Key('switch-sounds'),
                contentPadding: EdgeInsets.zero,
                title: Text(context.t('profile_settings_security_sounds')),
                subtitle: Text(context.t('profile_settings_security_sounds_help')),
                value: s.sounds,
                onChanged: (v) => guard(() => ref.read(settingsProvider.notifier).setSounds(v)),
              ),
              const SizedBox(height: XmSpace.s2),
              Text(context.t('profile_settings_language_title'), style: Theme.of(context).textTheme.titleMedium),
              // Seules les locales de l'API sont activables ; les autres sont « bientôt ».
              RadioGroup<String>(
                groupValue: s.locale,
                onChanged: (v) {
                  if (v != null && v != s.locale) guard(() => ref.read(settingsProvider.notifier).setLocale(v));
                },
                child: Column(children: [
                  for (final l in const ['fr', 'fon', 'yo', 'en'])
                    RadioListTile<String>(
                      key: Key('locale-$l'),
                      contentPadding: EdgeInsets.zero,
                      value: l,
                      enabled: kSupportedLocales.contains(l),
                      title: Text(context.t('profile_settings_language_options_$l')),
                      subtitle: kSupportedLocales.contains(l) ? null : Text(context.t('profile_settings_language_soon')),
                    ),
                ]),
              ),
            ]),
          ),
          const Divider(),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.shield_outlined),
            title: Text(context.t('mobile_security_title')),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => context.push('/securite'),
          ),
          const SizedBox(height: XmSpace.s4),
          OutlinedButton.icon(
            key: const Key('logout'),
            icon: const Icon(Icons.logout),
            label: Text(context.t('buttons_logout')),
            onPressed: () => confirmAndSignOut(context, ref),
          ),
        ],
      ),
    );
  }
}
