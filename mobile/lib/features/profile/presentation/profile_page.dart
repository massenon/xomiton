import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../auth/application/session_controller.dart';
import 'my_spaces_card.dart';
import 'settings_page.dart';

/// Onglet Profil : accès à mon profil, aux paramètres, à la sécurité et aux
/// parcours de sécurité (accessibles aussi sans code PIN).
class ProfileTabPage extends ConsumerWidget {
  const ProfileTabPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(sessionProvider).user;
    Widget tile(IconData icon, String route, String key, {Key? k}) => ListTile(
      key: k,
      contentPadding: EdgeInsets.zero,
      leading: Icon(icon),
      title: Text(context.t(key)),
      trailing: const Icon(Icons.chevron_right),
      onTap: () => context.push(route),
    );
    return SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(XmSpace.gutter),
        children: [
          Text(user?.pseudo ?? context.t('menus_mobile_profile'), style: Theme.of(context).textTheme.headlineMedium),
          const SizedBox(height: XmSpace.s2),
          if (user != null) ...[
            Text(context.t('profile_progressive_banner_progress', {'percent': user.completion})),
            const SizedBox(height: XmSpace.s2),
            LinearProgressIndicator(value: user.completion / 100),
          ],
          const SizedBox(height: XmSpace.s3),
          if (user != null) ...[
            const MySpacesCard(),
            const SizedBox(height: XmSpace.s3),
          ],
          tile(Icons.person_outline, '/mon-profil', 'profile_about_title', k: const Key('tile-my-profile')),
          tile(Icons.settings_outlined, '/parametres', 'profile_settings_title', k: const Key('tile-settings')),
          tile(Icons.shield_outlined, '/securite', 'mobile_security_title', k: const Key('tile-security')),
          tile(Icons.flag_outlined, '/signalements', 'mobile_reports_mine_title'),
          tile(Icons.search, '/suivi', 'alerts_report_tracking_title'),
          tile(Icons.groups_outlined, '/groupes', 'community_groups_title'),
          tile(Icons.event_outlined, '/evenements', 'community_events_title'),
          tile(Icons.smart_toy_outlined, '/assistant', 'assistant_page_title'),
          tile(Icons.phone_in_talk_outlined, '/urgences', 'alerts_emergency_title'),
          const SizedBox(height: XmSpace.s5),
          OutlinedButton.icon(
            key: const Key('profile-logout'),
            icon: const Icon(Icons.logout),
            label: Text(context.t('buttons_logout')),
            onPressed: () => confirmAndSignOut(context, ref),
          ),
        ],
      ),
    );
  }
}

/// Mon profil : ce que je partage (jamais les champs sensibles), et l'entrée
/// du profil progressif.
class MyProfilePage extends ConsumerWidget {
  const MyProfilePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(sessionProvider).user;
    final profile = user?.raw['profile'] is Map ? Map<String, dynamic>.from(user!.raw['profile'] as Map) : <String, dynamic>{};
    String? name(Object? o) => o is Map ? o['name'] as String? : null;
    final rows = <(String, String?)>[
      ('profile_about_pronouns', profile['pronouns'] as String?),
      ('profile_about_zone', [name(profile['zone']), name(profile['commune'])].whereType<String>().join(' · ')),
      ('profile_about_bio', profile['bio'] as String?),
      ('profile_about_interests', (profile['interests'] as List?)?.join(', ')),
    ];
    return Scaffold(
      appBar: AppBar(title: Text(context.t('profile_about_title'))),
      body: ListView(
        padding: const EdgeInsets.all(XmSpace.gutter),
        children: [
          Text(user?.pseudo ?? '', style: Theme.of(context).textTheme.headlineSmall),
          const SizedBox(height: XmSpace.s3),
          for (final r in rows)
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(context.t(r.$1)),
              subtitle: Text(r.$2 == null || r.$2!.isEmpty ? context.t('profile_about_not_provided') : r.$2!),
            ),
          Text(context.t('profile_about_sensitive_private'), style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(height: XmSpace.s4),
          FilledButton(
            key: const Key('open-progressive'),
            onPressed: () => context.push('/profil-progressif'),
            child: Text(context.t('profile_progressive_banner_title')),
          ),
        ],
      ),
    );
  }
}
