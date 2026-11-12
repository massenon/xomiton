import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../app/theme/xm_tokens.dart';
import '../../../core/l10n/app_strings.dart';
import '../../../core/ui/xm_widgets.dart';
import '../data/engagement_api.dart';

final forumsListProvider = FutureProvider.autoDispose<List<ForumSummary>>(
  (ref) => ref.watch(engagementApiProvider).forums(),
);

/// Forums (P18-10) : liste des forums dans l'ordre choisi par l'administration,
/// avec leur nombre de sujets ; un forum archivé est signalé (lisible, sans
/// nouveau sujet). Les sujets se lisent dans l'espace membre web.
class ForumsPage extends ConsumerWidget {
  const ForumsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(forumsListProvider);
    return Scaffold(
      appBar: AppBar(title: Text(context.t('community_forums_title'))),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () async => ref.invalidate(forumsListProvider),
          child: state.when(
            loading: () => const LoadingView(),
            error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(forumsListProvider)),
            data: (forums) => forums.isEmpty
                ? ListView(children: [EmptyView(context.t('community_forums_empty'))])
                : ListView(
                    padding: const EdgeInsets.symmetric(vertical: XmSpace.s2),
                    children: [
                      for (final f in forums)
                        ListTile(
                          key: Key('forum-${f.key}'),
                          title: Text(f.name),
                          subtitle: Text(
                            [
                              if (f.description.isNotEmpty) f.description,
                              context.t('community_forums_topics', {'count': f.topics}),
                              if (f.archived) context.t('community_forums_archived'),
                            ].join(' · '),
                          ),
                          trailing: f.archived ? const Icon(Icons.archive_outlined) : null,
                        ),
                    ],
                  ),
          ),
        ),
      ),
    );
  }
}
