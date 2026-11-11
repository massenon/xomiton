import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../data/home_models.dart';

class CoverImage extends StatelessWidget {
  const CoverImage(this.url, {super.key, this.height, this.width});
  final String? url;
  final double? height;
  final double? width;

  @override
  Widget build(BuildContext context) {
    final fallback = Container(
      height: height,
      width: width,
      color: context.xm.primarySubtle,
      child: Icon(Icons.menu_book_outlined, color: context.xm.primary),
    );
    if (url == null || url!.isEmpty) return fallback;
    return Image.network(
      url!,
      height: height,
      width: width,
      fit: BoxFit.cover,
      excludeFromSemantics: true,
      errorBuilder: (_, _, _) => fallback,
    );
  }
}

/// Carte de fiche thématique. `compact` = carrousel « À la une ».
class ThemeCard extends StatelessWidget {
  const ThemeCard({super.key, required this.theme, this.compact = false});
  final ThemeCardData theme;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    final minutes = theme.readingMinutes == null
        ? null
        : context.t('pages_thematiques_reading_minutes', {'count': theme.readingMinutes});
    void open() => context.push('/thematiques/${theme.slug}');
    if (compact) {
      return Card(
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: open,
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            SizedBox(height: 100, width: double.infinity, child: CoverImage(theme.coverUrl, height: 100)),
            Padding(
              padding: const EdgeInsets.all(XmSpace.s3),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(theme.title, style: t.titleSmall, maxLines: 2, overflow: TextOverflow.ellipsis),
                if (minutes != null) Text(minutes, style: t.labelSmall),
              ]),
            ),
          ]),
        ),
      );
    }
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: open,
        child: IntrinsicHeight(
          child: Row(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            SizedBox(width: 92, child: CoverImage(theme.coverUrl, width: 92)),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(XmSpace.s3),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(theme.title, style: t.titleSmall),
                  if (theme.summary != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(theme.summary!, style: t.bodySmall, maxLines: 2, overflow: TextOverflow.ellipsis),
                    ),
                  if (minutes != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Row(children: [
                        Icon(Icons.schedule, size: 14, color: context.xm.textMuted),
                        const SizedBox(width: 4),
                        Text(minutes, style: t.labelSmall),
                      ]),
                    ),
                ]),
              ),
            ),
          ]),
        ),
      ),
    );
  }
}
