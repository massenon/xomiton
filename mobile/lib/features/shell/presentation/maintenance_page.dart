import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/maintenance/maintenance.dart';
import '../../../core/ui/xm_widgets.dart';
import '../../auth/application/session_controller.dart';

/// Décalage d'affichage connu (le serveur envoie le fuseau, les dates en UTC).
/// Sans fuseau reconnu, l'heure de l'appareil est utilisée.
Duration? _fixedOffset(String? zone) => switch (zone) {
  'Africa/Porto-Novo' || 'Africa/Lagos' || 'Africa/Douala' => const Duration(hours: 1),
  'UTC' || 'Africa/Abidjan' || 'Africa/Lome' || 'Africa/Accra' => Duration.zero,
  _ => null,
};

/// « 14 h 30 » (même jour) ou « 30/10 à 14 h 30 », dans le fuseau du serveur.
String maintenanceEndLabel(DateTime endsUtc, String? zone, {DateTime? now}) {
  final offset = _fixedOffset(zone);
  DateTime shown(DateTime d) => offset == null ? d.toLocal() : d.toUtc().add(offset);
  final end = shown(endsUtc);
  final today = shown(now ?? DateTime.now().toUtc());
  String two(int n) => n.toString().padLeft(2, '0');
  final hm = '${end.hour} h ${two(end.minute)}';
  final sameDay = end.year == today.year && end.month == today.month && end.day == today.day;
  return sameDay ? hm : '${two(end.day)}/${two(end.month)} à $hm';
}

/// Écran plein de la maintenance (RG-14). Le SOS, le signalement et les
/// urgences restent ouverts (RG-01) ; la boîte d'envoi est conservée et
/// rejouée à la réouverture.
class MaintenancePage extends ConsumerWidget {
  const MaintenancePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final m = ref.watch(maintenanceProvider);
    final authenticated = ref.watch(sessionProvider.select((s) => s.authenticated));
    final xm = context.xm;
    final theme = Theme.of(context);
    final message = m.message ?? context.t('maintenance_default_message');
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 520),
            child: ListView(
              padding: const EdgeInsets.all(XmSpace.s5),
              children: [
                const Center(child: XmLogo(width: 112)),
                const SizedBox(height: XmSpace.s5),
                Icon(Icons.build_circle_outlined, size: 48, color: xm.warning, semanticLabel: context.t('maintenance_page_title')),
                const SizedBox(height: XmSpace.s3),
                Text(
                  context.t('maintenance_page_title'),
                  key: const Key('maintenance-title'),
                  style: theme.textTheme.headlineSmall,
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: XmSpace.s3),
                Text(message, key: const Key('maintenance-message'), textAlign: TextAlign.center),
                const SizedBox(height: XmSpace.s3),
                if (m.overdue)
                  Text(
                    context.t('maintenance_status_overdue'),
                    key: const Key('maintenance-overdue'),
                    textAlign: TextAlign.center,
                    style: theme.textTheme.bodyMedium,
                  )
                else if (m.endsAt != null)
                  Text(
                    '${context.t('maintenance_status_ends')} : ${maintenanceEndLabel(m.endsAt!, m.timezone)}',
                    key: const Key('maintenance-ends'),
                    textAlign: TextAlign.center,
                    style: theme.textTheme.titleSmall,
                  ),
                const SizedBox(height: XmSpace.s3),
                Text(
                  context.t('mobile_maintenance_outbox'),
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodySmall,
                ),
                const SizedBox(height: XmSpace.s4),
                Center(
                  child: OutlinedButton.icon(
                    key: const Key('maintenance-retry'),
                    style: OutlinedButton.styleFrom(minimumSize: const Size(200, 48)),
                    onPressed: m.checking ? null : () => ref.read(maintenanceProvider.notifier).check(),
                    icon: m.checking
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                        : const Icon(Icons.refresh),
                    label: Text(context.t('mobile_maintenance_check')),
                  ),
                ),
                if (m.lastCheckFailed) ...[
                  const SizedBox(height: XmSpace.s2),
                  Text(context.t('microcopy_error_unreachable'), textAlign: TextAlign.center, style: theme.textTheme.bodySmall),
                ],
                const SizedBox(height: XmSpace.s2),
                Text(
                  context.t('maintenance_status_check'),
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodySmall?.copyWith(color: xm.textMuted),
                ),
                const SizedBox(height: XmSpace.s5),
                Material(
                  color: xm.surfaceMuted,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(XmSpace.radiusMd),
                    side: BorderSide(color: xm.border),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(XmSpace.s4),
                    child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Semantics(header: true, child: Text(context.t('maintenance_always_available_title'), style: theme.textTheme.titleMedium)),
                      const SizedBox(height: XmSpace.s2),
                      Text(context.t('maintenance_always_available_text')),
                      const SizedBox(height: XmSpace.s3),
                      Row(
                        children: [
                          Expanded(
                            child: FilledButton(
                              key: const Key('maintenance-sos'),
                              style: FilledButton.styleFrom(backgroundColor: xm.danger, minimumSize: const Size(0, 48)),
                              // Connecté·e : l'onglet SOS (route de la barre du bas) ; sinon l'écran plein public.
                              onPressed: () => authenticated ? context.go('/sos') : context.push('/alerte-sos'),
                              child: Text(context.t('maintenance_always_available_sos')),
                            ),
                          ),
                          const SizedBox(width: XmSpace.s2),
                          Expanded(
                            child: OutlinedButton(
                              key: const Key('maintenance-report'),
                              style: OutlinedButton.styleFrom(minimumSize: const Size(0, 48)),
                              onPressed: () => context.push('/signaler'),
                              child: Text(context.t('maintenance_always_available_report')),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: XmSpace.s2),
                      OutlinedButton(
                        key: const Key('maintenance-emergency'),
                        style: OutlinedButton.styleFrom(minimumSize: const Size(double.infinity, 48)),
                        onPressed: () => context.push('/urgences'),
                        child: Text(context.t('maintenance_always_available_all_contacts')),
                      ),
                      if (m.numbers.isNotEmpty) ...[
                        const SizedBox(height: XmSpace.s3),
                        Text(context.t('maintenance_always_available_numbers_text')),
                        for (final n in m.numbers)
                          ListTile(
                            key: Key('maintenance-number-${n.number}'),
                            contentPadding: EdgeInsets.zero,
                            minTileHeight: 48,
                            leading: const Icon(Icons.phone_outlined),
                            title: Text(n.label.isEmpty ? n.number : n.label),
                            subtitle: n.label.isEmpty ? null : Text(n.number),
                            onTap: () => launchUrl(Uri(scheme: 'tel', path: n.number.replaceAll(' ', ''))),
                          ),
                      ],
                    ],
                    ),
                  ),
                ),
                if (!authenticated) ...[
                  const SizedBox(height: XmSpace.s4),
                  Center(
                    child: TextButton(
                      key: const Key('maintenance-team-login'),
                      onPressed: () => context.go('/login'),
                      child: Text(context.t('mobile_maintenance_team_login')),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}
