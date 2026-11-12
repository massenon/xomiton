import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../data/engagement_api.dart';

/// Annonce en cours pour le compte (P18-13) ; null sans annonce, hors ligne ou
/// en cas d'erreur (la bannière ne bloque jamais l'écran).
final announcementProvider = FutureProvider.autoDispose<Announcement?>((ref) async {
  try {
    return await ref.watch(engagementApiProvider).activeAnnouncement();
  } catch (_) {
    return null;
  }
});

/// Annonces fermées pendant la session (la fermeture est aussi mémorisée par le
/// serveur, par compte : elle ne revient ni sur le web ni dans l'application).
final dismissedAnnouncementsProvider = StateProvider<Set<int>>((ref) => <int>{});

/// Bannière d'annonce globale en haut de l'espace membre de l'application
/// (accueil et fil) : titre, message, lien interne facultatif, bouton Fermer.
/// Jamais rouge (réservé à l'urgence) : bleu (information) ou orange (important).
class AnnouncementBanner extends ConsumerWidget {
  const AnnouncementBanner({super.key, this.padding = const EdgeInsets.only(bottom: XmSpace.s3)});

  /// Marges appliquées seulement quand une annonce s'affiche.
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final a = ref.watch(announcementProvider).valueOrNull;
    final dismissed = ref.watch(dismissedAnnouncementsProvider);
    if (a == null || dismissed.contains(a.id)) return const SizedBox.shrink();
    final scheme = Theme.of(context).colorScheme;
    final bg = a.important ? scheme.tertiaryContainer : scheme.secondaryContainer;
    final fg = a.important ? scheme.onTertiaryContainer : scheme.onSecondaryContainer;
    return Padding(
      padding: padding,
      child: Semantics(
        container: true,
        liveRegion: true,
        child: Material(
          key: Key('announcement-${a.id}'),
          color: bg,
          borderRadius: BorderRadius.circular(12),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(XmSpace.s4, XmSpace.s3, XmSpace.s1, XmSpace.s3),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.campaign_outlined, color: fg),
                const SizedBox(width: XmSpace.s3),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(a.title, style: Theme.of(context).textTheme.titleSmall?.copyWith(color: fg)),
                      const SizedBox(height: XmSpace.s1),
                      Text(a.message, style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: fg)),
                      if (a.link != null && a.link!.startsWith('/'))
                        TextButton(
                          style: TextButton.styleFrom(padding: EdgeInsets.zero, foregroundColor: fg),
                          onPressed: () => context.push(a.link!),
                          child: Text(context.t('community_announcement_open')),
                        ),
                    ],
                  ),
                ),
                IconButton(
                  key: const Key('announcement-close'),
                  tooltip: context.t('community_announcement_close'),
                  color: fg,
                  icon: const Icon(Icons.close),
                  onPressed: () {
                    ref.read(dismissedAnnouncementsProvider.notifier).state = {...dismissed, a.id};
                    ref.read(engagementApiProvider).dismissAnnouncement(a.id).catchError((_) {});
                  },
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
