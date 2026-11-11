import 'package:flutter/material.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';

/// Image d'un outil CCC : espace réservé pendant le chargement, message si elle
/// est indisponible (jamais une zone vide muette). [alt] est lu par les lecteurs
/// d'écran ; avec [semantic] = faux, l'appelant porte déjà le texte alternatif.
class CccImage extends StatelessWidget {
  const CccImage(this.url, {super.key, required this.alt, this.contain = false, this.semantic = true});
  final String? url;
  final String alt;
  final bool contain;
  final bool semantic;

  @override
  Widget build(BuildContext context) {
    final url = this.url;
    if (url == null || url.isEmpty) return _Failed(alt: alt, semantic: semantic);
    return Image.network(
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
