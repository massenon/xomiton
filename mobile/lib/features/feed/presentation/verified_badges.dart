import 'package:flutter/material.dart';

import '../../../app/theme/xm_tokens.dart';
import '../data/feed_models.dart';

/// Badges vérifiés par l'administration (P18-09, Q97), à côté du pseudo dans
/// le fil, les commentaires et le profil. Attribués à la main par l'équipe ;
/// jamais de classement. Rien n'est rendu sans badge ; le libellé est lu par
/// les lecteurs d'écran, l'icône est décorative.
class VerifiedBadges extends StatelessWidget {
  const VerifiedBadges({super.key, required this.badges});
  final List<VerifiedBadge> badges;

  @override
  Widget build(BuildContext context) {
    if (badges.isEmpty) return const SizedBox.shrink();
    final scheme = Theme.of(context).colorScheme;
    final style = Theme.of(context).textTheme.labelSmall?.copyWith(color: scheme.primary);
    return Wrap(
      spacing: XmSpace.s1,
      runSpacing: XmSpace.s1,
      children: [
        for (final b in badges)
          Container(
            key: ValueKey('verified-badge-${b.key}'),
            padding: const EdgeInsets.symmetric(horizontal: XmSpace.s2, vertical: 2),
            decoration: BoxDecoration(
              color: scheme.primary.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(XmSpace.s2),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                ExcludeSemantics(
                  child: Icon(
                    b.key == 'pair_animateur' ? Icons.workspace_premium_outlined : Icons.verified_outlined,
                    size: 14,
                    color: scheme.primary,
                  ),
                ),
                const SizedBox(width: 4),
                Flexible(child: Text(b.label, style: style, overflow: TextOverflow.ellipsis)),
              ],
            ),
          ),
      ],
    );
  }
}
