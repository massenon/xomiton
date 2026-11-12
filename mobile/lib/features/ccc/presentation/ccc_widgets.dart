import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/ui/xm_widgets.dart';
import '../application/ccc_offline.dart';
import '../data/ccc_repository.dart';

/// Image d'un outil CCC : espace réservé pendant le chargement, message si elle
/// est indisponible (jamais une zone vide muette). [alt] est lu par les lecteurs
/// d'écran ; avec [semantic] = faux, l'appelant porte déjà le texte alternatif.
class CccImage extends ConsumerWidget {
  const CccImage(this.url, {super.key, required this.alt, this.contain = false, this.semantic = true});
  final String? url;
  final String alt;
  final bool contain;
  final bool semantic;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final url = this.url;
    if (url == null || url.isEmpty) return _Failed(alt: alt, semantic: semantic);
    // Image enregistrée sur le disque : lue d'abord (marche sans réseau) ; si le
    // fichier a disparu, repli sur le réseau.
    final saved = ref.watch(cccImagesProvider.select((s) => s.has(url)));
    final path = saved ? ref.read(cccImageStoreProvider).pathFor(url) : null;
    if (path != null) {
      return Image.file(
        File(path),
        fit: contain ? BoxFit.contain : BoxFit.cover,
        width: double.infinity,
        height: double.infinity,
        semanticLabel: semantic ? alt : null,
        excludeFromSemantics: !semantic,
        errorBuilder: (context, _, _) => _network(url),
      );
    }
    return _network(url);
  }

  Widget _network(String url) => Image.network(
        url,
        fit: contain ? BoxFit.contain : BoxFit.cover,
        width: double.infinity,
        height: double.infinity,
        semanticLabel: semantic ? alt : null,
        excludeFromSemantics: !semantic,
        loadingBuilder: (context, child, progress) => progress == null
            ? child
            : Container(
                color: context.xm.surfaceMuted,
                alignment: Alignment.center,
                child: Semantics(
                  label: context.t('ccc_image_loading'),
                  child: const CircularProgressIndicator.adaptive(),
                ),
              ),
        errorBuilder: (context, _, _) => _Failed(alt: alt, semantic: semantic),
      );
}

class _Failed extends StatelessWidget {
  const _Failed({required this.alt, required this.semantic});
  final String alt;
  final bool semantic;

  @override
  Widget build(BuildContext context) {
    final message = context.t('ccc_image_failed');
    return Semantics(
      container: true,
      label: message,
      excludeSemantics: true,
      child: Container(
        color: context.xm.surfaceMuted,
        alignment: Alignment.center,
        padding: const EdgeInsets.all(XmSpace.s2),
        // Vignette trop petite pour le texte : l'icône seule, le message reste lu par les lecteurs d'écran.
        child: LayoutBuilder(
          builder: (context, c) => Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.broken_image_outlined, color: context.xm.textMuted),
              if (c.maxHeight >= 120) ...[
                const SizedBox(height: XmSpace.s1),
                Text(
                  message,
                  textAlign: TextAlign.center,
                  maxLines: 3,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(color: context.xm.textMuted),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// Barre de progression annoncée aux lecteurs d'écran : [label] (quoi) et
/// [announce] (valeur) sont lus d'un bloc ; zone « live » pour que chaque
/// changement soit annoncé sans déplacer le focus.
class CccProgressBar extends StatelessWidget {
  const CccProgressBar({super.key, required this.label, required this.announce, required this.percent, this.caption});
  final String label;
  final String announce;
  final int percent;

  /// Texte visible sous la barre (ex. « 2 sur 5 points vus · 40 % »).
  final String? caption;

  @override
  Widget build(BuildContext context) => Semantics(
        container: true,
        liveRegion: true,
        label: '$label. $announce',
        child: ExcludeSemantics(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(XmSpace.radiusSm),
                child: LinearProgressIndicator(
                  value: percent.clamp(0, 100) / 100,
                  minHeight: 8,
                  backgroundColor: context.xm.surfaceMuted,
                  color: context.xm.success,
                ),
              ),
              if (caption != null) ...[
                const SizedBox(height: XmSpace.s1),
                Text(caption!, style: Theme.of(context).textTheme.labelMedium),
              ],
            ],
          ),
        ),
      );
}

/// Titre de section (annoncé comme en-tête).
class CccHeading extends StatelessWidget {
  const CccHeading(this.title, {super.key, this.subtitle});
  final String title;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Semantics(header: true, child: Text(title, style: t.titleLarge)),
        if (subtitle != null) ...[
          const SizedBox(height: XmSpace.s1),
          Text(subtitle!, style: t.bodyMedium?.copyWith(color: context.xm.textMuted)),
        ],
      ],
    );
  }
}

/// Erreur de lecture : copie trop ancienne (message dédié) ou erreur ordinaire avec « Réessayer ».
class CccErrorView extends StatelessWidget {
  const CccErrorView({super.key, required this.error, required this.onRetry});
  final Object error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    if (error is! CccCopyExpiredException) return ErrorView(error: error, onRetry: onRetry);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(XmSpace.s5),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Icon(Icons.history_toggle_off, size: 40, color: context.xm.textMuted),
          const SizedBox(height: XmSpace.s3),
          Semantics(
            liveRegion: true,
            child: Text(
              context.t('ccc_offline_expired', {'days': kCccOfflineRetention.inDays}),
              key: const ValueKey('ccc-copy-expired'),
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleMedium,
            ),
          ),
          const SizedBox(height: XmSpace.s4),
          OutlinedButton(
            style: OutlinedButton.styleFrom(minimumSize: const Size(160, 48)),
            onPressed: onRetry,
            child: Text(context.t('common_retry')),
          ),
        ]),
      ),
    );
  }
}

/// Bandeau affiché quand la copie locale est servie : « Hors ligne », date de la
/// dernière synchronisation, bouton « Actualiser ». Texte et icône, jamais la couleur seule.
class CccOfflineBanner extends StatelessWidget {
  const CccOfflineBanner({super.key, required this.syncedAt, required this.refreshing, required this.onRefresh});
  final DateTime syncedAt;
  final bool refreshing;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    final date = DateFormat(AppStrings.of(context).raw('dates_formats_medium'), 'fr').format(syncedAt.toLocal());
    final tone = context.xm.warning;
    return Container(
      key: const ValueKey('ccc-offline-banner'),
      padding: const EdgeInsets.all(XmSpace.s3),
      decoration: BoxDecoration(
        color: tone.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(XmSpace.radiusMd),
        border: Border.all(color: tone.withValues(alpha: 0.5)),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // L'état est annoncé (zone « live ») ; le bouton reste un élément à part, atteignable seul.
        Semantics(
          key: const ValueKey('ccc-offline-status'),
          container: true,
          liveRegion: true,
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            ExcludeSemantics(child: Icon(Icons.cloud_off_outlined, size: 20, color: tone)),
            const SizedBox(width: XmSpace.s2),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('${context.t('ccc_offline_status')} ${context.t('common_separator')} ${context.t('ccc_offline_saved_on', {'date': date})}',
                    key: const ValueKey('ccc-offline-saved'), style: t.titleSmall),
                const SizedBox(height: XmSpace.s1),
                Text(context.t('ccc_offline_notice'), style: t.bodySmall),
              ]),
            ),
          ]),
        ),
        Align(
          alignment: Alignment.centerLeft,
          child: Semantics(
            button: true,
            enabled: !refreshing,
            label: context.t('ccc_offline_refresh_label'),
            excludeSemantics: true,
            onTap: refreshing ? null : onRefresh,
            child: TextButton.icon(
              key: const ValueKey('ccc-offline-refresh'),
              style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
              onPressed: refreshing ? null : onRefresh,
              icon: const Icon(Icons.refresh),
              label: Text(context.t('ccc_offline_refresh')),
            ),
          ),
        ),
      ]),
    );
  }
}

/// « Disponible hors ligne » : icône et texte (jamais la couleur seule).
class CccOfflineBadge extends StatelessWidget {
  const CccOfflineBadge({super.key, required this.toolKey});
  final String toolKey;

  @override
  Widget build(BuildContext context) => Row(
        key: ValueKey('ccc-offline-$toolKey'),
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.download_done, size: 18, color: context.xm.success),
          const SizedBox(width: XmSpace.s1),
          Flexible(
            child: Text(context.t('ccc_offline_tool_available'),
                style: Theme.of(context).textTheme.labelMedium?.copyWith(color: context.xm.success)),
          ),
        ],
      );
}

/// Téléchargement des images pour l'hors ligne : bouton (jamais automatique),
/// progression annoncée (zone « live » par quarts, pour ne pas saturer le lecteur
/// d'écran), annulation.
class CccDownloadCard extends ConsumerWidget {
  const CccDownloadCard({super.key, required this.urls, this.offline = false});
  final Set<String> urls;

  /// La copie locale est servie : pas de réseau, donc pas de téléchargement possible.
  final bool offline;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(cccImagesProvider);
    if (!s.supported || urls.isEmpty) return const SizedBox.shrink();
    final t = Theme.of(context).textTheme;
    final running = s.phase == CccDownloadPhase.running;
    final all = s.hasAll(urls);
    Widget body;
    if (running) {
      final percent = s.total == 0 ? 0 : (100 * s.done / s.total).round();
      // Annonce par quarts : « 0 sur 40 », « 10 sur 40 », …
      final announced = s.total <= 4 ? s.done : (s.done * 4 ~/ s.total) * s.total ~/ 4;
      body = Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        CccProgressBar(
          key: const ValueKey('ccc-download-progress'),
          label: context.t('ccc_offline_download_cta'),
          announce: context.t('ccc_offline_download_progress', {'done': announced, 'total': s.total}),
          percent: percent,
          caption: context.t('ccc_offline_download_progress', {'done': s.done, 'total': s.total}),
        ),
        TextButton.icon(
          key: const ValueKey('ccc-download-cancel'),
          style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
          onPressed: () => ref.read(cccImagesProvider.notifier).cancel(),
          icon: const Icon(Icons.close),
          label: Text(context.t('ccc_offline_download_cancel')),
        ),
      ]);
    } else if (all) {
      body = Semantics(
        liveRegion: true,
        container: true,
        child: Row(key: const ValueKey('ccc-download-done'), children: [
          Icon(Icons.download_done, color: context.xm.success),
          const SizedBox(width: XmSpace.s2),
          Expanded(child: Text(context.t('ccc_offline_download_done'), style: t.bodyMedium)),
        ]),
      );
    } else if (offline) {
      body = Text(context.t('ccc_offline_download_needs_network'), key: const ValueKey('ccc-download-needs-network'), style: t.bodySmall);
    } else {
      final message = switch (s.phase) {
        CccDownloadPhase.cancelled => context.t('ccc_offline_download_cancelled'),
        CccDownloadPhase.done when s.full => context.t('ccc_offline_download_full'),
        CccDownloadPhase.done when s.failed > 0 => context.t('ccc_offline_download_failed', {'count': s.failed}),
        _ => null,
      };
      body = Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(context.t('ccc_offline_download_hint'), style: t.bodySmall),
        if (message != null)
          Padding(
            padding: const EdgeInsets.only(top: XmSpace.s2),
            child: Semantics(
              liveRegion: true,
              container: true,
              child: Text(message, key: const ValueKey('ccc-download-message'), style: t.bodyMedium),
            ),
          ),
        FilledButton.icon(
          key: const ValueKey('ccc-download'),
          style: FilledButton.styleFrom(minimumSize: const Size(48, 48)),
          onPressed: () => ref.read(cccImagesProvider.notifier).download(urls),
          icon: const Icon(Icons.download_outlined),
          label: Text(context.t('ccc_offline_download_cta')),
        ),
      ]);
    }
    return Card(
      key: const ValueKey('ccc-download-card'),
      margin: const EdgeInsets.only(bottom: XmSpace.s3),
      child: Padding(padding: const EdgeInsets.all(XmSpace.s3), child: body),
    );
  }
}
