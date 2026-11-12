import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/ui/xm_widgets.dart';
import '../application/feed_controller.dart';
import 'feed_pages.dart' show PostCard;

/// « Mes vidéos » : mes vidéos en attente de relecture ou refusées (`GET posts/review`, 50 au plus).
/// Accès : bouton de la barre du Fil. Chaque carte montre l'état, le motif d'un refus et « Retirer ».
class MyVideosPage extends ConsumerWidget {
  const MyVideosPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(myReviewProvider);
    return Scaffold(
      appBar: AppBar(title: Text(context.t('mobile_myvideos_title'))),
      body: SafeArea(
        child: state.when(
          loading: () => const LoadingView(),
          error: (e, _) =>
              ErrorView(error: e, onRetry: () => ref.invalidate(myReviewProvider)),
          data: (items) => RefreshIndicator(
            onRefresh: () async => ref.invalidate(myReviewProvider),
            child: ListView(
              padding: const EdgeInsets.all(XmSpace.gutter),
              children: [
                Text(
                  context.t('mobile_myvideos_intro'),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                if (items.isEmpty)
                  EmptyView(context.t('mobile_myvideos_empty'))
                else
                  for (final p in items) PostCard(key: ValueKey(p.id), post: p),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
